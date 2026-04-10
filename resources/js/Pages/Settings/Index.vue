<template>
  <AppLayout>
    <!-- Page header -->
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold mb-1" style="color: var(--text-primary)">Settings</h1>
      <p class="text-body-2" style="color: var(--text-muted)">
        Configuration and account management.
      </p>
    </div>

    <div class="d-flex flex-column ga-4">
      <!-- GitHub section -->
      <v-card color="surface" rounded="lg" class="neon-border-primary">
        <v-card-text class="pa-4">
          <div class="d-flex align-center ga-2 mb-3">
            <v-icon icon="mdi-github" size="22" color="secondary" />
            <span class="text-body-1 font-weight-medium" style="color: var(--text-primary)">GitHub</span>
          </div>
          <div class="d-flex flex-column ga-2">
            <div class="d-flex align-center ga-2">
              <span class="text-caption font-label" style="color: var(--text-muted); min-width: 60px">Owner:</span>
              <code class="text-body-2" style="color: var(--text-primary)">{{ maskedOwner }}</code>
            </div>
            <div class="d-flex align-center ga-2">
              <span class="text-caption font-label" style="color: var(--text-muted); min-width: 60px">Repo:</span>
              <code class="text-body-2" style="color: var(--text-primary)">{{ maskedRepo }}</code>
            </div>
          </div>
        </v-card-text>
      </v-card>

      <!-- Authentication section -->
      <v-card color="surface" rounded="lg" class="neon-border-primary">
        <v-card-text class="pa-4">
          <div class="d-flex align-center ga-2 mb-3">
            <v-icon icon="mdi-shield-lock" size="22" color="primary" />
            <span class="text-body-1 font-weight-medium" style="color: var(--text-primary)">Authentication</span>
          </div>
          <div class="d-flex flex-column ga-2">
            <div class="d-flex align-center ga-2">
              <v-icon
                :icon="totp.confirmed ? 'mdi-check-circle' : 'mdi-close-circle'"
                :color="totp.confirmed ? 'secondary' : 'error'"
                size="16"
              />
              <span class="text-body-2" style="color: var(--text-primary)">
                TOTP {{ totp.confirmed ? 'Confirmed' : 'Not Configured' }}
              </span>
            </div>
            <div class="d-flex align-center ga-2">
              <v-icon icon="mdi-check-circle" color="secondary" size="16" />
              <span class="text-body-2" style="color: var(--text-primary)">
                API Key Active
              </span>
            </div>
          </div>
        </v-card-text>
      </v-card>

      <!-- About section -->
      <v-card color="surface" rounded="lg" class="neon-border-primary">
        <v-card-text class="pa-4">
          <div class="d-flex align-center ga-2 mb-3">
            <v-icon icon="mdi-information" size="22" color="warning" />
            <span class="text-body-1 font-weight-medium" style="color: var(--text-primary)">About</span>
          </div>
          <div class="text-body-2" style="color: var(--text-muted)">
            Herold &mdash; Voice Dispatch System
          </div>
          <div class="text-caption mt-1" style="color: var(--text-muted)">
            Version 1.0.0
          </div>
        </v-card-text>
      </v-card>

      <!-- Logout -->
      <v-btn
        block
        color="error"
        variant="tonal"
        size="large"
        prepend-icon="mdi-logout"
        :loading="logoutLoading"
        @click="logout"
      >
        Logout
      </v-btn>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'

const props = defineProps<{
  github: { owner: string; repo: string }
  totp: { confirmed: boolean }
}>()

const logoutLoading = ref(false)

function maskString(value: string): string {
  if (!value) return '***'
  if (value.length <= 3) return '***'
  return value.slice(0, 2) + '*'.repeat(Math.max(value.length - 3, 3)) + value.slice(-1)
}

const maskedOwner = maskString(props.github.owner)
const maskedRepo = maskString(props.github.repo)

function logout() {
  logoutLoading.value = true
  router.post('/logout')
}
</script>
