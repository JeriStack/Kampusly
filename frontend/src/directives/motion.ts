import type { Directive } from 'vue'

interface RevealOptions {
  delay?: number
  y?: number
  scale?: number
}

interface MotionState {
  frame?: number
  delayTimer?: number
  observer?: IntersectionObserver
  cleanup?: () => void
}

const revealStates = new WeakMap<HTMLElement, MotionState>()
const hoverStates = new WeakMap<HTMLElement, MotionState>()

function prefersReducedMotion(): boolean {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

function spring(
  element: HTMLElement,
  from: { opacity: number; y: number; scale: number },
  to: { opacity: number; y: number; scale: number },
  tension: number,
  friction: number,
  activeStates: WeakMap<HTMLElement, MotionState>,
): number {
  const state = {
    opacity: from.opacity,
    y: from.y,
    scale: from.scale,
    opacityVelocity: 0,
    yVelocity: 0,
    scaleVelocity: 0,
  }
  let previousTime = 0

  const frame = (time: number): void => {
    const dt = previousTime === 0 ? 0 : Math.min((time - previousTime) / 1000, 0.032)
    previousTime = time

    state.opacityVelocity +=
      (-tension * (state.opacity - to.opacity) - friction * state.opacityVelocity) * dt
    state.opacity += state.opacityVelocity * dt
    state.yVelocity += (-tension * (state.y - to.y) - friction * state.yVelocity) * dt
    state.y += state.yVelocity * dt
    state.scaleVelocity +=
      (-tension * (state.scale - to.scale) - friction * state.scaleVelocity) * dt
    state.scale += state.scaleVelocity * dt

    element.style.opacity = String(state.opacity)
    element.style.translate = `0 ${state.y}px`
    element.style.scale = String(state.scale)

    const settled =
      Math.abs(state.opacity - to.opacity) < 0.001 &&
      Math.abs(state.y - to.y) < 0.05 &&
      Math.abs(state.scale - to.scale) < 0.001 &&
      Math.abs(state.opacityVelocity) < 0.01 &&
      Math.abs(state.yVelocity) < 0.1 &&
      Math.abs(state.scaleVelocity) < 0.01

    if (settled) {
      element.style.opacity = String(to.opacity)
      element.style.translate = `0 ${to.y}px`
      element.style.scale = String(to.scale)
      return
    }

    activeStates.get(element)!.frame = requestAnimationFrame(frame)
  }

  return requestAnimationFrame(frame)
}

export const revealDirective: Directive<HTMLElement, RevealOptions> = {
  mounted(element, binding) {
    if (prefersReducedMotion()) {
      return
    }

    element.style.opacity = '0'
    element.style.translate = `0 ${binding.value?.y ?? 28}px`
    element.style.scale = String(binding.value?.scale ?? 1)

    const state: MotionState = {}
    revealStates.set(element, state)

    const reveal = (): void => {
      state.observer?.disconnect()
      state.delayTimer = window.setTimeout(() => {
        state.frame = spring(
          element,
          {
            opacity: Number(element.style.opacity),
            y: binding.value?.y ?? 28,
            scale: binding.value?.scale ?? 1,
          },
          { opacity: 1, y: 0, scale: 1 },
          200,
          26,
          revealStates,
        )
      }, binding.value?.delay ?? 0)
    }

    if (!('IntersectionObserver' in window)) {
      reveal()
      return
    }

    state.observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          reveal()
        }
      },
      { threshold: 0.12, rootMargin: '0px 0px -24px 0px' },
    )
    state.observer.observe(element)
    state.cleanup = () => state.observer?.disconnect()
  },
  unmounted(element) {
    const state = revealStates.get(element)
    if (!state) return
    state.observer?.disconnect()
    if (state.delayTimer !== undefined) window.clearTimeout(state.delayTimer)
    if (state.frame !== undefined) cancelAnimationFrame(state.frame)
    state.cleanup?.()
    revealStates.delete(element)
  },
}

export const hoverSpringDirective: Directive<HTMLElement, { y?: number; scale?: number }> = {
  mounted(element, binding) {
    if (prefersReducedMotion()) {
      return
    }

    const state: MotionState = {}
    hoverStates.set(element, state)
    element.style.translate = '0 0'
    element.style.scale = '1'

    const animate = (active: boolean): void => {
      if (window.matchMedia('(max-width: 768px)').matches) {
        return
      }
      if (state.frame !== undefined) cancelAnimationFrame(state.frame)
      state.frame = spring(
        element,
        {
          opacity: Number(element.style.opacity || 1),
          y: Number.parseFloat(element.style.translate.split(' ')[1] || '0'),
          scale: Number(element.style.scale || 1),
        },
        {
          opacity: Number(element.style.opacity || 1),
          y: active ? (binding.value?.y ?? -8) : 0,
          scale: active ? (binding.value?.scale ?? 1) : 1,
        },
        300,
        22,
        hoverStates,
      )
    }
    const enter = (): void => animate(true)
    const leave = (): void => animate(false)
    element.addEventListener('pointerenter', enter)
    element.addEventListener('pointerleave', leave)
    element.addEventListener('focusin', enter)
    element.addEventListener('focusout', leave)

    hoverStates.set(element, {
      ...state,
      cleanup: () => {
        element.removeEventListener('pointerenter', enter)
        element.removeEventListener('pointerleave', leave)
        element.removeEventListener('focusin', enter)
        element.removeEventListener('focusout', leave)
      },
    })
  },
  unmounted(element) {
    const state = hoverStates.get(element)
    if (!state) return
    state.cleanup?.()
    if (state.frame !== undefined) cancelAnimationFrame(state.frame)
    hoverStates.delete(element)
  },
}
