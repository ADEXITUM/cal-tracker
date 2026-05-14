import { describe, it, expect } from 'vitest'
import { renderInlineMarkdown } from '@/lib/markdown'

describe('renderInlineMarkdown', () => {
  it('escapes HTML special characters', () => {
    expect(renderInlineMarkdown('<script>alert(1)</script>')).toBe(
      '&lt;script&gt;alert(1)&lt;/script&gt;',
    )
    expect(renderInlineMarkdown('a & b')).toBe('a &amp; b')
    expect(renderInlineMarkdown('say "hi"')).toBe('say &quot;hi&quot;')
  })

  it('renders **bold**', () => {
    expect(renderInlineMarkdown('hello **world**')).toBe('hello <strong>world</strong>')
  })

  it('renders *italic* and _italic_', () => {
    expect(renderInlineMarkdown('a *b* c')).toBe('a <em>b</em> c')
    expect(renderInlineMarkdown('a _b_ c')).toBe('a <em>b</em> c')
  })

  it('does not turn 5 * 3 * 2 into emphasis', () => {
    expect(renderInlineMarkdown('5 * 3 * 2')).toBe('5 * 3 * 2')
  })

  it('does not break snake_case_identifier', () => {
    expect(renderInlineMarkdown('foo_bar_baz')).toBe('foo_bar_baz')
  })

  it('renders `code` and protects content from emphasis parsing', () => {
    expect(renderInlineMarkdown('use `*not bold*` here')).toBe(
      'use <code>*not bold*</code> here',
    )
  })

  it('handles multiple bolds in one line', () => {
    expect(renderInlineMarkdown('**a** and **b**')).toBe(
      '<strong>a</strong> and <strong>b</strong>',
    )
  })

  it('escapes html inside emphasis content', () => {
    expect(renderInlineMarkdown('**<b>x</b>**')).toBe('<strong>&lt;b&gt;x&lt;/b&gt;</strong>')
  })

  it('leaves plain text unchanged', () => {
    expect(renderInlineMarkdown('просто текст без разметки')).toBe(
      'просто текст без разметки',
    )
  })
})
