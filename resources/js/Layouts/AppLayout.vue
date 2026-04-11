<template>
  <v-app>
    <!-- Desktop side nav (>= 960px) -->
    <v-navigation-drawer
      v-if="!mobile"
      permanent
      :width="220"
      color="surface"
      class="glass"
      style="border-right: 1px solid rgba(255, 255, 255, 0.06)"
    >
      <div class="pa-5 pb-2 d-flex align-center ga-3">
        <img src="/images/herold-icon.png" alt="Herold" style="height: 32px; width: auto" />
        <div>
          <div class="brand-text text-h5">Herold</div>
          <div class="brand-subtitle mt-1" style="font-size: 0.5rem">VOICE DISPATCH SYSTEM</div>
        </div>
      </div>

      <v-divider class="mx-4 my-3" style="border-color: rgba(255, 255, 255, 0.06)" />

      <v-list density="compact" nav class="px-2">
        <v-list-item
          v-for="item in navItems"
          :key="item.to"
          :href="item.to"
          :prepend-icon="item.icon"
          :title="item.label"
          :active="isActive(item.to)"
          color="primary"
          rounded="lg"
          class="mb-1"
          @click.prevent="navigate(item.to)"
        />
      </v-list>

      <template #append>
        <div class="px-2 pb-4">
          <v-list-item
            prepend-icon="mdi-logout"
            title="Logout"
            color="error"
            rounded="lg"
            @click="logout"
          />
        </div>
      </template>
    </v-navigation-drawer>

    <!-- Main content -->
    <v-main>
      <div class="content-container pa-4 pa-sm-6" :class="{ 'pb-20': mobile }">
        <slot />
      </div>
    </v-main>

    <!-- Mobile bottom nav (< 960px) -->
    <v-bottom-navigation
      v-if="mobile"
      grow
      color="primary"
      class="glass"
      style="border-top: 1px solid rgba(255, 255, 255, 0.06)"
      :model-value="activeNavIndex"
    >
      <v-btn
        v-for="(item, index) in navItems"
        :key="item.to"
        :value="index"
        @click="navigate(item.to)"
      >
        <v-icon
          :icon="item.icon"
          :size="item.to === '/notes/create' ? 28 : 22"
          :color="item.to === '/notes/create' ? 'primary' : undefined"
        />
        <span class="text-caption">{{ item.label }}</span>
      </v-btn>
    </v-bottom-navigation>
  </v-app>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useDisplay } from 'vuetify'

const { mobile } = useDisplay({ mobileBreakpoint: 960 })
const page = usePage()

const navItems = [
  { label: 'Dashboard', icon: 'mdi-view-dashboard', to: '/' },
  { label: 'Record', icon: 'mdi-microphone', to: '/notes/create' },
  { label: 'Notes', icon: 'mdi-format-list-bulleted', to: '/notes' },
  { label: 'Settings', icon: 'mdi-cog', to: '/settings' },
]

const currentPath = computed(() => page.url)

function isActive(to: string): boolean {
  const current = currentPath.value
  if (to === '/') return current === '/' || current === '/dashboard'
  if (to === '/notes') return current === '/notes' || (current.startsWith('/notes/') && !current.startsWith('/notes/create'))
  return current.startsWith(to)
}

const activeNavIndex = computed(() => {
  const index = navItems.findIndex((item) => isActive(item.to))
  return index >= 0 ? index : 0
})

function navigate(to: string) {
  router.visit(to)
}

function logout() {
  router.post('/logout')
}
</script>
