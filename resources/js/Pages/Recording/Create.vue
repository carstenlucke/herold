<template>
  <AppLayout>
    <!-- Page header -->
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold mb-1" style="color: var(--text-primary)">New Recording</h1>
      <p class="text-body-2" style="color: var(--text-muted)">
        Select a type, record your voice note, then save.
      </p>
    </div>

    <!-- Type selector -->
    <div class="mb-6">
      <div class="text-caption font-label mb-2" style="color: var(--text-muted)">Message Type</div>
      <TypeSelector
        v-model="selectedType"
        :types="types"
      />
    </div>

    <!-- Dynamic extra fields -->
    <div v-if="currentExtraFields.length > 0" class="mb-6">
      <template v-for="field in currentExtraFields" :key="field.name">
        <v-text-field
          v-if="field.type === 'url' || field.type === 'text'"
          v-model="extraFieldValues[field.name]"
          :label="field.label"
          :type="field.type === 'url' ? 'url' : 'text'"
          :rules="field.required ? [rules.required] : []"
          variant="filled"
          color="primary"
          class="mb-2"
        />
        <v-menu
          v-else-if="field.type === 'date'"
          v-model="dateMenus[field.name]"
          :close-on-content-click="false"
          location="bottom start"
        >
          <template #activator="{ props: menuProps }">
            <v-text-field
              v-bind="menuProps"
              :model-value="formatDate(extraFieldValues[field.name])"
              :label="field.label"
              variant="filled"
              color="primary"
              readonly
              prepend-inner-icon="mdi-calendar"
              class="mb-2"
              :rules="field.required ? [rules.required] : []"
            />
          </template>
          <v-date-picker
            :model-value="parseDateForPicker(extraFieldValues[field.name])"
            color="primary"
            @update:model-value="(d: Date) => onDateFieldPicked(field.name, d)"
          />
        </v-menu>
      </template>
    </div>

    <!-- Audio recorder -->
    <div class="py-6">
      <AudioRecorder @recorded="onRecorded" @discarded="onDiscarded" />
    </div>

    <!-- Save button -->
    <v-btn
      v-if="audioBlob"
      block
      color="primary"
      variant="outlined"
      size="large"
      prepend-icon="mdi-content-save"
      class="mt-4"
      :loading="form.processing"
      :disabled="!canSave"
      @click="save"
    >
      Save Voice Note
    </v-btn>

    <!-- Validation error -->
    <v-alert
      v-if="form.errors.audio || form.errors.type"
      type="error"
      variant="tonal"
      density="compact"
      class="mt-4"
    >
      {{ form.errors.audio || form.errors.type }}
    </v-alert>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import type { MessageType } from '../../Types'
import AppLayout from '../../Layouts/AppLayout.vue'
import TypeSelector from '../../Components/TypeSelector.vue'
import AudioRecorder from '../../Components/AudioRecorder.vue'

const props = defineProps<{
  types: Record<string, MessageType>
}>()

const selectedType = ref(Object.keys(props.types)[0] ?? 'general')
const audioBlob = ref<Blob | null>(null)
const extraFieldValues = ref<Record<string, string>>({})
const dateMenus = ref<Record<string, boolean>>({})

// Prune stale metadata keys when type changes
watch(selectedType, () => {
  const allowedKeys = new Set(currentExtraFields.value.map(f => f.name))
  const pruned: Record<string, string> = {}
  for (const key of Object.keys(extraFieldValues.value)) {
    if (allowedKeys.has(key)) {
      pruned[key] = extraFieldValues.value[key]
    }
  }
  extraFieldValues.value = pruned
})

const form = useForm({
  type: '',
  audio: null as File | null,
  metadata: {} as Record<string, string>,
})

const currentExtraFields = computed(() => {
  const typeConfig = props.types[selectedType.value]
  return typeConfig?.extra_fields ?? []
})

const canSave = computed(() => {
  if (!audioBlob.value) return false

  for (const field of currentExtraFields.value) {
    if (field.required && !extraFieldValues.value[field.name]) {
      return false
    }
  }

  return true
})

const rules = {
  required: (v: string) => !!v || 'This field is required',
}

function formatDate(iso?: string): string {
  if (!iso) return ''
  const d = new Date(iso + 'T00:00:00')
  return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function parseDateForPicker(iso?: string): Date | undefined {
  if (!iso) return undefined
  return new Date(iso + 'T00:00:00')
}

function onDateFieldPicked(fieldName: string, date: Date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  extraFieldValues.value[fieldName] = `${y}-${m}-${d}`
  dateMenus.value[fieldName] = false
}

function onRecorded(blob: Blob) {
  audioBlob.value = blob
}

function onDiscarded() {
  audioBlob.value = null
}

function save() {
  if (!audioBlob.value) return

  form.type = selectedType.value
  // Normalize MIME type — strip codec suffix (e.g. "audio/webm;codecs=opus" → "audio/webm")
  const mimeType = audioBlob.value.type.split(';')[0] || 'audio/webm'
  form.audio = new File([audioBlob.value], 'recording.webm', { type: mimeType })
  form.metadata = { ...extraFieldValues.value }

  form.post('/notes', {
    forceFormData: true,
  })
}
</script>
