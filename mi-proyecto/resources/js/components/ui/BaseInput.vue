<!-- resources/js/components/ui/BaseInput.vue -->
<template>
  <div class="field">
    <label
      v-if="label"
      class="field__label"
    >
      {{ label }}
    </label>
    <input
      class="input"
      :value="modelValue"
      v-bind="$attrs"
      @input="$emit('update:modelValue', $event.target.value)"
    >
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: '',
  },
  label: {
    type: String,
    default: '',
  },
})

defineEmits(['update:modelValue'])
</script>

<style scoped>
.field__label {
  display: block;
  font-size: 0.8rem;
  color: #4b5563;
  margin-bottom: 0.15rem;
}
</style>
