import { ref, readonly } from 'vue'
import { router } from '@inertiajs/vue3'

export function useProcessing() {
  const isProcessing = ref(false)
  const error = ref<string | null>(null)

  function process(noteId: string): Promise<void> {
    return new Promise((resolve, reject) => {
      isProcessing.value = true
      error.value = null

      router.post(
        `/notes/${noteId}/process`,
        {},
        {
          preserveScroll: true,
          onSuccess: () => {
            isProcessing.value = false
            resolve()
          },
          onError: (errors) => {
            isProcessing.value = false
            error.value = Object.values(errors).flat().join(', ') || 'Processing failed.'
            reject(new Error(error.value!))
          },
          onFinish: () => {
            isProcessing.value = false
          },
        },
      )
    })
  }

  function send(noteId: string): Promise<void> {
    return new Promise((resolve, reject) => {
      isProcessing.value = true
      error.value = null

      router.post(
        `/notes/${noteId}/send`,
        {},
        {
          preserveScroll: true,
          onSuccess: () => {
            isProcessing.value = false
            resolve()
          },
          onError: (errors) => {
            isProcessing.value = false
            error.value = Object.values(errors).flat().join(', ') || 'Sending failed.'
            reject(new Error(error.value!))
          },
          onFinish: () => {
            isProcessing.value = false
          },
        },
      )
    })
  }

  function clearError() {
    error.value = null
  }

  return {
    isProcessing: readonly(isProcessing),
    error: readonly(error),
    process,
    send,
    clearError,
  }
}
