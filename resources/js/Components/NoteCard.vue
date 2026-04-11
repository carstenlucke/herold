<template>
  <v-card
    class="glow-hover neon-border-primary cursor-pointer"
    color="surface"
    rounded="lg"
    @click="navigate"
  >
    <v-card-text class="d-flex align-start ga-3 pa-4">
      <!-- Type icon -->
      <v-avatar
        :color="note.status === 'error' ? 'error' : 'primary'"
        variant="tonal"
        size="40"
      >
        <v-icon :icon="typeIcon" size="20" />
      </v-avatar>

      <!-- Content -->
      <div class="flex-grow-1 overflow-hidden">
        <div class="d-flex align-center ga-2 mb-1">
          <span class="text-body-1 font-weight-medium text-truncate" style="color: var(--text-primary)">
            {{ displayTitle }}
          </span>
        </div>

        <p
          v-if="snippet"
          class="text-body-2 text-truncate mb-2"
          style="color: var(--text-muted)"
        >
          {{ snippet }}
        </p>

        <div class="d-flex align-center ga-2">
          <NoteStatusBadge :status="note.status" />
          <span class="text-caption" style="color: var(--text-muted)">
            {{ formattedDate }}
          </span>
        </div>
      </div>

      <!-- Chevron -->
      <v-icon icon="mdi-chevron-right" size="20" style="color: var(--text-muted)" />
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import type { VoiceNote, MessageType } from '../Types'
import NoteStatusBadge from './NoteStatusBadge.vue'

const props = defineProps<{
  note: VoiceNote
  type?: MessageType
}>()

const typeIcon = computed(() => props.type?.icon ?? 'mdi-message-text')

const displayTitle = computed(() => {
  if (props.note.processed_title) return props.note.processed_title
  if (props.note.transcript) return props.note.transcript.slice(0, 60)
  return `Voice Note #${props.note.id.slice(0, 8)}`
})

const snippet = computed(() => {
  if (props.note.processed_body) return props.note.processed_body.slice(0, 100)
  if (props.note.transcript && props.note.processed_title) return props.note.transcript.slice(0, 100)
  return null
})

const formattedDate = computed(() => {
  const date = new Date(props.note.created_at)
  return date.toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
})

function navigate() {
  router.visit(`/notes/${props.note.id}`)
}
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
