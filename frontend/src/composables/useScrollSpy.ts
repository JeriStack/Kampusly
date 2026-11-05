import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue'

/**
 * Tracks which section is currently in view and returns its id.
 *
 * Observes the elements matching the given ids and reports the one nearest the
 * top of the viewport. Uses IntersectionObserver so it is passive and cheap,
 * with a scroll fallback for the top and bottom edges of the page.
 *
 * @param sectionIds Ids of the sections to watch, in document order.
 */
export function useScrollSpy(sectionIds: string[]): Ref<string | null> {
  const activeId = ref<string | null>(null)

  let observer: IntersectionObserver | null = null
  // Visibility ratio per section, used to pick the most-visible one.
  const ratios = new Map<string, number>()

  function pickMostVisible() {
    let best: string | null = null
    let bestRatio = 0
    for (const [id, ratio] of ratios) {
      if (ratio > bestRatio) {
        bestRatio = ratio
        best = id
      }
    }
    if (best && bestRatio > 0) {
      activeId.value = best
    }
  }

  function handleEdges() {
    // At the very top, no section is "active" (hero is above the first anchor).
    if (window.scrollY < 120) {
      activeId.value = null
      return
    }
    // At the very bottom, force the last section active even if short.
    const atBottom =
      window.innerHeight + window.scrollY >=
      document.documentElement.scrollHeight - 2
    if (atBottom && sectionIds.length) {
      activeId.value = sectionIds[sectionIds.length - 1]
    }
  }

  onMounted(() => {
    const elements = sectionIds
      .map((id) => document.getElementById(id))
      .filter((el): el is HTMLElement => el !== null)

    observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          ratios.set(entry.target.id, entry.intersectionRatio)
        }
        pickMostVisible()
        handleEdges()
      },
      {
        // Bias the detection line toward the upper third of the viewport.
        rootMargin: '-45% 0px -45% 0px',
        threshold: [0, 0.25, 0.5, 0.75, 1],
      },
    )

    for (const el of elements) {
      observer.observe(el)
    }

    window.addEventListener('scroll', handleEdges, { passive: true })
    handleEdges()
  })

  onBeforeUnmount(() => {
    observer?.disconnect()
    observer = null
    window.removeEventListener('scroll', handleEdges)
  })

  return activeId
}
