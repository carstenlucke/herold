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
        <div class="text-center mb-8">
          <img :src="iconSrc" alt="Herold" style="height: 48px; width: auto; display: block; margin: 0 auto 16px" />
          <div class="brand-text text-h4 mb-1">Herold</div>
          <div class="brand-subtitle">VOICE DISPATCH SYSTEM</div>
        </div>

        <!-- Step indicator dots -->
        <div class="d-flex justify-center ga-2 mb-6">
          <div class="step-dot" :class="{ active: true }" />
          <div class="step-dot" :class="{ active: step !== 'key' }" />
        </div>

        <!-- Step 1: API Key -->
        <form v-if="step === 'key'" @submit.prevent="submitKey">
          <input type="text" autocomplete="username" value="herold" hidden aria-hidden="true" />
          <v-text-field
            v-model="keyForm.api_key"
            label="API Key"
            type="password"
            autocomplete="current-password"
            variant="filled"
            color="primary"
            prepend-inner-icon="mdi-key"
            :error-messages="keyForm.errors.api_key"
            :disabled="keyForm.processing"
          />

          <v-btn
            block
            color="primary"
            variant="outlined"
            size="large"
            class="mt-4"
            type="submit"
            :loading="keyForm.processing"
          >
            Verify
          </v-btn>
        </form>

        <!-- Step 2: TOTP -->
        <form v-if="step === 'totp'" @submit.prevent="submitTotp">
          <p class="text-body-2 text-center mb-4" style="color: var(--text-muted)">
            Enter the 6-digit code from your authenticator app.
          </p>

          <v-text-field
            v-model="totpForm.totp_code"
            label="TOTP Code"
            variant="filled"
            color="primary"
            prepend-inner-icon="mdi-shield-lock"
            maxlength="6"
            inputmode="numeric"
            :error-messages="totpForm.errors.totp_code"
            :disabled="totpForm.processing"
          />

          <v-btn
            block
            color="primary"
            variant="outlined"
            size="large"
            class="mt-4"
            type="submit"
            :loading="totpForm.processing"
          >
            Login
          </v-btn>

          <v-btn
            block
            variant="text"
            size="small"
            class="mt-3"
            color="secondary"
            @click="goBack"
          >
            Back
          </v-btn>
        </form>

        <!-- Step 2b: TOTP Setup (first time) -->
        <form v-if="step === 'totp_setup'" @submit.prevent="submitTotp">
          <p class="text-body-2 text-center mb-4" style="color: var(--text-muted)">
            Scan this QR code with your authenticator app, then enter the code below.
          </p>

          <div v-if="qrDataUrl" class="d-flex justify-center mb-4">
            <div class="pa-3 rounded-lg" style="background: #ffffff">
              <img :src="qrDataUrl" alt="TOTP QR Code" width="200" height="200" />
            </div>
          </div>

          <v-text-field
            v-model="totpForm.totp_code"
            label="Verification Code"
            variant="filled"
            color="primary"
            prepend-inner-icon="mdi-shield-check"
            maxlength="6"
            inputmode="numeric"
            :error-messages="totpForm.errors.totp_code"
            :disabled="totpForm.processing"
          />

          <v-btn
            block
            color="primary"
            variant="outlined"
            size="large"
            class="mt-4"
            type="submit"
            :loading="totpForm.processing"
          >
            Verify & Login
          </v-btn>

          <v-btn
            block
            variant="text"
            size="small"
            class="mt-3"
            color="secondary"
            @click="goBack"
          >
            Back
          </v-btn>
        </form>

        <!-- General error -->
        <v-alert
          v-if="generalError"
          type="error"
          variant="tonal"
          class="mt-4"
          density="compact"
        >
          {{ generalError }}
        </v-alert>
      </v-card>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import QRCode from 'qrcode'

const props = defineProps<{
  step?: string
  needsSetup?: boolean
  provisioningUri?: string | null
}>()

const page = usePage()

const iconSrc = '/images/herold-icon.png'

const step = ref(props.step ?? 'key')
const provisioningUri = ref(props.provisioningUri ?? null)
const qrDataUrl = ref<string | null>(null)

// Sync local refs when Inertia delivers new props after redirect
watch(() => props.step, (newStep) => {
  if (newStep) step.value = newStep
})
watch(() => props.provisioningUri, (newUri) => {
  provisioningUri.value = newUri ?? null
})

const keyForm = useForm({
  api_key: '',
})

const totpForm = useForm({
  totp_code: '',
})

const generalError = computed(() => {
  const errors = page.props.errors as Record<string, string> | undefined
  return errors?.general ?? null
})

async function generateQrCode(uri: string) {
  qrDataUrl.value = await QRCode.toDataURL(uri, { width: 200, margin: 0 })
}

watch(provisioningUri, (uri) => {
  if (uri) generateQrCode(uri)
}, { immediate: true })

function submitKey() {
  keyForm.post('/login/key', {
    preserveScroll: true,
  })
}

function submitTotp() {
  const url = step.value === 'totp_setup' ? '/login/totp/confirm' : '/login/totp'
  totpForm.post(url, {
    preserveScroll: true,
  })
}

function goBack() {
  step.value = 'key'
  keyForm.reset()
  totpForm.reset()
  provisioningUri.value = null
  qrDataUrl.value = null
}
</script>
