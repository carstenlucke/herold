<template>
  <AppLayout>
    <!-- Page header -->
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold mb-1" style="color: var(--text-primary)">Dashboard</h1>
      <p class="text-body-2" style="color: var(--text-muted)">
        Overview of your voice notes and processing status.
      </p>
    </div>

    <!-- Stats grid -->
    <v-row class="mb-6" dense>
      <v-col
        v-for="stat in statCards"
        :key="stat.label"
        cols="6"
        md="4"
      >
        <v-card
          color="surface"
          rounded="lg"
          class="neon-border-primary pa-4"
        >
          <div class="d-flex align-center ga-3">
            <v-avatar :color="stat.color" variant="tonal" size="44">
              <v-icon :icon="stat.icon" size="22" />
            </v-avatar>
            <div>
              <div class="text-h4 font-weight-bold" :style="{ color: `rgb(var(--v-theme-${stat.color}))` }">
                {{ stat.value }}
              </div>
              <div class="text-caption font-label" style="color: var(--text-muted)">
                {{ stat.label }}
              </div>
            </div>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Quick action -->
    <v-btn
      block
      color="primary"
      variant="outlined"
      size="large"
      prepend-icon="mdi-microphone"
      class="mb-8"
      @click="router.visit('/notes/create')"
    >
      New Recording
    </v-btn>

    <!-- Recent notes -->
    <div v-if="recentNotes.length > 0">
      <h2 class="text-h6 font-weight-bold mb-3" style="color: var(--text-primary)">
        Recent Notes
      </h2>
      <div class="d-flex flex-column ga-3">
        <NoteCard
          v-for="note in recentNotes"
          :key="note.id"
          :note="note"
        />
      </div>
    </div>

    <!-- Empty state -->
    <v-card
      v-else
      color="surface"
      rounded="lg"
      class="neon-border-primary pa-8 text-center"
    >
      <v-icon icon="mdi-microphone-off" size="48" color="primary" class="mb-3" />
      <p class="text-body-1" style="color: var(--text-muted)">
        No voice notes yet. Tap the button above to create your first recording.
      </p>
    </v-card>
  </AppLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import type { VoiceNote, DashboardStats } from '../Types'
import AppLayout from '../Layouts/AppLayout.vue'
import NoteCard from '../Components/NoteCard.vue'

const props = defineProps<{
  stats: DashboardStats
  recentNotes: VoiceNote[]
}>()

const statCards = computed(() => [
  { label: 'Total Notes', value: props.stats.total, icon: 'mdi-note-multiple', color: 'primary' },
  { label: 'Recorded', value: props.stats.recorded, icon: 'mdi-microphone', color: 'warning' },
  { label: 'Processed', value: props.stats.processed, icon: 'mdi-check-circle', color: 'secondary' },
  { label: 'Sent', value: props.stats.sent, icon: 'mdi-send-check', color: 'primary' },
  { label: 'Errors', value: props.stats.error, icon: 'mdi-alert-circle', color: 'error' },
])
</script>
