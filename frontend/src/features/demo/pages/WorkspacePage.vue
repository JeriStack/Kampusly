<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import {
  demoUsers,
  initialAnnouncements,
  initialClasses,
  initialNotifications,
  roleNames,
  type DemoAnnouncement,
  type DemoClass,
  type DemoClassNotice,
  type DemoNotification,
  type DemoUser,
  type UserRole,
} from '../demoData'

const props = defineProps<{
  user: DemoUser
}>()

const emit = defineEmits<{
  'sign-out': []
  'switch-user': [user: DemoUser]
}>()

type PageId = 'dashboard' | 'schedule' | 'announcements' | 'class-notices' | 'academic' | 'users' | 'notifications'
type ModalId = 'schedule' | 'announcement' | 'academic' | 'user' | 'notice-review'

interface NavItem {
  id: PageId
  label: string
  icon: string
}

interface AcademicRecord {
  category: string
  name: string
}

const page = ref<PageId>('dashboard')
const mobileMenuOpen = ref(false)
const profileMenuOpen = ref(false)
const classes = ref<DemoClass[]>(initialClasses.map((item) => ({ ...item })))
const notifications = ref<DemoNotification[]>(initialNotifications.map((item) => ({ ...item })))
const announcements = ref<DemoAnnouncement[]>(initialAnnouncements.map((item) => ({ ...item })))
const classNotices = ref<DemoClassNotice[]>([])
const accounts = ref<DemoUser[]>(demoUsers.map((item) => ({ ...item })))
const academicRecords = ref<AcademicRecord[]>([
  { category: 'Subjects', name: '86 active courses' },
  { category: 'Rooms', name: '32 campus rooms' },
  { category: 'Sections', name: '36 active sections' },
  { category: 'Instructors', name: '42 teaching staff' },
])
const activeModal = ref<ModalId | null>(null)
const formError = ref('')
const toastMessage = ref('')
const scheduleForm = reactive({
  code: '',
  subject: '',
  day: 'Monday',
  start: '08:30',
  end: '10:00',
  room: '',
  instructor: 'Jordan Rivera',
  section: 'BSCS · 2A',
})
const announcementForm = reactive({ title: '', category: 'Campus news', body: '' })
const academicForm = reactive({ category: 'Subjects', name: '' })
const userForm = reactive({ name: '', email: '', role: 'student' as UserRole, section: '' })
const noticeForm = reactive({ classId: '', type: '', message: '' })
const pendingNotice = ref<{ subject: string; section: string; type: string; message: string } | null>(null)

const navigation: Record<UserRole, NavItem[]> = {
  student: [
    { id: 'dashboard', label: 'Overview', icon: '⌂' },
    { id: 'schedule', label: 'My schedule', icon: '▦' },
    { id: 'notifications', label: 'Notifications', icon: '◉' },
  ],
  instructor: [
    { id: 'dashboard', label: 'Overview', icon: '⌂' },
    { id: 'schedule', label: 'My schedule', icon: '▦' },
    { id: 'announcements', label: 'Announcements', icon: '◌' },
    { id: 'class-notices', label: 'Class notices', icon: '↗' },
    { id: 'notifications', label: 'Notifications', icon: '◉' },
  ],
  administrator: [
    { id: 'dashboard', label: 'Overview', icon: '⌂' },
    { id: 'schedule', label: 'Schedules', icon: '▦' },
    { id: 'academic', label: 'Academic data', icon: '▤' },
    { id: 'users', label: 'User accounts', icon: '♙' },
    { id: 'announcements', label: 'Announcements', icon: '◌' },
    { id: 'notifications', label: 'Notifications', icon: '◉' },
  ],
}

const pageTitles: Record<PageId, string> = {
  dashboard: 'Overview',
  schedule: 'My schedule',
  announcements: 'Announcements',
  'class-notices': 'Class notices',
  academic: 'Academic data',
  users: 'User accounts',
  notifications: 'Notifications',
}
const demoRoles: UserRole[] = ['student', 'instructor', 'administrator']

const navItems = computed(() => navigation[props.user.role])
const visibleNotifications = computed(() =>
  notifications.value.filter((item) => !item.audience || item.audience.includes(props.user.role)),
)
const unreadCount = computed(() => visibleNotifications.value.filter((item) => item.unread).length)
const todayClasses = computed(() => {
  const roleClasses =
    props.user.role === 'administrator'
      ? classes.value
      : props.user.role === 'instructor'
        ? classes.value.filter((item) => item.instructor === props.user.name)
        : classes.value.filter((item) => item.section === props.user.section)
  return roleClasses.filter((item) => item.day === 'Monday' && item.status !== 'Cancelled')
})
const scheduleRows = computed(() => {
  if (props.user.role === 'administrator') {
    return classes.value
  }
  if (props.user.role === 'instructor') {
    return classes.value.filter((item) => item.instructor === props.user.name && item.status !== 'Cancelled')
  }
  return classes.value.filter((item) => item.section === props.user.section && item.status !== 'Cancelled')
})
const assignedClasses = computed(() =>
  classes.value.filter((item) => item.instructor === props.user.name && item.status !== 'Cancelled'),
)
const greeting = computed(() => {
  const firstName = props.user.name.split(' ')[0]
  return `Good morning, ${firstName}`
})
const academicCategories = ['Subjects', 'Rooms', 'Sections', 'Instructors']
const daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']

function openPage(id: PageId) {
  page.value = id
  mobileMenuOpen.value = false
}

function openModal(id: ModalId) {
  formError.value = ''
  activeModal.value = id
}

function closeModal() {
  activeModal.value = null
  formError.value = ''
}

function dismissToast() {
  toastMessage.value = ''
}

function markAllRead() {
  notifications.value = notifications.value.map((item) =>
    !item.audience || item.audience.includes(props.user.role)
      ? { ...item, unread: false }
      : item,
  )
}

function addNotification(
  title: string,
  detail: string,
  type: DemoNotification['type'],
  audience?: UserRole[],
) {
  notifications.value.unshift({
    id: Math.max(0, ...notifications.value.map((item) => item.id)) + 1,
    title,
    detail,
    time: 'Just now',
    unread: true,
    type,
    audience,
  })
}

function minutesSinceMidnight(time: string) {
  const [hours, minutes] = time.split(':').map(Number)
  return hours * 60 + minutes
}

function scheduleConflict() {
  const start = minutesSinceMidnight(scheduleForm.start)
  const end = minutesSinceMidnight(scheduleForm.end)

  if (end <= start) {
    return 'End time must be later than the start time.'
  }

  const overlap = classes.value.find((item) => {
    if (item.status === 'Cancelled' || item.day !== scheduleForm.day) {
      return false
    }

    return start < minutesSinceMidnight(item.end) && end > minutesSinceMidnight(item.start)
  })

  if (!overlap) {
    return ''
  }

  if (overlap.room.toLowerCase() === scheduleForm.room.trim().toLowerCase()) {
    return `${overlap.room} is already booked for ${overlap.code} from ${overlap.start} to ${overlap.end} on ${overlap.day}.`
  }

  if (overlap.instructor.toLowerCase() === scheduleForm.instructor.trim().toLowerCase()) {
    return `${overlap.instructor} is already teaching ${overlap.code} from ${overlap.start} to ${overlap.end} on ${overlap.day}.`
  }

  return `${overlap.section} already has ${overlap.code} from ${overlap.start} to ${overlap.end} on ${overlap.day}.`
}

function saveSchedule(status: DemoClass['status']) {
  if (
    !scheduleForm.code.trim() ||
    !scheduleForm.subject.trim() ||
    !scheduleForm.room.trim() ||
    !scheduleForm.instructor ||
    !scheduleForm.section ||
    !scheduleForm.start ||
    !scheduleForm.end
  ) {
    formError.value = 'Complete every required schedule field before saving.'
    return
  }

  const conflict = scheduleConflict()

  if (conflict) {
    formError.value = conflict
    return
  }

  const schedule: DemoClass = {
    id: Math.max(0, ...classes.value.map((item) => item.id)) + 1,
    code: scheduleForm.code.trim(),
    subject: scheduleForm.subject.trim(),
    day: scheduleForm.day,
    start: scheduleForm.start,
    end: scheduleForm.end,
    room: scheduleForm.room.trim(),
    instructor: scheduleForm.instructor,
    section: scheduleForm.section,
    status,
  }

  classes.value.push(schedule)

  if (status === 'Published') {
    addNotification(
      `New class · ${schedule.code}`,
      `${schedule.subject} is scheduled ${schedule.day} at ${schedule.start} in ${schedule.room}.`,
      'schedule',
      ['student'],
    )
  }

  toastMessage.value =
    status === 'Published'
      ? 'Schedule published. Affected students were notified in this demo.'
      : 'Schedule saved as a draft.'
  closeModal()
}

function publishSchedule(schedule: DemoClass) {
  if (
    schedule.status !== 'Draft' ||
    !window.confirm(`Publish ${schedule.code} for ${schedule.section}?`)
  ) {
    return
  }

  schedule.status = 'Published'
  addNotification(
    `New class · ${schedule.code}`,
    `${schedule.subject} is scheduled ${schedule.day} at ${schedule.start} in ${schedule.room}.`,
    'schedule',
    ['student'],
  )
  toastMessage.value = 'Schedule published. Affected students were notified in this demo.'
}

function cancelSchedule(schedule: DemoClass) {
  if (schedule.status !== 'Published' || !window.confirm(`Cancel ${schedule.code} on ${schedule.day}?`)) {
    return
  }

  schedule.status = 'Cancelled'
  addNotification(
    `Class cancelled · ${schedule.code}`,
    `${schedule.subject} on ${schedule.day} has been cancelled.`,
    'schedule',
    ['student'],
  )
  toastMessage.value = 'Schedule cancelled. Affected students were notified in this demo.'
}

function saveAnnouncement() {
  const announcement: DemoAnnouncement = {
    id: Math.max(0, ...announcements.value.map((item) => item.id)) + 1,
    title: announcementForm.title.trim(),
    body: announcementForm.body.trim(),
    category: announcementForm.category,
    date: 'Oct 12, 2026',
    audience: 'All students',
    author: props.user.name,
  }

  announcements.value.unshift(announcement)
  addNotification(
    announcement.title,
    `${announcement.category} · ${announcement.body}`,
    'announcement',
    ['student'],
  )
  toastMessage.value = 'Announcement published. Students were notified in this demo.'
  announcementForm.title = ''
  announcementForm.body = ''
  closeModal()
}

function saveAcademicRecord() {
  academicRecords.value.push({
    category: academicForm.category,
    name: academicForm.name.trim(),
  })

  toastMessage.value = `${academicForm.category.slice(0, -1)} record added to this demo.`
  academicForm.name = ''
  closeModal()
}

function saveUser() {
  const email = userForm.email.trim().toLowerCase()

  if (accounts.value.some((item) => item.email.toLowerCase() === email)) {
    formError.value = 'A demo account with this email already exists.'
    return
  }

  const name = userForm.name.trim()
  accounts.value.push({
    email,
    name,
    role: userForm.role,
    initials: name
      .split(/\s+/)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase() ?? '')
      .join(''),
    title: roleNames[userForm.role],
    section: userForm.section.trim() || undefined,
    active: true,
  })
  toastMessage.value = 'Demo user added to the sample account list.'
  userForm.name = ''
  userForm.email = ''
  userForm.section = ''
  closeModal()
}

function toggleAccountStatus(account: DemoUser) {
  if (account.email === props.user.email) {
    toastMessage.value = 'Switch to another demo role before deactivating this account.'
    return
  }

  const nextStatus = account.active === false ? 'reactivate' : 'deactivate'

  if (!window.confirm(`Confirm ${nextStatus} for ${account.name}?`)) {
    return
  }

  account.active = account.active === false
  toastMessage.value = `${account.name} is now ${account.active ? 'active' : 'inactive'} in the demo account list.`
}

function reviewNotice() {
  const selectedClass = classes.value.find(
    (item) => item.id === Number(noticeForm.classId) && item.instructor === props.user.name,
  )

  if (!selectedClass) {
    formError.value = 'Choose a class assigned to your instructor account.'
    return
  }

  pendingNotice.value = {
    subject: selectedClass.subject,
    section: selectedClass.section,
    type: noticeForm.type,
    message: noticeForm.message.trim(),
  }
  openModal('notice-review')
}

function publishNotice() {
  if (!pendingNotice.value) {
    return
  }

  classNotices.value.unshift({
    id: Math.max(0, ...classNotices.value.map((item) => item.id)) + 1,
    ...pendingNotice.value,
    status: 'Published',
    date: 'Just now',
  })
  addNotification(
    `Class update · ${pendingNotice.value.subject}`,
    pendingNotice.value.message,
    'notice',
    ['student'],
  )
  toastMessage.value = 'Class notice published. Affected students were notified in this demo.'
  pendingNotice.value = null
  noticeForm.classId = ''
  noticeForm.type = ''
  noticeForm.message = ''
  closeModal()
}

function switchRole(role: UserRole) {
  const sampleUsers: Record<UserRole, string> = {
    student: 'alex.student@demo.kampusly.edu',
    instructor: 'jordan.instructor@demo.kampusly.edu',
    administrator: 'casey.admin@demo.kampusly.edu',
  }
  const email = sampleUsers[role]
  const demoUser = demoUsers.find((candidate) => candidate.email === email)

  if (demoUser) {
    emit('switch-user', accounts.value.find((account) => account.email === demoUser.email) ?? demoUser)
    page.value = 'dashboard'
    profileMenuOpen.value = false
    mobileMenuOpen.value = false
  }
}

function toggleProfileMenu() {
  if (window.matchMedia('(max-width: 850px)').matches) {
    mobileMenuOpen.value = true
  }
  profileMenuOpen.value = !profileMenuOpen.value
}

function printSchedule() {
  window.print()
}
</script>

<template>
  <div class="workspace">
    <button
      v-if="mobileMenuOpen"
      class="sidebar-scrim"
      type="button"
      aria-label="Close navigation"
      @click="mobileMenuOpen = false"
    ></button>

    <aside class="sidebar" :class="{ 'sidebar-open': mobileMenuOpen }" aria-label="Application navigation">
      <a class="sidebar-brand" href="#overview" @click.prevent="openPage('dashboard')">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 32 32" fill="none">
            <rect x="3" y="4" width="26" height="25" rx="8" fill="currentColor" />
            <path d="M10 12.5h12M10 17h8M10 21.5h5" stroke="white" stroke-linecap="round" stroke-width="2" />
            <circle cx="23" cy="21.5" r="2" fill="#5EEAD4" />
          </svg>
        </span>
        <span>Kampusly</span>
      </a>

      <div class="campus-switcher">
        <span class="campus-mark" aria-hidden="true">N</span>
        <span class="campus-copy"><strong>Northstar University</strong><span>Academic portal</span></span>
        <span class="campus-chevron" aria-hidden="true">⌄</span>
      </div>

      <p class="nav-caption">WORKSPACE</p>
      <nav class="side-nav" :aria-label="`${roleNames[user.role]} navigation`">
        <button
          v-for="item in navItems"
          :key="item.id"
          class="nav-link"
          :class="{ 'nav-link-active': page === item.id }"
          type="button"
          :aria-current="page === item.id ? 'page' : undefined"
          @click="openPage(item.id)"
        >
          <span class="nav-icon" aria-hidden="true">{{ item.icon }}</span>
          <span>{{ item.label }}</span>
          <span v-if="item.id === 'notifications' && unreadCount" class="nav-count">{{ unreadCount }}</span>
        </button>
      </nav>

      <div class="sidebar-bottom">
        <div class="help-card">
          <span class="help-icon" aria-hidden="true">?</span>
          <div><strong>Need a hand?</strong><span>Visit the help center</span></div>
          <span class="help-arrow" aria-hidden="true">↗</span>
        </div>
        <button class="sidebar-profile" type="button" :aria-expanded="profileMenuOpen" @click="profileMenuOpen = !profileMenuOpen">
          <span class="profile-avatar" :class="`avatar-${user.role}`">{{ user.initials }}</span>
          <span class="profile-copy"><strong>{{ user.name }}</strong><span>{{ roleNames[user.role] }}</span></span>
          <span class="profile-dots" aria-hidden="true">•••</span>
        </button>
        <div v-if="profileMenuOpen" class="profile-menu">
          <p>SWITCH DEMO ROLE</p>
          <button v-for="role in demoRoles" :key="role" type="button" @click="switchRole(role)">
            {{ roleNames[role] }} <span v-if="user.role === role" aria-label="Current role">✓</span>
          </button>
          <button class="sign-out-link" type="button" @click="emit('sign-out')">Sign out</button>
        </div>
      </div>
    </aside>

    <div class="workspace-main">
      <header class="topbar">
        <div class="topbar-left">
          <button
            class="mobile-menu-button"
            type="button"
            :aria-expanded="mobileMenuOpen"
            :aria-label="mobileMenuOpen ? 'Close navigation menu' : 'Open navigation menu'"
            @click="mobileMenuOpen = !mobileMenuOpen"
          >☰</button>
          <div class="breadcrumb"><span>Workspace</span><span aria-hidden="true">/</span><strong>{{ pageTitles[page] }}</strong></div>
        </div>
        <div class="topbar-right">
          <span class="demo-pill"><span aria-hidden="true"></span> DEMO MODE</span>
          <button class="topbar-icon-button" type="button" aria-label="View notifications" @click="openPage('notifications')">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 12a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span v-if="unreadCount" class="notification-dot"></span>
          </button>
          <button class="topbar-user" type="button" :aria-expanded="profileMenuOpen" aria-label="Open account menu" @click="toggleProfileMenu">
            <span class="profile-avatar" :class="`avatar-${user.role}`">{{ user.initials }}</span>
            <span class="topbar-user-name">{{ user.name.split(' ')[0] }}</span>
            <span class="profile-chevron" aria-hidden="true">⌄</span>
          </button>
        </div>
      </header>

      <main class="page-content">
        <div v-if="toastMessage" class="toast-message" role="status">
          <span>{{ toastMessage }}</span>
          <button type="button" aria-label="Dismiss message" @click="dismissToast">×</button>
        </div>
        <div class="prototype-banner" role="note">
          <span aria-hidden="true">ⓘ</span>
          <span><strong>Interactive prototype.</strong> Sample data and sign-in are simulated; no server or account permissions are connected.</span>
        </div>

        <section class="page-heading">
          <div>
            <p class="page-date">MONDAY, OCTOBER 12, 2026</p>
            <h1>{{ page === 'dashboard' ? greeting : pageTitles[page] }}<span v-if="page === 'dashboard'"> 👋</span></h1>
            <p class="page-subtitle">
              <template v-if="user.role === 'student' && page === 'dashboard'">Here’s what’s happening with your campus day.</template>
              <template v-else-if="user.role === 'instructor' && page === 'dashboard'">Your classes and campus updates, all in one place.</template>
              <template v-else-if="user.role === 'administrator' && page === 'dashboard'">A clear view of today across your campus.</template>
              <template v-else>{{ user.section }} <span aria-hidden="true">·</span> Your {{ roleNames[user.role].toLowerCase() }} workspace</template>
            </p>
          </div>
          <div class="heading-actions">
            <button v-if="page === 'schedule' && user.role === 'student'" class="action-button action-button-secondary" type="button" @click="printSchedule">
              <span aria-hidden="true">↓</span> Export PDF
            </button>
            <button v-if="user.role === 'administrator' && page === 'schedule'" class="action-button action-button-primary" type="button" @click="openModal('schedule')">
              <span aria-hidden="true">＋</span> Create schedule
            </button>
          </div>
        </section>

        <template v-if="page === 'dashboard'">
          <section class="metric-grid" :aria-label="`${roleNames[user.role]} summary`">
            <article class="metric-card">
              <span class="metric-icon metric-blue" aria-hidden="true">▦</span>
              <span class="metric-label">{{ user.role === 'administrator' ? 'Published schedules' : 'Classes this week' }}</span>
              <strong>{{ user.role === 'administrator' ? '24' : user.role === 'instructor' ? '8' : '5' }}</strong>
              <span class="metric-foot"><span class="trend-up">↑ 2</span> {{ user.role === 'administrator' ? 'this semester' : 'from last week' }}</span>
            </article>
            <article class="metric-card">
              <span class="metric-icon metric-teal" aria-hidden="true">◉</span>
              <span class="metric-label">{{ user.role === 'administrator' ? 'Active students' : 'Unread updates' }}</span>
              <strong>{{ user.role === 'administrator' ? '1,248' : `${unreadCount.toString().padStart(2, '0')}` }}</strong>
              <span class="metric-foot">{{ user.role === 'administrator' ? 'Across 36 sections' : 'You’re all caught up soon' }}</span>
            </article>
            <article class="metric-card">
              <span class="metric-icon metric-violet" aria-hidden="true">{{ user.role === 'administrator' ? '♙' : '◷' }}</span>
              <span class="metric-label">{{ user.role === 'administrator' ? 'Teaching staff' : 'Next class' }}</span>
              <strong>{{ user.role === 'administrator' ? '42' : user.role === 'instructor' ? '10:30' : '10:30' }}</strong>
              <span class="metric-foot">{{ user.role === 'administrator' ? 'Across 8 departments' : 'Database Systems · Lab 3' }}</span>
            </article>
            <article class="metric-card">
              <span class="metric-icon metric-amber" aria-hidden="true">◷</span>
              <span class="metric-label">{{ user.role === 'administrator' ? 'Pending review' : 'Announcements' }}</span>
              <strong>{{ user.role === 'administrator' ? '3' : '2' }}</strong>
              <span class="metric-foot">{{ user.role === 'administrator' ? 'Schedules need attention' : '1 new this week' }}</span>
            </article>
          </section>

          <section class="dashboard-grid">
            <article class="content-card schedule-overview">
              <header class="card-heading">
                <div><p class="card-eyebrow">{{ user.role === 'administrator' ? 'CAMPUS AT A GLANCE' : 'MONDAY, OCT 12' }}</p><h2>{{ user.role === 'administrator' ? 'Schedule overview' : 'Your schedule today' }}</h2></div>
                <button class="text-action" type="button" @click="openPage('schedule')">View schedule <span aria-hidden="true">→</span></button>
              </header>
              <div v-if="todayClasses.length" class="dashboard-class-list">
                <article v-for="(item, index) in todayClasses" :key="item.id" class="dashboard-class">
                  <div class="time-column"><strong>{{ item.start }}</strong><span>{{ item.end }}</span></div>
                  <span class="class-color-bar" :class="`class-color-${index % 3}`" aria-hidden="true"></span>
                  <div class="dashboard-class-copy"><span>{{ item.code }} <span aria-hidden="true">·</span> {{ item.section }}</span><h3>{{ item.subject }}</h3><p>{{ item.room }} <span aria-hidden="true">·</span> {{ user.role === 'instructor' ? item.section : item.instructor }}</p></div>
                  <span class="status-badge" :class="`status-${item.status.toLowerCase()}`">{{ item.status }}</span>
                </article>
              </div>
              <div v-else class="empty-state"><span aria-hidden="true">◷</span><p>No classes scheduled today.</p></div>
            </article>

            <article class="content-card updates-card">
              <header class="card-heading">
                <div><p class="card-eyebrow">STAY IN THE LOOP</p><h2>Campus updates</h2></div>
                <button class="text-action" type="button" @click="openPage('notifications')">View all <span aria-hidden="true">→</span></button>
              </header>
              <div v-for="item in visibleNotifications.slice(0, 2)" :key="item.id" class="update-item">
                <span class="update-icon" :class="item.type === 'notice' ? 'update-icon-teal' : 'update-icon-blue'" aria-hidden="true">{{ item.type === 'announcement' ? '✦' : '↗' }}</span>
                <div><strong>{{ item.title }}</strong><p>{{ item.detail }}</p><span>{{ item.time }}</span></div>
              </div>
              <div v-if="!visibleNotifications.length" class="empty-state"><p>No campus updates yet.</p></div>
              <button class="all-updates-button" type="button" @click="openPage('announcements')">Browse announcements <span aria-hidden="true">→</span></button>
            </article>
          </section>

          <section v-if="user.role === 'administrator'" class="quick-actions-card">
            <div><span class="quick-action-icon" aria-hidden="true">✦</span><div><h2>Keep your campus in sync</h2><p>Publish a schedule update or share an announcement with your campus.</p></div></div>
            <div class="quick-actions-buttons"><button class="action-button action-button-primary" type="button" @click="openModal('schedule')">＋ Create schedule</button><button class="action-button action-button-secondary" type="button" @click="openModal('announcement')">Write announcement</button></div>
          </section>
          <section v-else-if="user.role === 'instructor'" class="quick-actions-card instructor-tip">
            <div><span class="quick-action-icon" aria-hidden="true">✦</span><div><h2>Something changed?</h2><p>Let your students know about a class update or a schedule change.</p></div></div>
            <div class="quick-actions-buttons"><button class="action-button action-button-primary" type="button" @click="openPage('class-notices')">Submit class notice</button><button class="action-button action-button-secondary" type="button" @click="openModal('announcement')">Create announcement</button></div>
          </section>
        </template>

        <section v-else-if="page === 'schedule'" class="content-card full-card">
          <header class="card-heading schedule-card-heading">
            <div><p class="card-eyebrow">{{ user.role === 'administrator' ? 'SCHEDULE MANAGEMENT' : 'YOUR WEEK' }}</p><h2>{{ user.role === 'administrator' ? 'Class schedules' : 'Classes this week' }}</h2></div>
            <div class="schedule-heading-right"><span class="week-select">Oct 12 – Oct 16, 2026 <span aria-hidden="true">⌄</span></span><div class="view-toggle"><button class="view-toggle-active" type="button">List</button><button type="button">Week</button></div></div>
          </header>
          <div class="schedule-table-wrap">
            <table class="schedule-table">
              <thead><tr><th>Class</th><th>Day &amp; time</th><th>Room</th><th>Instructor</th><th>Section</th><th>Status</th><th v-if="user.role === 'administrator'">Actions</th></tr></thead>
              <tbody>
                <tr v-for="(item, index) in scheduleRows" :key="item.id">
                  <td><span class="table-course"><span class="table-course-mark" :class="`class-color-${index % 3}`" aria-hidden="true"></span><span><strong>{{ item.subject }}</strong><small>{{ item.code }}</small></span></span></td>
                  <td><strong>{{ item.day }}</strong><small>{{ item.start }} – {{ item.end }}</small></td>
                  <td>{{ item.room }}</td><td>{{ item.instructor }}</td><td>{{ item.section }}</td>
                  <td><span class="status-badge" :class="`status-${item.status.toLowerCase()}`">{{ item.status }}</span></td>
                  <td v-if="user.role === 'administrator'" class="row-actions">
                    <button v-if="item.status === 'Draft'" type="button" @click="publishSchedule(item)">Publish</button>
                    <button v-if="item.status === 'Published'" type="button" @click="cancelSchedule(item)">Cancel</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="table-demo-note">Illustrative timetable data · No real student schedule</p>
        </section>

        <section v-else-if="page === 'notifications'" class="content-card full-card">
          <header class="card-heading"><div><p class="card-eyebrow">YOUR UPDATES</p><h2>Notifications <span class="heading-count">{{ unreadCount }} unread</span></h2></div><button class="text-action" type="button" :disabled="!unreadCount" @click="markAllRead">Mark all as read</button></header>
          <div class="notifications-list">
            <article v-for="item in visibleNotifications" :key="item.id" class="notification-row" :class="{ 'notification-unread': item.unread }">
              <span class="notification-type-icon" :class="`notification-type-${item.type}`" aria-hidden="true">{{ item.type === 'schedule' ? '↗' : item.type === 'notice' ? '◷' : '✦' }}</span>
              <div class="notification-copy"><h3>{{ item.title }} <span v-if="item.unread" class="unread-label">NEW</span></h3><p>{{ item.detail }}</p><span>{{ item.time }}</span></div>
              <button v-if="item.unread" class="mark-read-button" type="button" :aria-label="`Mark ${item.title} as read`" @click="notifications = notifications.map((notification) => notification.id === item.id ? { ...notification, unread: false } : notification)">Mark read</button>
              <span v-else class="read-label">Read</span>
            </article>
          </div>
          <p class="table-demo-note">Demo notifications are stored only for this browser session.</p>
        </section>

        <section v-else-if="page === 'announcements'" class="content-card full-card">
          <header class="card-heading"><div><p class="card-eyebrow">CAMPUS &amp; CLASS UPDATES</p><h2>Announcements</h2></div><button v-if="user.role !== 'student'" class="action-button action-button-primary" type="button" @click="openModal('announcement')">＋ Create announcement</button></header>
          <div class="announcement-list">
            <article v-for="(announcement, index) in announcements" :key="announcement.id" class="announcement-card">
              <span class="announcement-icon" :class="index % 2 ? 'announcement-teal' : 'announcement-blue'" aria-hidden="true">✦</span>
              <div><div class="announcement-meta"><span class="announcement-tag">{{ announcement.category }}</span><span>{{ announcement.date }}</span></div><h3>{{ announcement.title }}</h3><p>{{ announcement.body }}</p><span class="announcement-author">{{ announcement.author }} <span aria-hidden="true">·</span> {{ announcement.audience }}</span></div>
            </article>
          </div>
          <p class="table-demo-note">Sample campus announcements · Illustrative content</p>
        </section>

        <section v-else-if="page === 'academic'" class="academic-section">
          <div class="content-card full-card">
            <header class="card-heading">
              <div><p class="card-eyebrow">CAMPUS DIRECTORY</p><h2>Academic data</h2></div>
              <button class="action-button action-button-primary" type="button" @click="openModal('academic')">＋ Add record</button>
            </header>
            <div class="academic-grid">
              <article v-for="(record, index) in academicRecords" :key="record.category" class="academic-card">
                <span class="academic-icon" :class="['academic-blue', 'academic-teal', 'academic-violet', 'academic-amber'][index % 4]" aria-hidden="true">{{ ['▤', '⌂', '▦', '♙'][index % 4] }}</span>
                <div><h3>{{ record.category }}</h3><p>{{ record.name }}</p></div>
                <span>→</span>
              </article>
            </div>
          </div>
        </section>

        <section v-else-if="page === 'users'" class="content-card full-card">
          <header class="card-heading">
            <div><p class="card-eyebrow">ACCESS &amp; ACCOUNTS</p><h2>Campus users</h2></div>
            <button class="action-button action-button-primary" type="button" @click="openModal('user')">＋ Add user</button>
          </header>
          <div class="schedule-table-wrap">
            <table class="schedule-table">
              <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Department / section</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <tr v-for="demoUser in accounts" :key="demoUser.email">
                  <td><strong>{{ demoUser.name }}</strong></td><td>{{ demoUser.email }}</td><td>{{ roleNames[demoUser.role] }}</td><td>{{ demoUser.section }}</td>
                  <td><span class="status-badge" :class="demoUser.active === false ? 'status-cancelled' : 'status-published'">{{ demoUser.active === false ? 'Inactive' : 'Active' }}</span></td>
                  <td class="row-actions"><button type="button" @click="toggleAccountStatus(demoUser)">{{ demoUser.active === false ? 'Reactivate' : 'Deactivate' }}</button></td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="table-demo-note">Sample user accounts · Account management is not connected to a server</p>
        </section>

        <section v-else-if="page === 'class-notices'" class="two-column-section">
          <article class="content-card notice-form-card">
            <header class="card-heading"><div><p class="card-eyebrow">KEEP YOUR CLASS INFORMED</p><h2>Submit a class notice</h2></div></header>
            <form class="demo-form" @submit.prevent="reviewNotice">
              <label for="notice-class">Assigned class</label>
              <select id="notice-class" v-model="noticeForm.classId" required><option value="">Select a class</option><option v-for="item in assignedClasses" :key="item.id" :value="String(item.id)">{{ item.day }} {{ item.start }} · {{ item.subject }} · {{ item.section }}</option></select>
              <label for="notice-type">Notice type</label>
              <select id="notice-type" v-model="noticeForm.type" required><option value="">Choose notice type</option><option>Class unavailable</option><option>Room update</option><option>Schedule change</option></select>
              <label for="notice-message">Message for students</label>
              <textarea id="notice-message" v-model="noticeForm.message" rows="4" placeholder="Share what changed and what students should know…" required></textarea>
              <p class="form-help">Students in the selected class will receive an in-app notification.</p>
              <div v-if="formError" class="inline-error" role="alert">{{ formError }}</div>
              <button class="action-button action-button-primary" type="submit">Review notice <span aria-hidden="true">→</span></button>
            </form>
          </article>
          <aside class="content-card notice-info-card"><span class="notice-info-icon" aria-hidden="true">ⓘ</span><h2>Before you submit</h2><p>Only send notices for classes assigned to you. Review your message before submission—students will be notified after the notice is published.</p><div class="notice-status-sample"><span class="status-badge status-draft">Draft</span><span>Your draft stays private until published.</span></div><div class="notice-status-sample"><span class="status-badge status-published">Published</span><span>Affected students receive an in-app notification.</span></div></aside>
        </section>

        <section v-if="page === 'class-notices' && classNotices.length" class="content-card full-card">
          <header class="card-heading"><div><p class="card-eyebrow">YOUR RECENT SUBMISSIONS</p><h2>Class notices</h2></div></header>
          <div class="notice-history">
            <article v-for="notice in classNotices" :key="notice.id" class="notice-history-item">
              <span class="notification-type-icon notification-type-notice" aria-hidden="true">◷</span>
              <div><h3>{{ notice.type }} · {{ notice.subject }}</h3><p>{{ notice.message }}</p><span>{{ notice.date }}</span></div>
              <span class="status-badge status-published">{{ notice.status }}</span>
            </article>
          </div>
        </section>
      </main>
    </div>

    <div v-if="activeModal" class="modal-backdrop" @click.self="closeModal">
      <section
        class="workflow-modal"
        role="dialog"
        aria-modal="true"
        tabindex="-1"
        :aria-labelledby="activeModal === 'notice-review' ? 'notice-review-title' : 'workflow-modal-title'"
        @keydown.esc.stop="closeModal"
      >
        <button class="modal-close" type="button" aria-label="Close dialog" @click="closeModal">×</button>
        <template v-if="activeModal === 'schedule'">
          <p class="card-eyebrow">SCHEDULE MANAGEMENT</p>
          <h2 id="workflow-modal-title">Create class schedule</h2>
          <p class="modal-description">Assign a subject, instructor, room, section, and time. Conflicts are checked before saving.</p>
          <form class="demo-form modal-form" @submit.prevent="saveSchedule('Draft')">
            <div class="form-field-row"><div><label for="schedule-code">Subject code</label><input id="schedule-code" v-model="scheduleForm.code" placeholder="e.g. CS 240" required /></div><div><label for="schedule-subject">Subject name</label><input id="schedule-subject" v-model="scheduleForm.subject" placeholder="e.g. Web Development" required /></div></div>
            <label for="schedule-section">Section</label><select id="schedule-section" v-model="scheduleForm.section" required><option>BSCS · 2A</option><option>BSCS · 2B</option><option>BSIT · 3A</option></select>
            <label for="schedule-instructor">Instructor</label><select id="schedule-instructor" v-model="scheduleForm.instructor" required><option>Jordan Rivera</option><option>Taylor Santos</option><option>Casey Bennett</option></select>
            <label for="schedule-room">Room</label><input id="schedule-room" v-model="scheduleForm.room" placeholder="e.g. Room 210" required />
            <label for="schedule-day">Day</label><select id="schedule-day" v-model="scheduleForm.day" required><option v-for="day in daysOfWeek" :key="day">{{ day }}</option></select>
            <div class="form-field-row"><div><label for="schedule-start">Start time</label><input id="schedule-start" v-model="scheduleForm.start" type="time" required /></div><div><label for="schedule-end">End time</label><input id="schedule-end" v-model="scheduleForm.end" type="time" required /></div></div>
            <div v-if="formError" class="inline-error" role="alert">{{ formError }}</div>
            <p class="form-help">Save as a draft or publish now. Publishing notifies affected students in this prototype.</p>
            <div class="modal-actions"><button class="action-button action-button-secondary" type="button" @click="closeModal">Cancel</button><button class="action-button action-button-secondary" type="submit">Save draft</button><button class="action-button action-button-primary" type="button" @click="saveSchedule('Published')">Save &amp; publish</button></div>
          </form>
        </template>
        <template v-else-if="activeModal === 'announcement'">
          <p class="card-eyebrow">CAMPUS COMMUNICATION</p>
          <h2 id="workflow-modal-title">Create announcement</h2>
          <p class="modal-description">Share an update with students. Publishing adds an in-app notification in this demo.</p>
          <form class="demo-form modal-form" @submit.prevent="saveAnnouncement">
            <label for="announcement-title">Announcement title</label><input id="announcement-title" v-model="announcementForm.title" maxlength="100" required placeholder="What should students know?" />
            <label for="announcement-category">Category</label><select id="announcement-category" v-model="announcementForm.category"><option>Campus news</option><option>Academic calendar</option><option>Class update</option></select>
            <label for="announcement-body">Message</label><textarea id="announcement-body" v-model="announcementForm.body" rows="5" maxlength="600" required placeholder="Write a clear, helpful update…"></textarea>
            <p class="form-help">Audience: all students · Demo notices are not sent by email.</p>
            <div class="modal-actions"><button class="action-button action-button-secondary" type="button" @click="closeModal">Cancel</button><button class="action-button action-button-primary" type="submit">Publish announcement</button></div>
          </form>
        </template>
        <template v-else-if="activeModal === 'academic'">
          <p class="card-eyebrow">CAMPUS DIRECTORY</p>
          <h2 id="workflow-modal-title">Add academic record</h2>
          <p class="modal-description">Add a sample item to the selected campus directory group.</p>
          <form class="demo-form modal-form" @submit.prevent="saveAcademicRecord">
            <label for="academic-category">Record type</label><select id="academic-category" v-model="academicForm.category"><option v-for="category in academicCategories" :key="category">{{ category }}</option></select>
            <label for="academic-name">Name or identifier</label><input id="academic-name" v-model="academicForm.name" required placeholder="Enter a subject, room, section, or instructor" />
            <div class="modal-actions"><button class="action-button action-button-secondary" type="button" @click="closeModal">Cancel</button><button class="action-button action-button-primary" type="submit">Add to demo</button></div>
          </form>
        </template>
        <template v-else-if="activeModal === 'user'">
          <p class="card-eyebrow">ACCESS &amp; ACCOUNTS</p>
          <h2 id="workflow-modal-title">Add demo user</h2>
          <p class="modal-description">This adds a sample row only; it does not create an account or grant access.</p>
          <form class="demo-form modal-form" @submit.prevent="saveUser">
            <label for="user-name">Full name</label><input id="user-name" v-model="userForm.name" required autocomplete="off" />
            <label for="user-email">Campus email</label><input id="user-email" v-model="userForm.email" type="email" required autocomplete="off" />
            <label for="user-role">Role</label><select id="user-role" v-model="userForm.role"><option value="student">Student</option><option value="instructor">Instructor</option><option value="administrator">Administrator</option></select>
            <label for="user-section">Department or section</label><input id="user-section" v-model="userForm.section" placeholder="Optional" />
            <div v-if="formError" class="inline-error" role="alert">{{ formError }}</div>
            <div class="modal-actions"><button class="action-button action-button-secondary" type="button" @click="closeModal">Cancel</button><button class="action-button action-button-primary" type="submit">Add demo user</button></div>
          </form>
        </template>
        <template v-else-if="activeModal === 'notice-review' && pendingNotice">
          <p class="card-eyebrow">CONFIRM CLASS NOTICE</p>
          <h2 id="notice-review-title">Review before publishing</h2>
          <p class="modal-description">This notice will appear to students in the selected class and add an in-app notification.</p>
          <div class="notice-review-card"><span class="status-badge status-draft">{{ pendingNotice.type }}</span><h3>{{ pendingNotice.subject }}</h3><p>{{ pendingNotice.message }}</p><span>Audience: {{ pendingNotice.section }}</span></div>
          <div class="modal-actions"><button class="action-button action-button-secondary" type="button" @click="closeModal">Keep editing</button><button class="action-button action-button-primary" type="button" @click="publishNotice">Confirm &amp; notify students</button></div>
        </template>
      </section>
    </div>
  </div>
</template>

<style scoped>
.workspace {
  display: flex;
  min-height: 100vh;
  color: #0f172a;
  background: #f8fafc;
}

.sidebar {
  position: fixed;
  z-index: 5;
  top: 0;
  bottom: 0;
  left: 0;
  display: flex;
  width: 252px;
  flex-direction: column;
  padding: 1.35rem 1rem 0.9rem;
  border-right: 1px solid #e2e8f0;
  background: #ffffff;
}

.sidebar-brand {
  display: inline-flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.1rem 0.55rem 1.3rem;
  color: #123b78;
  font-size: 1.15rem;
  font-weight: 750;
  letter-spacing: -0.035em;
}

.brand-mark,
.brand-mark svg {
  display: block;
  width: 1.95rem;
  height: 1.95rem;
}

.campus-switcher {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.7rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.8rem;
}

.campus-mark {
  display: grid;
  width: 2.2rem;
  height: 2.2rem;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 0.65rem;
  color: #ffffff;
  background: #123b78;
  font-size: 0.85rem;
  font-weight: 750;
}

.campus-copy,
.profile-copy {
  display: grid;
  min-width: 0;
  gap: 0.2rem;
}

.campus-copy strong,
.profile-copy strong {
  overflow: hidden;
  color: #1e293b;
  font-size: 0.7rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.campus-copy span,
.profile-copy span {
  color: #64748b;
  font-size: 0.64rem;
}

.campus-chevron {
  margin-left: auto;
  color: #94a3b8;
}

.nav-caption {
  margin: 1.8rem 0 0.6rem 0.65rem;
  color: #94a3b8;
  font-size: 0.61rem;
  font-weight: 750;
  letter-spacing: 0.12em;
}

.side-nav {
  display: grid;
  gap: 0.23rem;
}

.nav-link {
  display: flex;
  min-height: 2.65rem;
  align-items: center;
  gap: 0.75rem;
  padding: 0 0.7rem;
  border: 0;
  border-radius: 0.7rem;
  color: #475569;
  text-align: left;
  background: transparent;
  font-size: 0.79rem;
  font-weight: 550;
  cursor: pointer;
}

.nav-link:hover {
  color: #123b78;
  background: #f8fafc;
}

.nav-link-active,
.nav-link-active:hover {
  color: #1d4ed8;
  background: #eff6ff;
  font-weight: 700;
}

.nav-icon {
  display: grid;
  width: 1.35rem;
  height: 1.35rem;
  place-items: center;
  color: #64748b;
  font-size: 1rem;
}

.nav-link-active .nav-icon {
  color: #2563eb;
}

.nav-count {
  display: grid;
  min-width: 1.3rem;
  height: 1.3rem;
  place-items: center;
  margin-left: auto;
  border-radius: 999px;
  color: #1d4ed8;
  background: #dbeafe;
  font-size: 0.64rem;
  font-weight: 750;
}

.sidebar-bottom {
  position: relative;
  margin-top: auto;
}

.help-card {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  padding: 0.7rem 0.55rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
  background: #f8fafc;
}

.help-icon {
  display: grid;
  width: 1.6rem;
  height: 1.6rem;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 50%;
  color: #1d4ed8;
  background: #dbeafe;
  font-size: 0.76rem;
  font-weight: 750;
}

.help-card > div {
  display: grid;
  gap: 0.17rem;
}

.help-card strong {
  color: #334155;
  font-size: 0.67rem;
}

.help-card > div span {
  color: #64748b;
  font-size: 0.61rem;
}

.help-arrow {
  margin-left: auto;
  color: #94a3b8;
}

.sidebar-profile {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 0.65rem;
  margin-top: 0.85rem;
  padding: 0.65rem 0.35rem 0.15rem;
  border: 0;
  border-top: 1px solid #e2e8f0;
  text-align: left;
  background: #ffffff;
  cursor: pointer;
}

.profile-avatar {
  display: grid;
  width: 2rem;
  height: 2rem;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 50%;
  font-size: 0.63rem;
  font-weight: 750;
}

.profile-dots {
  margin-left: auto;
  color: #94a3b8;
  letter-spacing: 0.08em;
}

.profile-menu {
  position: absolute;
  z-index: 12;
  right: 0;
  bottom: 3.6rem;
  left: 0;
  display: grid;
  gap: 0.15rem;
  padding: 0.6rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.85rem;
  background: #ffffff;
  box-shadow: 0 12px 30px rgb(15 23 42 / 12%);
}

.profile-menu p {
  margin: 0.2rem 0.4rem 0.35rem;
  color: #94a3b8;
  font-size: 0.58rem;
  font-weight: 750;
  letter-spacing: 0.08em;
}

.profile-menu button {
  display: flex;
  min-height: 2.2rem;
  justify-content: space-between;
  padding: 0 0.45rem;
  border: 0;
  border-radius: 0.5rem;
  color: #334155;
  text-align: left;
  background: transparent;
  font-size: 0.73rem;
  cursor: pointer;
}

.profile-menu button:hover {
  background: #f1f5f9;
}

.profile-menu .sign-out-link {
  margin-top: 0.2rem;
  border-top: 1px solid #e2e8f0;
  border-radius: 0;
  color: #b91c1c;
}

.workspace-main {
  width: calc(100% - 252px);
  min-height: 100vh;
  margin-left: 252px;
}

.topbar {
  position: sticky;
  z-index: 4;
  top: 0;
  display: flex;
  min-height: 66px;
  align-items: center;
  justify-content: space-between;
  padding: 0 clamp(1.3rem, 3vw, 2.6rem);
  border-bottom: 1px solid #e2e8f0;
  background: rgb(255 255 255 / 92%);
  backdrop-filter: blur(14px);
}

.topbar-left,
.topbar-right,
.breadcrumb,
.topbar-user {
  display: flex;
  align-items: center;
}

.breadcrumb {
  gap: 0.55rem;
  color: #94a3b8;
  font-size: 0.75rem;
}

.breadcrumb strong {
  color: #334155;
  font-weight: 600;
}

.topbar-right {
  gap: 1rem;
}

.demo-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.36rem 0.55rem;
  border: 1px solid #bfdbfe;
  border-radius: 999px;
  color: #1d4ed8;
  background: #eff6ff;
  font-size: 0.57rem;
  font-weight: 750;
  letter-spacing: 0.06em;
}

.demo-pill > span {
  width: 0.38rem;
  height: 0.38rem;
  border-radius: 50%;
  background: #2563eb;
}

.topbar-icon-button {
  position: relative;
  display: grid;
  width: 2.2rem;
  height: 2.2rem;
  place-items: center;
  border: 0;
  border-radius: 0.6rem;
  color: #475569;
  background: transparent;
  cursor: pointer;
}

.topbar-icon-button:hover {
  background: #f1f5f9;
}

.topbar-icon-button svg {
  width: 1.2rem;
  height: 1.2rem;
}

.notification-dot {
  position: absolute;
  top: 0.35rem;
  right: 0.37rem;
  width: 0.45rem;
  height: 0.45rem;
  border: 1.5px solid white;
  border-radius: 50%;
  background: #ef4444;
}

.topbar-user {
  gap: 0.45rem;
  padding: 0;
  border: 0;
  color: #334155;
  background: transparent;
  font-size: 0.75rem;
  cursor: pointer;
}

.topbar-user .profile-avatar {
  width: 1.85rem;
  height: 1.85rem;
}

.profile-chevron {
  color: #94a3b8;
}

.mobile-menu-button,
.sidebar-scrim {
  display: none;
}

.page-content {
  width: min(1440px, 100%);
  margin-inline: auto;
  padding: 1.25rem clamp(1.3rem, 3vw, 2.6rem) 3rem;
}

.prototype-banner {
  display: flex;
  align-items: flex-start;
  gap: 0.55rem;
  padding: 0.68rem 0.8rem;
  border: 1px solid #bfdbfe;
  border-radius: 0.65rem;
  color: #1e40af;
  background: #eff6ff;
  font-size: 0.68rem;
  line-height: 1.5;
}

.prototype-banner > span:first-child {
  flex: 0 0 auto;
  font-size: 0.8rem;
}

.page-heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  margin: 1.75rem 0 1.35rem;
}

.page-date,
.card-eyebrow {
  margin: 0 0 0.45rem;
  color: #64748b;
  font-size: 0.6rem;
  font-weight: 750;
  letter-spacing: 0.1em;
}

.page-heading h1 {
  margin: 0;
  color: #123b78;
  font-size: clamp(1.55rem, 3vw, 2.1rem);
  letter-spacing: -0.06em;
  line-height: 1.15;
}

.page-heading h1 span {
  font-size: 1.4rem;
}

.page-subtitle {
  margin: 0.5rem 0 0;
  color: #64748b;
  font-size: 0.8rem;
}

.heading-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.action-button {
  display: inline-flex;
  min-height: 2.45rem;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  padding: 0.55rem 0.8rem;
  border: 1px solid transparent;
  border-radius: 0.65rem;
  font-size: 0.71rem;
  font-weight: 700;
  cursor: pointer;
}

.action-button-primary {
  border-color: #2563eb;
  color: #ffffff;
  background: #2563eb;
}

.action-button-primary:hover {
  border-color: #1d4ed8;
  background: #1d4ed8;
}

.action-button-secondary {
  border-color: #cbd5e1;
  color: #334155;
  background: #ffffff;
}

.action-button-secondary:hover {
  background: #f8fafc;
}

.metric-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.85rem;
}

.metric-card {
  display: grid;
  grid-template-columns: 2.4rem 1fr;
  grid-template-rows: auto auto auto;
  column-gap: 0.65rem;
  align-items: center;
  min-width: 0;
  padding: 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.9rem;
  background: #ffffff;
}

.metric-icon {
  display: grid;
  width: 2.35rem;
  height: 2.35rem;
  grid-row: 1 / 4;
  place-items: center;
  border-radius: 0.7rem;
  font-size: 1rem;
  font-weight: 700;
}

.metric-blue {
  color: #1d4ed8;
  background: #eff6ff;
}

.metric-teal {
  color: #0f766e;
  background: #f0fdfa;
}

.metric-violet {
  color: #6d28d9;
  background: #f5f3ff;
}

.metric-amber {
  color: #b45309;
  background: #fffbeb;
}

.metric-label {
  overflow: hidden;
  color: #64748b;
  font-size: 0.66rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.metric-card > strong {
  margin-top: 0.12rem;
  color: #123b78;
  font-size: 1.35rem;
  letter-spacing: -0.045em;
}

.metric-foot {
  overflow: hidden;
  color: #64748b;
  font-size: 0.59rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.trend-up {
  color: #15803d;
  font-weight: 750;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.55fr) minmax(270px, 0.85fr);
  gap: 0.9rem;
  margin-top: 1rem;
}

.content-card {
  min-width: 0;
  padding: 1.2rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.95rem;
  background: #ffffff;
}

.card-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.card-eyebrow {
  margin-bottom: 0.35rem;
  font-size: 0.57rem;
}

.card-heading h2,
.quick-actions-card h2 {
  margin: 0;
  color: #123b78;
  font-size: 1rem;
  letter-spacing: -0.035em;
}

.text-action {
  flex: 0 0 auto;
  padding: 0;
  border: 0;
  color: #2563eb;
  background: transparent;
  font-size: 0.68rem;
  font-weight: 700;
  cursor: pointer;
}

.text-action:hover {
  text-decoration: underline;
  text-underline-offset: 0.2em;
}

.dashboard-class-list {
  display: grid;
  margin-top: 1rem;
}

.dashboard-class {
  display: grid;
  grid-template-columns: 3rem 3px minmax(0, 1fr) auto;
  gap: 0.7rem;
  align-items: center;
  min-width: 0;
  padding: 0.75rem 0;
  border-top: 1px solid #f1f5f9;
}

.time-column {
  display: grid;
  justify-items: end;
  gap: 0.15rem;
}

.time-column strong {
  color: #334155;
  font-size: 0.69rem;
}

.time-column span {
  color: #94a3b8;
  font-size: 0.59rem;
}

.class-color-bar {
  width: 3px;
  height: 2.7rem;
  border-radius: 999px;
}

.class-color-0,
.table-course-mark.class-color-0 {
  background-color: #2563eb;
}

.class-color-1,
.table-course-mark.class-color-1 {
  background-color: #0d9488;
}

.class-color-2,
.table-course-mark.class-color-2 {
  background-color: #7c3aed;
}

.dashboard-class-copy {
  min-width: 0;
}

.dashboard-class-copy > span {
  color: #64748b;
  font-size: 0.58rem;
}

.dashboard-class-copy h3 {
  overflow: hidden;
  margin: 0.18rem 0;
  color: #1e293b;
  font-size: 0.75rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dashboard-class-copy p {
  overflow: hidden;
  margin: 0;
  color: #64748b;
  font-size: 0.6rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.24rem 0.48rem;
  border-radius: 999px;
  font-size: 0.57rem;
  font-weight: 700;
  white-space: nowrap;
}

.status-published {
  color: #166534;
  background: #dcfce7;
}

.status-draft {
  color: #92400e;
  background: #fef3c7;
}

.status-cancelled {
  color: #991b1b;
  background: #fee2e2;
}

.empty-state {
  display: grid;
  min-height: 180px;
  place-content: center;
  justify-items: center;
  color: #64748b;
  font-size: 0.78rem;
}

.empty-state > span {
  color: #94a3b8;
  font-size: 1.5rem;
}

.updates-card {
  display: flex;
  flex-direction: column;
}

.update-item {
  display: flex;
  gap: 0.7rem;
  margin-top: 1rem;
  padding-bottom: 0.85rem;
  border-bottom: 1px solid #f1f5f9;
}

.update-icon,
.announcement-icon {
  display: grid;
  width: 2rem;
  height: 2rem;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 0.65rem;
  font-size: 0.88rem;
}

.update-icon-blue,
.announcement-blue {
  color: #1d4ed8;
  background: #eff6ff;
}

.update-icon-teal,
.announcement-teal {
  color: #0f766e;
  background: #f0fdfa;
}

.update-item > div {
  min-width: 0;
}

.update-item strong {
  display: block;
  overflow: hidden;
  color: #334155;
  font-size: 0.69rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.update-item p {
  margin: 0.25rem 0;
  color: #64748b;
  font-size: 0.63rem;
  line-height: 1.5;
}

.update-item > div > span {
  color: #94a3b8;
  font-size: 0.57rem;
}

.all-updates-button {
  display: flex;
  justify-content: space-between;
  margin-top: auto;
  padding: 0.85rem 0 0;
  border: 0;
  color: #2563eb;
  background: transparent;
  font-size: 0.66rem;
  font-weight: 700;
  cursor: pointer;
}

.quick-actions-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 0.9rem;
  padding: 1rem 1.2rem;
  border: 1px solid #dbeafe;
  border-radius: 0.9rem;
  background: linear-gradient(100deg, #eff6ff, #f8fafc);
}

.quick-actions-card > div:first-child {
  display: flex;
  align-items: center;
  gap: 0.8rem;
}

.quick-action-icon {
  display: grid;
  width: 2.3rem;
  height: 2.3rem;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 0.75rem;
  color: #1d4ed8;
  background: #dbeafe;
}

.quick-actions-card h2 {
  font-size: 0.8rem;
}

.quick-actions-card p {
  margin: 0.25rem 0 0;
  color: #64748b;
  font-size: 0.66rem;
}

.quick-actions-buttons {
  display: flex;
  flex: 0 0 auto;
  gap: 0.45rem;
}

.full-card {
  margin-top: 1rem;
}

.schedule-card-heading {
  align-items: center;
  margin-bottom: 1rem;
}

.schedule-heading-right {
  display: flex;
  align-items: center;
  gap: 0.55rem;
}

.week-select {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.65rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.55rem;
  color: #475569;
  font-size: 0.64rem;
}

.view-toggle {
  display: flex;
  padding: 0.2rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.55rem;
  background: #f8fafc;
}

.view-toggle button {
  min-height: 1.7rem;
  padding: 0 0.55rem;
  border: 0;
  border-radius: 0.4rem;
  color: #64748b;
  background: transparent;
  font-size: 0.62rem;
  cursor: pointer;
}

.view-toggle .view-toggle-active {
  color: #123b78;
  background: #ffffff;
  box-shadow: 0 1px 3px rgb(15 23 42 / 10%);
  font-weight: 700;
}

.schedule-table-wrap {
  overflow-x: auto;
}

.schedule-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.schedule-table th {
  padding: 0.65rem 0.55rem;
  border-bottom: 1px solid #e2e8f0;
  color: #64748b;
  font-size: 0.58rem;
  font-weight: 650;
  white-space: nowrap;
}

.schedule-table td {
  padding: 0.8rem 0.55rem;
  border-bottom: 1px solid #f1f5f9;
  color: #475569;
  font-size: 0.66rem;
  white-space: nowrap;
}

.schedule-table tr:last-child td {
  border-bottom: 0;
}

.schedule-table td > strong {
  display: block;
  color: #334155;
  font-size: 0.66rem;
}

.schedule-table td > small {
  display: block;
  margin-top: 0.25rem;
  color: #64748b;
  font-size: 0.59rem;
}

.table-course {
  display: flex;
  align-items: center;
  gap: 0.55rem;
}

.table-course-mark {
  width: 3px;
  height: 2rem;
  flex: 0 0 auto;
  border-radius: 999px;
}

.table-course strong,
.table-course small {
  display: block;
}

.table-course strong {
  max-width: 230px;
  overflow: hidden;
  color: #334155;
  font-size: 0.66rem;
  text-overflow: ellipsis;
}

.table-course small {
  margin-top: 0.2rem;
  color: #64748b;
  font-size: 0.58rem;
}

.table-demo-note {
  margin: 0.85rem 0 0;
  color: #94a3b8;
  font-size: 0.62rem;
}

.heading-count {
  display: inline-flex;
  margin-left: 0.4rem;
  padding: 0.25rem 0.48rem;
  border-radius: 999px;
  color: #1d4ed8;
  background: #eff6ff;
  font-size: 0.59rem;
  font-weight: 700;
  vertical-align: middle;
}

.notifications-list {
  display: grid;
  margin-top: 0.9rem;
}

.notification-row {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  padding: 1rem 0.15rem;
  border-top: 1px solid #f1f5f9;
}

.notification-unread {
  background: linear-gradient(90deg, #f8fbff, transparent 70%);
}

.notification-type-icon {
  display: grid;
  width: 2.35rem;
  height: 2.35rem;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 0.75rem;
  color: #1d4ed8;
  background: #eff6ff;
  font-size: 0.95rem;
}

.notification-type-announcement {
  color: #0f766e;
  background: #f0fdfa;
}

.notification-type-notice {
  color: #b45309;
  background: #fffbeb;
}

.notification-copy {
  min-width: 0;
}

.notification-copy h3 {
  margin: 0;
  color: #334155;
  font-size: 0.75rem;
}

.notification-copy p {
  margin: 0.25rem 0;
  color: #64748b;
  font-size: 0.68rem;
  line-height: 1.5;
}

.notification-copy > span {
  color: #94a3b8;
  font-size: 0.59rem;
}

.unread-label {
  margin-left: 0.3rem;
  color: #2563eb;
  font-size: 0.53rem;
  font-weight: 750;
  letter-spacing: 0.04em;
}

.mark-read-button {
  flex: 0 0 auto;
  margin-left: auto;
  padding: 0.4rem 0.55rem;
  border: 1px solid #dbeafe;
  border-radius: 0.5rem;
  color: #1d4ed8;
  background: #ffffff;
  font-size: 0.61rem;
  font-weight: 650;
  cursor: pointer;
}

.mark-read-button:hover {
  background: #eff6ff;
}

.read-label {
  margin-left: auto;
  color: #94a3b8;
  font-size: 0.62rem;
}

.announcement-list {
  display: grid;
  gap: 0.75rem;
  margin-top: 1rem;
}

.announcement-card {
  display: flex;
  gap: 0.85rem;
  padding: 1rem 0;
  border-top: 1px solid #f1f5f9;
}

.announcement-card > div {
  min-width: 0;
}

.announcement-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  color: #94a3b8;
  font-size: 0.59rem;
}

.announcement-tag {
  padding: 0.22rem 0.45rem;
  border-radius: 999px;
  color: #1d4ed8;
  background: #eff6ff;
  font-weight: 700;
}

.announcement-card h3 {
  margin: 0.5rem 0 0;
  color: #123b78;
  font-size: 0.92rem;
}

.announcement-card p {
  max-width: 820px;
  margin: 0.4rem 0;
  color: #64748b;
  font-size: 0.72rem;
  line-height: 1.65;
}

.announcement-author {
  color: #64748b;
  font-size: 0.61rem;
}

.announcement-author span {
  padding-inline: 0.2rem;
}

.academic-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.7rem;
  margin-top: 1.2rem;
}

.academic-card {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  padding: 0.9rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
}

.academic-icon {
  display: grid;
  width: 2.4rem;
  height: 2.4rem;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 0.7rem;
}

.academic-blue {
  color: #1d4ed8;
  background: #eff6ff;
}

.academic-teal {
  color: #0f766e;
  background: #f0fdfa;
}

.academic-violet {
  color: #6d28d9;
  background: #f5f3ff;
}

.academic-amber {
  color: #b45309;
  background: #fffbeb;
}

.academic-card h3 {
  margin: 0;
  color: #334155;
  font-size: 0.73rem;
}

.academic-card p {
  margin: 0.25rem 0 0;
  color: #64748b;
  font-size: 0.63rem;
}

.academic-card > span:last-child {
  margin-left: auto;
  color: #94a3b8;
}

.two-column-section {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(250px, 0.8fr);
  gap: 0.9rem;
  margin-top: 1rem;
}

.notice-form-card .card-heading {
  margin-bottom: 1rem;
}

.demo-form {
  display: grid;
  gap: 0.55rem;
}

.demo-form label {
  margin-top: 0.35rem;
  color: #334155;
  font-size: 0.7rem;
  font-weight: 650;
}

.demo-form input,
.demo-form select,
.demo-form textarea {
  width: 100%;
  padding: 0.68rem 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.6rem;
  color: #334155;
  background: #ffffff;
  font: inherit;
  font-size: 0.7rem;
}

.demo-form textarea {
  resize: vertical;
}

.form-help {
  margin: 0;
  color: #64748b;
  font-size: 0.62rem;
}

.demo-form .action-button {
  width: fit-content;
  margin-top: 0.4rem;
}

.notice-info-card {
  align-self: start;
  padding: 1.25rem;
  border-color: #bfdbfe;
  background: #eff6ff;
}

.notice-info-icon {
  color: #1d4ed8;
  font-size: 1.2rem;
}

.notice-info-card h2 {
  margin: 0.55rem 0;
  color: #123b78;
  font-size: 0.88rem;
}

.notice-info-card > p {
  margin: 0;
  color: #475569;
  font-size: 0.68rem;
  line-height: 1.65;
}

.notice-status-sample {
  display: flex;
  align-items: flex-start;
  gap: 0.55rem;
  margin-top: 1rem;
  color: #475569;
  font-size: 0.62rem;
  line-height: 1.5;
}

.toast-message {
  position: fixed;
  z-index: 20;
  top: 4.7rem;
  right: 1rem;
  display: flex;
  max-width: min(440px, calc(100vw - 2rem));
  align-items: center;
  gap: 1rem;
  padding: 0.85rem 1rem;
  border: 1px solid #bbf7d0;
  border-radius: 0.75rem;
  color: #166534;
  background: #f0fdf4;
  box-shadow: 0 10px 24px rgb(15 23 42 / 10%);
  font-size: 0.74rem;
  line-height: 1.5;
}

.toast-message button {
  flex: 0 0 auto;
  border: 0;
  color: inherit;
  background: transparent;
  font-size: 1rem;
  cursor: pointer;
}

.row-actions button {
  padding: 0.32rem 0.5rem;
  border: 1px solid #dbeafe;
  border-radius: 0.45rem;
  color: #1d4ed8;
  background: #ffffff;
  font-size: 0.59rem;
  font-weight: 650;
  cursor: pointer;
}

.row-actions button:hover {
  background: #eff6ff;
}

.inline-error {
  padding: 0.65rem 0.75rem;
  border: 1px solid #fecaca;
  border-radius: 0.6rem;
  color: #991b1b;
  background: #fef2f2;
  font-size: 0.68rem;
  line-height: 1.5;
}

.notice-history {
  display: grid;
}

.notice-history-item {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  padding: 0.9rem 0;
  border-top: 1px solid #f1f5f9;
}

.notice-history-item > div {
  min-width: 0;
}

.notice-history-item h3 {
  margin: 0;
  color: #334155;
  font-size: 0.72rem;
}

.notice-history-item p {
  margin: 0.3rem 0;
  color: #64748b;
  font-size: 0.66rem;
}

.notice-history-item > div > span {
  color: #94a3b8;
  font-size: 0.58rem;
}

.notice-history-item > .status-badge {
  margin-left: auto;
}

.modal-backdrop {
  position: fixed;
  z-index: 30;
  inset: 0;
  display: grid;
  align-items: center;
  justify-items: center;
  overflow: auto;
  padding: 1rem;
  background: rgb(15 23 42 / 52%);
  backdrop-filter: blur(3px);
}

.workflow-modal {
  position: relative;
  width: min(100%, 560px);
  max-height: calc(100dvh - 2rem);
  overflow-y: auto;
  padding: 1.5rem;
  border: 1px solid #e2e8f0;
  border-radius: 1rem;
  background: #ffffff;
  box-shadow: 0 24px 70px rgb(15 23 42 / 25%);
}

.workflow-modal > .card-eyebrow {
  margin: 0 2.2rem 0.4rem 0;
  color: #0f766e;
}

.workflow-modal > h2 {
  margin: 0;
  color: #123b78;
  font-size: 1.25rem;
  letter-spacing: -0.045em;
}

.modal-description {
  margin: 0.5rem 2rem 1rem 0;
  color: #64748b;
  font-size: 0.71rem;
  line-height: 1.6;
}

.modal-close {
  position: absolute;
  top: 0.9rem;
  right: 0.9rem;
  display: grid;
  width: 2rem;
  height: 2rem;
  place-items: center;
  border: 1px solid #e2e8f0;
  border-radius: 0.6rem;
  color: #64748b;
  background: #ffffff;
  font-size: 1.2rem;
  cursor: pointer;
}

.modal-form {
  gap: 0.48rem;
}

.modal-form label {
  margin-top: 0.25rem;
}

.modal-form input,
.modal-form select,
.modal-form textarea {
  min-height: 2.55rem;
  margin: 0;
  font-size: 0.73rem;
}

.modal-form textarea {
  min-height: auto;
}

.form-field-row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.7rem;
}

.form-field-row > div {
  display: grid;
  gap: 0.48rem;
}

.modal-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 0.45rem;
}

.notice-review-card {
  padding: 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
  background: #f8fafc;
}

.notice-review-card h3 {
  margin: 0.65rem 0 0.35rem;
  color: #123b78;
  font-size: 0.82rem;
}

.notice-review-card p {
  margin: 0;
  color: #475569;
  font-size: 0.72rem;
  line-height: 1.65;
}

.notice-review-card > span:last-child {
  display: block;
  margin-top: 0.6rem;
  color: #64748b;
  font-size: 0.61rem;
}

@media (max-width: 1120px) {
  .sidebar {
    width: 225px;
  }

  .workspace-main {
    width: calc(100% - 225px);
    margin-left: 225px;
  }

  .metric-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .quick-actions-card {
    align-items: flex-start;
    flex-direction: column;
  }
}

@media (max-width: 850px) {
  .sidebar {
    z-index: 11;
    width: 270px;
    box-shadow: 10px 0 30px rgb(15 23 42 / 12%);
    transform: translateX(-110%);
    transition: transform 180ms ease;
  }

  .sidebar-open {
    transform: translateX(0);
  }

  .sidebar-scrim {
    position: fixed;
    z-index: 10;
    inset: 0;
    display: block;
    border: 0;
    background: rgb(15 23 42 / 35%);
  }

  .workspace-main {
    width: 100%;
    margin-left: 0;
  }

  .mobile-menu-button {
    display: grid;
    width: 2rem;
    height: 2rem;
    place-items: center;
    margin-right: 0.6rem;
    border: 0;
    border-radius: 0.5rem;
    color: #334155;
    background: transparent;
    cursor: pointer;
  }

  .dashboard-grid {
    grid-template-columns: 1fr;
  }

  .updates-card {
    min-height: 250px;
  }
}

@media (max-width: 650px) {
  .topbar {
    min-height: 58px;
    padding-inline: 1rem;
  }

  .topbar-right {
    gap: 0.55rem;
  }

  .demo-pill {
    font-size: 0;
  }

  .demo-pill > span {
    width: 0.5rem;
    height: 0.5rem;
  }

  .topbar-user-name,
  .topbar-user .profile-chevron {
    display: none;
  }

  .breadcrumb {
    gap: 0.35rem;
    font-size: 0.68rem;
  }

  .page-content {
    padding: 0.8rem 1rem 2rem;
  }

  .page-heading {
    align-items: flex-start;
    flex-direction: column;
    margin: 1.25rem 0 1rem;
  }

  .heading-actions {
    width: 100%;
  }

  .heading-actions .action-button {
    flex: 1;
  }

  .metric-grid {
    gap: 0.55rem;
  }

  .metric-card {
    grid-template-columns: 1.9rem 1fr;
    gap: 0 0.5rem;
    padding: 0.7rem;
  }

  .metric-icon {
    width: 1.9rem;
    height: 1.9rem;
  }

  .metric-label {
    font-size: 0.59rem;
  }

  .metric-card > strong {
    font-size: 1.1rem;
  }

  .metric-foot {
    font-size: 0.54rem;
  }

  .content-card {
    padding: 0.95rem;
  }

  .dashboard-class {
    grid-template-columns: 2.6rem 3px minmax(0, 1fr);
    gap: 0.55rem;
  }

  .dashboard-class > .status-badge {
    display: none;
  }

  .schedule-card-heading {
    align-items: flex-start;
    flex-direction: column;
  }

  .schedule-heading-right {
    width: 100%;
    justify-content: space-between;
  }

  .week-select {
    font-size: 0.58rem;
  }

  .quick-actions-buttons {
    width: 100%;
    flex-wrap: wrap;
  }

  .quick-actions-buttons .action-button {
    flex: 1;
  }

  .two-column-section {
    grid-template-columns: 1fr;
  }

  .modal-actions .action-button {
    flex: 1;
  }

  .form-field-row {
    grid-template-columns: 1fr;
  }

  .notification-row {
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .notification-copy {
    flex: 1;
  }

  .mark-read-button,
  .read-label {
    margin-left: 3.15rem;
  }

  .academic-grid {
    grid-template-columns: 1fr;
  }
}

@media print {
  .sidebar,
  .topbar,
  .prototype-banner,
  .heading-actions,
  .page-date {
    display: none !important;
  }

  .workspace-main {
    width: 100%;
    margin: 0;
  }

  .page-content {
    padding: 0;
  }

  .content-card {
    border-color: #cbd5e1;
    box-shadow: none;
  }
}
</style>
