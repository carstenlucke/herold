<template>
  <v-app>
    <v-main class="d-flex align-center justify-center" style="min-height: 100vh">
      <v-card
        class="pa-8 neon-border-secondary"
        max-width="420"
        width="100%"
        color="surface"
        rounded="xl"
      >
        <!-- Brand header -->
        <div class="text-center mb-6">
          <div class="brand-text text-h4 mb-1">Herold</div>
          <div class="brand-subtitle">ACCOUNT RECOVERED</div>
        </div>

        <v-alert
          type="success"
          variant="tonal"
          class="mb-6"
          density="compact"
        >
          Your account has been successfully recovered.
        </v-alert>

        <!-- New API Key -->
        <div class="mb-6">
          <div class="text-caption font-label mb-2" style="color: var(--text-muted)">
            New API Key
          </div>
          <v-card
            color="surface-variant"
            rounded="lg"
            class="pa-3"
          >
            <code
              class="text-body-2 d-block"
              style="word-break: break-all; color: var(--neon-secondary)"
            >
              {{ apiKey }}
            </code>
          </v-card>
          <div class="d-flex align-center ga-2 mt-2">
            <v-btn
              size="small"
              variant="tonal"
              color="secondary"
              prepend-icon="mdi-content-copy"
              @click="copyKey"
            >
              {{ copied ? 'Copied!' : 'Copy' }}
            </v-btn>
          </div>
        </div>

        <v-alert
          type="warning"
          variant="tonal"
          class="mb-6"
          density="compact"
        >
          Save this API key now. It will not be shown again. You will also need to set up TOTP again on your next login.
        </v-alert>

        <v-btn
          block
          color="primary"
          variant="outlined"
          size="large"
          prepend-icon="mdi-login"
          @click="router.visit('/login')"
        >
          Go to Login
        </v-btn>
      </v-card>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps<{
  apiKey: string
}>()

const copied = ref(false)

async function copyKey() {
  try {
    await navigator.clipboard.writeText(props.apiKey)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch {
    // Fallback for insecure contexts
    const textarea = document.createElement('textarea')
    textarea.value = props.apiKey
    document.body.appendChild(textarea)
    textarea.select()
    document.execCommand('copy')
    document.body.removeChild(textarea)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  }
}
</script>
