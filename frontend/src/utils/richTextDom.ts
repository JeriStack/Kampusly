import { clampFontSize } from './richText'
import {
  PLAIN_STYLE,
  cssColorToHex,
  type Line,
  type LineAlign,
  type Run,
  type RunStyle,
} from './richTextLines'

/**
 * Browser-side glue for the WYSIWYG editor: builds editor DOM from the line model and reads it
 * back. Content is only ever created with createElement/createTextNode, never innerHTML.
 */
const BLOCK_TAGS = new Set(['DIV', 'P', 'LI', 'UL', 'OL', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE'])

function isBlock(node: Node): node is HTMLElement {
  return node.nodeType === Node.ELEMENT_NODE && BLOCK_TAGS.has((node as HTMLElement).tagName)
}

function readAlign(element: HTMLElement): LineAlign | null {
  const value = (element.style.textAlign || element.getAttribute('align') || '').toLowerCase()
  return value === 'left' || value === 'center' || value === 'right' ? value : null
}

/** The formatting an element adds, from its tag and its inline style. */
function styleOf(element: HTMLElement, inherited: RunStyle): RunStyle {
  const style = { ...inherited }
  const tag = element.tagName
  if (tag === 'B' || tag === 'STRONG') style.bold = true
  if (tag === 'I' || tag === 'EM') style.italic = true
  if (tag === 'U') style.underline = true
  if (tag === 'S' || tag === 'STRIKE' || tag === 'DEL') style.strike = true
  if (tag === 'FONT') {
    const color = cssColorToHex(element.getAttribute('color') ?? '')
    if (color) style.color = color
  }

  const css = element.style
  const weight = css.fontWeight
  if (weight === 'bold' || weight === 'bolder' || Number(weight) >= 600) style.bold = true
  if (css.fontStyle === 'italic') style.italic = true
  const decoration = `${css.textDecorationLine} ${css.textDecoration}`
  if (decoration.includes('underline')) style.underline = true
  if (decoration.includes('line-through')) style.strike = true
  const size = /^([\d.]+)px$/.exec(css.fontSize)
  if (size) style.size = clampFontSize(Number(size[1]))
  const color = css.color ? cssColorToHex(css.color) : null
  if (color) style.color = color
  const background = css.backgroundColor ? cssColorToHex(css.backgroundColor) : null
  if (background) style.bg = background
  return style
}

function addText(line: Line, rawText: string, style: RunStyle): void {
  const text = rawText.replace(/\u00a0/g, ' ').replace(/[\u200b\ufeff]/g, '')
  if (text) line.runs.push({ text, ...style })
}

function readInline(node: Node, style: RunStyle, ensure: () => Line, isLast: boolean): void {
  if (node.nodeType === Node.TEXT_NODE) {
    addText(ensure(), node.nodeValue ?? '', style)
    return
  }
  if (node.nodeType !== Node.ELEMENT_NODE) return

  const element = node as HTMLElement
  // A trailing <br> only keeps an empty line open in the browser; it is not a real line break.
  if (element.tagName === 'BR') {
    if (!isLast) addText(ensure(), '\n', style)
    return
  }
  const nextStyle = styleOf(element, style)
  const children = Array.from(element.childNodes)
  children.forEach((child, index) => readInline(child, nextStyle, ensure, index === children.length - 1))
}

function readBlock(
  element: HTMLElement,
  style: RunStyle,
  align: LineAlign | null,
  lines: Line[],
): void {
  let line: Line | null = null
  let sawBlock = false
  const ensure = (): Line => {
    if (!line) {
      line = { align, runs: [] }
      lines.push(line)
    }
    return line
  }

  const children = Array.from(element.childNodes)
  children.forEach((child, index) => {
    if (isBlock(child)) {
      sawBlock = true
      line = null
      readBlock(child, styleOf(child, style), readAlign(child) ?? align, lines)
      return
    }
    readInline(child, style, ensure, index === children.length - 1)
  })
  if (!line && !sawBlock) ensure()
}

/** Reads the editor DOM into the line model. */
export function readLines(root: HTMLElement): Line[] {
  const lines: Line[] = []
  readBlock(root, PLAIN_STYLE, null, lines)
  return lines.length > 0 ? lines : [{ align: null, runs: [] }]
}

function renderRun(run: Run): Node {
  let node: Node = document.createTextNode(run.text)
  const wrap = (tag: 'b' | 'i' | 'u' | 's' | 'span'): HTMLElement => {
    const element = document.createElement(tag)
    element.appendChild(node)
    node = element
    return element
  }
  if (run.strike) wrap('s')
  if (run.underline) wrap('u')
  if (run.italic) wrap('i')
  if (run.bold) wrap('b')
  if (run.bg || run.color || run.size) {
    const span = wrap('span')
    if (run.size) span.style.fontSize = `${run.size}px`
    if (run.color) span.style.color = run.color
    if (run.bg) span.style.backgroundColor = run.bg
  }
  return node
}

/** Replaces the editor content with the given lines (one <div> per line). */
export function renderLines(root: HTMLElement, lines: Line[]): void {
  root.replaceChildren(
    ...lines.map((line) => {
      const div = document.createElement('div')
      if (line.align) div.style.textAlign = line.align
      if (line.runs.length === 0) div.appendChild(document.createElement('br'))
      for (const run of line.runs) div.appendChild(renderRun(run))
      return div
    }),
  )
}
