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
          <div class="step-dot active" />
          <div class="step-dot active" />
        </div>

        <p class="text-body-2 text-center mb-4" style="color: var(--text-muted)">
          Enter the 6-digit code from your authenticator app.
        </p>

        <v-text-field
          v-model="form.totp_code"
          label="TOTP Code"
          variant="filled"
          color="primary"
          prepend-inner-icon="mdi-shield-lock"
          maxlength="6"
          inputmode="numeric"
          :error-messages="form.errors.totp_code"
          :disabled="form.processing"
          @keyup.enter="submit"
        />

        <v-btn
          block
          color="primary"
          variant="outlined"
          size="large"
          class="mt-4"
          :loading="form.processing"
          @click="submit"
        >
          Login
        </v-btn>

        <v-btn
          block
          variant="text"
          size="small"
          class="mt-3"
          color="secondary"
          @click="router.visit('/login')"
        >
          Back
        </v-btn>

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
import { computed } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'

const page = usePage()

const form = useForm({
  totp_code: '',
})

const generalError = computed(() => {
  const errors = page.props.errors as Record<string, string> | undefined
  return errors?.general ?? null
})

function submit() {
  form.post('/login/totp', {
    preserveScroll: true,
  })
}
</script>
