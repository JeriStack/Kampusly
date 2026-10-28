import type { IconName } from '@/components/common/AppIcon.vue'

export interface Feature {
  icon: IconName
  title: string
  description: string
}

export interface Step {
  number: number
  icon: IconName
  title: string
  description: string
}

export interface UserRole {
  icon: IconName
  title: string
  description: string
}

export const features: Feature[] = [
  {
    icon: 'calendar',
    title: 'Class Schedules',
    description:
      'View subjects, class times, rooms, and instructors in one organized timetable.',
  },
  {
    icon: 'bell',
    title: 'Smart Notifications',
    description:
      'Stay informed about schedule changes, cancellations, and upcoming classes.',
  },
  {
    icon: 'megaphone',
    title: 'Class Announcements',
    description:
      'Keep up with important academic updates and instructor notices.',
  },
  {
    icon: 'download',
    title: 'PDF Schedule Export',
    description:
      'Download your personal class schedule for offline reference.',
  },
]

export const steps: Step[] = [
  {
    number: 1,
    icon: 'login',
    title: 'Access Kampusly',
    description: 'Sign in using your authorized account.',
  },
  {
    number: 2,
    icon: 'calendar',
    title: 'Check Your Classes',
    description: 'View your personal timetable and important announcements.',
  },
  {
    number: 3,
    icon: 'bell',
    title: 'Stay Updated',
    description: 'Receive relevant notifications when class information changes.',
  },
]

export const userRoles: UserRole[] = [
  {
    icon: 'student',
    title: 'Students',
    description:
      'View personal schedules, check class details, receive notifications, and download schedules.',
  },
  {
    icon: 'instructor',
    title: 'Instructors',
    description:
      'View assigned classes and communicate announcements or class-unavailability notices.',
  },
  {
    icon: 'admin',
    title: 'Administrators',
    description:
      'Manage academic schedules, rooms, subjects, users, and published updates.',
  },
]

export interface NavLink {
  label: string
  href: string
}

export const navLinks: NavLink[] = [
  { label: 'Features', href: '#features' },
  { label: 'How It Works', href: '#how-it-works' },
  { label: 'About', href: '#about' },
]
