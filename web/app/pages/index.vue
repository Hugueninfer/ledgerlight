<script setup lang="ts">
const { status } = useApiHealth()

const labels = { ok: 'API healthy', fail: 'API unhealthy', unknown: 'API status unknown' } as const
</script>

<template>
  <main>
    <h1>Ledgerlight</h1>
    <p data-testid="api-status">
      {{ labels[status] }}
    </p>
  </main>
</template>
