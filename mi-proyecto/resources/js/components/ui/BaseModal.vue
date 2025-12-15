<!-- resources/js/components/ui/BaseModal.vue -->
<template>
  <transition name="fade">
    <div
      v-if="open"
      class="modal-backdrop"
      @click.self="handleBackdrop"
    >
      <!-- Cambio: 'modal' → 'bsf-modal' para evitar conflicto con Bootstrap -->
      <div :class="['bsf-modal', sizeClass]">
        <header v-if="title" class="bsf-modal__header">
          <h3 class="bsf-modal__title">{{ title }}</h3>
          <button @click="$emit('close')" class="btn-close" aria-label="Cerrar">
            ✕
          </button>
        </header>

        <section class="bsf-modal__body">
          <slot name="body">
            <slot />
          </slot>
        </section>

        <footer v-if="$slots.footer" class="bsf-modal__footer">
          <slot name="footer" />
        </footer>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
  size: {
    type: String,
    default: 'normal', // normal | large
  },
  closeOnBackdrop: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['close'])

const sizeClass = computed(() =>
  props.size === 'large' ? 'bsf-modal--large' : '',
)

function handleBackdrop () {
  if (props.closeOnBackdrop) {
    emit('close')
  }
}
</script>

<style scoped>
/* Estilos específicos del encabezado y cuerpo del modal */
.bsf-modal {
  background: white;
  border-radius: 12px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  position: relative;
  animation: modal-in 0.3s ease-out;
}

.bsf-modal--large {
  max-width: 1100px; /* Aumentado de 900px para mejor vista horizontal */
  width: 95%;
}

.bsf-modal__header {
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f9fafb;
  border-radius: 12px 12px 0 0;
}

.bsf-modal__title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #111827;
  margin: 0;
  flex: 1;
}

.btn-close {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: none;
  background: #f3f4f6;
  color: #6b7280;
  font-size: 1.25rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  line-height: 1;
  padding: 0;
}

.btn-close:hover {
  background: #e5e7eb;
  color: #374151;
  transform: scale(1.1);
}

.bsf-modal__body {
  padding: 1.5rem;
  overflow-y: auto; /* Scroll vertical si es necesario */
  font-size: 0.9rem;
  color: #374151;
  flex: 1; /* Ocupar espacio restante */
}

.bsf-modal__footer {
  padding: 1.25rem 1.5rem;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
  border-radius: 0 0 12px 12px;
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

/* Backdrop */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 10000; /* Aumentado de 50 a 10000 para estar sobre todo */
  padding: 1rem;
}

/* Animations */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@keyframes modal-in {
  from {
    opacity: 0;
    transform: scale(0.95) translateY(10px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}
</style>
