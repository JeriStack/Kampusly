<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/features/auth/stores/useAuth'
import { apiClient } from '@/services/api/client'
import { API_ENDPOINTS } from '@/services/api/endpoints'
import KampuslyLogo from '@/components/common/KampuslyLogo.vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'
import AppIcon from '@/components/common/AppIcon.vue'
import PaginationBar from '@/features/academic/components/PaginationBar.vue'

const router = useRouter()
const route = useRoute()
const { user, logout } = useAuth()

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
}
const createdCredentials = ref<CreatedCredentials | null>(null)
const isPasswordCopied = ref(false)

function closeCredentials(): void {
  createdCredentials.value = null
  isPasswordCopied.value = false
}

async function copyGeneratedPassword(): Promise<void> {
  if (!createdCredentials.value) return
  try {
    await navigator.clipboard.writeText(createdCredentials.value.password)
    isPasswordCopied.value = true
  } catch {
    isPasswordCopied.value = false
  }
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
    <header class="sticky top-0 z-20 border-b border-hairline bg-surface/90 backdrop-blur-md">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <div class="flex items-center gap-4">
          <KampuslyLogo size="sm" />
          <span class="rounded-full bg-navy-100 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider text-navy-800 dark:bg-navy-900/60 dark:text-primary-300">
            Administrator Portal
          </span>
        </div>
        <div class="flex items-center gap-3">
          <ThemeToggle />
          <div class="hidden text-right sm:block">
            <div class="text-xs font-bold text-ink">{{ user?.name }}</div>
            <div class="text-[11px] text-muted">{{ user?.email }}</div>
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-hairline bg-canvas px-3 py-1.5 text-xs font-semibold text-rose-600 transition-colors hover:bg-rose-50 dark:hover:bg-rose-950/40"
            @click="handleLogout"
          >
            <AppIcon name="logout" class="h-3.5 w-3.5" />
            <span>Sign Out</span>
          </button>
        </div>
      </div>
      <nav class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4 sm:px-6 lg:px-8" aria-label="Admin navigation">
        <RouterLink
          v-for="tab in [
            { name: 'Dashboard', path: '/admin/dashboard' },
            { name: 'Students', path: '/admin/students' },
            { name: 'Instructors', path: '/admin/instructors' },
            { name: 'Schedules', path: '/admin/schedules' },
            { name: 'Subjects', path: '/admin/subjects' },
            { name: 'Rooms', path: '/admin/rooms' },
            { name: 'Sections', path: '/admin/sections' },
          ]"
          :key="tab.path"
          :to="tab.path"
          class="whitespace-nowrap border-b-2 px-3 py-2.5 text-xs font-semibold transition-colors"
          :class="route.path === tab.path ? 'border-navy text-navy dark:border-primary-400 dark:text-primary-300' : 'border-transparent text-muted hover:text-ink'"
        >
          {{ tab.name }}
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

      <div v-else-if="summary" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-hairline bg-surface p-5 shadow-card">
          <div class="text-xs font-semibold uppercase tracking-wider text-muted">Total Students</div>
          <div class="mt-2 text-3xl font-bold text-ink">{{ summary.total_students }}</div>
          <div class="mt-1 text-xs text-muted">Registered student profiles</div>
        </div>
        <div class="rounded-2xl border border-hairline bg-surface p-5 shadow-card">
          <div class="text-xs font-semibold uppercase tracking-wider text-muted">Instructors</div>
          <div class="mt-2 text-3xl font-bold text-ink">{{ summary.total_instructors }}</div>
          <div class="mt-1 text-xs text-muted">Registered faculty profiles</div>
        </div>
        <div class="rounded-2xl border border-hairline bg-surface p-5 shadow-card">
          <div class="text-xs font-semibold uppercase tracking-wider text-muted">Today's Classes</div>
          <div class="mt-2 text-3xl font-bold text-primary-600 dark:text-primary-400">
            {{ summary.todays_classes }}
          </div>
          <div class="mt-1 text-xs text-muted">Published for today</div>
        </div>
        <div class="rounded-2xl border border-hairline bg-surface p-5 shadow-card">
          <div class="text-xs font-semibold uppercase tracking-wider text-muted">Active Subjects</div>
          <div class="mt-2 text-3xl font-bold text-ink">{{ summary.total_subjects }}</div>
          <div class="mt-1 text-xs text-muted">Available in the curriculum</div>
        </div>
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
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h2 class="text-lg font-semibold text-ink">
              {{ accountRoleFilter ? `${accountRoleFilter === 'student' ? 'Student' : 'Instructor'} Accounts` : 'User Accounts' }}
            </h2>
            <p class="mt-1 text-sm text-muted">View, create, activate, or deactivate accounts.</p>
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-action focus-visible:ring-offset-2"
            @click="openCreateForm"
          >
            <AppIcon name="plus" class="h-4 w-4" />
            Add {{ roleLabel }}
          </button>
        </div>

        <div v-if="successMessage" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">
          {{ successMessage }}
        </div>
        <div v-if="accountError" class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" role="alert">
          {{ accountError }}
          <button type="button" class="ml-2 font-semibold underline" @click="fetchAccounts()">Retry</button>
        </div>

        <div v-if="isLoadingAccounts" class="mt-5 space-y-3" aria-label="Loading user accounts">
          <div v-for="i in 3" :key="i" class="h-12 animate-pulse rounded-lg bg-canvas" />
        </div>
        <div v-else-if="!accountError" class="mt-5 overflow-x-auto">
          <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="border-b border-hairline text-xs uppercase tracking-wide text-muted">
              <tr>
                <th scope="col" class="px-3 py-3 font-semibold">Name</th>
                <th scope="col" class="px-3 py-3 font-semibold">Email</th>
                <th scope="col" class="px-3 py-3 font-semibold">Role</th>
                <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                <th scope="col" class="px-3 py-3 text-right font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
              <tr v-for="account in accounts" :key="account.id">
                <td class="px-3 py-3 font-medium text-ink">{{ account.full_name }}</td>
                <td class="px-3 py-3 text-muted">{{ account.email }}</td>
                <td class="px-3 py-3 capitalize text-ink">{{ account.role }}</td>
                <td class="px-3 py-3">
                  <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                    :class="account.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                  >
                    {{ account.status }}
                  </span>
                </td>
                <td class="px-3 py-3 text-right">
                  <button
                    type="button"
                    class="rounded-md border border-hairline px-2.5 py-1.5 text-xs font-semibold text-ink hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="account.id === user?.id"
                    @click="toggleAccountStatus(account)"
                  >
                    {{ account.status === 'active' ? 'Deactivate' : 'Activate' }}
                  </button>
                </td>
              </tr>
              <tr v-if="accounts.length === 0">
                <td colspan="5" class="px-3 py-8 text-center text-muted">
                  No user accounts yet. Add a student or instructor to get started.
                </td>
              </tr>
            </tbody>
          </table>
          <PaginationBar
            v-if="accountTotal > 0"
            class="mt-4 rounded-lg border border-hairline bg-canvas"
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
        <section class="rounded-2xl border border-hairline bg-surface p-5 shadow-card sm:p-6">
          <div class="flex items-baseline justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold text-ink">Active Subjects</h2>
              <p class="mt-1 text-sm text-muted">Subjects currently available.</p>
            </div>
            <span class="rounded-full bg-navy-50 px-3 py-1 text-xs font-semibold text-navy">
              {{ subjectTotal }}
            </span>
          </div>
          <p v-if="subjectsError" class="mt-4 text-sm text-rose-700" role="alert">
            {{ subjectsError }}
            <button type="button" class="ml-2 font-semibold underline" @click="fetchActiveSubjects()">Retry</button>
          </p>
          <div v-else-if="isLoadingSubjects" class="mt-5 h-24 animate-pulse rounded-lg bg-canvas" />
          <div v-else-if="activeSubjects.length === 0" class="mt-5 rounded-lg bg-canvas p-4 text-sm text-muted">
            No active subjects found.
          </div>
          <ul v-else class="mt-4 divide-y divide-hairline">
            <li v-for="subject in activeSubjects" :key="subject.id" class="flex items-center justify-between gap-4 py-3">
              <div>
                <div class="font-semibold text-ink">{{ subject.subject_code }}</div>
                <div class="text-sm text-muted">{{ subject.subject_name }}</div>
              </div>
              <span class="shrink-0 text-xs text-muted">{{ subject.units }} units</span>
            </li>
          </ul>
          <PaginationBar
            v-if="!subjectsError && subjectTotal > 0"
            class="mt-4 rounded-lg border border-hairline bg-canvas"
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

        <section class="rounded-2xl border border-hairline bg-surface p-5 shadow-card sm:p-6">
          <div>
            <h2 class="text-lg font-semibold text-ink">Classes Today</h2>
            <p class="mt-1 text-sm text-muted">{{ todayLabel }} &middot; all sections and instructors</p>
          </div>
          <p v-if="classesError" class="mt-4 text-sm text-rose-700" role="alert">
            {{ classesError }}
            <button type="button" class="ml-2 font-semibold underline" @click="fetchTodaysClasses">Retry</button>
          </p>
          <div v-else-if="isLoadingClasses" class="mt-5 h-24 animate-pulse rounded-lg bg-canvas" />
          <div v-else-if="todaysClasses.length === 0" class="mt-5 rounded-lg bg-canvas p-4 text-sm text-muted">
            No published classes are scheduled for today.
          </div>
          <ul v-else class="mt-4 divide-y divide-hairline">
            <li v-for="item in todaysClasses" :key="item.id" class="py-3">
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <div class="font-semibold text-ink">{{ item.subject_code }} &middot; {{ item.subject_name }}</div>
                  <div class="mt-1 text-sm text-muted">
                    {{ item.instructor_name }} &middot; {{ item.section_name }} &middot; {{ item.room_code }}
                  </div>
                </div>
                <span class="shrink-0 text-sm font-medium text-navy">
                  {{ formatTime(item.start_time) }}–{{ formatTime(item.end_time) }}
                </span>
              </div>
            </li>
          </ul>
        </section>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-hairline bg-surface p-6 shadow-card">
          <h2 class="text-base font-semibold text-ink">Your Session</h2>
          <div class="mt-4 space-y-2 text-xs">
            <div class="flex justify-between border-b border-hairline py-1.5">
              <span class="text-muted">Account Role</span>
              <span class="font-bold uppercase text-navy dark:text-primary-400">{{ user?.role }}</span>
            </div>
            <div class="flex justify-between border-b border-hairline py-1.5">
              <span class="text-muted">Status</span>
              <span class="font-semibold capitalize text-emerald-600 dark:text-emerald-400">{{ user?.status }}</span>
            </div>
            <div class="flex justify-between py-1.5">
              <span class="text-muted">User ID</span>
              <span class="font-mono">{{ user?.id }}</span>
            </div>
          </div>
        </div>
      </div>
    </main>

    <div
      v-if="isCreateFormOpen"
      class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4"
      @click.self="isCreateFormOpen = false"
    >
      <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="create-user-title"
        class="my-8 w-full max-w-2xl rounded-2xl border border-hairline bg-surface p-6 shadow-2xl"
      >
        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 id="create-user-title" class="text-xl font-bold text-ink">Add {{ roleLabel }} Account</h2>
            <p class="mt-1 text-sm text-muted">
              {{ accountRoleFilter ? `Create an active ${accountRoleFilter} account.` : 'Create an active account with the selected role.' }}
            </p>
          </div>
          <button
            type="button"
            class="rounded-lg px-2 py-1 text-muted hover:bg-canvas hover:text-ink"
            aria-label="Close form"
            @click="isCreateFormOpen = false"
          >
            ✕
          </button>
        </div>

        <form class="mt-5 space-y-4" @submit.prevent="createAccount">
          <div v-if="!accountRoleFilter">
            <label for="account-role" class="mb-1 block text-sm font-medium text-ink">Role</label>
            <select
              id="account-role"
              v-model="accountForm.role"
              required
              class="w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-action"
            >
              <option value="student">Student</option>
              <option value="instructor">Instructor</option>
            </select>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label for="account-first-name" class="mb-1 block text-sm font-medium text-ink">First name</label>
              <input id="account-first-name" v-model="accountForm.firstName" required minlength="2" maxlength="80" autocomplete="given-name" class="w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-action" />
            </div>
            <div>
              <label for="account-last-name" class="mb-1 block text-sm font-medium text-ink">Last name</label>
              <input id="account-last-name" v-model="accountForm.lastName" required minlength="2" maxlength="80" autocomplete="family-name" class="w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-action" />
            </div>
          </div>

          <div>
            <label for="account-email" class="mb-1 block text-sm font-medium text-ink">Email</label>
            <input id="account-email" v-model="accountForm.email" type="email" required maxlength="191" autocomplete="email" class="w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-action" />
          </div>
          <p class="rounded-lg bg-canvas p-3 text-sm text-muted">
            A secure password is generated automatically. It is shown once after the account is created.
          </p>

          <template v-if="formRole === 'student'">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label for="student-number" class="mb-1 block text-sm font-medium text-ink">Student number</label>
                <input id="student-number" v-model="accountForm.studentNumber" required minlength="3" maxlength="32" class="w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-action" />
              </div>
              <div>
                <label for="student-program" class="mb-1 block text-sm font-medium text-ink">Program</label>
                <input id="student-program" v-model="accountForm.program" required minlength="2" maxlength="120" class="w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-action" />
              </div>
            </div>
            <div>
              <label for="student-section" class="mb-1 block text-sm font-medium text-ink">Section</label>
              <select id="student-section" v-model="accountForm.sectionId" required class="w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-action">
                <option value="" disabled>Select a section</option>
                <option v-for="section in sections" :key="section.id" :value="String(section.id)">
                  {{ section.section_name }} · {{ section.program }}
                </option>
              </select>
              <p v-if="sections.length === 0" class="mt-1 text-xs text-amber-700">Create an active section before adding a student.</p>
            </div>
          </template>

          <template v-else>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label for="employee-number" class="mb-1 block text-sm font-medium text-ink">Employee number</label>
                <input id="employee-number" v-model="accountForm.employeeNumber" required minlength="3" maxlength="32" class="w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-action" />
              </div>
              <div>
                <label for="instructor-department" class="mb-1 block text-sm font-medium text-ink">Department</label>
                <input id="instructor-department" v-model="accountForm.department" required minlength="2" maxlength="120" class="w-full rounded-lg border border-hairline bg-canvas px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-action" />
              </div>
            </div>
          </template>

          <div v-if="createAccountError" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" role="alert">
            {{ createAccountError }}
          </div>

          <div class="flex flex-wrap justify-end gap-3 border-t border-hairline pt-4">
            <button type="button" class="rounded-lg border border-hairline px-4 py-2 text-sm font-semibold text-ink hover:bg-canvas" @click="isCreateFormOpen = false">
              Cancel
            </button>
            <button
              type="submit"
              class="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white hover:bg-navy-700 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="isCreatingAccount || (formRole === 'student' && sections.length === 0)"
            >
              {{ isCreatingAccount ? 'Creating...' : 'Create Account' }}
            </button>
          </div>
        </form>
      </section>
    </div>

    <div
      v-if="createdCredentials"
      class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4"
    >
      <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="credentials-title"
        class="my-8 w-full max-w-lg rounded-2xl border border-hairline bg-surface p-6 shadow-2xl"
      >
        <h2 id="credentials-title" class="text-xl font-bold text-ink">Account created</h2>
        <p class="mt-1 text-sm text-muted">
          Give these sign-in details to {{ createdCredentials.name }}. The password is permanent.
        </p>

        <dl class="mt-5 space-y-4">
          <div>
            <dt class="text-sm font-medium text-muted">Email</dt>
            <dd class="mt-1 break-all font-semibold text-ink">{{ createdCredentials.email }}</dd>
          </div>
          <div>
            <dt class="text-sm font-medium text-muted">Password</dt>
            <dd class="mt-1 flex items-center gap-2">
              <code class="min-w-0 flex-1 select-all break-all rounded-lg bg-canvas px-3 py-2 font-mono text-base font-semibold text-ink">{{ createdCredentials.password }}</code>
              <button
                type="button"
                class="shrink-0 rounded-lg border border-hairline px-3 py-2 text-sm font-semibold text-ink hover:bg-canvas"
                @click="copyGeneratedPassword"
              >
                {{ isPasswordCopied ? 'Copied' : 'Copy' }}
              </button>
            </dd>
          </div>
        </dl>

        <p class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200" role="alert">
          Copy this password now. It will not be shown again.
        </p>

        <div class="mt-5 flex justify-end">
          <button
            type="button"
            class="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white hover:bg-navy-700"
            @click="closeCredentials"
          >
            Done
          </button>
        </div>
      </section>
    </div>
  </div>
</template>
