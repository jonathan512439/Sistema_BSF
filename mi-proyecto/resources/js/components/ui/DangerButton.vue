<!-- resources/js/components/ui/DangerButton.vue -->
<template>
  <BaseButton
    variant="danger"
    v-bind="$attrs"
    @click="$emit('click', $event)"
  >
    <slot />
  </BaseButton>
</template>

<script setup>
import BaseButton from './BaseButton.vue'

defineEmits(['click'])
</script>
