<template>
  <v-chip-group
    :model-value="modelValue"
    mandatory
    selected-class="text-primary neon-glow-primary"
    @update:model-value="(val: string) => $emit('update:modelValue', val)"
  >
    <v-chip
      v-for="(type, key) in types"
      :key="key"
      :value="key"
      variant="tonal"
      :prepend-icon="type.icon"
      class="font-label"
    >
      {{ type.label }}
    </v-chip>
  </v-chip-group>
</template>

<script setup lang="ts">
import type { MessageType } from '../Types'

defineProps<{
  types: Record<string, MessageType>
  modelValue: string
}>()

defineEmits<{
  'update:modelValue': [type: string]
}>()
</script>
