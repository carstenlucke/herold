<template>
  <v-app>
    <v-main class="d-flex align-center justify-center" style="min-height: 100vh">
      <v-card
        class="pa-8 neon-border-primary"
        max-width="380"
        width="100%"
        color="surface"
        rounded="xl"
      >
        <!-- Brand header -->
        <div class="text-center mb-6">
          <div class="brand-text text-h4 mb-1">Herold</div>
          <div class="brand-subtitle">TOTP SETUP</div>
        </div>

        <!-- Step indicator dots -->
        <div class="d-flex justify-center ga-2 mb-6">
          <div class="step-dot active" />
          <div class="step-dot active" />
        </div>

        <!-- Loading state: fetching QR code -->
        <div v-if="loading" class="text-center py-4">
          <v-progress-circular indeterminate color="primary" />
          <p class="text-body-2 mt-3" style="color: var(--text-muted)">
            Generating TOTP secret...
          </p>
        </div>

        <!-- Setup form -->
        <div v-else>
          <p class="text-body-2 text-center mb-4" style="color: var(--text-muted)">
            Scan this QR code with your authenticator app, then enter the verification code below.
          </p>

          <!-- QR code display -->
          <div v-if="provisioningUri" class="d-flex justify-center mb-4">
            <div class="pa-3 rounded-lg" style="background: #ffffff">
              <img
                :src="qrCodeUrl"
                alt="TOTP QR Code"
                width="200"
                height="200"
              />
            </div>
          </div>

          <!-- Manual secret -->
          <div v-if="secret" class="mb-4">
            <div class="text-caption font-label text-center" style="color: var(--text-muted)">
              Or enter this secret manually:
            </div>
            <div class="text-center mt-1">
              <code style="color: var(--neon-secondary); letter-spacing: 0.1em; font-size: 0.85rem">
                {{ secret }}
              </code>
            </div>
          </div>

          <!-- TOTP code input -->
          <v-text-field
            v-model="form.totp_code"
            label="Verification Code"
            variant="filled"
            color="primary"
            prepend-inner-icon="mdi-shield-check"
            maxlength="6"
            inputmode="numeric"
            :error-messages="form.errors.totp_code"
            :disabled="form.processing"
            @keyup.enter="confirm"
          />

          <v-btn
            block
            color="primary"
            variant="outlined"
            size="large"
            class="mt-4"
            :loading="form.processing"
            @click="confirm"
          >
            Verify & Login
          </v-btn>

          <v-btn
            block
            variant="text"
            size="small"
            class="mt-3"
            color="secondary"
            @click="router.visit('/login')"
          >
            Back to Login
          </v-btn>
        </div>

        <!-- Error -->
        <v-alert
          v-if="setupError"
          type="error"
          variant="tonal"
          class="mt-4"
          density="compact"
        >
          {{ setupError }}
        </v-alert>
      </v-card>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'

defineProps<{
  needs_setup?: boolean
}>()

const loading = ref(true)
const secret = ref('')
const provisioningUri = ref('')
const setupError = ref<string | null>(null)

const form = useForm({
  totp_code: '',
})

const qrCodeUrl = computed(() => {
  if (!provisioningUri.value) return ''
  // Use a QR code generation API or encode inline
  return `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(provisioningUri.value)}`
})

onMounted(async () => {
  try {
    const response = await fetch('/login/totp/setup', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
    })

    if (!response.ok) {
      throw new Error('Failed to set up TOTP')
    }

    const data = await response.json()
    secret.value = data.secret
    provisioningUri.value = data.provisioning_uri
  } catch (err) {
    setupError.value = 'Could not initialize TOTP setup. Please try again.'
  } finally {
    loading.value = false
  }
})

function getCsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

function confirm() {
  form.post('/login/totp/confirm', {
    preserveScroll: true,
  })
}
</script>
