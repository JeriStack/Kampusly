import { features, navLinks, steps, userRoles } from '@/features/landing/content'
import { describe, expect, it } from './harness'

describe('Kampusly Landing Content & Brand Invariants', () => {
  it('exposes the three primary section navigation links', () => {
    expect(navLinks.length).toBe(3)
    expect(navLinks.map((link) => link.label)).toEqual([
      'Features',
      'How It Works',
      'About',
    ])
  })

  it('defines four core MVP feature cards, three onboarding steps, and three role benefit cards', () => {
    expect(features.length).toBe(4)
    expect(steps.length).toBe(3)
    expect(userRoles.length).toBe(3)

    const roles = userRoles.map((card) => card.title)
    expect(roles).toEqual(['Students', 'Instructors', 'Administrators'])
  })
})
