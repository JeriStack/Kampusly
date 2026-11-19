import { API_ENDPOINTS, buildApiUrl, resolveApiBaseUrl } from '@/services/api/endpoints'
import { ApiError } from '@/services/api/errors'
import { apiClient, fetchApiHealth } from '@/services/api/client'
import { describe, expect, it } from './harness'

describe('Kampusly Frontend API Configuration & Client', () => {
  it('normalizes the API base URL and strips trailing slashes', () => {
    expect(resolveApiBaseUrl('http://localhost:8080/api/v1/')).toBe(
      'http://localhost:8080/api/v1',
    )
    expect(resolveApiBaseUrl('')).toBe('/api/v1')
  })

  it('builds versioned API endpoint URLs accurately', () => {
    expect(
      buildApiUrl(API_ENDPOINTS.health.live, 'http://localhost:8080/api/v1'),
    ).toBe('http://localhost:8080/api/v1/health/live')
    expect(buildApiUrl(API_ENDPOINTS.health.ready, '/api/v1')).toBe(
      '/api/v1/health/ready',
    )
  })

  it('constructs structured ApiError instances from backend error envelopes', () => {
    const error = ApiError.fromPayload(422, {
      code: 'VALIDATION_ERROR',
      message: 'The submitted data failed validation.',
      errors: { email: ['The email field must be a valid email address.'] },
      request_id: 'req_test_422',
    })

    expect(error.status).toBe(422)
    expect(error.code).toBe('VALIDATION_ERROR')
    expect(error.requestId).toBe('req_test_422')
    expect(error.fieldErrors).toEqual({
      email: ['The email field must be a valid email address.'],
    })
  })

  it('fetches /health/live and parses JSON response through apiClient', async () => {
    const originalFetch = globalThis.fetch
    let capturedUrl = ''
    let capturedCredentials = ''

    globalThis.fetch = (async (input: RequestInfo | URL, init?: RequestInit) => {
      capturedUrl = String(input)
      capturedCredentials = String(init?.credentials ?? '')
      return new Response(
        JSON.stringify({
          status: 'ok',
          service: 'kampusly-api',
          version: '0.1.0',
          environment: 'testing',
          timezone: 'Asia/Manila',
          timestamp: '2026-10-28T13:00:00Z',
        }),
        {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        },
      )
    }) as typeof fetch

    try {
      const response = await fetchApiHealth('http://localhost:8080/api/v1')
      expect(capturedUrl).toBe('http://localhost:8080/api/v1/health/live')
      expect(capturedCredentials).toBe('include')
      expect(response.status).toBe('ok')
      expect(response.service).toBe('kampusly-api')
    } finally {
      globalThis.fetch = originalFetch
    }
  })

  it('attaches X-CSRF-Token on mutating POST requests and throws ApiError on non-2xx', async () => {
    const originalFetch = globalThis.fetch
    let capturedCsrf = ''
    let caughtError: unknown = null

    globalThis.fetch = (async (_input: RequestInfo | URL, init?: RequestInit) => {
      const headers = new Headers(init?.headers)
      capturedCsrf = headers.get('X-CSRF-Token') ?? ''
      return new Response(
        JSON.stringify({
          code: 'UNAUTHORIZED',
          message: 'Authentication required.',
          request_id: 'req_auth_01',
        }),
        { status: 401, headers: { 'Content-Type': 'application/json' } },
      )
    }) as typeof fetch

    try {
      try {
        await apiClient.post(
          '/schedules',
          { subject_id: 1 },
          { baseUrl: 'http://localhost:8080/api/v1', csrfToken: 'csrf_123' },
        )
      } catch (err) {
        caughtError = err
      }

      expect(caughtError).toBeInstanceOf(ApiError)
      if (caughtError instanceof ApiError) {
        expect(caughtError.status).toBe(401)
        expect(caughtError.code).toBe('UNAUTHORIZED')
      }
      expect(capturedCsrf).toBe('csrf_123')
    } finally {
      globalThis.fetch = originalFetch
    }
  })
})
