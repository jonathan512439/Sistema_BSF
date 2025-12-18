<template>
  <header class="app-topbar">
    <div class="topbar-content">
      <!-- Left: Menu Toggle (mobile) + Logo -->
      <div class="left-section">
        <button @click="$emit('toggle-menu')" class="menu-toggle">
          <span class="hamburger-icon">☰</span>
        </button>
        
        <div class="logo-container">
          <img src="/public/assets/logo.png" alt="Logo BSF" class="logo-img" />
          <div class="logo-text">
            <span class="logo-title">BSF</span>
            <span class="logo-subtitle">Sistema Documental</span>
          </div>
        </div>
      </div>
      
      <!-- Right: User Info Section -->
      <div v-if="user" class="user-section">
        <!-- User Details -->
        <div class="user-details">
          <div class="user-main">
            <span class="user-name">{{ user.name }}</span>
            <span :class="['role-badge', `role-${user.role}`]">
              {{ getRoleName(user.role) }}
            </span>
          </div>
          <div class="user-meta">
            <span class="meta-item" :title="userAgent">
              <span class="meta-icon">🌐</span>
              {{ browserName }}
            </span>
            <span class="meta-item" :title="'Tu dirección IP'">
              <span class="meta-icon">📍</span>
              {{ userIP }}
            </span>
          </div>
        </div>
        
        <!-- System Stats 
        <div class="stats-badge" title="Sistema seguro con auditoría completa">
          <span class="stat-value">{{ stats }}</span>
        </div>
        -->
        <!-- Logout Button -->
        <button @click="$emit('logout')" class="btn-logout" title="Cerrar sesión">
          Salir
        </button>
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'

const props = defineProps({
  user: {
    type: Object,
    default: null
  }
})

defineEmits(['toggle-menu', 'navigate', 'logout'])

const userIP = ref('Detectando...')
const userAgent = ref(navigator.userAgent)

const browserName = computed(() => {
  const ua = userAgent.value
  if (ua.includes('Edg')) return 'Edge'
  if (ua.includes('Chrome')) return 'Chrome'
  if (ua.includes('Firefox')) return 'Firefox'
  if (ua.includes('Safari')) return 'Safari'
  return 'Navegador'
})
/*
const stats = computed(() => {
  const now = new Date()
  const uptime = Math.floor((now - new Date(now.getFullYear(), 0, 1)) / (1000 * 60 * 60 * 24))
  return `${uptime}d activo`
})
*/
function getRoleName(role) {
  const roleMap = {
    'superadmin': 'Administrador',
    'archivist': 'Archivista',
    'reader': 'Lector'
  }
  return roleMap[role] || 'Usuario'
}

onMounted(async () => {
  try {
    // Obtener IP real desde el backend de Laravel
    const response = await fetch('/api/wm-context', {
      credentials: 'include',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    
    if (response.ok) {
      const data = await response.json()
      // El endpoint wm-context devuelve { user, email, ip, ts }
      userIP.value = data.ip || 'No disponible'
    } else {
      // Fallback: intentar con servicio externo
      const fallbackResponse = await fetch('https://api.ipify.org?format=json')
      const fallbackData = await fallbackResponse.json()
      userIP.value = fallbackData.ip
    }
  } catch (e) {
    console.error('Error al obtener IP:', e)
    userIP.value = 'No disponible'
  }
})
</script>

<style scoped>
.app-topbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 75px;
  background: linear-gradient(135deg, #556b2f 0%, #3d4e2b 100%);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  z-index: 1100;
}

.topbar-content {
  height: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 1.5rem;
  gap: 1rem;
}

.left-section {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.menu-toggle {
  display: none;
  background: transparent;
  border: none;
  color: white;
  font-size: 1.75rem;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 6px;
  transition: background 0.2s;
}

.menu-toggle:hover {
  background: rgba(255, 255, 255, 0.1);
}

.hamburger-icon {
  display: block;
  line-height: 1;
}

.logo-container {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.logo-img {
  height: 48px;
  width: auto;
  object-fit: contain;
  filter: brightness(0) invert(1);
}

.logo-text {
  display: flex;
  flex-direction: column;
  color: white;
}

.logo-title {
  font-size: 1.25rem;
  font-weight: 800;
  letter-spacing: 0.05em;
  line-height: 1;
}

.logo-subtitle {
  font-size: 0.625rem;
  opacity: 0.8;
  letter-spacing: 0.025em;
  font-weight: 500;
  margin-top: 0.125rem;
}

.user-section {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.user-details {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  align-items: flex-end;
}

.user-main {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.user-name {
  color: white;
  font-size: 0.875rem;
  font-weight: 700;
  letter-spacing: -0.025em;
}

.role-badge {
  padding: 0.125rem 0.5rem;
  border-radius: 10px;
  font-size: 0.625rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

.role-badge.role-superadmin {
  background: rgba(239, 68, 68, 0.9);
  color: white;
}

.role-badge.role-archivist {
  background: rgba(59, 130, 246, 0.9);
  color: white;
}

.role-badge.role-reader {
  background: rgba(34, 197, 94, 0.9);
  color: white;
}

.user-meta {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  color: rgba(255, 255, 255, 0.85);
  font-size: 0.688rem;
  font-weight: 500;
}

.meta-icon {
  font-size: 0.75rem;
}

.stats-badge {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.375rem 0.75rem;
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 16px;
  backdrop-filter: blur(10px);
}

.stat-icon {
  font-size: 0.875rem;
}

.stat-value {
  color: white;
  font-size: 0.688rem;
  font-weight: 700;
  letter-spacing: 0.025em;
}

.btn-logout {
  background: rgba(239, 68, 68, 0.2);
  border: 1px solid rgba(239, 68, 68, 0.4);
  color: white;
  font-size: 1.25rem;
  width: 70px;
  height: 40px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-logout:hover {
  background: rgba(239, 68, 68, 0.4);
  border-color: rgba(239, 68, 68, 0.6);
  transform: translateY(-2px);
}

@media (max-width: 1024px) {
  .user-details {
    display: none;
  }
  
  .stats-badge {
    padding: 0.25rem 0.5rem;
  }
}

@media (max-width: 768px) {
  .menu-toggle {
    display: block;
  }
  
  .topbar-content {
    padding: 0 1rem;
  }
  
  .logo-text {
    display: none;
  }
  
  .logo-img {
    height: 40px;
  }
  
  .stats-badge {
    display: none;
  }
  
  .btn-logout {
    width: 36px;
    height: 36px;
    font-size: 1rem;
  }
}

@media (max-width: 480px) {
  .logo-img {
    height: 36px;
  }
}
</style>
