<template>
  <aside :class="['app-sidebar', { collapsed: isCollapsed, 'mobile-open': mobileOpen }]">
    <!-- Header -->
    <div class="sidebar-header">
      <div class="logo-container">
        <div class="logo-icon">
          <svg width="24" height="24" viewBox="0 0 16 16" fill="currentColor">
            <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h11A1.5 1.5 0 0 1 15 2.5v11a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 1 13.5v-11zM2.5 2a.5.5 0 0 0-.5.5v11a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 .5-.5v-11a.5.5 0 0 0-.5-.5h-11z"/>
            <path d="M5.5 4a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5z"/>
          </svg>
        </div>
        <transition name="fade">
          <div v-if="!isCollapsed" class="logo-text">
            <span class="logo-title">BSF Documentos</span>
          </div>
        </transition>
      </div>
      <button @click="toggleSidebar" class="toggle-btn" :title="isCollapsed ? 'Expandir' : 'Colapsar'">
        <span v-if="isCollapsed">→</span>
        <span v-else>←</span>
      </button>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
      <div 
        v-for="item in navigationItems" 
        :key="item.id"
        @click="handleNavigate(item.id)"
        :class="['nav-item', { active: currentView === item.id, disabled: !item.visible }]"
        :title="isCollapsed ? item.label : ''"
      >
        <span class="nav-icon" v-html="item.icon"></span>
        <transition name="fade">
          <span v-if="!isCollapsed" class="nav-label">{{ item.label }}</span>
        </transition>
        <span v-if="!isCollapsed && item.badge" class="nav-badge">{{ item.badge }}</span>
      </div>
    </nav>

    <!-- User Menu -->
    <div class="sidebar-footer">
      <div class="user-info">
        <div class="user-avatar">
          <span>{{ userInitials }}</span>
        </div>
        <transition name="fade">
          <div v-if="!isCollapsed" class="user-details">
            <div class="user-name">{{ user?.name || 'Usuario' }}</div>
            <div class="user-role">{{ userRoleName }}</div>
          </div>
        </transition>
      </div>
      <button v-if="!isCollapsed" @click="$emit('logout')" class="logout-btn" title="Cerrar Sesión">
        Salir
      </button>
    </div>
    
    <!-- Mobile Overlay -->
    <div v-if="mobileOpen" class="mobile-overlay" @click="closeMobile"></div>
  </aside>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  currentView: {
    type: String,
    default: 'dashboard'
  },
  user: {
    type: Object,
    default: () => ({})
  },
  mobileOpen: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['navigate', 'logout', 'toggle-collapsed', 'close-mobile'])

const isCollapsed = ref(false)

// Navigation items with RBAC
const navigationItems = computed(() => {
  const role = props.user?.role
  
  // SuperAdmin SOLO ve Gestión de Usuarios
  if (role === 'superadmin') {
    return [{
      id: 'users',
      label: 'Gestión de Usuarios',
      icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/></svg>',
      visible: true
    }]
  }
  
  // Archivist y Reader ven el sistema completo
  const isArchivist = role === 'archivist'
  
  return [
    {
      id: 'dashboard',
      label: 'Dashboard',
      icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/></svg>',
      visible: true
    },
    {
      id: 'documents',
      label: 'Gestión de Documentos',
      icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5H2a.5.5 0 0 1-.5-.5v-2zM2 2v1h12V2H2z"/><path d="M0 5.5A.5.5 0 0 1 .5 5H2a.5.5 0 0 1 .5.5V14a.5.5 0 0 1-.5.5H.5a.5.5 0 0 1-.5-.5V5.5z"/></svg>',
      visible: true
    },
    {
      id: 'certifications',
      label: 'Certificaciones',
      icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M15.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 0 1 .708-.708L12.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0z"/><path d="M1 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/></svg>',
      visible: isArchivist,
      badge: null
    },
    {
      id: 'audit',
      label: 'Auditoría',
      icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>',
      visible: true
    },
    /*{
      id: 'anchors',
      label: 'Anclas Blockchain',
      icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/></svg>',
      visible: isArchivist,
      badge: null
    },*/
    {
      id: 'reports',
      label: 'Reportes',
      icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M14 4.5V14a2 2 0 0 1-2 2v-1a1 1 0 0 0 1-1V4.5h-2A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v9H2V2a2 2 0 0 1 2-2h5.5L14 4.5z"/></svg>',
      visible: isArchivist,
      badge: null
    },
    {
      id: 'settings',
      label: 'Configuración',
      icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/><path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319z"/></svg>',
      visible: true
    }
  ].filter(item => item.visible)
})

const userInitials = computed(() => {
  const name = props.user?.name || 'U'
  const parts = name.split(' ')
  if (parts.length >= 2) {
    return parts[0][0] + parts[1][0]
  }
  return name.substring(0, 2).toUpperCase()
})

const userRoleName = computed(() => {
  const roleMap = {
    'superadmin': 'Administrador',
    'archivist': 'Archivista',
    'reader': 'Lector'
  }
  return roleMap[props.user?.role] || 'Usuario'
})

function toggleSidebar() {
  isCollapsed.value = !isCollapsed.value
  emit('toggle-collapsed', isCollapsed.value)
}

function handleNavigate(viewId) {
  emit('navigate', viewId)
  // En móvil, cerrar el sidebar después de navegar
  if (window.innerWidth <= 768) {
    closeMobile()
  }
}

function closeMobile() {
  emit('close-mobile')
}
</script>

<style scoped>
.app-sidebar {
  position: fixed;
  left: 0;
  top: 75px;
  height: calc(100vh - 75px);
  width: 280px;
  background: linear-gradient(180deg, #2d3e2b 0%, #1f2b1e 100%);
  color: white;
  display: flex;
  flex-direction: column;
  box-shadow: 4px 0 12px rgba(0, 0, 0, 0.15);
  transition: width 0.3s ease, transform 0.3s ease;
  z-index: 1000;
  overflow: hidden;
}

.app-sidebar.collapsed {
  width: 80px;
}

/* Header */
.sidebar-header {
  padding: 1.5rem 1rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  min-height: 80px;
}

.logo-container {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex: 1;
  min-width: 0;
}

.logo-icon {
  font-size: 2rem;
  flex-shrink: 0;
}

.logo-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.logo-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: white;
  letter-spacing: -0.025em;
  white-space: nowrap;
}

.logo-subtitle {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.7);
  white-space: nowrap;
}

.toggle-btn {
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: white;
  width: 35px;
  height: 38px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.8rem;
  font-weight: 700;
  position: absolute;
  right: -4px;
  top: 165px;
  z-index: 50;
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

.toggle-btn:hover {
  background: rgba(255, 255, 255, 0.2);
}

/* Navigation */
.sidebar-nav {
  flex: 1;
  padding: 1rem 0.5rem;
  overflow-y: auto;
  overflow-x: hidden;
}

.sidebar-nav::-webkit-scrollbar {
  width: 4px;
}

.sidebar-nav::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
  border-radius: 2px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
  margin-bottom: 0.5rem;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  color: rgba(255, 255, 255, 0.8);
  white-space: nowrap;
}

.nav-item:hover:not(.disabled) {
  background: rgba(255, 255, 255, 0.1);
  color: white;
}

.nav-item.active {
  background: #556b2f;
  color: white;
  font-weight: 600;
}

.nav-item.active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: #8ab540;
  border-radius: 0 4px 4px 0;
}

.nav-item.disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.nav-icon {
  font-size: 1.25rem;
  flex-shrink: 0;
  width: 24px;
  text-align: center;
}

.nav-label {
  flex: 1;
  font-size: 0.938rem;
}

.nav-badge {
  background: #ef4444;
  color: white;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.125rem 0.5rem;
  border-radius: 12px;
  min-width: 20px;
  text-align: center;
}

/* Footer */
.sidebar-footer {
  padding: 1rem;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.user-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #556b2f;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.875rem;
  flex-shrink: 0;
  color: white;
}

.user-details {
  flex: 1;
  min-width: 0;
}

.user-name {
  font-size: 0.875rem;
  font-weight: 600;
  color: white;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.6);
}

.logout-btn {
  width: 100%;
  padding: 0.625rem;
  background: rgba(239, 68, 68, 0.2);
  border: 1px solid rgba(239, 68, 68, 0.4);
  color: #fca5a5;
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.logout-btn:hover {
  background: rgba(239, 68, 68, 0.3);
  border-color: rgba(239, 68, 68, 0.6);
  color: white;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Mobile */
.mobile-overlay {
  display: none;
}

@media (max-width: 768px) {
  .app-sidebar {
    transform: translateX(-100%);
    width: 280px !important;
  }
  
  .app-sidebar.collapsed {
    width: 280px !important;
  }
  
  .app-sidebar.mobile-open {
    transform: translateX(0);
  }
  
  .mobile-overlay {
    display: block;
    position: fixed;
    top: 75px;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
  }
  
  .toggle-btn {
    display: none;
  }
}
</style>
