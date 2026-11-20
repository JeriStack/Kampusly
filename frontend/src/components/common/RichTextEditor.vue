<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { MAX_FONT_SIZE, MIN_FONT_SIZE, clampFontSize, stripRichText } from '@/utils/richText'
import { linesToMarkup, markupToLines } from '@/utils/richTextLines'
import { readLines, renderLines } from '@/utils/richTextDom'
import { toTinyText } from '@/utils/tinyText'

/**
 * WYSIWYG message editor. The text is shown formatted while typing; the value is stored as the
 * announcement markup (see utils/richText.ts), never as HTML.
 */
const props = withDefaults(
  defineProps<{ modelValue: string; maxLength?: number; placeholder?: string; ariaLabel?: string }>(),
  { maxLength: 5000, placeholder: 'Write your announcement…', ariaLabel: 'Message' },
)
const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>()

const editor = ref<HTMLElement | null>(null)
const fontSize = ref(18)
const textColor = ref('#dc2626')
const highlightColor = ref('#fde047')
const undoStack = ref<string[]>([])
const isToolbarOpen = ref(false)
const FONT_SIZE_PRESETS = [10, 12, 14, 16, 18, 20, 24, 28, 32, 36, 40, 48, 56, 64, 72, 96]
const toolButtonClass =
  'rounded-md border border-hairline bg-surface px-2.5 py-1 text-[11px] font-semibold text-ink hover:bg-canvas disabled:opacity-50'

let lastEmitted = props.modelValue
let savedRange: Range | null = null
let isToolbarAction = false

const isEmpty = computed(() => stripRichText(props.modelValue).trim() === '')
const isTooLong = computed(() => props.modelValue.length > props.maxLength)

function render(value: string): void {
  if (!editor.value) return
  renderLines(editor.value, markupToLines(value))
  lastEmitted = value
}

function emitFromDom(): void {
  if (!editor.value) return
  const markup = linesToMarkup(readLines(editor.value))
  lastEmitted = markup
  emit('update:modelValue', markup)
}

function onInput(): void {
  if (!isToolbarAction) undoStack.value = []
  emitFromDom()
}

function onPaste(event: ClipboardEvent): void {
  event.preventDefault()
  const text = event.clipboardData?.getData('text/plain') ?? ''
  if (text) document.execCommand('insertText', false, text)
}

function rememberSelection(): void {
  const selection = window.getSelection()
  if (!editor.value || !selection || selection.rangeCount === 0) return
  const range = selection.getRangeAt(0)
  if (editor.value.contains(range.commonAncestorContainer)) savedRange = range.cloneRange()
}

onMounted(() => {
  render(props.modelValue)
  document.addEventListener('selectionchange', rememberSelection)
})
onBeforeUnmount(() => document.removeEventListener('selectionchange', rememberSelection))
watch(
  () => props.modelValue,
  (value) => {
    if (value !== lastEmitted) render(value)
  },
)

const BULLET = '\u2022 '

/** Runs a formatting action on the selected text, or on the whole message when nothing is selected. */
function runAction(action: (range: Range) => void): void {
  const field = editor.value
  const selection = window.getSelection()
  if (!field || !selection || isEmpty.value) return

  field.focus()
  const usable = savedRange && !savedRange.collapsed && field.contains(savedRange.commonAncestorContainer)
  const target = usable && savedRange ? savedRange : document.createRange()
  if (!usable) target.selectNodeContents(field)
  selection.removeAllRanges()
  selection.addRange(target)

  isToolbarAction = true
  undoStack.value = [...undoStack.value.slice(-29), lastEmitted]
  try {
    action(selection.getRangeAt(0))
  } finally {
    isToolbarAction = false
  }
  emitFromDom()
  rememberSelection()
}

function format(command: 'bold' | 'italic' | 'underline' | 'strikeThrough'): void {
  runAction(() => {
    document.execCommand('styleWithCSS', false, 'false')
    document.execCommand(command)
  })
}

function applySize(): void {
  const size = clampFontSize(Number(fontSize.value) || 16)
  fontSize.value = size
  runAction(() => {
    document.execCommand('styleWithCSS', false, 'false')
    document.execCommand('fontSize', false, '7')
    editor.value?.querySelectorAll('font[size="7"]').forEach((font) => {
      const span = document.createElement('span')
      span.style.fontSize = `${size}px`
      span.append(...Array.from(font.childNodes))
      // Sizes set earlier inside the selection are replaced, so the new size wins.
      span.querySelectorAll<HTMLElement>('[style]').forEach((inner) => {
        inner.style.fontSize = ''
        if (!inner.getAttribute('style')) inner.removeAttribute('style')
      })
      font.replaceWith(span)
    })
  })
}

function applyColor(command: 'foreColor' | 'hiliteColor', value: string): void {
  runAction(() => {
    document.execCommand('styleWithCSS', false, 'true')
    if (!document.execCommand(command, false, value) && command === 'hiliteColor') {
      document.execCommand('backColor', false, value)
    }
  })
}

function align(command: 'justifyLeft' | 'justifyCenter' | 'justifyRight'): void {
  runAction(() => {
    document.execCommand('styleWithCSS', false, 'true')
    document.execCommand(command)
  })
}

function toggleBullets(): void {
  runAction((range) => {
    const lines = Array.from(editor.value?.children ?? []).filter((line) => range.intersectsNode(line))
    const filled = lines.filter((line) => (line.textContent ?? '').trim() !== '')
    const remove = filled.length > 0 && filled.every((line) => (line.textContent ?? '').startsWith(BULLET))
    for (const line of filled) {
      const first = document.createTreeWalker(line, NodeFilter.SHOW_TEXT).nextNode()
      if (!first) continue
      const value = first.nodeValue ?? ''
      if (remove) {
        if (value.startsWith(BULLET)) first.nodeValue = value.slice(BULLET.length)
      } else if (!(line.textContent ?? '').startsWith(BULLET)) {
        first.nodeValue = BULLET + value
      }
    }
  })
}

/** Turns letters into tiny Unicode text. The characters keep their length, so ranges stay valid. */
function applyTinyText(): void {
  runAction((range) => {
    const root = range.commonAncestorContainer
    const nodes: Text[] = []
    if (root.nodeType === Node.TEXT_NODE) {
      nodes.push(root as Text)
    } else {
      const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)
      for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        if (range.intersectsNode(node)) nodes.push(node as Text)
      }
    }
    for (const node of nodes) {
      const value = node.nodeValue ?? ''
      const start = node === range.startContainer ? range.startOffset : 0
      const end = node === range.endContainer ? range.endOffset : value.length
      node.nodeValue = value.slice(0, start) + toTinyText(value.slice(start, end)) + value.slice(end)
    }
  })
}

function clearFormatting(): void {
  runAction((range) => {
    document.execCommand('styleWithCSS', false, 'false')
    document.execCommand('removeFormat')
    editor.value?.querySelectorAll<HTMLElement>('span, font').forEach((element) => {
      if (range.intersectsNode(element)) element.replaceWith(...Array.from(element.childNodes))
    })
    Array.from(editor.value?.children ?? []).forEach((line) => {
      if (range.intersectsNode(line)) (line as HTMLElement).style.textAlign = ''
    })
  })
}

function undo(): void {
  const previous = undoStack.value[undoStack.value.length - 1]
  if (previous === undefined) return
  undoStack.value = undoStack.value.slice(0, -1)
  render(previous)
  emit('update:modelValue', previous)
}
</script>

<template>
  <div>
    <div class="mb-1 flex justify-end">
      <button
        type="button"
        class="inline-flex items-center gap-1 rounded-md border border-hairline bg-canvas px-3 py-1 text-[11px] font-semibold text-ink hover:bg-surface"
        :aria-expanded="isToolbarOpen"
        aria-controls="rich-text-toolbar"
        title="Show or hide the formatting tools"
        @mousedown.prevent
        @click="isToolbarOpen = !isToolbarOpen"
      >
        Edit
        <span aria-hidden="true" class="inline-block transition-transform" :class="{ 'rotate-90': isToolbarOpen }">&rsaquo;</span>
      </button>
    </div>
    <div
      id="rich-text-toolbar"
      class="flex flex-wrap items-center gap-1.5 rounded-t-lg border border-b-0 border-hairline bg-canvas p-2"
      v-show="isToolbarOpen"
      role="toolbar"
      aria-label="Message formatting. Applies to the selected text, or the whole message if nothing is selected."
    >
      <button type="button" :class="toolButtonClass" :disabled="isEmpty" title="Bold" @mousedown.prevent @click="format('bold')"><strong>B</strong></button>
      <button type="button" :class="toolButtonClass" :disabled="isEmpty" title="Italic" @mousedown.prevent @click="format('italic')"><em>I</em></button>
      <button type="button" :class="toolButtonClass" :disabled="isEmpty" title="Underline" @mousedown.prevent @click="format('underline')"><u>U</u></button>
      <button type="button" :class="toolButtonClass" :disabled="isEmpty" title="Strikethrough" @mousedown.prevent @click="format('strikeThrough')"><s>S</s></button>

      <span class="mx-1 h-5 w-px bg-hairline" aria-hidden="true" />

      <label class="flex items-center gap-1 text-[11px] font-semibold text-muted">
        Size
        <input
          v-model.number="fontSize"
          type="number"
          :min="MIN_FONT_SIZE"
          :max="MAX_FONT_SIZE"
          list="rich-text-font-sizes"
          class="w-16 rounded-md border border-hairline bg-surface px-1.5 py-1 text-[11px] text-ink"
          aria-label="Font size in pixels"
          @keydown.enter.prevent="applySize"
        />
        <span>px</span>
      </label>
      <datalist id="rich-text-font-sizes">
        <option v-for="size in FONT_SIZE_PRESETS" :key="size" :value="size" />
      </datalist>
      <button type="button" :class="toolButtonClass" :disabled="isEmpty" title="Apply this font size" @mousedown.prevent @click="applySize">Set size</button>

      <span class="mx-1 h-5 w-px bg-hairline" aria-hidden="true" />

      <input v-model="textColor" type="color" class="h-7 w-8 cursor-pointer rounded border border-hairline bg-surface p-0.5" aria-label="Text color" />
      <button type="button" :class="toolButtonClass" :disabled="isEmpty" title="Apply text color" @mousedown.prevent @click="applyColor('foreColor', textColor)">Text color</button>
      <input v-model="highlightColor" type="color" class="h-7 w-8 cursor-pointer rounded border border-hairline bg-surface p-0.5" aria-label="Highlight color" />
      <button type="button" :class="toolButtonClass" :disabled="isEmpty" title="Apply highlight" @mousedown.prevent @click="applyColor('hiliteColor', highlightColor)">Highlight</button>

      <span class="mx-1 h-5 w-px bg-hairline" aria-hidden="true" />

      <button type="button" :class="toolButtonClass" :disabled="isEmpty" title="Align left" @mousedown.prevent @click="align('justifyLeft')">Left</button>
      <button type="button" :class="toolButtonClass" :disabled="isEmpty" title="Center" @mousedown.prevent @click="align('justifyCenter')">Center</button>
      <button type="button" :class="toolButtonClass" :disabled="isEmpty" title="Align right" @mousedown.prevent @click="align('justifyRight')">Right</button>

      <span class="mx-1 h-5 w-px bg-hairline" aria-hidden="true" />

      <button type="button" :class="toolButtonClass" :disabled="isEmpty" title="Add or remove bullets on the selected lines" @mousedown.prevent @click="toggleBullets">&bull; List</button>
      <button type="button" :class="toolButtonClass" :disabled="isEmpty" title="Turn text into tiny text" @mousedown.prevent @click="applyTinyText">ᵗⁱⁿʸ</button>
      <button type="button" :class="toolButtonClass" :disabled="isEmpty" title="Remove all formatting" @mousedown.prevent @click="clearFormatting">Clear</button>
      <button
        v-if="undoStack.length > 0"
        type="button"
        class="text-[11px] font-semibold text-primary-700 hover:underline dark:text-primary-300"
        @mousedown.prevent
        @click="undo"
      >
        Undo
      </button>
    </div>

    <div class="relative">
      <div
        ref="editor"
        contenteditable="true"
        role="textbox"
        aria-multiline="true"
        :aria-label="ariaLabel"
        :class="isToolbarOpen ? 'rounded-b-lg' : 'rounded-lg'"
        class="min-h-[9rem] w-full whitespace-pre-wrap break-words border border-hairline bg-canvas px-3 py-2 text-xs font-normal text-ink focus:outline-none focus:ring-2 focus:ring-action"
        @input="onInput"
        @paste="onPaste"
      />
      <span v-if="isEmpty" class="pointer-events-none absolute left-3 top-2 text-xs text-muted">{{ placeholder }}</span>
    </div>
    <p class="mt-1 text-[11px]" :class="isTooLong ? 'text-rose-700' : 'text-muted'">
      Select text, then use the toolbar. With nothing selected, the whole message is changed.
      <span class="float-right tabular-nums">{{ modelValue.length }} / {{ maxLength }}</span>
    </p>
  </div>
</template>
