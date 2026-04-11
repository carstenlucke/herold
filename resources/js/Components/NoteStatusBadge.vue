<template>
  <v-chip
    :color="statusColor"
    size="small"
    variant="tonal"
    class="text-uppercase font-label"
    style="font-size: 0.65rem; letter-spacing: 0.08em"
  >
    <v-icon :icon="statusIcon" size="12" class="mr-1" />
    {{ status }}
  </v-chip>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  status: string
}>()

const statusColor = computed(() => {
  switch (props.status) {
    case 'recorded':
      return 'warning'
    case 'processed':
      return 'secondary'
    case 'sent':
      return 'primary'
    case 'error':
      return 'error'
    default:
      return 'grey'
  }
})

const statusIcon = computed(() => {
  switch (props.status) {
    case 'recorded':
      return 'mdi-microphone'
    case 'processed':
      return 'mdi-check-circle'
    case 'sent':
      return 'mdi-send-check'
    case 'error':
      return 'mdi-alert-circle'
    default:
      return 'mdi-help-circle'
  }
})
</script>
