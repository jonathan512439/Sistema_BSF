<!-- resources/js/components/ui/BaseSelect.vue -->
<template>
  <div class="field">
    <label
      v-if="label"
      class="field__label"
    >
      {{ label }}
    </label>
    <select
      class="input"
      :value="modelValue"
      v-bind="$attrs"
      @change="onChange"
    >
      <slot />
    </select>
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: {
    type: [String, Number, null],
    default: null,
  },
  label: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue'])

function onChange (e) {
  const value = e.target.value === '' ? null : e.target.value
  emit('update:modelValue', value)
}
</script>

<style scoped>
.field__label {
  display: block;
  font-size: 0.8rem;
  color: #4b5563;
  margin-bottom: 0.15rem;
}
</style>
