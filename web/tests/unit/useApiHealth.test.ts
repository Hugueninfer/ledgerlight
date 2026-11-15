import { describe, expect, it } from 'vitest'
import { registerEndpoint } from '@nuxt/test-utils/runtime'
import { createError, setResponseStatus } from 'h3'
import { useApiHealth } from '../../app/composables/useApiHealth'

describe('useApiHealth', () => {
  it('starts as unknown', () => {
    const { status } = useApiHealth()
    expect(status.value).toBe('unknown')
  })

  it('maps a 200 {status:"ok"} to "ok"', async () => {
    registerEndpoint('/api/health', () => ({ status: 'ok', checks: { database: 'ok', redis: 'ok' } }))
    const { status, refresh } = useApiHealth()
    await refresh()
    expect(status.value).toBe('ok')
  })

  it('maps a 503 to "fail"', async () => {
    registerEndpoint('/api/health', (event) => {
      setResponseStatus(event, 503)
      return { status: 'fail', checks: { database: 'fail', redis: 'ok' } }
    })
    const { status, refresh } = useApiHealth()
    await refresh()
    expect(status.value).toBe('fail')
  })

  it('maps any other failure (e.g. a 500) to "unknown"', async () => {
    registerEndpoint('/api/health', () => {
      throw createError({ statusCode: 500, statusMessage: 'boom' })
    })
    const { status, refresh } = useApiHealth()
    await refresh()
    expect(status.value).toBe('unknown')
  })
})
