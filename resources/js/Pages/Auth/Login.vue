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
          <div class="brand-text text-h4 mb-1">Herold</div>
          <div class="brand-subtitle">VOICE DISPATCH SYSTEM</div>
        </div>

        <!-- Step indicator dots -->
        <div class="d-flex justify-center ga-2 mb-6">
          <div class="step-dot" :class="{ active: step >= 1 }" />
          <div class="step-dot" :class="{ active: step >= 2 }" />
        </div>

        <!-- Step 1: API Key -->
        <div v-if="step === 1">
          <v-text-field
            v-model="keyForm.api_key"
            label="API Key"
            type="password"
            variant="filled"
            color="primary"
            prepend-inner-icon="mdi-key"
            :error-messages="keyForm.errors.api_key"
            :disabled="keyForm.processing"
            @keyup.enter="submitKey"
          />

          <v-btn
            block
            color="primary"
            variant="outlined"
            size="large"
            class="mt-4"
            :loading="keyForm.processing"
            @click="submitKey"
          >
            Verify
          </v-btn>
        </div>

        <!-- Step 2: TOTP -->
        <div v-if="step === 2 && !showTotpSetup">
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
            @keyup.enter="submitTotp"
          />

          <v-btn
            block
            color="primary"
            variant="outlined"
            size="large"
            class="mt-4"
            :loading="totpForm.processing"
            @click="submitTotp"
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
        </div>

        <!-- Step 2b: TOTP Setup (first time) -->
        <div v-if="step === 2 && showTotpSetup">
          <p class="text-body-2 text-center mb-4" style="color: var(--text-muted)">
            Scan this QR code with your authenticator app, then enter the code below.
          </p>

          <div v-if="qrCodeSvg" class="d-flex justify-center mb-4">
            <div
              class="pa-3 rounded-lg"
              style="background: #ffffff"
              v-html="qrCodeSvg"
            />
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
            @keyup.enter="submitTotp"
          />

          <v-btn
            block
            color="primary"
            variant="outlined"
            size="large"
            class="mt-4"
            :loading="totpForm.processing"
            @click="submitTotp"
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
        </div>

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
import { ref, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

const props = defineProps<{
  step?: number
  showTotpSetup?: boolean
  qrCodeSvg?: string | null
}>()

const page = usePage()

const step = ref(props.step ?? 1)
const showTotpSetup = ref(props.showTotpSetup ?? false)
const qrCodeSvg = ref(props.qrCodeSvg ?? null)

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

function submitKey() {
  keyForm.post('/login/key', {
    preserveScroll: true,
    onSuccess: (response: any) => {
      step.value = 2
      const pageProps = usePage().props as Record<string, any>
      if (pageProps.showTotpSetup) {
        showTotpSetup.value = true
        qrCodeSvg.value = pageProps.qrCodeSvg ?? null
      }
    },
  })
}

function submitTotp() {
  totpForm.post('/login/totp', {
    preserveScroll: true,
  })
}

function goBack() {
  step.value = 1
  keyForm.reset()
  totpForm.reset()
  showTotpSetup.value = false
  qrCodeSvg.value = null
}
</script>
