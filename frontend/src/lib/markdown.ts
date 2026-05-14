/**
 * Минимальный inline-markdown для текста, который присылает LLM в чат.
 * Поддерживает только то, что реально использует модель: **bold**, *italic*,
 * _italic_, `code`. Без блочных конструкций (списки, заголовки, цитаты) —
 * для них есть отдельный layer, если когда-то понадобится.
 *
 * Безопасность: сначала экранируем HTML, потом применяем regex. Поскольку
 * regex добавляет только заранее известные теги (<strong>, <em>, <code>),
 * v-html на выходе безопасен.
 */

function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

export function renderInlineMarkdown(input: string): string {
  let html = escapeHtml(input)

  // `code` — вынимаем содержимое в плейсхолдеры до emphasis-парсинга, чтобы
  // * и _ внутри кода не превратились в <em>/<strong>. \x00 не появляется
  // в обычном тексте от модели и не задевается ни одной из ниже идущих
  // regex'ов.
  const codeChunks: string[] = []
  html = html.replace(/`([^`\n]+?)`/g, (_, body: string) => {
    const i = codeChunks.push(body) - 1
    return `\x00C${i}\x00`
  })

  // **bold** — двойные звёздочки. Ленивая жадность, чтобы "**a** **b**"
  // парсилось как два отдельных bold.
  html = html.replace(/\*\*([^*\n]+?)\*\*/g, '<strong>$1</strong>')

  // *italic* — одиночная звёздочка. Запрещаем пустой контент и пробел
  // сразу после открывающей звёздочки, чтобы "5 * 3 * 2" не превращалось
  // в emphasis.
  html = html.replace(/(^|[^*\w])\*(?!\s)([^*\n]+?)(?<!\s)\*(?!\w)/g, '$1<em>$2</em>')

  // _italic_ — с теми же предосторожностями: только если не внутри слова,
  // чтобы snake_case_identifier не разваливался.
  html = html.replace(/(^|[^_\w])_(?!\s)([^_\n]+?)(?<!\s)_(?!\w)/g, '$1<em>$2</em>')

  html = html.replace(/\x00C(\d+)\x00/g, (_, i: string) => `<code>${codeChunks[Number(i)]}</code>`)

  return html
}
