/**
 * Lightweight announcement formatting. Messages stay plain text with BBCode-style tags:
 *
 *   [b] [i] [u] [s]            bold, italic, underline, strikethrough
 *   [size=24]                  any font size from MIN_FONT_SIZE to MAX_FONT_SIZE (px)
 *   [color=#dc2626]            text color (hex)
 *   [bg=#fde047]               highlight color (hex)
 *   [left] [center] [right]    paragraph alignment
 *   \[ and \\                  a literal "[" and a literal "\"
 *
 * The parser never produces HTML. It builds a node tree that the FormattedText component renders
 * with Vue elements, so a message cannot inject markup or scripts. Tags with invalid values are
 * shown as literal text.
 */
export type RichTag = 'b' | 'i' | 'u' | 's' | 'size' | 'color' | 'bg' | 'left' | 'center' | 'right'

export type RichNode =
  | { type: 'text'; text: string }
  | { type: 'tag'; tag: RichTag; value: string | null; children: RichNode[] }

export const MIN_FONT_SIZE = 8
export const MAX_FONT_SIZE = 96
const MAX_DEPTH = 20
const TAG_NAMES = 'b|i|u|s|size|color|bg|left|center|right'
// Group 1: escaped character. Groups 2-4: closing slash, tag name, tag value.
const TAG_PATTERN = new RegExp(
  `\\\\([\\\\\\[])|\\[(\\/)?(${TAG_NAMES})(?:=([^\\[\\]]{1,20}))?\\]`,
  'gi',
)

export function clampFontSize(size: number): number {
  return Math.min(MAX_FONT_SIZE, Math.max(MIN_FONT_SIZE, Math.round(size)))
}

/** Returns the normalized tag value, `null` for tags without a value, or `false` when invalid. */
function normalizeValue(tag: RichTag, raw: string | undefined): string | null | false {
  if (tag === 'size') {
    return raw !== undefined && /^\d{1,3}$/.test(raw) ? String(clampFontSize(Number(raw))) : false
  }
  if (tag === 'color' || tag === 'bg') {
    return raw !== undefined && /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test(raw) ? raw.toLowerCase() : false
  }
  return raw === undefined ? null : false
}

interface OpenTag {
  tag: RichTag
  value: string | null
  children: RichNode[]
}

export function parseRichText(source: string): RichNode[] {
  const root: RichNode[] = []
  const stack: OpenTag[] = []
  const currentChildren = (): RichNode[] => (stack.length ? stack[stack.length - 1].children : root)
  // An alignment block already ends its line, so one newline right after it is not a blank line.
  let afterAlignClose = false
  const pushText = (text: string): void => {
    let value = text
    if (afterAlignClose) {
      afterAlignClose = false
      if (value.startsWith('\n')) value = value.slice(1)
    }
    if (value) currentChildren().push({ type: 'text', text: value })
  }
  const closeTop = (): void => {
    const open = stack.pop()
    if (open) {
      currentChildren().push({ type: 'tag', tag: open.tag, value: open.value, children: open.children })
    }
  }

  let cursor = 0
  for (const match of source.matchAll(TAG_PATTERN)) {
    const index = match.index ?? 0
    pushText(source.slice(cursor, index))
    cursor = index + match[0].length

    if (match[1] !== undefined) {
      pushText(match[1])
      continue
    }

    const tag = match[3].toLowerCase() as RichTag
    if (match[2] === '/') {
      let openIndex = -1
      for (let i = stack.length - 1; i >= 0; i--) {
        if (stack[i].tag === tag) {
          openIndex = i
          break
        }
      }
      if (match[4] !== undefined || openIndex === -1) {
        pushText(match[0])
        continue
      }
      while (stack.length > openIndex) closeTop()
      afterAlignClose = tag === 'left' || tag === 'center' || tag === 'right'
      continue
    }

    const value = normalizeValue(tag, match[4])
    if (value === false || stack.length >= MAX_DEPTH) {
      pushText(match[0])
      continue
    }
    afterAlignClose = false
    stack.push({ tag, value, children: [] })
  }
  pushText(source.slice(cursor))
  while (stack.length > 0) closeTop()
  return root
}

function flatten(nodes: RichNode[]): string {
  return nodes.map((node) => (node.type === 'text' ? node.text : flatten(node.children))).join('')
}

/** The message without any formatting tags. */
export function stripRichText(source: string): string {
  return flatten(parseRichText(source))
}
