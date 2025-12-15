<!-- resources/js/components/ui/PrimaryButton.vue -->
<template>
  <BaseButton
    variant="primary"
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
