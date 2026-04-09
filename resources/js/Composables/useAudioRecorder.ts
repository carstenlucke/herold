import { ref, readonly } from 'vue'

export function useAudioRecorder() {
  const isRecording = ref(false)
  const isPaused = ref(false)
  const duration = ref(0)
  const audioBlob = ref<Blob | null>(null)
  const error = ref<string | null>(null)

  let mediaRecorder: MediaRecorder | null = null
  let chunks: BlobPart[] = []
  let timerInterval: ReturnType<typeof setInterval> | null = null
  let stream: MediaStream | null = null

  function startTimer() {
    timerInterval = setInterval(() => {
      duration.value++
    }, 1000)
  }

  function stopTimer() {
    if (timerInterval) {
      clearInterval(timerInterval)
      timerInterval = null
    }
  }

  function pauseTimer() {
    stopTimer()
  }

  function resumeTimer() {
    startTimer()
  }

  function getMimeType(): string {
    if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
      return 'audio/webm;codecs=opus'
    }
    if (MediaRecorder.isTypeSupported('audio/webm')) {
      return 'audio/webm'
    }
    if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) {
      return 'audio/ogg;codecs=opus'
    }
    return ''
  }

  async function start() {
    error.value = null
    audioBlob.value = null
    duration.value = 0
    chunks = []

    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true })
    } catch (err) {
      error.value = 'Microphone access denied. Please allow microphone permissions and try again.'
      return
    }

    const mimeType = getMimeType()
    const options: MediaRecorderOptions = mimeType ? { mimeType } : {}

    try {
      mediaRecorder = new MediaRecorder(stream, options)
    } catch (err) {
      error.value = 'Could not initialize audio recorder.'
      stream.getTracks().forEach((t) => t.stop())
      stream = null
      return
    }

    mediaRecorder.ondataavailable = (event: BlobEvent) => {
      if (event.data.size > 0) {
        chunks.push(event.data)
      }
    }

    mediaRecorder.onstop = () => {
      const finalMime = mimeType || 'audio/webm'
      audioBlob.value = new Blob(chunks, { type: finalMime })
      chunks = []

      if (stream) {
        stream.getTracks().forEach((t) => t.stop())
        stream = null
      }
    }

    mediaRecorder.start(250)
    isRecording.value = true
    isPaused.value = false
    startTimer()
  }

  function stop() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
      mediaRecorder.stop()
    }
    stopTimer()
    isRecording.value = false
    isPaused.value = false
  }

  function pause() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
      mediaRecorder.pause()
      isPaused.value = true
      pauseTimer()
    }
  }

  function resume() {
    if (mediaRecorder && mediaRecorder.state === 'paused') {
      mediaRecorder.resume()
      isPaused.value = false
      resumeTimer()
    }
  }

  return {
    isRecording: readonly(isRecording),
    isPaused: readonly(isPaused),
    duration: readonly(duration),
    audioBlob: readonly(audioBlob),
    error: readonly(error),
    start,
    stop,
    pause,
    resume,
  }
}
