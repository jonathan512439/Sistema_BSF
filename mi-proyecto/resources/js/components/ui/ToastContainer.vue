<!-- Contenedor global de notificaciones Toast -->
<!-- Este componente se monta una sola vez en App.vue o Dashboard.vue -->
<template>
  <div class="toast-container">
    <transition-group name="toast" tag="div">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        :class="['toast', `toast--${toast.type}`]"
        @click="remove(toast.id)"
      >
        <!-- Icono según el tipo -->
        <div class="toast__icon">
          <svg v-if="toast.type === 'success'" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <svg v-else-if="toast.type === 'error'" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <svg v-else-if="toast.type === 'warning'" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>

        <!-- Contenido del mensaje -->
        <div class="toast__content">
          <div class="toast__message">{{ toast.message }}</div>
          <div v-if="toast.detail" class="toast__detail">{{ toast.detail }}</div>
        </div>

        <!-- Botón de cerrar -->
        <button class="toast__close" @click.stop="remove(toast.id)" title="Cerrar">
          ×
        </button>
      </div>
    </transition-group>
  </div>
</template>

<script setup>
import { useToast } from '@/composables/useToast'

// Usar el composable global
const { toasts, remove } = useToast()
</script>

<style scoped>
/* Contenedor fijo en la esquina superior derecha */
.toast-container {
  position: fixed;
  top: 1rem;
  right: 1rem;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-width: 400px;
  pointer-events: none;
}

/* Tarjeta de notificación */
.toast {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1rem;
  background: white;
  border-radius: 0.5rem;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  border-left: 4px solid currentColor;
  min-width: 320px;
  pointer-events: all;
  cursor: pointer;
  transition: all 0.3s ease;
}

.toast:hover {
  transform: translateX(-4px);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);
}

/* Icono */
.toast__icon {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  color: currentColor;
}

.toast__icon svg {
  width: 100%;
  height: 100%;
}

/* Contenido */
.toast__content {
  flex: 1;
  min-width: 0;
}

.toast__message {
  font-weight: 600;
  font-size: 0.9rem;
  color: #111827;
  margin-bottom: 0.25rem;
}

.toast__detail {
  font-size: 0.8rem;
  color: #6b7280;
  line-height: 1.4;
}

/* Botón cerrar */
.toast__close {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  border: none;
  background: transparent;
  color: #9ca3af;
  font-size: 1.5rem;
  line-height: 1;
  cursor: pointer;
  transition: color 0.2s;
  padding: 0;
}

.toast__close:hover {
  color: #4b5563;
}

/* Variantes de color */
.toast--success {
  color: #10b981;
  background: #f0fdf4;
}

.toast--error {
  color: #ef4444;
  background: #fef2f2;
}

.toast--warning {
  color: #f59e0b;
  background: #fffbeb;
}

.toast--info {
  color: #3b82f6;
  background: #eff6ff;
}

/* Animaciones de entrada/salida */
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(50%) scale(0.8);
}

.toast-move {
  transition: transform 0.3s ease;
}
</style>
