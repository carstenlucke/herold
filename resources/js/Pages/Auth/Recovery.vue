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
          <div class="brand-subtitle">ACCOUNT RECOVERY</div>
        </div>

        <!-- Token input -->
        <v-text-field
          v-model="form.token"
          label="Recovery Token"
          variant="filled"
          color="primary"
          prepend-inner-icon="mdi-key-chain"
          :error-messages="form.errors.token"
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
          Recover Account
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
      </v-card>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3'

const form = useForm({
  token: '',
})

function submit() {
  form.post('/recovery', {
    preserveScroll: true,
  })
}
</script>
