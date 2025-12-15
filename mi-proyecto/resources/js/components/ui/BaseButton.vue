<!-- resources/js/components/ui/BaseButton.vue -->
<template>
  <component
    :is="as"
    class="btn"
    :class="classes"
    v-bind="$attrs"
    @click="onClick"
  >
    <slot />
  </component>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'ghost', // primary | danger | ghost
  },
  size: {
    type: String,
    default: 'md', // sm | md | lg
  },
  block: {
    type: Boolean,
    default: false,
  },
  as: {
    type: String,
    default: 'button', // o 'a'
  },
})

const emit = defineEmits(['click'])

const classes = computed(() => [
  `btn--${props.variant}`,
  `btn--${props.size}`,
  { 'btn--block': props.block },
])

function onClick (event) {
  emit('click', event)
}
</script>
