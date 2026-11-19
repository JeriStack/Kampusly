export interface PaginationMeta {
  page: number
  per_page: number
  total: number
  total_pages: number
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: PaginationMeta
}

export interface Student {
  id: number
  user_id: number
  section_id: number
  student_number: string
  first_name: string
  last_name: string
  full_name: string
  program: string
  email: string | null
  status: 'active' | 'inactive' | 'graduated' | string
  section_name: string | null
  created_at: string | null
  updated_at: string | null
}

export interface CreateStudentPayload {
  student_number: string
  first_name: string
  last_name: string
  email: string
  program: string
  section_id: number
  password?: string
  status?: string
}

export interface UpdateStudentPayload {
  student_number: string
  first_name: string
  last_name: string
  email: string
  program: string
  section_id: number
}

export interface Instructor {
  id: number
  user_id: number
  employee_number: string
  first_name: string
  last_name: string
  full_name: string
  department: string
  email: string | null
  status: 'active' | 'inactive' | string
  created_at: string | null
  updated_at: string | null
}

export interface CreateInstructorPayload {
  employee_number: string
  first_name: string
  last_name: string
  email: string
  department: string
  password?: string
  status?: string
}

export interface UpdateInstructorPayload {
  employee_number: string
  first_name: string
  last_name: string
  email: string
  department: string
}

export interface Subject {
  id: number
  subject_code: string
  subject_name: string
  description: string | null
  units: number
  status: 'active' | 'inactive'
  created_at: string | null
  updated_at: string | null
}

export interface CreateSubjectPayload {
  subject_code: string
  subject_name: string
  description?: string
  units: number
  status?: 'active' | 'inactive'
}

export interface UpdateSubjectPayload {
  subject_code: string
  subject_name: string
  description?: string
  units: number
  status?: 'active' | 'inactive'
}

export interface Room {
  id: number
  room_code: string
  room_name: string
  building: string
  capacity: number
  status: 'active' | 'inactive'
  created_at: string | null
  updated_at: string | null
}

export interface CreateRoomPayload {
  room_code: string
  room_name: string
  building: string
  capacity: number
  status?: 'active' | 'inactive'
}

export interface UpdateRoomPayload {
  room_code: string
  room_name: string
  building: string
  capacity: number
  status?: 'active' | 'inactive'
}

export interface Section {
  id: number
  section_name: string
  program: string
  year_level: number
  school_year: string
  semester: '1st' | '2nd' | 'Summer'
  term_start_date?: string | null
  status: 'active' | 'inactive'
  enrolled_students_count?: number
  created_at: string | null
  updated_at: string | null
}

export interface Schedule {
  id: number
  subject_id: number
  subject_code: string | null
  subject_name: string | null
  instructor_id: number
  instructor_name: string | null
  room_id: number
  room_code: string | null
  section_id: number
  section_name: string | null
  school_year: string
  semester: '1st' | '2nd' | 'Summer'
  day_of_week: string
  start_time: string
  end_time: string
  status: 'draft' | 'published' | 'cancelled'
  created_at: string | null
  updated_at: string | null
}

export interface CreateSectionPayload {
  section_name: string
  program: string
  year_level: number
  school_year: string
  semester: '1st' | '2nd' | 'Summer'
  status?: 'active' | 'inactive'
  term_start_date?: string | null
}

export interface UpdateSectionPayload {
  section_name: string
  program: string
  year_level: number
  school_year: string
  semester: '1st' | '2nd' | 'Summer'
  status?: 'active' | 'inactive'
  term_start_date?: string | null
}
