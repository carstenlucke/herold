<template>
  <v-textarea
    :model-value="modelValue"
    :readonly="readonly"
    variant="filled"
    color="primary"
    label="Transcript"
    rows="5"
    auto-grow
    :bg-color="readonly ? 'surface-variant' : 'surface'"
    @update:model-value="(val: string) => $emit('update:modelValue', val)"
  />
</template>

<script setup lang="ts">
defineProps<{
  modelValue: string
  readonly?: boolean
}>()

defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>
