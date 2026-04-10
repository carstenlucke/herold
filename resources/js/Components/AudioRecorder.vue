<template>
  <div class="d-flex flex-column align-center ga-4">
    <!-- Review state: playback after recording -->
    <template v-if="audioBlob && !isRecording">
      <!-- Audio player -->
      <audio ref="audioPlayer" :src="audioUrl" @ended="isPlaying = false" @timeupdate="onTimeUpdate" />

      <!-- Playback progress bar -->
      <div class="d-flex align-center ga-2" style="width: 240px">
        <div
          style="flex: 1; height: 4px; border-radius: 2px; background: var(--surface-3); cursor: pointer; position: relative"
          @click="seekAudio"
        >
          <div
            style="height: 100%; border-radius: 2px; background: var(--neon-primary); transition: width 0.1s"
            :style="{ width: playbackProgress + '%' }"
          />
        </div>
      </div>

      <!-- Playback timer -->
      <div class="font-mono text-h5" style="color: var(--text-primary); letter-spacing: 0.1em">
        {{ formattedPlayback }} / {{ formattedDuration }}
      </div>

      <!-- Playback controls -->
      <div class="d-flex align-center ga-4">
        <!-- Discard / re-record -->
        <v-btn
          icon
          variant="tonal"
          color="error"
          size="48"
          @click="discard"
        >
          <v-icon icon="mdi-delete" size="24" />
        </v-btn>

        <!-- Play / Pause -->
        <button
          class="record-button"
          @click="togglePlayback"
        >
          <v-icon
            :icon="isPlaying ? 'mdi-pause' : 'mdi-play'"
            :size="36"
            color="white"
          />
        </button>

        <!-- Spacer to keep play button centered -->
        <div style="width: 48px" />
      </div>
    </template>

    <!-- Recording / idle state -->
    <template v-else>
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
    </template>

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
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useAudioRecorder } from '../Composables/useAudioRecorder'

const emit = defineEmits<{
  recorded: [blob: Blob]
  discarded: []
}>()

const { isRecording, isPaused, duration, audioBlob, error, start, stop, pause, resume, reset } =
  useAudioRecorder()

const audioPlayer = ref<HTMLAudioElement | null>(null)
const isPlaying = ref(false)
const playbackTime = ref(0)
const playbackProgress = ref(0)

const audioUrl = computed(() => {
  if (audioBlob.value) {
    return URL.createObjectURL(audioBlob.value)
  }
  return ''
})

const formattedDuration = computed(() => {
  const mins = Math.floor(duration.value / 60)
  const secs = duration.value % 60
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
})

const formattedPlayback = computed(() => {
  const mins = Math.floor(playbackTime.value / 60)
  const secs = Math.floor(playbackTime.value % 60)
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
})

function handleMainButton() {
  if (isRecording.value) {
    stop()
  } else {
    start()
  }
}

function togglePlayback() {
  const player = audioPlayer.value
  if (!player) return

  if (isPlaying.value) {
    player.pause()
    isPlaying.value = false
  } else {
    player.play()
    isPlaying.value = true
  }
}

function onTimeUpdate() {
  const player = audioPlayer.value
  if (!player) return
  playbackTime.value = player.currentTime
  playbackProgress.value = player.duration ? (player.currentTime / player.duration) * 100 : 0
}

function seekAudio(event: MouseEvent) {
  const player = audioPlayer.value
  if (!player || !player.duration) return
  const target = event.currentTarget as HTMLElement
  const rect = target.getBoundingClientRect()
  const ratio = (event.clientX - rect.left) / rect.width
  player.currentTime = ratio * player.duration
}

function discard() {
  const player = audioPlayer.value
  if (player) {
    player.pause()
  }
  isPlaying.value = false
  playbackTime.value = 0
  playbackProgress.value = 0
  reset()
  emit('discarded')
}

watch(audioBlob, (blob) => {
  if (blob) {
    emit('recorded', blob)
  }
})

// Clean up object URL on unmount
onBeforeUnmount(() => {
  if (audioUrl.value) {
    URL.revokeObjectURL(audioUrl.value)
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
