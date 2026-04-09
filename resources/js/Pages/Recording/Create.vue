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
      </template>
    </div>

    <!-- Audio recorder -->
    <div class="py-6">
      <AudioRecorder @recorded="onRecorded" />
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
import { ref, computed } from 'vue'
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

function onRecorded(blob: Blob) {
  audioBlob.value = blob
}

function save() {
  if (!audioBlob.value) return

  const formData = new FormData()
  formData.append('type', selectedType.value)
  formData.append('audio', new File([audioBlob.value], 'recording.webm', { type: audioBlob.value.type }))

  if (Object.keys(extraFieldValues.value).length > 0) {
    for (const [key, value] of Object.entries(extraFieldValues.value)) {
      formData.append(`metadata[${key}]`, value)
    }
  }

  form.type = selectedType.value
  form.audio = new File([audioBlob.value], 'recording.webm', { type: audioBlob.value.type })
  form.metadata = { ...extraFieldValues.value }

  form.post('/notes', {
    forceFormData: true,
  })
}
</script>
