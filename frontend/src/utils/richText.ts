import { toTinyText } from './tinyText'

/**
 * Lightweight announcement formatting. Messages stay plain text with BBCode-style tags:
 *
 *   [b] [i] [u] [s]            bold, italic, underline, strikethrough
 *   [size=24]                  any font size from MIN_FONT_SIZE to MAX_FONT_SIZE (px)
 *   [color=#dc2626]            text color (hex)
 *   [bg=#fde047]               highlight color (hex)
 *   [left] [center] [right]    paragraph alignment
 *
 * The parser never produces HTML. It builds a node tree that the FormattedText component renders
 * with Vue elements, so a message cannot inject markup or scripts. Tags with invalid values are
 * shown as literal text.
 */
export type RichTag = 'b' | 'i' | 'u' | 's' | 'size' | 'color' | 'bg' | 'left' | 'center' | 'right'

export type RichNode =
  | { type: 'text'; text: string }
  | { type: 'tag'; tag: RichTag; value: string | null; children: RichNode[] }

export interface EditResult {
  text: string
  selectionStart: number
  selectionEnd: number
}

export const MIN_FONT_SIZE = 8
export const MAX_FONT_SIZE = 96
const MAX_DEPTH = 20
const TAG_NAMES = 'b|i|u|s|size|color|bg|left|center|right'
const TAG_PATTERN = new RegExp(`\\[(\\/)?(${TAG_NAMES})(?:=([^\\[\\]]{1,20}))?\\]`, 'gi')
const TAG_SPLIT = new RegExp(`(\\[\\/?(?:${TAG_NAMES})(?:=[^\\[\\]]{1,20})?\\])`, 'i')
const BULLET = '• '

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
  const pushText = (text: string): void => {
    if (text) currentChildren().push({ type: 'text', text })
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

    const tag = match[2].toLowerCase() as RichTag
    if (match[1] === '/') {
      let openIndex = -1
      for (let i = stack.length - 1; i >= 0; i--) {
        if (stack[i].tag === tag) {
          openIndex = i
          break
        }
      }
      if (match[3] !== undefined || openIndex === -1) {
        pushText(match[0])
        continue
      }
      while (stack.length > openIndex) closeTop()
      continue
    }

    const value = normalizeValue(tag, match[3])
    if (value === false || stack.length >= MAX_DEPTH) {
      pushText(match[0])
      continue
    }
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

export function hasRichText(source: string): boolean {
  return stripRichText(source) !== source
}

function replaceRange(text: string, start: number, end: number, replacement: string): string {
  return text.slice(0, start) + replacement + text.slice(end)
}

export function wrapRange(text: string, start: number, end: number, open: string, close: string): EditResult {
  const selected = text.slice(start, end)
  return {
    text: replaceRange(text, start, end, open + selected + close),
    selectionStart: start + open.length,
    selectionEnd: start + open.length + selected.length,
  }
}

/** Wraps the range in `[tag=value]…[/tag]`. */
export function applyTag(
  text: string,
  start: number,
  end: number,
  tag: RichTag,
  value?: string | number,
): EditResult {
  const open = value === undefined ? `[${tag}]` : `[${tag}=${value}]`
  return wrapRange(text, start, end, open, `[/${tag}]`)
}

/** Bold/italic/underline/strikethrough: wraps the range, or removes the tag when already applied. */
export function toggleTag(text: string, start: number, end: number, tag: RichTag): EditResult {
  const open = `[${tag}]`
  const close = `[/${tag}]`

  if (text.slice(start - open.length, start) === open && text.slice(end, end + close.length) === close) {
    return {
      text: text.slice(0, start - open.length) + text.slice(start, end) + text.slice(end + close.length),
      selectionStart: start - open.length,
      selectionEnd: end - open.length,
    }
  }

  const selected = text.slice(start, end)
  if (selected.length >= open.length + close.length && selected.startsWith(open) && selected.endsWith(close)) {
    const inner = selected.slice(open.length, selected.length - close.length)
    return { text: replaceRange(text, start, end, inner), selectionStart: start, selectionEnd: start + inner.length }
  }

  return wrapRange(text, start, end, open, close)
}

export function clearFormatting(text: string, start: number, end: number): EditResult {
  const cleaned = stripRichText(text.slice(start, end))
  return { text: replaceRange(text, start, end, cleaned), selectionStart: start, selectionEnd: start + cleaned.length }
}

/** Converts letters to tiny Unicode text without touching the formatting tags. */
export function toTinyTextKeepingTags(text: string): string {
  return text
    .split(TAG_SPLIT)
    .map((part, index) => (index % 2 === 1 ? part : toTinyText(part)))
    .join('')
}

export function tinyTextRange(text: string, start: number, end: number): EditResult {
  const converted = toTinyTextKeepingTags(text.slice(start, end))
  return { text: replaceRange(text, start, end, converted), selectionStart: start, selectionEnd: start + converted.length }
}

/** Adds (or removes) a bullet at the start of every non-empty line touched by the selection. */
export function toggleBullets(text: string, start: number, end: number): EditResult {
  const lineStart = start === 0 ? 0 : text.lastIndexOf('\n', start - 1) + 1
  const newline = text.indexOf('\n', end)
  const lineEnd = newline === -1 ? text.length : newline
  const lines = text.slice(lineStart, lineEnd).split('\n')
  const filled = lines.filter((line) => line.trim() !== '')
  const allBulleted = filled.length > 0 && filled.every((line) => line.startsWith(BULLET))

  const block = lines
    .map((line) => {
      if (line.trim() === '') return line
      if (allBulleted) return line.slice(BULLET.length)
      return line.startsWith(BULLET) ? line : BULLET + line
    })
    .join('\n')

  return {
    text: replaceRange(text, lineStart, lineEnd, block),
    selectionStart: lineStart,
    selectionEnd: lineStart + block.length,
  }
}
