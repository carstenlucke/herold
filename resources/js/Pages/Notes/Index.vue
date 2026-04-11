<template>
  <AppLayout>
    <!-- Page header -->
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold mb-1" style="color: var(--text-primary)">Notes</h1>
      <p class="text-body-2" style="color: var(--text-muted)">
        All your voice notes in one place.
      </p>
    </div>

    <!-- Filter bar -->
    <v-row dense class="mb-4">
      <v-col cols="6">
        <v-select
          v-model="selectedType"
          :items="typeOptions"
          label="Type"
          variant="filled"
          color="primary"
          density="compact"
          clearable
          hide-details
        />
      </v-col>
      <v-col cols="6">
        <v-select
          v-model="selectedStatus"
          :items="statusOptions"
          label="Status"
          variant="filled"
          color="primary"
          density="compact"
          clearable
          hide-details
        />
      </v-col>
    </v-row>

    <!-- Notes list -->
    <div v-if="notes.data.length > 0" class="d-flex flex-column ga-3">
      <NoteCard
        v-for="note in notes.data"
        :key="note.id"
        :note="note"
        :type="types[note.type]"
      />

      <!-- Pagination -->
      <div v-if="notes.last_page > 1" class="d-flex justify-center mt-4">
        <v-pagination
          :model-value="notes.current_page"
          :length="notes.last_page"
          :total-visible="5"
          density="compact"
          color="primary"
          @update:model-value="goToPage"
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
      <v-icon icon="mdi-text-box-remove-outline" size="48" color="primary" class="mb-3" />
      <p class="text-body-1 mb-4" style="color: var(--text-muted)">
        No voice notes found.
      </p>
      <v-btn
        color="primary"
        variant="outlined"
        prepend-icon="mdi-microphone"
        @click="router.visit('/notes/create')"
      >
        Create Recording
      </v-btn>
    </v-card>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import type { VoiceNote, MessageType, PaginatedData } from '../../Types'
import AppLayout from '../../Layouts/AppLayout.vue'
import NoteCard from '../../Components/NoteCard.vue'

const props = defineProps<{
  notes: PaginatedData<VoiceNote>
  types: Record<string, MessageType>
  filters: { type?: string; status?: string }
}>()

const selectedType = ref(props.filters.type ?? null)
const selectedStatus = ref(props.filters.status ?? null)

const typeOptions = Object.entries(props.types).map(([key, type]) => ({
  title: type.label,
  value: key,
}))

const statusOptions = [
  { title: 'Recorded', value: 'recorded' },
  { title: 'Processed', value: 'processed' },
  { title: 'Sent', value: 'sent' },
  { title: 'Error', value: 'error' },
]

function applyFilters() {
  const query: Record<string, string> = {}
  if (selectedType.value) query.type = selectedType.value
  if (selectedStatus.value) query.status = selectedStatus.value

  router.get('/notes', query, {
    preserveState: true,
    replace: true,
  })
}

watch(selectedType, () => applyFilters())
watch(selectedStatus, () => applyFilters())

function goToPage(page: number) {
  const query: Record<string, string> = { page: String(page) }
  if (selectedType.value) query.type = selectedType.value
  if (selectedStatus.value) query.status = selectedStatus.value

  router.get('/notes', query, {
    preserveState: true,
  })
}
</script>
