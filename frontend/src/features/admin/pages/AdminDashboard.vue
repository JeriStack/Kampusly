<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/features/auth/stores/useAuth'
import { apiClient } from '@/services/api/client'
import { API_ENDPOINTS } from '@/services/api/endpoints'
import KampuslyLogo from '@/components/common/KampuslyLogo.vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'
import NotificationCenter from '@/components/common/NotificationCenter.vue'
import UserSessionPopover from '@/components/common/UserSessionPopover.vue'
import AppIcon, { type IconName } from '@/components/common/AppIcon.vue'
import PaginationBar from '@/features/academic/components/PaginationBar.vue'

const router = useRouter()
const route = useRoute()
const { user, logout } = useAuth()

const adminNavTabs: { name: string; path: string; icon: IconName }[] = [
  { name: 'Dashboard', path: '/admin/dashboard', icon: 'admin' },
  { name: 'Students', path: '/admin/students', icon: 'student' },
  { name: 'Instructors', path: '/admin/instructors', icon: 'instructor' },
  { name: 'Schedules', path: '/admin/schedules', icon: 'calendar' },
  { name: 'Subjects', path: '/admin/subjects', icon: 'book' },
  { name: 'Rooms', path: '/admin/rooms', icon: 'grid' },
  { name: 'Sections', path: '/admin/sections', icon: 'layers' },
]

interface DashboardSummary {
  total_students: number
  total_instructors: number
  total_subjects: number
  total_rooms: number
  todays_classes: number
  upcoming_classes: number
  active_announcements: number
  cancelled_or_pending_notices: number
}

interface PaginationMeta {
  page: number
  per_page: number
  total: number
  total_pages: number
}

interface PaginatedResponse<T> {
  data: T[]
  meta: PaginationMeta
}

interface UserAccount {
  id: number
  full_name: string
  email: string
  role: 'admin' | 'instructor' | 'student'
  status: string
}

interface Subject {
  id: number
  subject_code: string
  subject_name: string
  units: number
  status: string
}

interface TodayClass {
  id: number
  subject_code: string
  subject_name: string
  start_time: string
  end_time: string
  instructor_name: string
  room_code: string
  section_name: string
  status: string
}

interface Section {
  id: number
  section_name: string
  program: string
  status: string
}

const summary = ref<DashboardSummary | null>(null)
const isLoading = ref(true)
const error = ref<string | null>(null)

const accounts = ref<UserAccount[]>([])
const accountPage = ref(1)
const accountPerPage = ref(10)
const accountTotal = ref(0)
const accountTotalPages = ref(0)
const isLoadingAccounts = ref(true)
const accountError = ref<string | null>(null)

const activeSubjects = ref<Subject[]>([])
const subjectPage = ref(1)
const subjectPerPage = ref(10)
const subjectTotal = ref(0)
const subjectTotalPages = ref(0)
const isLoadingSubjects = ref(true)
const subjectsError = ref<string | null>(null)

const todaysClasses = ref<TodayClass[]>([])
const isLoadingClasses = ref(true)
const classesError = ref<string | null>(null)

const sections = ref<Section[]>([])
const isCreateFormOpen = ref(false)
const isCreatingAccount = ref(false)
const createAccountError = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const accountForm = reactive({
  role: 'student' as 'student' | 'instructor',
  firstName: '',
  lastName: '',
  email: '',
  studentNumber: '',
  program: '',
  sectionId: '',
  employeeNumber: '',
  department: '',
})

/** Shown once after an account is created; the password is never stored on the client. */
interface CreatedCredentials {
  name: string
  email: string
  password: string
  isReset: boolean
}
const createdCredentials = ref<CreatedCredentials | null>(null)
const isPasswordCopied = ref(false)
const passwordText = ref<HTMLElement | null>(null)

function closeCredentials(): void {
  if (
    !isPasswordCopied.value &&
    !window.confirm(
      'Hindi mo pa nako-copy o nada-download ang password. Hindi na ito makikita ulit. ' +
        'Kung mawala, pwede kang mag-Reset password para makagawa ng bago. Isara pa rin?',
    )
  ) {
    return
  }
  createdCredentials.value = null
  isPasswordCopied.value = false
}

function selectPasswordText(): void {
  const node = passwordText.value
  const selection = window.getSelection()
  if (!node || !selection) return
  const range = document.createRange()
  range.selectNodeContents(node)
  selection.removeAllRanges()
  selection.addRange(range)
}

function legacyCopy(text: string): boolean {
  const field = document.createElement('textarea')
  field.value = text
  field.setAttribute('readonly', '')
  field.style.position = 'fixed'
  field.style.opacity = '0'
  document.body.appendChild(field)
  field.select()
  try {
    return document.execCommand('copy')
  } catch {
    return false
  } finally {
    document.body.removeChild(field)
  }
}

async function copyGeneratedPassword(): Promise<void> {
  if (!createdCredentials.value) return
  const text = createdCredentials.value.password
  try {
    await navigator.clipboard.writeText(text)
    isPasswordCopied.value = true
  } catch {
    isPasswordCopied.value = legacyCopy(text)
    if (!isPasswordCopied.value) selectPasswordText()
  }
}

function downloadCredentials(): void {
  const creds = createdCredentials.value
  if (!creds) return
  const body = `Kampusly sign-in details\r\nName: ${creds.name}\r\nEmail: ${creds.email}\r\nPassword: ${creds.password}\r\n`
  const url = URL.createObjectURL(new Blob([body], { type: 'text/plain;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = 'kampusly-credentials.txt'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
  isPasswordCopied.value = true
}

const todayLabel = new Intl.DateTimeFormat('en', {
  weekday: 'long',
  month: 'long',
  day: 'numeric',
  timeZone: 'Asia/Manila',
}).format(new Date())

const todayWeekday = new Intl.DateTimeFormat('en', {
  weekday: 'long',
  timeZone: 'Asia/Manila',
}).format(new Date())

const accountRoleFilter = computed<'student' | 'instructor' | null>(() => {
  if (route.path === '/admin/students') return 'student'
  if (route.path === '/admin/instructors') return 'instructor'
  return null
})

/** The role the form creates: fixed on the Students and Instructors pages, selectable on the dashboard. */
const formRole = computed<'student' | 'instructor'>(() => accountRoleFilter.value ?? accountForm.role)
const roleLabel = computed(() => {
  if (accountRoleFilter.value === 'student') return 'Student'
  if (accountRoleFilter.value === 'instructor') return 'Instructor'
  return 'User'
})

function messageFromError(err: unknown, fallback: string): string {
  return err instanceof Error ? err.message : fallback
}

async function fetchSummary(): Promise<void> {
  isLoading.value = true
  error.value = null
  try {
    const response = await apiClient.get<{ data: DashboardSummary }>(
      API_ENDPOINTS.admin.dashboardSummary,
    )
    summary.value = response.data
  } catch (err: unknown) {
    error.value = messageFromError(err, 'Failed to load dashboard metrics.')
  } finally {
    isLoading.value = false
  }
}

async function fetchAccounts(page = accountPage.value): Promise<void> {
  isLoadingAccounts.value = true
  accountError.value = null
  try {
    const query = new URLSearchParams({
      page: String(page),
      per_page: String(accountPerPage.value),
    })
    if (accountRoleFilter.value) {
      query.set('role', accountRoleFilter.value)
    }
    const response = await apiClient.get<PaginatedResponse<UserAccount>>(
      `${API_ENDPOINTS.users.list}?${query.toString()}`,
    )
    accounts.value = response.data
    accountPage.value = response.meta.page
    accountTotal.value = response.meta.total
    accountTotalPages.value = response.meta.total_pages
  } catch (err: unknown) {
    accountError.value = messageFromError(err, 'Failed to load user accounts.')
  } finally {
    isLoadingAccounts.value = false
  }
}

function handleAccountPageChange(page: number): void {
  void fetchAccounts(page)
}

function handleAccountPerPageChange(perPage: number): void {
  accountPerPage.value = perPage
  void fetchAccounts(1)
}

async function fetchActiveSubjects(page = subjectPage.value): Promise<void> {
  isLoadingSubjects.value = true
  subjectsError.value = null
  try {
    const query = new URLSearchParams({
      page: String(page),
      per_page: String(subjectPerPage.value),
      status: 'active',
    })
    const response = await apiClient.get<PaginatedResponse<Subject>>(
      `${API_ENDPOINTS.academic.subjects}?${query.toString()}`,
    )
    activeSubjects.value = response.data
    subjectPage.value = response.meta.page
    subjectTotal.value = response.meta.total
    subjectTotalPages.value = response.meta.total_pages
  } catch (err: unknown) {
    subjectsError.value = messageFromError(err, 'Failed to load active subjects.')
  } finally {
    isLoadingSubjects.value = false
  }
}

function handleSubjectPageChange(page: number): void {
  void fetchActiveSubjects(page)
}

function handleSubjectPerPageChange(perPage: number): void {
  subjectPerPage.value = perPage
  void fetchActiveSubjects(1)
}

async function fetchTodaysClasses(): Promise<void> {
  isLoadingClasses.value = true
  classesError.value = null
  try {
    const query = new URLSearchParams({
      page: '1',
      per_page: '100',
      day_of_week: todayWeekday,
      status: 'published',
    })
    const response = await apiClient.get<PaginatedResponse<TodayClass>>(
      `${API_ENDPOINTS.scheduling.schedules}?${query.toString()}`,
    )
    todaysClasses.value = response.data
  } catch (err: unknown) {
    classesError.value = messageFromError(err, "Failed to load today's classes.")
  } finally {
    isLoadingClasses.value = false
  }
}

async function fetchSections(): Promise<void> {
  const query = new URLSearchParams({ page: '1', per_page: '100', status: 'active' })
  const response = await apiClient.get<PaginatedResponse<Section>>(
    `${API_ENDPOINTS.academic.sections}?${query.toString()}`,
  )
  sections.value = response.data
}

function resetAccountForm(): void {
  accountForm.role = accountRoleFilter.value ?? 'student'
  accountForm.firstName = ''
  accountForm.lastName = ''
  accountForm.email = ''
  accountForm.studentNumber = ''
  accountForm.program = ''
  accountForm.sectionId = ''
  accountForm.employeeNumber = ''
  accountForm.department = ''
  createAccountError.value = null
}

async function openCreateForm(): Promise<void> {
  accountForm.role = accountRoleFilter.value ?? 'student'
  successMessage.value = null
  createAccountError.value = null
  try {
    await fetchSections()
    isCreateFormOpen.value = true
  } catch (err: unknown) {
    createAccountError.value = messageFromError(err, 'Unable to load sections for the account form.')
  }
}

async function createAccount(): Promise<void> {
  isCreatingAccount.value = true
  createAccountError.value = null
  successMessage.value = null
  const shared = {
    first_name: accountForm.firstName.trim(),
    last_name: accountForm.lastName.trim(),
    email: accountForm.email.trim(),
    status: 'active',
  }

  try {
    let response: { data: { generated_password?: string } }
    if (formRole.value === 'student') {
      response = await apiClient.post(API_ENDPOINTS.academic.students, {
        ...shared,
        student_number: accountForm.studentNumber.trim(),
        program: accountForm.program.trim(),
        section_id: Number(accountForm.sectionId),
      })
    } else {
      response = await apiClient.post(API_ENDPOINTS.academic.instructors, {
        ...shared,
        employee_number: accountForm.employeeNumber.trim(),
        department: accountForm.department.trim(),
      })
    }

    const generatedPassword = response.data.generated_password
    if (generatedPassword) {
      createdCredentials.value = {
        name: `${shared.first_name} ${shared.last_name}`,
        email: shared.email,
        password: generatedPassword,
        isReset: false,
      }
      isPasswordCopied.value = false
    }

    isCreateFormOpen.value = false
    resetAccountForm()
    successMessage.value = 'User account created successfully.'
    await Promise.all([fetchAccounts(), fetchSummary()])
  } catch (err: unknown) {
    createAccountError.value = messageFromError(err, 'Failed to create user account.')
  } finally {
    isCreatingAccount.value = false
  }
}

async function toggleAccountStatus(account: UserAccount): Promise<void> {
  if (account.id === user.value?.id) return

  const status = account.status === 'active' ? 'inactive' : 'active'
  const action = status === 'inactive' ? 'deactivate' : 'reactivate'
  if (!window.confirm(`Are you sure you want to ${action} ${account.full_name}?`)) return

  accountError.value = null
  successMessage.value = null
  try {
    await apiClient.patch(API_ENDPOINTS.users.updateStatus(account.id), { status })
    successMessage.value = `${account.full_name} is now ${status}.`
    await Promise.all([fetchAccounts(), fetchSummary()])
  } catch (err: unknown) {
    accountError.value = messageFromError(err, 'Failed to update account status.')
  }
}

async function resetAccountPassword(account: UserAccount): Promise<void> {
  if (account.id === user.value?.id || account.role === 'admin') return
  const prompt =
    `Mag-generate ng bagong password para kay ${account.full_name}? ` +
    'Hindi na gagana ang lumang password niya.'
  if (!window.confirm(prompt)) return

  accountError.value = null
  successMessage.value = null
  try {
    const response = await apiClient.post<{ data: { generated_password?: string } }>(
      API_ENDPOINTS.users.resetPassword(account.id),
      {},
    )
    const password = response.data.generated_password
    if (!password) throw new Error('No password returned.')
    createdCredentials.value = {
      name: account.full_name,
      email: account.email,
      password,
      isReset: true,
    }
    isPasswordCopied.value = false
  } catch (err: unknown) {
    accountError.value = messageFromError(err, 'Failed to reset password.')
  }
}

function formatTime(value: string): string {
  const [hours = '0', minutes = '00'] = value.split(':')
  const date = new Date()
  date.setHours(Number(hours), Number(minutes), 0, 0)
  return new Intl.DateTimeFormat('en', { hour: 'numeric', minute: '2-digit' }).format(date)
}

async function handleLogout(): Promise<void> {
  await logout()
  await router.push('/login')
}

onMounted(() => {
  void fetchSummary()
  void fetchAccounts()
  void fetchActiveSubjects()
  void fetchTodaysClasses()
})

watch(accountRoleFilter, () => {
  accountPage.value = 1
  void fetchAccounts()
})
</script>

<template>
  <div class="dashboard-readable min-h-screen bg-canvas text-ink">
    <header class="sticky top-0 z-20 border-b border-hairline bg-surface/95 backdrop-blur-md">
      <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-3">
          <RouterLink to="/admin/dashboard" class="flex shrink-0 items-center gap-2">
            <KampuslyLogo size="sm" />
          </RouterLink>
          <span class="hidden sm:inline-flex items-center rounded-full border border-navy/15 bg-navy-50 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wider text-navy-800 dark:border-sky-400/25 dark:bg-sky-500/15 dark:text-sky-200">
            Administrator Portal
          </span>
        </div>
        <div class="flex items-center gap-2.5">
          <NotificationCenter />
          <ThemeToggle />
          <div aria-hidden="true" class="hidden h-6 w-px bg-hairline sm:block" />
          <UserSessionPopover :subtitle="user?.email" />
          <button
            type="button"
            aria-label="Sign out"
            title="Sign out"
            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-hairline bg-surface text-rose-600 transition-colors hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:border-rose-500/40 dark:hover:bg-rose-950/50 dark:hover:text-rose-300"
            @click="handleLogout"
          >
            <AppIcon name="logout" class="h-5 w-5" />
          </button>
        </div>
      </div>
      <nav
        class="mx-auto flex max-w-7xl items-center gap-1.5 overflow-x-auto border-t border-hairline/60 px-4 py-2 sm:px-6 lg:px-8"
        aria-label="Admin navigation"
      >
        <RouterLink
          v-for="tab in adminNavTabs"
          :key="tab.path"
          :to="tab.path"
          :aria-current="route.path === tab.path ? 'page' : undefined"
          class="group inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
          :class="
            route.path === tab.path
              ? 'bg-action/10 text-navy ring-1 ring-inset ring-action/20 shadow-sm dark:bg-sky-500/15 dark:text-sky-200 dark:ring-sky-400/30'
              : 'text-muted hover:bg-canvas hover:text-ink'
          "
        >
          <AppIcon
            :name="tab.icon"
            class="h-4 w-4 transition-colors"
            :class="
              route.path === tab.path
                ? 'text-action dark:text-sky-300'
                : 'text-subtle group-hover:text-ink'
            "
          />
          <span>{{ tab.name }}</span>
        </RouterLink>
      </nav>
    </header>

    <main class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">
          {{ user?.name }}
        </h1>
        <p class="mt-1 text-sm text-muted">
          Kampusly Administration System &bull; Schedule &amp; User Overview
        </p>
      </div>

      <div v-if="isLoading" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="i in 4"
          :key="i"
          class="h-28 animate-pulse rounded-2xl border border-hairline bg-surface p-5"
        />
      </div>

      <div v-else-if="summary" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <RouterLink
          to="/admin/students"
          class="group rounded-2xl border border-hairline bg-surface p-5 shadow-card transition-all hover:-translate-y-0.5 hover:border-blue-500/35 hover:shadow-card-hover"
        >
          <div class="flex items-center justify-between gap-3">
            <div class="text-xs font-semibold uppercase tracking-wider text-muted">Total Students</div>
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300">
              <AppIcon name="student" class="h-5 w-5" />
            </span>
          </div>
          <div class="mt-3 text-center">
            <div class="text-3xl font-bold tracking-tight tabular-nums text-ink">
              {{ summary.total_students }}
            </div>
            <div class="mt-1 text-xs text-subtle">Registered student profiles</div>
          </div>
        </RouterLink>

        <RouterLink
          to="/admin/instructors"
          class="group rounded-2xl border border-hairline bg-surface p-5 shadow-card transition-all hover:-translate-y-0.5 hover:border-teal-500/35 hover:shadow-card-hover"
        >
          <div class="flex items-center justify-between gap-3">
            <div class="text-xs font-semibold uppercase tracking-wider text-muted">Instructors</div>
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-500/10 text-teal-600 dark:bg-teal-500/15 dark:text-teal-300">
              <AppIcon name="instructor" class="h-5 w-5" />
            </span>
          </div>
          <div class="mt-3 text-center">
            <div class="text-3xl font-bold tracking-tight tabular-nums text-ink">
              {{ summary.total_instructors }}
            </div>
            <div class="mt-1 text-xs text-subtle">Registered faculty profiles</div>
          </div>
        </RouterLink>

        <RouterLink
          to="/admin/schedules"
          class="group rounded-2xl border border-hairline bg-surface p-5 shadow-card transition-all hover:-translate-y-0.5 hover:border-sky-500/35 hover:shadow-card-hover"
        >
          <div class="flex items-center justify-between gap-3">
            <div class="text-xs font-semibold uppercase tracking-wider text-muted">Today's Classes</div>
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300">
              <AppIcon name="calendar" class="h-5 w-5" />
            </span>
          </div>
          <div class="mt-3 text-center">
            <div class="text-3xl font-bold tracking-tight tabular-nums text-action dark:text-sky-300">
              {{ summary.todays_classes }}
            </div>
            <div class="mt-1 text-xs text-subtle">Published for today</div>
          </div>
        </RouterLink>

        <RouterLink
          to="/admin/subjects"
          class="group rounded-2xl border border-hairline bg-surface p-5 shadow-card transition-all hover:-translate-y-0.5 hover:border-violet-500/35 hover:shadow-card-hover"
        >
          <div class="flex items-center justify-between gap-3">
            <div class="text-xs font-semibold uppercase tracking-wider text-muted">Active Subjects</div>
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300">
              <AppIcon name="book" class="h-5 w-5" />
            </span>
          </div>
          <div class="mt-3 text-center">
            <div class="text-3xl font-bold tracking-tight tabular-nums text-ink">
              {{ summary.total_subjects }}
            </div>
            <div class="mt-1 text-xs text-subtle">Available in the curriculum</div>
          </div>
        </RouterLink>
      </div>
      <div
        v-else-if="error"
        class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300"
        role="alert"
      >
        {{ error }}
        <button type="button" class="ml-3 font-semibold underline" @click="fetchSummary">Retry</button>
      </div>

      <section class="rounded-2xl border border-hairline bg-surface p-5 shadow-card sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-hairline pb-4">
          <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300">
              <AppIcon name="users" class="h-5 w-5" />
            </span>
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-base font-bold text-ink">
                  {{ accountRoleFilter ? `${accountRoleFilter === 'student' ? 'Student' : 'Instructor'} Accounts` : 'User Accounts' }}
                </h2>
                <span class="inline-flex items-center rounded-full border border-blue-500/20 bg-blue-500/10 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-blue-700 dark:text-blue-300">
                  {{ accountTotal }}
                </span>
              </div>
              <p class="mt-0.5 text-xs text-subtle">View, create, activate, or deactivate accounts.</p>
            </div>
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-action px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-action-hover active:bg-action-active focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-action focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
            @click="openCreateForm"
          >
            <AppIcon name="plus" class="h-4 w-4" />
            Add {{ roleLabel }}
          </button>
        </div>

        <div
          v-if="successMessage"
          class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/90 p-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-950/40 dark:text-emerald-200"
          role="status"
        >
          {{ successMessage }}
        </div>
        <div
          v-if="accountError"
          class="mt-4 rounded-xl border border-rose-200 bg-rose-50/90 p-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-200"
          role="alert"
        >
          {{ accountError }}
          <button type="button" class="ml-2 font-semibold underline" @click="fetchAccounts()">Retry</button>
        </div>

        <div v-if="isLoadingAccounts" class="mt-5 space-y-2.5" aria-label="Loading user accounts">
          <div v-for="i in 3" :key="i" class="h-12 animate-pulse rounded-xl bg-canvas" />
        </div>
        <div v-else-if="!accountError" class="mt-5">
          <div class="overflow-x-auto rounded-xl border border-hairline">
            <table class="w-full min-w-[680px] text-center text-sm">
              <thead class="border-b border-hairline bg-canvas/70 text-[11px] uppercase tracking-wider text-subtle">
                <tr>
                  <th scope="col" class="px-4 py-3 font-semibold">Name</th>
                  <th scope="col" class="px-4 py-3 font-semibold">Email</th>
                  <th scope="col" class="px-4 py-3 font-semibold">Role</th>
                  <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                  <th scope="col" class="px-4 py-3 font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-hairline/70">
                <tr
                  v-for="account in accounts"
                  :key="account.id"
                  class="transition-colors hover:bg-canvas/60"
                >
                  <td class="px-4 py-3.5 font-semibold text-ink">{{ account.full_name }}</td>
                  <td class="px-4 py-3.5 text-muted">{{ account.email }}</td>
                  <td class="px-4 py-3.5">
                    <span
                      class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold capitalize"
                      :class="
                        account.role === 'admin'
                          ? 'border-sky-500/20 bg-sky-500/10 text-navy dark:text-sky-300'
                          : account.role === 'instructor'
                            ? 'border-teal-500/20 bg-teal-500/10 text-teal-700 dark:text-teal-300'
                            : 'border-blue-500/20 bg-blue-500/10 text-blue-700 dark:text-blue-300'
                      "
                    >
                      {{ account.role }}
                    </span>
                  </td>
                  <td class="px-4 py-3.5">
                    <span
                      class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold capitalize"
                      :class="
                        account.status === 'active'
                          ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                          : 'border-hairline bg-canvas text-subtle'
                      "
                    >
                      <span
                        class="h-1.5 w-1.5 rounded-full"
                        :class="account.status === 'active' ? 'bg-emerald-500' : 'bg-slate-400'"
                        aria-hidden="true"
                      />
                      {{ account.status }}
                    </span>
                  </td>
                  <td class="px-4 py-3.5">
                    <div class="inline-grid grid-cols-[10.5rem_7.75rem] items-center gap-2">
                      <button
                        v-if="account.role !== 'admin' && account.id !== user?.id"
                        type="button"
                        class="w-full whitespace-nowrap rounded-lg border border-hairline bg-canvas px-3 py-1.5 text-xs font-semibold text-ink transition-colors hover:border-action/40 hover:text-action dark:hover:text-sky-300"
                        @click="resetAccountPassword(account)"
                      >
                        Reset password
                      </button>
                      <span v-else aria-hidden="true" />
                      <button
                        type="button"
                        class="w-full whitespace-nowrap rounded-lg border border-hairline bg-canvas px-3 py-1.5 text-xs font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-45"
                        :class="
                          account.status === 'active'
                            ? 'text-rose-600 hover:border-rose-300 hover:bg-rose-50 dark:text-rose-400 dark:hover:border-rose-500/40 dark:hover:bg-rose-950/40'
                            : 'text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:border-emerald-500/40 dark:hover:bg-emerald-950/40'
                        "
                        :disabled="account.id === user?.id"
                        @click="toggleAccountStatus(account)"
                      >
                        {{ account.status === 'active' ? 'Deactivate' : 'Activate' }}
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="accounts.length === 0">
                  <td colspan="5" class="px-4 py-10 text-center text-sm text-muted">
                    No user accounts yet. Add a student or instructor to get started.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <PaginationBar
            v-if="accountTotal > 0"
            class="mt-4 rounded-xl border border-hairline bg-canvas"
            :meta="{
              page: accountPage,
              per_page: accountPerPage,
              total: accountTotal,
              total_pages: accountTotalPages,
            }"
            :disabled="isLoadingAccounts"
            @change-page="handleAccountPageChange"
            @change-per-page="handleAccountPerPageChange"
          />
        </div>
      </section>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <!-- Active Subjects -->
        <section class="flex flex-col rounded-2xl border border-hairline bg-surface p-5 shadow-card sm:p-6">
          <div class="flex items-start justify-between gap-3 border-b border-hairline pb-4">
            <div class="flex items-center gap-3">
              <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300">
                <AppIcon name="book" class="h-5 w-5" />
              </span>
              <div>
                <div class="flex items-center gap-2">
                  <h2 class="text-base font-bold text-ink">Active Subjects</h2>
                  <span class="inline-flex items-center rounded-full border border-violet-500/20 bg-violet-500/10 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-violet-700 dark:text-violet-300">
                    {{ subjectTotal }}
                  </span>
                </div>
                <p class="mt-0.5 text-xs text-subtle">Subjects currently available in the curriculum</p>
              </div>
            </div>
            <RouterLink
              to="/admin/subjects"
              class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-action transition-colors hover:bg-action/10 dark:text-sky-300 dark:hover:bg-sky-500/15"
            >
              <span>Manage</span>
              <AppIcon name="chevron-right" class="h-3.5 w-3.5" />
            </RouterLink>
          </div>

          <p v-if="subjectsError" class="mt-4 text-sm text-rose-600 dark:text-rose-400" role="alert">
            {{ subjectsError }}
            <button type="button" class="ml-2 font-semibold underline" @click="fetchActiveSubjects()">Retry</button>
          </p>
          <div v-else-if="isLoadingSubjects" class="mt-4 space-y-2.5">
            <div v-for="n in 3" :key="n" class="h-14 animate-pulse rounded-xl bg-canvas" />
          </div>
          <div
            v-else-if="activeSubjects.length === 0"
            class="mt-5 flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-hairline bg-canvas/50 px-6 py-10 text-center"
          >
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-surface text-subtle shadow-sm ring-1 ring-hairline">
              <AppIcon name="book" class="h-5 w-5" />
            </span>
            <p class="mt-3 text-sm font-semibold text-ink">No active subjects found</p>
            <p class="mt-1 text-xs text-subtle">Add subjects to make them available for scheduling.</p>
          </div>
          <ul v-else class="mt-4 space-y-2">
            <li
              v-for="subject in activeSubjects"
              :key="subject.id"
              class="flex items-center justify-between gap-3 rounded-xl border border-hairline/70 bg-canvas/50 px-3.5 py-3 transition-colors hover:border-hairline hover:bg-canvas"
            >
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <span class="rounded-md border border-hairline bg-surface px-2 py-0.5 font-mono text-xs font-bold text-ink">
                    {{ subject.subject_code }}
                  </span>
                </div>
                <div class="mt-1 truncate text-sm font-medium text-muted">{{ subject.subject_name }}</div>
              </div>
              <span class="shrink-0 rounded-full border border-hairline bg-surface px-2.5 py-1 text-xs font-semibold tabular-nums text-muted">
                {{ subject.units }} {{ subject.units === 1 ? 'unit' : 'units' }}
              </span>
            </li>
          </ul>
          <PaginationBar
            v-if="!subjectsError && subjectTotalPages > 1"
            class="mt-4 rounded-xl border border-hairline bg-canvas"
            :meta="{
              page: subjectPage,
              per_page: subjectPerPage,
              total: subjectTotal,
              total_pages: subjectTotalPages,
            }"
            :disabled="isLoadingSubjects"
            @change-page="handleSubjectPageChange"
            @change-per-page="handleSubjectPerPageChange"
          />
        </section>

        <!-- Classes Today -->
        <section class="flex flex-col rounded-2xl border border-hairline bg-surface p-5 shadow-card sm:p-6">
          <div class="flex items-start justify-between gap-3 border-b border-hairline pb-4">
            <div class="flex items-center gap-3">
              <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300">
                <AppIcon name="calendar" class="h-5 w-5" />
              </span>
              <div>
                <div class="flex items-center gap-2">
                  <h2 class="text-base font-bold text-ink">Classes Today</h2>
                  <span class="inline-flex items-center rounded-full border border-sky-500/20 bg-sky-500/10 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-sky-700 dark:text-sky-300">
                    {{ todaysClasses.length }}
                  </span>
                </div>
                <p class="mt-0.5 text-xs text-subtle">{{ todayLabel }} &middot; all sections and instructors</p>
              </div>
            </div>
            <RouterLink
              to="/admin/schedules"
              class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-action transition-colors hover:bg-action/10 dark:text-sky-300 dark:hover:bg-sky-500/15"
            >
              <span>Schedules</span>
              <AppIcon name="chevron-right" class="h-3.5 w-3.5" />
            </RouterLink>
          </div>

          <p v-if="classesError" class="mt-4 text-sm text-rose-600 dark:text-rose-400" role="alert">
            {{ classesError }}
            <button type="button" class="ml-2 font-semibold underline" @click="fetchTodaysClasses">Retry</button>
          </p>
          <div v-else-if="isLoadingClasses" class="mt-4 space-y-2.5">
            <div v-for="n in 3" :key="n" class="h-14 animate-pulse rounded-xl bg-canvas" />
          </div>
          <div
            v-else-if="todaysClasses.length === 0"
            class="mt-5 flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-hairline bg-canvas/50 px-6 py-10 text-center"
          >
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-surface text-subtle shadow-sm ring-1 ring-hairline">
              <AppIcon name="calendar" class="h-5 w-5" />
            </span>
            <p class="mt-3 text-sm font-semibold text-ink">No published classes today</p>
            <p class="mt-1 max-w-xs text-xs text-subtle">
              There are no active timetable entries scheduled for {{ todayLabel }}.
            </p>
            <RouterLink
              to="/admin/schedules"
              class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-hairline bg-surface px-3 py-1.5 text-xs font-semibold text-ink transition-colors hover:border-action/40 hover:text-action dark:hover:text-sky-300"
            >
              <span>Open Schedule Manager</span>
              <AppIcon name="arrow-right" class="h-3.5 w-3.5" />
            </RouterLink>
          </div>
          <ul v-else class="mt-4 space-y-2">
            <li
              v-for="item in todaysClasses"
              :key="item.id"
              class="rounded-xl border border-hairline/70 bg-canvas/50 px-3.5 py-3 transition-colors hover:border-hairline hover:bg-canvas"
            >
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <span class="rounded-md border border-hairline bg-surface px-2 py-0.5 font-mono text-xs font-bold text-ink">
                      {{ item.subject_code }}
                    </span>
                    <span class="truncate text-sm font-semibold text-ink">{{ item.subject_name }}</span>
                  </div>
                  <div class="mt-1.5 text-xs text-muted">
                    {{ item.instructor_name }} &middot; {{ item.section_name }} &middot; {{ item.room_code }}
                  </div>
                </div>
                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-sky-500/20 bg-sky-500/10 px-2.5 py-1 text-xs font-semibold tabular-nums text-navy dark:text-sky-300">
                  <AppIcon name="clock" class="h-3.5 w-3.5" />
                  {{ formatTime(item.start_time) }}–{{ formatTime(item.end_time) }}
                </span>
              </div>
            </li>
          </ul>
        </section>
      </div>
    </main>

    <div
      v-if="isCreateFormOpen"
      class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm"
      @click.self="isCreateFormOpen = false"
    >
      <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="create-user-title"
        class="my-8 w-full max-w-xl overflow-hidden rounded-2xl border border-hairline bg-surface shadow-2xl"
      >
        <div class="h-1 w-full bg-gradient-to-r from-navy via-action to-accent" />

        <div class="flex items-center justify-between gap-4 border-b border-hairline px-6 py-4">
          <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300">
              <AppIcon name="users" class="h-5 w-5" />
            </span>
            <div>
              <h2 id="create-user-title" class="text-lg font-bold text-ink">Add {{ roleLabel }} Account</h2>
              <p class="mt-0.5 text-xs text-subtle">
                {{ accountRoleFilter ? `Create an active ${accountRoleFilter} account.` : 'Create an active account with the selected role.' }}
              </p>
            </div>
          </div>
          <button
            type="button"
            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-hairline bg-canvas text-subtle transition-colors hover:text-ink"
            aria-label="Close form"
            @click="isCreateFormOpen = false"
          >
            <AppIcon name="close" class="h-4 w-4" />
          </button>
        </div>

        <form class="space-y-4 p-6" @submit.prevent="createAccount">
          <div v-if="!accountRoleFilter">
            <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-muted">Role</span>
            <div class="grid grid-cols-2 gap-2.5">
              <button
                type="button"
                class="flex items-center justify-center gap-2 rounded-xl border px-3.5 py-2.5 text-xs font-semibold transition-all"
                :class="
                  accountForm.role === 'student'
                    ? 'border-action bg-action/10 text-navy ring-1 ring-inset ring-action/25 dark:border-sky-400/50 dark:bg-sky-500/15 dark:text-sky-200'
                    : 'border-hairline bg-canvas text-muted hover:text-ink'
                "
                @click="accountForm.role = 'student'"
              >
                <AppIcon name="student" class="h-4 w-4" />
                <span>Student</span>
              </button>
              <button
                type="button"
                class="flex items-center justify-center gap-2 rounded-xl border px-3.5 py-2.5 text-xs font-semibold transition-all"
                :class="
                  accountForm.role === 'instructor'
                    ? 'border-action bg-action/10 text-navy ring-1 ring-inset ring-action/25 dark:border-sky-400/50 dark:bg-sky-500/15 dark:text-sky-200'
                    : 'border-hairline bg-canvas text-muted hover:text-ink'
                "
                @click="accountForm.role = 'instructor'"
              >
                <AppIcon name="instructor" class="h-4 w-4" />
                <span>Instructor</span>
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label for="account-first-name" class="mb-1.5 block text-xs font-semibold text-ink">First name</label>
              <input
                id="account-first-name"
                v-model="accountForm.firstName"
                required
                minlength="2"
                maxlength="80"
                autocomplete="given-name"
                placeholder="e.g. Maria"
                class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink placeholder-subtle/60 transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
              />
            </div>
            <div>
              <label for="account-last-name" class="mb-1.5 block text-xs font-semibold text-ink">Last name</label>
              <input
                id="account-last-name"
                v-model="accountForm.lastName"
                required
                minlength="2"
                maxlength="80"
                autocomplete="family-name"
                placeholder="e.g. Santos"
                class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink placeholder-subtle/60 transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
              />
            </div>
          </div>

          <div>
            <label for="account-email" class="mb-1.5 block text-xs font-semibold text-ink">Institutional Email</label>
            <input
              id="account-email"
              v-model="accountForm.email"
              type="email"
              required
              maxlength="191"
              autocomplete="email"
              spellcheck="false"
              placeholder="name@kampusly.edu.ph"
              class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink placeholder-subtle/60 transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
            />
          </div>

          <div class="flex items-start gap-2.5 rounded-xl border border-sky-500/20 bg-sky-500/10 px-3.5 py-3 text-xs text-navy dark:text-sky-200">
            <AppIcon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-action dark:text-sky-300" />
            <p class="leading-relaxed">
              A secure password is generated automatically and shown once after the account is created.
            </p>
          </div>

          <template v-if="formRole === 'student'">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label for="student-number" class="mb-1.5 block text-xs font-semibold text-ink">Student number</label>
                <input
                  id="student-number"
                  v-model="accountForm.studentNumber"
                  required
                  minlength="3"
                  maxlength="32"
                  placeholder="e.g. 2026-00105"
                  class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink placeholder-subtle/60 transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
                />
              </div>
              <div>
                <label for="student-program" class="mb-1.5 block text-xs font-semibold text-ink">Program</label>
                <input
                  id="student-program"
                  v-model="accountForm.program"
                  required
                  minlength="2"
                  maxlength="120"
                  placeholder="e.g. BS Computer Science"
                  class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink placeholder-subtle/60 transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
                />
              </div>
            </div>
            <div>
              <label for="student-section" class="mb-1.5 block text-xs font-semibold text-ink">Section</label>
              <select
                id="student-section"
                v-model="accountForm.sectionId"
                required
                class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
              >
                <option value="" disabled>Select a section</option>
                <option v-for="section in sections" :key="section.id" :value="String(section.id)">
                  {{ section.section_name }} · {{ section.program }}
                </option>
              </select>
              <p v-if="sections.length === 0" class="mt-1.5 text-xs text-amber-700 dark:text-amber-300">
                Create an active section before adding a student.
              </p>
            </div>
          </template>

          <template v-else>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label for="employee-number" class="mb-1.5 block text-xs font-semibold text-ink">Employee number</label>
                <input
                  id="employee-number"
                  v-model="accountForm.employeeNumber"
                  required
                  minlength="3"
                  maxlength="32"
                  placeholder="e.g. EMP-2026-010"
                  class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink placeholder-subtle/60 transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
                />
              </div>
              <div>
                <label for="instructor-department" class="mb-1.5 block text-xs font-semibold text-ink">Department</label>
                <input
                  id="instructor-department"
                  v-model="accountForm.department"
                  required
                  minlength="2"
                  maxlength="120"
                  placeholder="e.g. Dept. of Computer Science"
                  class="w-full rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-sm text-ink placeholder-subtle/60 transition-colors focus:border-action focus:outline-none focus:ring-2 focus:ring-action/25"
                />
              </div>
            </div>
          </template>

          <div
            v-if="createAccountError"
            class="rounded-xl border border-rose-200 bg-rose-50/90 p-3 text-xs text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-200"
            role="alert"
          >
            {{ createAccountError }}
          </div>

          <div class="flex flex-wrap items-center justify-end gap-2.5 border-t border-hairline pt-4">
            <button
              type="button"
              class="rounded-xl border border-hairline bg-canvas px-4 py-2.5 text-xs font-semibold text-ink transition-colors hover:bg-surface"
              @click="isCreateFormOpen = false"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="inline-flex items-center justify-center gap-2 rounded-xl bg-action px-5 py-2.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-action-hover active:bg-action-active disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="isCreatingAccount || (formRole === 'student' && sections.length === 0)"
            >
              <AppIcon v-if="isCreatingAccount" name="loader" class="h-4 w-4 animate-spin" />
              <span>{{ isCreatingAccount ? 'Creating...' : 'Create Account' }}</span>
            </button>
          </div>
        </form>
      </section>
    </div>

    <div
      v-if="createdCredentials"
      class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm"
    >
      <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="credentials-title"
        class="my-8 w-full max-w-lg overflow-hidden rounded-2xl border border-hairline bg-surface shadow-2xl"
      >
        <div class="h-1 w-full bg-gradient-to-r from-navy via-action to-accent" />
        <div class="p-6">
          <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300">
              <AppIcon name="check" class="h-5 w-5" />
            </span>
            <div>
              <h2 id="credentials-title" class="text-lg font-bold text-ink">
                {{ createdCredentials.isReset ? 'Password reset' : 'Account created' }}
              </h2>
              <p class="mt-0.5 text-xs text-subtle">
                Share these sign-in details with {{ createdCredentials.name }}.
                <template v-if="createdCredentials.isReset"> The previous password no longer works.</template>
              </p>
            </div>
          </div>

          <dl class="mt-5 space-y-4">
            <div class="rounded-xl border border-hairline bg-canvas/60 px-3.5 py-2.5">
              <dt class="text-[11px] font-semibold uppercase tracking-wider text-subtle">Email</dt>
              <dd class="mt-1 break-all text-sm font-semibold text-ink">{{ createdCredentials.email }}</dd>
            </div>
            <div>
              <dt class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-subtle">Generated Password</dt>
              <dd class="flex flex-wrap items-center gap-2">
                <code
                  ref="passwordText"
                  class="min-w-0 flex-1 cursor-pointer select-all break-all rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 font-mono text-sm font-semibold text-ink"
                  title="Click to select"
                  @click="selectPasswordText"
                >{{ createdCredentials.password }}</code>
                <button
                  type="button"
                  class="shrink-0 rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-xs font-semibold text-ink transition-colors hover:border-action/40 hover:text-action dark:hover:text-sky-300"
                  @click="copyGeneratedPassword"
                >
                  {{ isPasswordCopied ? 'Copied' : 'Copy' }}
                </button>
                <button
                  type="button"
                  class="shrink-0 rounded-xl border border-hairline bg-canvas px-3.5 py-2.5 text-xs font-semibold text-ink transition-colors hover:border-action/40 hover:text-action dark:hover:text-sky-300"
                  @click="downloadCredentials"
                >
                  Download .txt
                </button>
              </dd>
            </div>
          </dl>

          <p class="mt-4 rounded-xl border border-amber-300/70 bg-amber-50/90 p-3 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/30 dark:text-amber-200" role="alert">
            Copy or download this password now. It will not be shown again. If it gets lost, use
            "Reset password" on the account to generate a new one.
          </p>

          <div class="mt-5 flex justify-end border-t border-hairline pt-4">
            <button
              type="button"
              class="rounded-xl bg-action px-5 py-2.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-action-hover active:bg-action-active"
              @click="closeCredentials"
            >
              Done
            </button>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
