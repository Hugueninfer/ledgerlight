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

  // Fetched on the server too, so the status is part of the SSR payload (no flash on hydration).
  const { data, refresh: refreshData } = useAsyncData<ApiHealthStatus>('api-health', async () => {
    // Server-side calls go straight to the API container; browser calls are same-origin through the edge.
    const base = import.meta.server ? config.apiInternalBase : ''
    try {
      const payload = await $fetch<HealthPayload>(`${base}/api/health`, { headers: { Accept: 'application/json' } })
      return payload.status === 'ok' ? 'ok' : 'fail'
    }
    catch (error) {
      const { statusCode, status: httpStatus, data: body } = error as HttpErrorLike
      // The API answered but reported a failing dependency: 503 with status "fail".
      return (statusCode ?? httpStatus) === 503 || body?.status === 'fail' ? 'fail' : 'unknown'
    }
  }, { default: (): ApiHealthStatus => 'unknown' })

  const status = computed<ApiHealthStatus>(() => data.value ?? 'unknown')

  async function refresh(): Promise<void> {
    await refreshData()
  }

  return { status, refresh }
}
