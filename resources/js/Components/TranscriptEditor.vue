<template>
  <div>
    <div class="text-caption font-label mb-1" style="color: var(--text-muted)">Transcript</div>
    <div
      class="py-2 px-4 text-body-2"
      style="border-left: 3px solid var(--neon-secondary); color: var(--text-muted); white-space: pre-wrap; line-height: 1.6; font-style: italic"
    >
      {{ modelValue || 'No transcript available.' }}
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  modelValue: string
}>()
</script>
