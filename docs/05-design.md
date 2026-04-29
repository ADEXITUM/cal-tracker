# 05. Design System

## Принципы

1. Mobile-first, 380px базовый viewport
2. Минимализм с одним акцентом (тёплый оранжевый `#FF5A1F`)
3. Числа доминируют (JetBrains Mono Light, крупно), подписи мелко серым
4. Поверхности слоятся: bg → surface → surface-2 → surface-3
5. Motion подкрепляет действие, не отвлекает

## Темы

Auto-detect через `prefers-color-scheme`, override через настройки. Атрибут `data-theme` на html.

### Light (default, off-white фон)
- bg `#FAFAF7`, surface `#FFFFFF`, surface-2 `#F4F4F0`, surface-3 `#ECECE6`
- text `#1A1A1A`, text-2 `#6B6B6B`, text-3 `#A0A0A0`
- accent `#FF5A1F`, accent-soft `#FFE4D6`, accent-tint `#FFF5EE`
- border `#E8E8E3`

### Dark
- bg `#0F0F0E` (не чистый чёрный), surface `#1A1A18`, surface-2 `#252522`, surface-3 `#303030`
- text `#F5F5F0`, text-2 `#A8A8A0`, text-3 `#6E6E68`
- accent `#FF7A45` (чуть светлее на тёмном)
- border `#2A2A28`

### Семантические (обе темы)
- green / red / yellow / blue + `-soft` варианты
- Использовать только для статусов и mode badges

## Типографика

- **Body/UI:** Inter variable (`@fontsource-variable/inter`)
- **Numbers:** JetBrains Mono variable, `tabular-nums`. Большие цифры — weight 300 (Light), 36-48px

Размеры: `xs 11`, `sm 13`, `base 15`, `lg 17`, `xl 21`, `2xl 28`, `3xl 36`, `4xl 48`. Веса: 400/500/600.

## Радиусы

`sm 8` (chips, кнопки), `md 12` (карточки), `lg 16` (большие), `xl 24` (modals/sheets), `full` (pills, avatars). Не используем 4px и острые углы.

## Ключевые компоненты

### AButton
Варианты `primary | secondary | ghost | danger`. Размеры `sm | md | lg`. На `:active` — `transform: scale(0.97)` для haptic feel.

### ACard
Default: surface + border + radius-md, без теней. Варианты: `default | tinted | accent | outlined`. Опциональный `accentSide="left"` с цветным бордером для подсказок.

### AModeBadge
Главный визуальный элемент режима. Pill с цветом, иконкой и delta:

| Mode | Color | Icon | Label |
|---|---|---|---|
| extreme_cut | red | Flame | Экстрим-сушка |
| cut | red-soft | TrendingDown | Сушка |
| cut_lite | yellow-soft | Minus | Лёгкая сушка |
| maintenance | surface-2 | Equal | Поддержка |
| light_bulk | green-soft | Plus | Лёгкий набор |
| bulk | green | TrendingUp | Набор |

Тап → mode explainer modal (см. 06-insights).

### KcalRing
SVG-кольцо прогресса с числом по центру. 220x220 на мобиле, 280 на desktop. Stroke 12px. Цвет: accent для cut (red при перепрогрессе), green для bulk, gray для maintenance. Анимация: stroke рисуется от 0 за 800ms ease-out при mount; при обновлении — lerp числа и stroke.

### ASheet (Bottom Sheet)
Главный паттерн ввода. Свайп вверх с FAB или кнопок. На мобиле — bottom sheet, на desktop — modal по центру.

- Фон surface, скругление сверху radius-xl, тень
- Drag handle 32x4 surface-3 сверху
- Backdrop `rgba(0,0,0,0.4)` тапается для закрытия
- Свайп вниз закрывает (порог ~100px)
- Анимация iOS-spring: `cubic-bezier(0.32, 0.72, 0, 1)` 320ms

### ANumpad
Встроенная цифровая клавиатура (не системная) для веса и калорий — убирает прыжок viewport на iOS Safari. Кнопки 56x56 минимум. Haptic на каждый тап. Live preview сверху JetBrains Mono.

### ASegmented
Period selector "7д / 30д / 90д / Год / Всё". Активный — `bg-accent-tint text-accent`, sliding pill при переключении.

### Heatmap (HistoryView)
Сетка 32x32 ячеек по неделям. Цвет:
- Нет данных — surface-2
- Перебор >+200 — red-soft
- Чуть выше (0..+200) — yellow-soft
- В цели (±100) — green-soft
- Дефицит -100..-300 — green mid opacity
- Большой дефицит >−300 — green full

Тап → переход на /day/{date}.

## Иконки

`lucide-vue-next`, размер 20px стандарт, stroke 1.75. Часто используем: Plus, TrendingDown/Up, Flame, Activity, Heart, Calendar, Settings, User, LogOut, ChevronLeft/Right, Trash2, Edit3, X, Check, AlertTriangle.

## Motion

Стандартные timings:
- `--ease-out: cubic-bezier(0, 0, 0.2, 1)`
- `--ease-spring: cubic-bezier(0.32, 0.72, 0, 1)` — для sheets
- `--duration-fast: 150ms`, `--duration-normal: 250ms`, `--duration-slow: 400ms`

Анимировать: переход между днями (slide), числа (lerp через requestAnimationFrame), бары/кольца на mount, подсказки fade-up, sheets spring.

Не анимировать: появление модалок которые юзер вызвал (мгновенно), layout shifts.

`prefers-reduced-motion: reduce` — отключать всё кроме fade.

## Графики

Один акцент, остальные нейтрально:
- chart-1 = accent (главная метрика)
- chart-2 = text-2 (второстепенная)
- chart-3 = text-3

Никаких rainbow-палитр. Фокус на тренде главной линии.

## A11y

- Focus-visible с outline 2px accent
- Контрасты WCAG AA (4.5:1 текст, 3:1 UI)
- ARIA для sheets (`role="dialog" aria-modal`)
- Touch targets min 44x44px

## Open questions

- iOS-специфичные haptics (через web-share API трюки) — не делаем, базовый `navigator.vibrate` достаточно
