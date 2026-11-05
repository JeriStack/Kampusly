export type UserRole = 'student' | 'instructor' | 'administrator'
export type ScheduleStatus = 'Draft' | 'Published' | 'Cancelled'

export interface DemoUser {
  email: string
  name: string
  role: UserRole
  initials: string
  title: string
  section?: string
  active?: boolean
}

export interface DemoClass {
  id: number
  code: string
  subject: string
  day: string
  start: string
  end: string
  room: string
  instructor: string
  section: string
  status: ScheduleStatus
}

export interface DemoNotification {
  id: number
  title: string
  detail: string
  time: string
  unread: boolean
  type: 'schedule' | 'announcement' | 'notice'
  audience?: UserRole[]
}

export interface DemoAnnouncement {
  id: number
  title: string
  body: string
  category: string
  date: string
  audience: string
  author: string
}

export interface DemoClassNotice {
  id: number
  subject: string
  type: string
  message: string
  status: 'Published'
  date: string
}

export const demoUsers: DemoUser[] = [
  {
    email: 'alex.student@demo.kampusly.edu',
    name: 'Alex Morgan',
    role: 'student',
    initials: 'AM',
    title: 'Student',
    section: 'BSCS · 2A',
  },
  {
    email: 'jordan.instructor@demo.kampusly.edu',
    name: 'Jordan Rivera',
    role: 'instructor',
    initials: 'JR',
    title: 'Instructor',
    section: 'Computer Science',
  },
  {
    email: 'casey.admin@demo.kampusly.edu',
    name: 'Casey Bennett',
    role: 'administrator',
    initials: 'CB',
    title: 'Administrator',
    section: 'Academic office',
  },
]

export const initialClasses: DemoClass[] = [
  {
    id: 1,
    code: 'CS 204',
    subject: 'Human-Computer Interaction',
    day: 'Monday',
    start: '08:30',
    end: '10:00',
    room: 'Room 204',
    instructor: 'Jordan Rivera',
    section: 'BSCS · 2A',
    status: 'Published',
  },
  {
    id: 2,
    code: 'CS 212',
    subject: 'Database Systems',
    day: 'Monday',
    start: '10:30',
    end: '12:00',
    room: 'Lab 3',
    instructor: 'Taylor Santos',
    section: 'BSCS · 2A',
    status: 'Published',
  },
  {
    id: 3,
    code: 'CS 230',
    subject: 'Software Engineering',
    day: 'Monday',
    start: '13:00',
    end: '14:30',
    room: 'Room 118',
    instructor: 'Jordan Rivera',
    section: 'BSCS · 2A',
    status: 'Published',
  },
  {
    id: 4,
    code: 'CS 204',
    subject: 'Human-Computer Interaction',
    day: 'Tuesday',
    start: '09:00',
    end: '10:30',
    room: 'Room 204',
    instructor: 'Jordan Rivera',
    section: 'BSCS · 2A',
    status: 'Published',
  },
  {
    id: 5,
    code: 'CS 212',
    subject: 'Database Systems',
    day: 'Wednesday',
    start: '10:30',
    end: '12:00',
    room: 'Lab 3',
    instructor: 'Taylor Santos',
    section: 'BSCS · 2A',
    status: 'Published',
  },
]

export const initialNotifications: DemoNotification[] = [
  {
    id: 1,
    title: 'Room update · CS 204',
    detail: 'Human-Computer Interaction is in Room 204 this Monday.',
    time: '10 minutes ago',
    unread: true,
    type: 'schedule',
  },
  {
    id: 2,
    title: 'Midterm week reminder',
    detail: 'Check the academic calendar for upcoming assessment dates.',
    time: 'Yesterday',
    unread: true,
    type: 'announcement',
  },
  {
    id: 3,
    title: 'Welcome to the new semester',
    detail: 'Your illustrative demo timetable is ready to explore.',
    time: 'Monday',
    unread: false,
    type: 'announcement',
  },
]

export const initialAnnouncements: DemoAnnouncement[] = [
  {
    id: 1,
    title: 'Midterm week is coming up',
    body: 'Take a moment to review the academic calendar and plan ahead. Reach out to your instructors if you have questions about your classes.',
    category: 'Academic calendar',
    date: 'Oct 10, 2026',
    audience: 'All students',
    author: 'Academic office',
  },
  {
    id: 2,
    title: 'Welcome to a new semester',
    body: 'Your class schedule and campus announcements are in one place. Check in here to stay up to date as the semester gets underway.',
    category: 'Campus news',
    date: 'Oct 08, 2026',
    audience: 'All students',
    author: 'Academic office',
  },
]

export const roleNames: Record<UserRole, string> = {
  student: 'Student',
  instructor: 'Instructor',
  administrator: 'Administrator',
}
