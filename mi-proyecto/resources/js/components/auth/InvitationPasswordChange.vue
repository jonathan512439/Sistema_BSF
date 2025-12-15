<!-- resources/js/components/auth/InvitationPasswordChange.vue -->
<template>
  <div class="invitation-container">
    <div class="invitation-card">
      <div class="invitation-header">
        <h1>Bienvenido al Sistema BSF</h1>
        <p v-if="usuario">Hola, <strong>{{ usuario.name }}</strong></p>
        <p>Establece tu contraseña para activar tu cuenta</p>
      </div>

      <div v-if="error" class="error-message">
        {{ error }}
      </div>

      <form v-else @submit.prevent="cambiarPassword" class="invitation-form">
        <div class="form-group">
          <label>Nueva Contraseña</label>
          <input
            v-model="password"
            type="password"
            class="form-input"
            placeholder="Mínimo 8 caracteres"
            required
            @input="validarPassword"
          />
          
          <!-- Indicador de fortaleza -->
          <div class="password-requirements">
            <div class="requirement" :class="{ valid: requisitos.length }">
              {{ requisitos.length ? '✓' : '○' }} Mínimo 8 caracteres
            </div>
            <div class="requirement" :class="{ valid: requisitos.uppercase }">
              {{ requisitos.uppercase ? '✓' : '○' }} Una mayúscula
            </div>
            <div class="requirement" :class="{ valid: requisitos.lowercase }">
              {{ requisitos.lowercase ? '✓' : '○' }} Una minúscula
            </div>
            <div class="requirement" :class="{ valid: requisitos.number }">
              {{ requisitos.number ? '✓' : '○' }} Un número
            </div>
            <div class="requirement" :class="{ valid: requisitos.symbol }">
              {{ requisitos.symbol ? '✓' : '○' }} Un símbolo (@$!%*#?&)
            </div>
          </div>

          <!-- Barra de fortaleza -->
          <div class="strength-bar">
            <div 
              class="strength-fill" 
              :class="'strength-' + fortaleza"
              :style="{ width: (requisitosCompletos / 5 * 100) + '%' }"
            ></div>
          </div>
          <div class="strength-label" :class="'strength-' + fortaleza">
            {{ fortalezaTexto }}
          </div>
        </div>

        <div class="form-group">
          <label>Confirmar Contraseña</label>
          <input
            v-model="passwordConfirm"
            type="password"
            class="form-input"
            placeholder="Repite tu contraseña"
            required
          />
          <div v-if="passwordConfirm && !passwordsCoinciden" class="field-error">
            Las contraseñas no coinciden
          </div>
          <div v-if="passwordConfirm && passwordsCoinciden" class="field-success">
            ✓ Las contraseñas coinciden
          </div>
        </div>

        <div v-if="errorSubmit" class="error-message">
          {{ errorSubmit }}
        </div>

        <button 
          type="submit" 
          class="submit-button"
          :disabled="!formularioValido || enviando"
        >
          {{ enviando ? 'Activando cuenta...' : 'Activar Cuenta' }}
        </button>

        <div class="info-box">
          <p><strong>Tu rol:</strong> {{ usuario ? roleLabel(usuario.role) : '-' }}</p>
          <p class="small-text">
            Después de establecer tu contraseña, serás redirigido automáticamente.
          </p>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

// Estado
const usuario = ref(null)
const password = ref('')
const passwordConfirm = ref('')
const enviando = ref(false)
const error = ref(null)
const errorSubmit = ref(null)

// Requisitos de contraseña
const requisitos = ref({
  length: false,
  uppercase: false,
  lowercase: false,
  number: false,
  symbol: false,
})

// Computados
const requisitosCompletos = computed(() => {
  return Object.values(requisitos.value).filter(Boolean).length
})

const fortaleza = computed(() => {
  const count = requisitosCompletos.value
  if (count === 0) return 'none'
  if (count <= 2) return 'weak'
  if (count <= 4) return 'medium'
  return 'strong'
})

const fortalezaTexto = computed(() => {
  const labels = {
    none: 'Sin contraseña',
    weak: 'Débil',
    medium: 'Media',
    strong: 'Fuerte',
  }
  return labels[fortaleza.value]
})

const passwordsCoinciden = computed(() => {
  return password.value === passwordConfirm.value
})

const formularioValido = computed(() => {
  return requisitosCompletos.value === 5 && 
         passwordsCoinciden.value &&
         password.value.length >= 8
})

// Métodos
function validarPassword() {
  const p = password.value
  
  requisitos.value = {
    length: p.length >= 8,
    uppercase: /[A-Z]/.test(p),
    lowercase: /[a-z]/.test(p),
    number: /[0-9]/.test(p),
    symbol: /[@$!%*#?&]/.test(p),
  }
}

async function cambiarPassword() {
  enviando.value = true
  errorSubmit.value = null
  
  try {
    const token = route.params.token
    
    const response = await fetch(`/invitation/${token}`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
      },
      body: JSON.stringify({
        password: password.value,
        password_confirmation: passwordConfirm.value,
      }),
    })

    if (response.redirected) {
      // Laravel redirigió automáticamente
      window.location.href = response.url
      return
    }

    const data = await response.json()
    
    if (response.ok) {
      // Redirigir al dashboard (Vue Router si aplica, o recarga)
      window.location.href = '/'
    } else {
      errorSubmit.value = data.message || data.errors?.password?.[0] || 'Error al establecer contraseña'
    }
  } catch (err) {
    errorSubmit.value = 'Error de conexión. Intenta nuevamente.'
  } finally {
    enviando.value = false
  }
}

function roleLabel(role) {
  const labels = {
    superadmin: 'Superadministrador',
    archivist: 'Archivista / Encargado de Documentos',
    reader: 'Lector',
  }
  return labels[role] || role
}

// Lifecycle
onMounted(async () => {
  // Validar token y obtener datos del usuario
  const token = route.params.token
  
  try {
    const response = await fetch(`/invitation/${token}`, {
      credentials: 'include',
    })
    
    if (!response.ok) {
      error.value = 'El enlace de invitación es inválido o ha expirado'
      return
    }
    
    // Si Laravel retorna HTML, parsearlo para extraer datos
    const html = await response.text()
    
    // Por ahora, si la ruta funciona, asumimos que el token es válido
    // En producción, podrías hacer una ruta API separada para validar el token
  } catch (err) {
    error.value = 'No se pudo validar el enlace de invitación'
  }
})
</script>

<style scoped>
.invitation-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 1rem;
}

.invitation-card {
  background: white;
  border-radius: 1rem;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  max-width: 500px;
  width: 100%;
  padding: 2.5rem;
}

.invitation-header {
  text-align: center;
  margin-bottom: 2rem;
}

.invitation-header h1 {
  font-size: 2rem;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 0.5rem;
}

.invitation-header p {
  color: #6b7280;
  font-size: 1rem;
}

.invitation-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #374151;
}

.form-input {
  padding: 0.75rem;
  border: 2px solid #e5e7eb;
  border-radius: 0.5rem;
  font-size: 1rem;
  transition: border-color 0.2s;
}

.form-input:focus {
  outline: none;
  border-color: #667eea;
}

.password-requirements {
  margin-top: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.requirement {
  font-size: 0.875rem;
  color: #9ca3af;
  transition: color 0.2s;
}

.requirement.valid {
  color: #10b981;
  font-weight: 600;
}

.strength-bar {
  height: 0.5rem;
  background-color: #e5e7eb;
  border-radius: 0.25rem;
  overflow: hidden;
  margin-top: 0.75rem;
}

.strength-fill {
  height: 100%;
  transition: width 0.3s, background-color 0.3s;
}

.strength-fill.strength-weak {
  background-color: #ef4444;
}

.strength-fill.strength-medium {
  background-color: #f59e0b;
}

.strength-fill.strength-strong {
  background-color: #10b981;
}

.strength-label {
  font-size: 0.875rem;
  font-weight: 600;
  margin-top: 0.25rem;
}

.strength-label.strength-weak {
  color: #ef4444;
}

.strength-label.strength-medium {
  color: #f59e0b;
}

.strength-label.strength-strong {
  color: #10b981;
}

.field-error {
  color: #ef4444;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.field-success {
  color: #10b981;
  font-size: 0.875rem;
  margin-top: 0.25rem;
  font-weight: 600;
}

.error-message {
  background-color: #fee2e2;
  color: #991b1b;
  padding: 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.875rem;
}

.submit-button {
  padding: 1rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 0.5rem;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}

.submit-button:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
}

.submit-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.info-box {
  background-color: #f3f4f6;
  padding: 1rem;
  border-radius: 0.5rem;
  text-align: center;
}

.info-box p {
  margin: 0.25rem 0;
  color: #374151;
}

.small-text {
  font-size: 0.875rem;
  color: #6b7280;
}
</style>
