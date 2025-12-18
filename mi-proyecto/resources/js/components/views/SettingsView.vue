<template>
  <div class="settings-view">
    <div class="view-header">
      <div>
        <h1 class="view-title">Configuración</h1>
        <p class="view-subtitle">Personaliza tu perfil y preferencias</p>
      </div>
    </div>

    <div class="settings-content">
      <!-- Profile Section -->
      <div class="settings-card">
        <h3 class="card-title">Información del Perfil</h3>
        <div class="profile-section">
          <div class="avatar-section">
            <div class="avatar-large">
              <span>{{ userInitials }}</span>
            </div>
          </div>
          
          <div class="profile-fields">
            <div class="field-group">
              <label>Nombre Completo</label>
              <div class="field-readonly">{{ profile.name }}</div>
              <p class="field-help">No editable</p>
            </div>
            
            <div class="field-group">
              <label>Email</label>
              <div class="field-readonly">{{ profile.email }}</div>
              <p class="field-help">No editable</p>
            </div>
            
            <div class="field-group">
              <label>Rol</label>
              <div class="field-readonly">
                <span :class="['role-badge', `role-${user?.role}`]">{{ profile.role }}</span>
              </div>
              <p class="field-help">Asignado por el administrador</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Password Change -->
      <div class="settings-card">
        <h3 class="card-title">Cambiar Contraseña</h3>
        <div class="password-fields">
          <div class="field-group">
            <label>Contraseña Actual</label>
            <input 
              v-model="passwords.current" 
              type="password" 
              class="field-input"
              placeholder="••••••••"
            />
          </div>
          
          <div class="field-group">
            <label>Nueva Contraseña</label>
            <input 
              v-model="passwords.new" 
              type="password" 
              class="field-input"
              placeholder="••••••••"
            />
          </div>
          
          <div class="field-group">
            <label>Confirmar Nueva Contraseña</label>
            <input 
              v-model="passwords.confirm" 
              type="password" 
              class="field-input"
              placeholder="••••••••"
            />
          </div>
        </div>
        
        <button @click="changePassword" class="btn-save" :disabled="!passwordsValid">
          Cambiar Contraseña
        </button>
      </div>

      <!-- Preferences -->
      <div class="settings-card">
        <h3 class="card-title">Preferencias</h3>
        <div class="preferences">
          <div class="pref-item">
            <div class="pref-info">
              <span class="pref-label">Notificaciones por Email</span>
              <span class="pref-description">Recibir alertas cuando se validen documentos</span>
            </div>
            <label class="toggle">
              <input v-model="prefs.emailNotifications" type="checkbox" />
              <span class="slider"></span>
            </label>
          </div>
          
          <div class="pref-item">
            <div class="pref-info">
              <span class="pref-label">Modo Compacto</span>
              <span class="pref-description">Vista condensada de la lista de documentos</span>
            </div>
            <label class="toggle">
              <input v-model="prefs.compactMode" type="checkbox" />
              <span class="slider"></span>
            </label>
          </div>
        </div>
      </div>

      <!-- About -->
      <div class="settings-card">
        <h3 class="card-title">Acerca del Sistema</h3>
        <div class="about-info">
          <div class="info-row">
            <span class="info-label">Versión:</span>
            <span class="info-value">1.0.0</span>
          </div>
          <div class="info-row">
            <span class="info-label">Sistema:</span>
            <span class="info-value">BSF Document Management System</span>
          </div>
          <div class="info-row">
            <span class="info-label">Última actualización:</span>
            <span class="info-value">Noviembre 2025</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  user: {
    type: Object,
    default: () => ({})
  },
  headers: {
    type: Object,
    required: true
  }
})

const profile = ref({
  name: props.user?.name || '',
  email: props.user?.email || '',
  role: getRoleName(props.user?.role)
})

const passwords = ref({
  current: '',
  new: '',
  confirm: ''
})

const prefs = ref({
  emailNotifications: true,
  compactMode: false
})

const userInitials = computed(() => {
  const name = profile.value.name || 'U'
  const parts = name.split(' ')
  if (parts.length >= 2) {
    return parts[0][0] + parts[1][0]
  }
  return name.substring(0, 2).toUpperCase()
})

const passwordsValid = computed(() => {
  return passwords.value.current && 
         passwords.value.new && 
         passwords.value.new === passwords.value.confirm &&
         passwords.value.new.length >= 8
})

function getRoleName(role) {
  const roleMap = {
    'superadmin': 'Administrador',
    'archivist': 'Archivista',
    'reader': 'Lector'
  }
  return roleMap[role] || 'Usuario'
}

async function changePassword() {
  if (!passwordsValid.value) {
    warning('Validación de contraseña', 'Por favor verifica que las contraseñas coincidan y tengan al menos 8 caracteres')
    return
  }
  
  try {
    const response = await fetch('/api/user/change-password', {
      method: 'POST',
      headers: {
        ...props.headers,
        'Content-Type': 'application/json'
      },
      credentials: 'include',
      body: JSON.stringify({
        current_password: passwords.value.current,
        new_password: passwords.value.new
      })
    })
    
    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.message || 'Error al cambiar contraseña')
    }
    
    success('Contraseña actualizada', 'Tu contraseña ha sido cambiada exitosamente')
    passwords.value = { current: '', new: '', confirm: '' }
  } catch (e) {
    error('Error al cambiar contraseña', e.message)
  }
}
</script>

<style scoped>
.settings-view {
  padding: 2rem;
  max-width: 1000px;
  margin: 0 auto;
}

.view-header {
  margin-bottom: 2rem;
}

.view-title {
  font-size: 2rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 0.5rem 0;
}

.view-subtitle {
  font-size: 1rem;
  color: #6b7280;
  margin: 0;
}

.settings-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.settings-card {
  background: white;
  border-radius: 12px;
  padding: 2rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.card-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #374151;
  margin: 0 0 1.5rem 0;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #e5e7eb;
}

/* Profile */
.profile-section {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 2rem;
  align-items: start;
}

.avatar-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.avatar-large {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  background: #556b2f;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  font-weight: 700;
  color: white;
}

.profile-fields,
.password-fields {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.field-group label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
}

.field-input {
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.938rem;
}

.field-input:disabled {
  background: #f9fafb;
  color: #9ca3af;
  cursor: not-allowed;
}

.field-readonly {
  padding: 0.75rem;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 0.938rem;
  color: #1f2937;
}

.field-help {
  font-size: 0.75rem;
  color: #9ca3af;
  margin: 0.25rem 0 0 0;
  font-style: italic;
}

.role-badge {
  display: inline-block;
  padding: 0.375rem 0.75rem;
  border-radius: 12px;
  font-size: 0.875rem;
  font-weight: 600;
}

.role-badge.role-superadmin {
  background: #fee2e2;
  color: #991b1b;
}

.role-badge.role-archivist {
  background: #dbeafe;
  color: #1e40af;
}

.role-badge.role-reader {
  background: #d1fae5;
  color: #065f46;
}

.field-input:focus:not(:disabled) {
  outline: none;
  border-color: #556b2f;
  box-shadow: 0 0 0 3px rgba(85, 107, 47, 0.1);
}

.btn-save {
  margin-top: 1rem;
  padding: 0.75rem 1.5rem;
  background: #556b2f;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-save:hover:not(:disabled) {
  background: #4b5f2a;
  transform: translateY(-2px);
}

.btn-save:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Preferences */
.preferences {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.pref-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 8px;
}

.pref-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.pref-label {
  font-size: 0.938rem;
  font-weight: 600;
  color: #1f2937;
}

.pref-description {
  font-size: 0.813rem;
  color: #6b7280;
}

/* Toggle Switch */
.toggle {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 24px;
}

.toggle input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #cbd5e1;
  transition: 0.3s;
  border-radius: 24px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.3s;
  border-radius: 50%;
}

.toggle input:checked + .slider {
  background-color: #556b2f;
}

.toggle input:checked + .slider:before {
  transform: translateX(24px);
}

/* About */
.about-info {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.info-row {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem;
  background: #f9fafb;
  border-radius: 6px;
}

.info-label {
  font-weight: 600;
  color: #6b7280;
  font-size: 0.875rem;
}

.info-value {
  color: #1f2937;
  font-size: 0.875rem;
}

@media (max-width: 768px) {
  .settings-view {
    padding: 1rem;
  }
  
  .profile-section {
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }
  
  .avatar-section {
    flex-direction: row;
    justify-content: flex-start;
  }
  
  .pref-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }
  
  .info-row {
    flex-direction: column;
    gap: 0.5rem;
  }
}
</style>
