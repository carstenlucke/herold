<template>
  <div class="d-flex flex-column align-center ga-4">
    <!-- Waveform visualization -->
    <div class="d-flex align-center justify-center ga-1" style="height: 40px">
      <template v-if="isRecording">
        <div
          v-for="i in 7"
          :key="i"
          class="waveform-bar"
          :class="{ 'waveform-bar-paused': isPaused }"
        />
      </template>
      <template v-else>
        <div
          v-for="i in 7"
          :key="i"
          style="width: 3px; height: 8px; border-radius: 2px; background: var(--surface-3)"
        />
      </template>
    </div>

    <!-- Timer -->
    <div class="font-mono text-h5" style="color: var(--text-primary); letter-spacing: 0.1em">
      {{ formattedDuration }}
    </div>

    <!-- Controls -->
    <div class="d-flex align-center ga-4">
      <!-- Pause / Resume (visible during recording) -->
      <v-btn
        v-if="isRecording"
        icon
        variant="tonal"
        color="secondary"
        size="48"
        @click="isPaused ? resume() : pause()"
      >
        <v-icon :icon="isPaused ? 'mdi-play' : 'mdi-pause'" size="24" />
      </v-btn>

      <!-- Main record / stop button -->
      <button
        class="record-button"
        :class="{
          'record-button--recording': isRecording && !isPaused,
          'record-button--paused': isRecording && isPaused,
        }"
        @click="handleMainButton"
      >
        <v-icon
          :icon="isRecording ? 'mdi-stop' : 'mdi-microphone'"
          :size="isRecording ? 32 : 36"
          color="white"
        />
      </button>

      <!-- Spacer to keep main button centered -->
      <div v-if="isRecording" style="width: 48px" />
    </div>

    <!-- Error message -->
    <v-alert
      v-if="error"
      type="error"
      variant="tonal"
      density="compact"
      class="mt-2"
      max-width="360"
    >
      {{ error }}
    </v-alert>
  </div>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { useAudioRecorder } from '../Composables/useAudioRecorder'

const emit = defineEmits<{
  recorded: [blob: Blob]
}>()

const { isRecording, isPaused, duration, audioBlob, error, start, stop, pause, resume } =
  useAudioRecorder()

const formattedDuration = computed(() => {
  const mins = Math.floor(duration.value / 60)
  const secs = duration.value % 60
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
})

function handleMainButton() {
  if (isRecording.value) {
    stop()
  } else {
    start()
  }
}

watch(audioBlob, (blob) => {
  if (blob) {
    emit('recorded', blob)
  }
})
</script>

<style scoped>
.record-button {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  border: 3px solid var(--neon-primary);
  background: rgba(255, 45, 120, 0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.25s ease;
  outline: none;
}

.record-button:hover {
  background: rgba(255, 45, 120, 0.25);
  box-shadow: 0 0 24px rgba(255, 45, 120, 0.4);
}

.record-button--recording {
  border-color: #ff4444;
  background: rgba(255, 68, 68, 0.2);
  animation: pulse-record 1.5s ease-in-out infinite;
}

.record-button--paused {
  border-color: var(--neon-secondary);
  background: rgba(0, 255, 204, 0.1);
  animation: none;
}
</style>
