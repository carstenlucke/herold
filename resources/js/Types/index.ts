export interface VoiceNote {
  id: string
  type: string
  status: 'recorded' | 'processed' | 'sent' | 'error'
  audio_path: string | null
  transcript: string | null
  processed_title: string | null
  processed_body: string | null
  metadata: Record<string, any> | null
  github_issue_number: number | null
  github_issue_url: string | null
  error_message: string | null
  created_at: string
  updated_at: string
}

export interface MessageType {
  label: string
  icon: string
  github_label: string
  extra_fields: ExtraField[]
}

export interface ExtraField {
  name: string
  type: string
  required: boolean
  label: string
}

export interface DashboardStats {
  total: number
  recorded: number
  processed: number
  sent: number
  error: number
}

export interface PaginatedData<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  links: PaginationLink[]
}

export interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}
