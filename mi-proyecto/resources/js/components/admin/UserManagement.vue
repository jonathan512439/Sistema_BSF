<!-- resources/js/components/admin/UserManagement.vue -->
<template>
  <div class="user-management">
    <h2 class="title">Gestión de Usuarios</h2>
    
    <!-- Filtros y búsqueda -->
    <div class="card form-card">
      <div class="row" style="justify-content: space-between; align-items: center">
        <div style="flex: 1; min-width: 220px; margin-right: .5rem">
          <BaseInput
            v-model="filtros.search"
            label="Buscar usuarios"
            placeholder="Nombre, email o username"
            @input="cargarUsuarios"
          />
        </div>
        
        <div class="row">
          <select v-model="filtros.role" @change="cargarUsuarios" class="base-select">
            <option value="all">Todos los roles</option>
            <option value="archivist">Archivista</option>
            <option value="reader">Lector</option>
          </select>
          
          <select v-model="filtros.status" @change="cargarUsuarios" class="base-select">
            <option value="all">Todos los estados</option>
            <option value="invited">Invitados</option>
            <option value="active">Activos</option>
            <option value="disabled">Deshabilitados</option>
          </select>
          
          <PrimaryButton @click="abrirModalCrear">
            + Crear Usuario
          </PrimaryButton>
        </div>
      </div>
    </div>

    <!-- Tabla de usuarios -->
    <div class="card">
      <div v-if="loading" class="loading">Cargando usuarios...</div>
      
      <div v-else-if="error" class="error">{{ error }}</div>
      
      <table v-else-if="usuarios.length" class="users-table">
        <thead>
          <tr>
            <th>Usuario</th>
            <th>Email</th>
            <th>Rol</th>
            <th>Estado</th>
            <th>Creado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in usuarios" :key="user.id">
            <td>
              <div class="user-name">{{ user.name }}</div>
              <div class="user-username">@{{ user.username }}</div>
            </td>
            <td>{{ user.email }}</td>
            <td>
              <span class="role-badge" :class="'role-' + user.role">
                {{ roleLabel(user.role) }}
              </span>
            </td>
            <td>
              <span class="status-badge" :class="'status-' + user.status">
                {{ statusLabel(user.status) }}
              </span>
            </td>
            <td>{{ formatDate(user.created_at) }}</td>
            <td>
              <div class="actions-row">
                <BaseButton 
                  v-if="user.status === 'invited'"
                  @click="reenviarInvitacion(user)"
                  class="btn-small"
                >
                  <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414.05 3.555zM0 4.697v7.104l5.803-3.558L0 4.697zM6.761 8.83l-6.57 4.027A2 2 0 0 0 2 14h12a2 2 0 0 0 1.808-1.144l-6.57-4.027L8 9.586l-1.239-.757zm3.436-.586L16 11.801V4.697l-5.803 3.546z"/>
                  </svg>
                  Reenviar
                </BaseButton>
                
                <BaseButton 
                  v-if="user.deleted_at"
                  @click="darDeAlta(user)"
                  class="btn-small"
                >
                  <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/>
                  </svg>
                  Dar de Alta
                </BaseButton>
                
                <DangerButton 
                  v-if="!user.deleted_at && user.role !== 'superadmin'"
                  @click="abrirModalBaja(user)"
                  class="btn-small"
                >
                  <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8 2.146 2.854Z"/>
                  </svg>
                  Dar de Baja
                </DangerButton>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      
      <div v-else class="no-data">
        No hay usuarios para mostrar
      </div>
    </div>

    <!-- Modal Crear Usuario -->
    <BaseModal :open="modalCrear" title="Crear Nuevo Usuario" @close="cerrarModalCrear">
      <template #body>
        <div class="form-group">
          <BaseInput
            v-model="nuevoUsuario.name"
            label="Nombre completo"
            placeholder="Juan Pérez"
            required
          />
        </div>
        
        <div class="form-group">
          <BaseInput
            v-model="nuevoUsuario.email"
            label="Email"
            type="email"
            placeholder="juan@example.com"
            required
          />
        </div>
        
        <div class="form-group">
          <BaseInput
            v-model="nuevoUsuario.username"
            label="Username"
            placeholder="jperez"
            required
          />
          <p class="field-hint">
            Si el username ya existe, se permitirá reutilizarlo pero quedará registrado en auditoría.
          </p>
        </div>
        
        <div class="form-group">
          <label>Rol</label>
          <select v-model="nuevoUsuario.role" class="base-select" required>
            <option value="">Seleccione un rol...</option>
            <option value="archivist">Archivista</option>
            <option value="reader">Lector</option>
          </select>
          <p class="field-hint">
            No se puede crear usuarios con rol Superadmin.
          </p>
        </div>
        
        <div v-if="errorCrear" class="error">{{ errorCrear }}</div>
      </template>
      
      <template #footer>
        <BaseButton @click="cerrarModalCrear">Cancelar</BaseButton>
        <PrimaryButton 
          @click="crearUsuario" 
          :disabled="creando || !formularioValido"
        >
          {{ creando ? 'Creando...' : 'Crear y Enviar Invitación' }}
        </PrimaryButton>
      </template>
    </BaseModal>

    <!-- Modal Dar de Baja -->
    <BaseModal :open="modalBaja" title="Dar de Baja Usuario" @close="cerrarModalBaja">
      
      <template #body>
        <p>¿Está seguro que desea dar de baja al usuario <strong>{{ usuarioSeleccionado?.name }}</strong>?</p>
        
        <div class="form-group">
          <label>Motivo de la baja (requerido)</label>
          <textarea 
            v-model="motivoBaja"
            class="base-textarea"
            placeholder="Explique el motivo de la baja..."
            rows="4"
            required
          ></textarea>
        </div>
        
        <div v-if="errorBaja" class="error">{{ errorBaja }}</div>
      </template>
      
      <template #footer>
        <BaseButton @click="cerrarModalBaja">Cancelar</BaseButton>
        <DangerButton 
          @click="darDeBaja" 
          :disabled="dando || !motivoBaja"
        >
          {{ dando ? 'Procesando...' : 'Confirmar Baja' }}
        </DangerButton>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from '@/composables/useToast'
import BaseInput from '../ui/BaseInput.vue'
import BaseButton from '../ui/BaseButton.vue'
import PrimaryButton from '../ui/PrimaryButton.vue'
import DangerButton from '../ui/DangerButton.vue'
import BaseModal from '../ui/BaseModal.vue'

const props = defineProps({
  headers: { type: Object, required: true },
})

const { success, error: toastError, warning } = useToast()

// Estado
const usuarios = ref([])
const loading = ref(false)
const error = ref(null)

// Filtros
const filtros = ref({
  search: '',
  role: 'all',
  status: 'all',
})

// Modal crear
const modalCrear = ref(false)
const nuevoUsuario = ref({
  name: '',
  email: '',
  username: '',
  role: '',
})
const creando = ref(false)
const errorCrear = ref(null)

// Modal baja
const modalBaja = ref(false)
const usuarioSeleccionado = ref(null)
const motivoBaja = ref('')
const dando = ref(false)
const errorBaja = ref(null)

// Computados
const formularioValido = computed(() => {
  return nuevoUsuario.value.name && 
         nuevoUsuario.value.email && 
         nuevoUsuario.value.username && 
         nuevoUsuario.value.role
})

// Métodos
async function cargarUsuarios() {
  loading.value = true
  error.value = null
  
  try {
    const params = new URLSearchParams()
    if (filtros.value.search) params.append('search', filtros.value.search)
    if (filtros.value.role !== 'all') params.append('role', filtros.value.role)
    if (filtros.value.status !== 'all') params.append('status', filtros.value.status)
    
    const response = await fetch(`/api/admin/users?${params}`, {
      credentials: 'include',
      headers: props.headers,
    })
    
    const data = await response.json()
    
    if (data.ok) {
      usuarios.value = data.users
    } else {
      error.value = data.message || 'Error al cargar usuarios'
    }
  } catch (err) {
    error.value = 'Error de conexión al cargar usuarios'
  } finally {
    loading.value = false
  }
}

function abrirModalCrear() {
  nuevoUsuario.value = { name: '', email: '', username: '', role: '' }
  errorCrear.value = null
  modalCrear.value = true
}

function cerrarModalCrear() {
  modalCrear.value = false
}

async function crearUsuario() {
  creando.value = true
  errorCrear.value = null
  
  try {
    const response = await fetch('/api/admin/users', {
      method: 'POST',
      credentials: 'include',
      headers: {
        ...props.headers,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(nuevoUsuario.value),
    })
    
    const data = await response.json()
    
    if (data.ok) {
      success('Usuario creado', 'Se ha enviado una invitación por email')
      
      if (data.username_reused) {
        warning('Username reutilizado', 'Este username ya existía. Acción registrada en auditoría.')
      }
      
      cerrarModalCrear()
      await cargarUsuarios()
    } else {
      errorCrear.value = data.message || 'Error al crear usuario'
    }
  } catch (err) {
    errorCrear.value = 'Error de conexión'
  } finally {
    creando.value = false
  }
}

function abrirModalBaja(user) {
  usuarioSeleccionado.value = user
  motivoBaja.value = ''
  errorBaja.value = null
  modalBaja.value = true
}

function cerrarModalBaja() {
  modalBaja.value = false
  usuarioSeleccionado.value = null
}

async function darDeBaja() {
  dando.value = true
  errorBaja.value = null
  
  try {
    const response = await fetch(`/api/admin/users/${usuarioSeleccionado.value.id}`, {
      method: 'DELETE',
      credentials: 'include',
      headers: {
        ...props.headers,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ reason: motivoBaja.value }),
    })
    
    if (!response.ok) {
      throw new Error(`Error HTTP ${response.status}`)
    }
    
    const data = await response.json()
    
    if (data.ok) {
      success('Usuario dado de baja correctamente', `${usuarioSeleccionado.value.name} ya no podrá acceder al sistema`)
      cerrarModalBaja()
      await cargarUsuarios() // Recargar la lista para mostrar el cambio de estado
    } else {
      errorBaja.value = data.message || 'Error al dar de baja'
      toastError('Error al dar de baja', data.message)
    }
  } catch (err) {
    console.error('Error al dar de baja:', err)
    errorBaja.value = 'Error de conexión: ' + err.message
    toastError('Error de conexión', 'No se pudo conectar con el servidor')
  } finally {
    dando.value = false
  }
}

async function darDeAlta(user) {
  if (!confirm(`¿Dar de alta a ${user.name}?`)) return
  
  try {
    const response = await fetch(`/api/admin/users/${user.id}/restore`, {
      method: 'POST',
      credentials: 'include',
      headers: props.headers,
    })
    
    if (!response.ok) {
      throw new Error(`Error HTTP ${response.status}`)
    }
    
    const data = await response.json()
    
    if (data.ok) {
      success('Usuario dado de alta correctamente', `${user.name} ha sido reactivado y puede acceder al sistema nuevamente`)
      await cargarUsuarios() // Recargar para actualizar el estado en la lista
    } else {
      toastError('Error al dar de alta', data.message || 'No se pudo dar de alta')
    }
  } catch (err) {
    console.error('Error al dar de alta:', err)
    toastError('Error de conexión', 'No se pudo conectar con el servidor')
  }
}

async function reenviarInvitacion(user) {
  // Confirmar acción
  if (!confirm(`¿Reenviar invitación a ${user.email}?\n\nSe generará un nuevo token y se enviará un email de invitación.`)) {
    return
  }
  
  // Mostrar notificación de progreso
  info('Reenviando invitación...', 'Por favor espera')
  
  try {
    const response = await fetch(`/api/admin/users/${user.id}/resend-invitation`, {
      method: 'POST',
      credentials: 'include',
      headers: props.headers,
    })
    
    if (!response.ok) {
      throw new Error(`Error HTTP ${response.status}`)
    }
    
    const data = await response.json()
    
    if (data.ok) {
      success(
        'Invitación reenviada correctamente', 
        `Email enviado a ${user.email}. El usuario puede activar su cuenta con el nuevo link.`
      )
      // Recargar para actualizar fecha de expiración si es necesario
      await cargarUsuarios()
    } else {
      toastError('Error al reenviar invitación', data.message || 'No se pudo reenviar')
    }
  } catch (err) {
    console.error('Error al reenviar invitación:', err)
    toastError('Error de conexión', 'No se pudo conectar con el servidor. Verifica tu conexión.')
  }
}

function roleLabel(role) {
  const labels = {
    superadmin: 'Superadmin',
    archivist: 'Archivista',
    reader: 'Lector',
  }
  return labels[role] || role
}

function statusLabel(status) {
  const labels = {
    invited: 'Invitado',
    active: 'Activo',
    disabled: 'Deshabilitado',
  }
  return labels[status] || status
}

function formatDate(dateString) {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return date.toLocaleDateString('es-ES', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric' 
  })
}

// Lifecycle
onMounted(() => {
  cargarUsuarios()
})
</script>

<style scoped>
.user-management {
  padding: 1rem;
}

.title {
  font-size: 1.8rem;
  font-weight: 700;
  margin-bottom: 1.5rem;
  color: #1f2937;
}

.users-table {
  width: 100%;
  border-collapse: collapse;
}

.users-table th {
  text-align: left;
  padding: 0.75rem;
  background-color: #f3f4f6;
  font-weight: 600;
  border-bottom: 2px solid #e5e7eb;
}

.users-table td {
  padding: 0.75rem;
  border-bottom: 1px solid #e5e7eb;
}

.user-name {
  font-weight: 600;
  color: #111827;
}

.user-username {
  font-size: 0.875rem;
  color: #6b7280;
}

.role-badge,
.status-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.role-archivist {
  background-color: #dbeafe;
  color: #1e40af;
}

.role-reader {
  background-color: #fef3c7;
  color: #92400e;
}

.status-invited {
  background-color: #fef3c7;
  color: #92400e;
}

.status-active {
  background-color: #d1fae5;
  color: #065f46;
}

.status-disabled {
  background-color: #fee2e2;
  color: #991b1b;
}

.actions-row {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.btn-small {
  font-size: 0.75rem;
  padding: 0.25rem 0.5rem;
}

.loading {
  text-align: center;
  padding: 2rem;
  color: #6b7280;
}

.no-data {
  text-align: center;
  padding: 2rem;
  color: #9ca3af;
}

.error {
  color: #dc2626;
  padding: 0.5rem;
  margin: 0.5rem 0;
}

.form-group {
  margin-bottom: 1rem;
}

.base-select,
.base-textarea {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  font-size: 0.875rem;
}

.base-textarea {
  font-family: inherit;
  resize: vertical;
}

.field-hint {
  font-size: 0.75rem;
  color: #6b7280;
  margin-top: 0.25rem;
}
</style>
