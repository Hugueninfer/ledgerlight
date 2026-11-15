export type ApiHealthStatus = 'ok' | 'fail' | 'unknown'

interface HealthPayload {
  status?: string
}

interface HttpErrorLike {
  statusCode?: number
  status?: number
  data?: HealthPayload
}

export function useApiHealth() {
  const config = useRuntimeConfig()
  const status = ref<ApiHealthStatus>('unknown')

  async function refresh(): Promise<void> {
    // Browser calls are same-origin through the edge; server-side calls use the internal base URL.
    const base = import.meta.server ? config.apiInternalBase : ''
    try {
      const payload = await $fetch<HealthPayload>(`${base}/api/health`, { headers: { Accept: 'application/json' } })
      status.value = payload.status === 'ok' ? 'ok' : 'fail'
    }
    catch (error) {
      const { statusCode, status: httpStatus, data } = error as HttpErrorLike
      // The API answered but reported a failing dependency: 503 with status "fail".
      status.value = (statusCode ?? httpStatus) === 503 || data?.status === 'fail' ? 'fail' : 'unknown'
    }
  }

  if (import.meta.client) {
    void refresh()
  }

  return { status, refresh }
}
