<script lang="ts">
import { defineComponent, h, type VNodeChild } from 'vue'
import { parseRichText, type RichNode } from '@/utils/richText'

/**
 * Renders an announcement message with its formatting tags. Output is built from Vue elements
 * (never v-html), and sizes and colors are validated by the parser, so message text cannot
 * inject markup, scripts or arbitrary CSS.
 */
function renderNodes(nodes: RichNode[]): VNodeChild[] {
  return nodes.map((node): VNodeChild => {
    if (node.type === 'text') return node.text

    const children = renderNodes(node.children)
    const value = node.value ?? undefined
    switch (node.tag) {
      case 'b':
        return h('strong', null, children)
      case 'i':
        return h('em', null, children)
      case 'u':
        return h('u', null, children)
      case 's':
        return h('s', null, children)
      case 'size':
        return h('span', { style: { fontSize: `${value}px`, lineHeight: '1.35' } }, children)
      case 'color':
        return h('span', { style: { color: value } }, children)
      case 'bg':
        return h(
          'span',
          { style: { backgroundColor: value, borderRadius: '0.2em', padding: '0 0.15em' } },
          children,
        )
      default:
        return h('span', { style: { display: 'block', textAlign: node.tag } }, children)
    }
  })
}

export default defineComponent({
  name: 'FormattedText',
  props: {
    text: { type: String, required: true },
  },
  setup(props) {
    return () =>
      h('div', { class: 'whitespace-pre-wrap break-words' }, renderNodes(parseRichText(props.text)))
  },
})
</script>
