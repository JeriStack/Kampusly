/**
 * Standardized error payload returned by the Kampusly REST API (`/api/v1`).
 */
export interface ApiErrorPayload {
  code: string
  message: string
  errors?: Record<string, string[]>
  request_id?: string
}

/**
 * Typed HTTP client error wrapping Kampusly API error envelopes.
 */
export class ApiError extends Error {
  public readonly status: number
  public readonly code: string
  public readonly fieldErrors: Record<string, string[]>
  public readonly requestId: string | null

  constructor(
    message: string,
    status: number,
    code = 'UNKNOWN_API_ERROR',
    fieldErrors: Record<string, string[]> = {},
    requestId: string | null = null,
  ) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.fieldErrors = fieldErrors
    this.requestId = requestId
  }

  public static fromPayload(status: number, payload: Partial<ApiErrorPayload>): ApiError {
    return new ApiError(
      payload.message || `Request failed with HTTP status ${status}.`,
      status,
      payload.code || 'API_ERROR',
      payload.errors || {},
      payload.request_id || null,
    )
  }
}

