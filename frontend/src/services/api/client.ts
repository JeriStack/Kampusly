import { API_ENDPOINTS, buildApiUrl } from './endpoints'
import { ApiError, type ApiErrorPayload } from './errors'

export interface ApiRequestOptions extends Omit<RequestInit, 'body'> {
  body?: unknown
  csrfToken?: string
  baseUrl?: string
}

export interface ApiLiveHealthResponse {
  status: 'ok'
  service: string
  version: string
  environment: string
  timezone: string
  timestamp: string
}

export interface ApiReadyHealthResponse {
  status: 'ready' | 'degraded'
  service: string
  version: string
  checks: {
    database: {
      status: 'up' | 'down'
      driver: string
      latency_ms: number
    }
  }
  timestamp: string
}

const MUTATING_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE'])

let currentCsrfToken: string | null = null

export function setCsrfToken(token: string | null): void {
  currentCsrfToken = token
}

export function getCsrfToken(): string | null {
  return currentCsrfToken
}

/**
 * Perform a typed HTTP request against the Kampusly REST API (`/api/v1`).
 */
export async function apiRequest<TResponse>(
  path: string,
  options: ApiRequestOptions = {},
): Promise<TResponse> {
  const { body, csrfToken, baseUrl, headers: customHeaders, ...restInit } = options
  const method = (restInit.method || 'GET').toUpperCase()
  const url = buildApiUrl(path, baseUrl)

  const headers = new Headers(customHeaders)
  if (!headers.has('Accept')) {
    headers.set('Accept', 'application/json')
  }

  let serializedBody: BodyInit | undefined
  if (body !== undefined && body !== null) {
    if (typeof body === 'string' || body instanceof FormData) {
      serializedBody = body
    } else {
      if (!headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json')
      }
      serializedBody = JSON.stringify(body)
    }
  }

  const activeCsrfToken = csrfToken ?? currentCsrfToken
  if (activeCsrfToken && MUTATING_METHODS.has(method)) {
    headers.set('X-CSRF-Token', activeCsrfToken)
  }

  const response = await fetch(url, {
    ...restInit,
    method,
    headers,
    body: serializedBody,
    credentials: restInit.credentials ?? 'include',
  })

  if (response.status === 204) {
    return undefined as TResponse
  }

  const rawText = await response.text()
  let parsed: unknown = null
  if (rawText.trim() !== '') {
    try {
      parsed = JSON.parse(rawText)
    } catch {
      parsed = null
    }
  }

  if (!response.ok) {
    if (parsed && typeof parsed === 'object') {
      throw ApiError.fromPayload(response.status, parsed as Partial<ApiErrorPayload>)
    }
    throw new ApiError(
      `API request to ${path} failed with HTTP status ${response.status}.`,
      response.status,
      'HTTP_ERROR',
      {},
      response.headers.get('X-Request-Id'),
    )
  }

  // Auto-capture CSRF token from response if present
  if (parsed && typeof parsed === 'object' && 'data' in (parsed as Record<string, unknown>)) {
    const dataObj = (parsed as { data: Record<string, unknown> }).data
    if (dataObj && typeof dataObj === 'object' && typeof dataObj.csrf_token === 'string') {
      setCsrfToken(dataObj.csrf_token)
    }
  }

  return parsed as TResponse
}

export const apiClient = {
  get<TResponse>(path: string, options: Omit<ApiRequestOptions, 'method' | 'body'> = {}) {
    return apiRequest<TResponse>(path, { ...options, method: 'GET' })
  },
  post<TResponse>(path: string, body?: unknown, options: Omit<ApiRequestOptions, 'method' | 'body'> = {}) {
    return apiRequest<TResponse>(path, { ...options, method: 'POST', body })
  },
  put<TResponse>(path: string, body?: unknown, options: Omit<ApiRequestOptions, 'method' | 'body'> = {}) {
    return apiRequest<TResponse>(path, { ...options, method: 'PUT', body })
  },
  patch<TResponse>(path: string, body?: unknown, options: Omit<ApiRequestOptions, 'method' | 'body'> = {}) {
    return apiRequest<TResponse>(path, { ...options, method: 'PATCH', body })
  },
  delete<TResponse>(path: string, options: Omit<ApiRequestOptions, 'method'> = {}) {
    return apiRequest<TResponse>(path, { ...options, method: 'DELETE' })
  },
}

/**
 * Convenience helper to query the backend liveness probe (`/api/v1/health/live`).
 */
export function fetchApiHealth(baseUrl?: string): Promise<ApiLiveHealthResponse> {
  return apiClient.get<ApiLiveHealthResponse>(API_ENDPOINTS.health.live, { baseUrl })
}

