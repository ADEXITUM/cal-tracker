# 07. PWA и оффлайн

## Цели

1. Установка как нативное приложение (PWA, без браузерных контролов)
2. Базовый оффлайн — открыть app, увидеть последний день
3. Запись оффлайн с очередью + idempotency keys
4. **Не делаем:** push, background sync API (нестабилен), фоновое обновление

## Стек

- `vite-plugin-pwa` (генерация manifest + SW)
- Workbox под капотом (стратегии кэширования)
- IndexedDB через `idb-keyval` для токенов, кэша, очереди

## Manifest

`name: DietTracker`, `short_name: DietTracker`, `start_url: /?source=pwa`, `display: standalone`, `theme_color: #FAFAF7`, `background_color: #FAFAF7`, `lang: ru`. Иконки 192/512 + maskable. Добавить apple-touch-icon и iOS meta-tags в `index.html`.

## iOS PWA нюансы

- IndexedDB и localStorage могут очищаться если PWA не открыт 7+ дней
- Нет push до iOS 16.4 (только в установленном PWA)
- `navigator.vibrate` не работает в Safari
- Нет background sync
- SW выгружается агрессивнее чем на Android

**Вывод:** для iOS критичные данные (токены, очередь) должны быть готовы пропасть. При перерыве 7+ дней — повторный логин это ок для MVP.

## Service Worker — стратегии

`registerType: 'autoUpdate'` + `runtimeCaching`:

- **Шрифты** (`fonts.googleapis.com`): CacheFirst, 1 год, max 10 entries
- **GET /days/\***: NetworkFirst с timeout 3s, 30 дней TTL, 50 entries
- **GET /stats/\***: NetworkFirst с timeout 3s, 24 часа TTL, 20 entries
- **GET /dishes, /goals, /profile, /auth/me**: StaleWhileRevalidate, 7 дней, 30 entries
- **POST/PUT/DELETE**: НЕ кэшируем — обрабатываем через offline queue вручную

`navigateFallback: '/index.html'` для SPA, `navigateFallbackDenylist: [/^\/api/]`. `skipWaiting: true`, `clientsClaim: true`.

## Update flow

`useRegisterSW()` → при `needRefresh: true` показать тост "Доступна новая версия [Обновить]". По кнопке — `updateSW()` → reload с новой версией.

## Offline queue

IndexedDB store `offline_queue`:

```ts
interface QueuedAction {
  id: string              // UUID v4 (= idempotencyKey)
  url: string
  method: 'POST' | 'PUT' | 'DELETE'
  body: unknown
  createdAt: number
  attempts: number
  lastError: string | null
}
```

### Поток

1. Юзер сохраняет приём → `useOfflineQueue.enqueue(action)` пишет в IDB
2. Сразу — optimistic update в Pinia store (UI обновляется мгновенно)
3. Sheet закрывается
4. `processQueue()`:
   - Online → POST с `Idempotency-Key: {id}`. Success → удалить из очереди, обновить state с реальным uuid
   - Offline → пропустить
5. Listener `window.addEventListener('online', processQueue)`
6. 5xx → инкремент `attempts`, exponential backoff (1s, 5s, 30s, 5min)
7. 4xx (validation) → удалить из очереди + toast «Не удалось сохранить» + откат optimistic

### Ограничения

- Последовательная обработка (одна задача за раз)
- Limit 100 элементов (старые удаляются с warning)
- Конфликты — last-write-wins

### UI индикация

В углу — индикатор синка:
- Очередь пустая + online — ничего
- Очередь не пустая + online — точка с цифрой
- Не пустая + offline — серая точка
- Отправляется — пульсация

Тап → экран статуса очереди с ручным retry.

## Кэш данных в IDB

Поверх SW — храним последние 7 дней целиком:

```ts
interface CachedDay {
  date: string
  data: DayResource
  cachedAt: number
  userId: string  // разделять между аккаунтами
}
```

При открытии `/day/{date}`:
1. Сразу читаем из IDB → показываем (с пометкой «обновляется»)
2. Параллельно GET → если 200, обновить IDB и UI
3. Offline → остаёмся на cached, баннер «Оффлайн»

## Тестирование PWA

- Lighthouse PWA score >= 90
- iOS: Safari → Share → Add to Home Screen
- Android: автоматический install prompt
- Offline scenario: airplane mode → reload app → видны кэшированные данные → добавить приём → включить интернет → синк за 5 сек

## Open questions

- Background Sync API — **нет**, обходимся `online` event и retry при открытии app
