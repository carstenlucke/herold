<template>
  <AppLayout>
    <!-- Back button -->
    <v-btn
      variant="text"
      color="secondary"
      prepend-icon="mdi-arrow-left"
      size="small"
      class="mb-4"
      @click="router.visit('/notes')"
    >
      Back to Notes
    </v-btn>

    <!-- Header with status -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h5 font-weight-bold" style="color: var(--text-primary)">
          {{ note.processed_title || `Voice Note` }}
        </h1>
        <div class="d-flex align-center ga-2 mt-1">
          <NoteStatusBadge :status="note.status" />
          <v-chip size="small" variant="tonal" :prepend-icon="typeConfig?.icon">
            {{ typeConfig?.label ?? note.type }}
          </v-chip>
          <span class="text-caption" style="color: var(--text-muted)">{{ formattedDate }}</span>
        </div>
      </div>
    </div>

    <!-- Error alert -->
    <v-alert
      v-if="note.status === 'error' && note.error_message"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      <div class="font-weight-medium mb-1">Processing Error</div>
      {{ note.error_message }}
    </v-alert>

    <!-- Processing error from composable -->
    <v-alert
      v-if="processingError"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="clearError"
    >
      {{ processingError }}
    </v-alert>

    <!-- Stepper -->
    <div class="d-flex flex-column ga-4">
      <!-- Step 1: Recorded -->
      <v-card
        color="surface"
        rounded="lg"
        :class="stepClass(1)"
      >
        <v-card-text class="pa-4">
          <div class="d-flex align-center ga-2 mb-3">
            <v-avatar
              :color="stepReached(1) ? 'warning' : 'grey'"
              variant="tonal"
              size="28"
            >
              <span class="text-caption font-weight-bold">1</span>
            </v-avatar>
            <span class="text-body-1 font-weight-medium" style="color: var(--text-primary)">
              Recorded
            </span>
            <v-icon
              v-if="stepReached(1)"
              icon="mdi-check"
              size="16"
              color="warning"
            />
          </div>

          <div class="text-body-2" style="color: var(--text-muted)">
            <div v-if="note.audio_path" class="d-flex align-center ga-2">
              <v-icon icon="mdi-waveform" size="16" />
              <span>Audio file recorded</span>
            </div>
            <div v-if="note.metadata && Object.keys(note.metadata).length > 0" class="mt-2">
              <div v-for="(value, key) in note.metadata" :key="key">
                <span class="font-label text-caption">{{ key }}:</span> {{ value }}
              </div>
            </div>
          </div>
        </v-card-text>
      </v-card>

      <!-- Step 2: Processed -->
      <v-card
        color="surface"
        rounded="lg"
        :class="stepClass(2)"
      >
        <v-card-text class="pa-4">
          <div class="d-flex align-center ga-2 mb-3">
            <v-avatar
              :color="stepReached(2) ? 'secondary' : 'grey'"
              variant="tonal"
              size="28"
            >
              <span class="text-caption font-weight-bold">2</span>
            </v-avatar>
            <span class="text-body-1 font-weight-medium" style="color: var(--text-primary)">
              Processed
            </span>
            <v-icon
              v-if="stepReached(2)"
              icon="mdi-check"
              size="16"
              color="secondary"
            />
          </div>

          <template v-if="stepReached(2) || isEditing">
            <!-- Transcript (always read-only) -->
            <div class="mb-3">
              <TranscriptEditor :model-value="props.note.transcript ?? ''" />
            </div>

            <!-- Title -->
            <v-text-field
              v-model="editForm.processed_title"
              label="Title"
              variant="filled"
              color="primary"
              :readonly="!isEditing"
              class="mb-3"
            />

            <!-- Body -->
            <v-textarea
              v-model="editForm.processed_body"
              label="Body"
              variant="filled"
              color="primary"
              rows="5"
              auto-grow
              :readonly="!isEditing"
            />

            <!-- Edit actions -->
            <div class="d-flex ga-2 mt-3">
              <v-btn
                v-if="!isEditing"
                variant="tonal"
                color="secondary"
                size="small"
                prepend-icon="mdi-pencil"
                @click="startEditing"
              >
                Edit
              </v-btn>
              <template v-if="isEditing">
                <v-btn
                  variant="tonal"
                  color="primary"
                  size="small"
                  prepend-icon="mdi-content-save"
                  :loading="editForm.processing"
                  @click="saveEdits"
                >
                  Save
                </v-btn>
                <v-btn
                  v-if="props.note.transcript && editForm.processed_body !== props.note.transcript"
                  variant="tonal"
                  color="warning"
                  size="small"
                  prepend-icon="mdi-restore"
                  @click="editForm.processed_body = props.note.transcript"
                >
                  Reset to Transcript
                </v-btn>
                <v-btn
                  variant="text"
                  size="small"
                  @click="cancelEditing"
                >
                  Cancel
                </v-btn>
              </template>
            </div>
          </template>

          <!-- Process button (shown when in recorded state) -->
          <v-btn
            v-if="note.status === 'recorded' || (note.status === 'error' && !stepReached(2))"
            color="secondary"
            variant="outlined"
            prepend-icon="mdi-cog"
            class="mt-3"
            :loading="isProcessing"
            @click="handleProcess"
          >
            Process
          </v-btn>
        </v-card-text>
      </v-card>

      <!-- Step 3: Sent -->
      <v-card
        color="surface"
        rounded="lg"
        :class="stepClass(3)"
      >
        <v-card-text class="pa-4">
          <div class="d-flex align-center ga-2 mb-3">
            <v-avatar
              :color="stepReached(3) ? 'primary' : 'grey'"
              variant="tonal"
              size="28"
            >
              <span class="text-caption font-weight-bold">3</span>
            </v-avatar>
            <span class="text-body-1 font-weight-medium" style="color: var(--text-primary)">
              Sent to GitHub
            </span>
            <v-icon
              v-if="stepReached(3)"
              icon="mdi-check"
              size="16"
              color="primary"
            />
          </div>

          <template v-if="stepReached(3)">
            <div class="d-flex align-center ga-2" style="margin-left: 36px">
              <v-icon icon="mdi-github" size="18" />
              <a
                :href="note.github_issue_url ?? '#'"
                target="_blank"
                rel="noopener"
                class="text-primary text-body-2"
              >
                Issue #{{ note.github_issue_number }}
              </a>
            </div>
          </template>

          <!-- Create Ticket button (shown when in processed state) -->
          <v-btn
            v-if="note.status === 'processed' || (note.status === 'error' && stepReached(2) && !stepReached(3))"
            color="primary"
            variant="outlined"
            prepend-icon="mdi-send"
            class="mt-3"
            :loading="isProcessing"
            @click="handleSend"
          >
            Create Ticket
          </v-btn>
        </v-card-text>
      </v-card>
    </div>

    <!-- Delete button -->
    <div class="mt-8 d-flex justify-end">
      <v-btn
        variant="tonal"
        color="error"
        size="small"
        prepend-icon="mdi-delete"
        :loading="deleteLoading"
        @click="showDeleteDialog = true"
      >
        Delete Note
      </v-btn>
    </div>

    <!-- Delete confirmation dialog -->
    <v-dialog v-model="showDeleteDialog" max-width="400">
      <v-card color="surface" rounded="lg" class="neon-border-primary">
        <v-card-title class="text-h6">Delete Voice Note</v-card-title>
        <v-card-text style="color: var(--text-muted)">
          Are you sure you want to delete this voice note? This action cannot be undone.
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showDeleteDialog = false">Cancel</v-btn>
          <v-btn
            color="error"
            variant="tonal"
            :loading="deleteLoading"
            @click="handleDelete"
          >
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import type { VoiceNote, MessageType } from '../../Types'
import { useProcessing } from '../../Composables/useProcessing'
import AppLayout from '../../Layouts/AppLayout.vue'
import NoteStatusBadge from '../../Components/NoteStatusBadge.vue'
import TranscriptEditor from '../../Components/TranscriptEditor.vue'

const props = defineProps<{
  note: VoiceNote
  types: Record<string, MessageType>
}>()

const { isProcessing, error: processingError, process, send, clearError } = useProcessing()

const typeConfig = computed(() => props.types[props.note.type])

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

// Stepper logic
const statusOrder = ['recorded', 'processed', 'sent'] as const

function stepReached(step: number): boolean {
  const statusIndex = statusOrder.indexOf(props.note.status as typeof statusOrder[number])
  if (statusIndex === -1) {
    // Error state: check what was reached before error based on data
    if (step === 1) return true
    if (step === 2) return !!props.note.processed_title || !!props.note.transcript
    if (step === 3) return !!props.note.github_issue_number
    return false
  }
  return statusIndex >= step - 1
}

function stepClass(step: number): string {
  if (stepReached(step)) return 'neon-border-secondary'
  return 'neon-border-primary'
}

// Editing
const isEditing = ref(false)
const editForm = useForm({
  processed_title: props.note.processed_title ?? '',
  processed_body: props.note.processed_body ?? '',
})

// Sync form when Inertia delivers updated note props (e.g. after processing)
watch(() => props.note, (note) => {
  if (!isEditing.value) {
    editForm.processed_title = note.processed_title ?? ''
    editForm.processed_body = note.processed_body ?? ''
  }
}, { deep: true })

function startEditing() {
  editForm.processed_title = props.note.processed_title ?? ''
  editForm.processed_body = props.note.processed_body ?? ''
  isEditing.value = true
}

function cancelEditing() {
  isEditing.value = false
  editForm.reset()
}

function saveEdits() {
  editForm.put(`/notes/${props.note.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      isEditing.value = false
    },
  })
}

// Processing actions
function handleProcess() {
  process(props.note.id)
}

function handleSend() {
  send(props.note.id)
}

// Delete
const showDeleteDialog = ref(false)
const deleteLoading = ref(false)

function handleDelete() {
  deleteLoading.value = true
  router.delete(`/notes/${props.note.id}`, {
    onFinish: () => {
      deleteLoading.value = false
      showDeleteDialog.value = false
    },
  })
}
</script>
