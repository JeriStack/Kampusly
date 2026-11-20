import { clampFontSize, parseRichText, type RichNode } from './richText'

/**
 * Line model used by the WYSIWYG editor. A message is a list of lines (paragraphs); each line has
 * an optional alignment and a list of styled text runs. The editor converts its DOM to this model
 * and the model to the stored markup, so the DOM is never trusted or stored.
 */
export type LineAlign = 'left' | 'center' | 'right'

export interface RunStyle {
  bold: boolean
  italic: boolean
  underline: boolean
  strike: boolean
  size: number | null
  color: string | null
  bg: string | null
}

export interface Run extends RunStyle {
  text: string
}

export interface Line {
  align: LineAlign | null
  runs: Run[]
}

export const PLAIN_STYLE: RunStyle = {
  bold: false,
  italic: false,
  underline: false,
  strike: false,
  size: null,
  color: null,
  bg: null,
}

/** Converts #abc, #aabbcc, rgb() and rgba() colors to #rrggbb. Returns null when unusable. */
export function cssColorToHex(value: string): string | null {
  const color = value.trim().toLowerCase()
  const short = /^#([0-9a-f]{3})$/.exec(color)
  if (short) return `#${short[1].split('').map((digit) => digit + digit).join('')}`
  if (/^#[0-9a-f]{6}$/.test(color)) return color

  const rgb = /^rgba?\(\s*(\d{1,3})\s*[, ]\s*(\d{1,3})\s*[, ]\s*(\d{1,3})(?:\s*[,/]\s*([\d.]+))?\s*\)$/.exec(color)
  if (!rgb) return null
  if (rgb[4] !== undefined && Number.parseFloat(rgb[4]) === 0) return null
  return `#${[rgb[1], rgb[2], rgb[3]]
    .map((part) => Math.min(255, Number(part)).toString(16).padStart(2, '0'))
    .join('')}`
}

function applyTag(style: RunStyle, node: Extract<RichNode, { type: 'tag' }>): RunStyle {
  const next = { ...style }
  if (node.tag === 'b') next.bold = true
  else if (node.tag === 'i') next.italic = true
  else if (node.tag === 'u') next.underline = true
  else if (node.tag === 's') next.strike = true
  else if (node.tag === 'size') next.size = Number(node.value)
  else if (node.tag === 'color') next.color = node.value
  else if (node.tag === 'bg') next.bg = node.value
  return next
}

function alignOf(node: Extract<RichNode, { type: 'tag' }>): LineAlign | null {
  return node.tag === 'left' || node.tag === 'center' || node.tag === 'right' ? node.tag : null
}

export function markupToLines(source: string): Line[] {
  const lines: Line[] = []
  let current: Line = { align: null, runs: [] }
  let breakBeforeNext = false

  const touch = (align: LineAlign | null): void => {
    if (breakBeforeNext) {
      lines.push(current)
      current = { align: null, runs: [] }
      breakBeforeNext = false
    }
    if (align) current.align = align
  }

  const walk = (nodes: RichNode[], style: RunStyle, align: LineAlign | null): void => {
    for (const node of nodes) {
      if (node.type === 'text') {
        node.text.split('\n').forEach((segment, index) => {
          if (index > 0) {
            touch(align)
            lines.push(current)
            current = { align: null, runs: [] }
          }
          if (segment) {
            touch(align)
            current.runs.push({ text: segment, ...style })
          }
        })
        continue
      }
      const blockAlign = alignOf(node)
      walk(node.children, applyTag(style, node), blockAlign ?? align)
      if (blockAlign) breakBeforeNext = true
    }
  }

  walk(parseRichText(source), PLAIN_STYLE, null)
  lines.push(current)
  return lines
}

function sameStyle(a: RunStyle, b: RunStyle): boolean {
  return (
    a.bold === b.bold
    && a.italic === b.italic
    && a.underline === b.underline
    && a.strike === b.strike
    && a.size === b.size
    && a.color === b.color
    && a.bg === b.bg
  )
}

/** Escapes the two characters the parser treats specially: a backslash and an opening bracket. */
export function escapeMarkupText(text: string): string {
  return text.replace(/[\\[]/g, '\\$&')
}

function wrapRun(run: Run): string {
  let out = escapeMarkupText(run.text)
  if (run.strike) out = `[s]${out}[/s]`
  if (run.underline) out = `[u]${out}[/u]`
  if (run.italic) out = `[i]${out}[/i]`
  if (run.bold) out = `[b]${out}[/b]`
  if (run.bg) out = `[bg=${run.bg}]${out}[/bg]`
  if (run.color) out = `[color=${run.color}]${out}[/color]`
  if (run.size) out = `[size=${clampFontSize(run.size)}]${out}[/size]`
  return out
}

export function linesToMarkup(lines: Line[]): string {
  return lines
    .map((line) => {
      const merged: Run[] = []
      for (const run of line.runs) {
        if (!run.text) continue
        const last = merged[merged.length - 1]
        if (last && sameStyle(last, run)) last.text += run.text
        else merged.push({ ...run })
      }
      const body = merged.map(wrapRun).join('')
      return line.align && body ? `[${line.align}]${body}[/${line.align}]` : body
    })
    .join('\n')
}
