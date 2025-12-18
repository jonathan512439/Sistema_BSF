<script setup>
import { ref, onMounted, computed, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppTopbar from './layout/AppTopbar.vue'
import AppSidebar from './layout/AppSidebar.vue'
import DashboardView from './views/DashboardView.vue'
import DocumentManagementView from './views/DocumentManagementView.vue'
import AuditView from './views/AuditView.vue'
import CertificationsView from './views/CertificationsView.vue'
import ReportsView from './views/ReportsView.vue'
import SettingsView from './views/SettingsView.vue'
import UserManagementView from './views/UserManagementView.vue'
import UbicacionesView from './views/UbicacionesView.vue'
import AnchorDashboard from './audit/AnchorDashboard.vue'
import DeletedDocumentsView from './views/DeletedDocumentsView.vue'
import ToastContainer from './ui/ToastContainer.vue'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const user = ref(null)
const catalogs = ref({
  tipos_documento: [],
  secciones: [],
  subsecciones: [],
  gestiones: [],
  ubicaciones: [],
  almacenes: [],
  motivos_acceso: [],
})

const loading = ref(true)
const errorMsg = ref('')
const currentView = ref('dashboard') 
const sidebarCollapsed = ref(false)
const mobileSidebarOpen = ref(false)

// Sistema de notificaciones
const { success, error, info, warning } = useToast()

const apiHeaders = computed(() => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || ''
  return {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': csrfToken,
  }
})

const currentViewComponent = computed(() => {
  // SuperAdmin solo ve gestión de usuarios
  if (user.value?.role === 'superadmin') {
    return UserManagementView
  }
  
  const components = {
    'dashboard': DashboardView,
    'documents': DocumentManagementView,
    'audit': AuditView,
    'anchors': AnchorDashboard,
    'ubicaciones': UbicacionesView,
    'certifications': CertificationsView,
    'reports': ReportsView,
    'settings': SettingsView,
    'users': UserManagementView,
    'deleted': DeletedDocumentsView
  }
  return components[currentView.value] || DashboardView
})

// Estado para forzar navegación legacy (bypass del router)
const forceNavigationMode = ref(false)

// Detectar si debemos usar router-view (rutas jerárquicas)
// PERO: si forceNavigationMode está activo, siempre usar legacy
const isRouterView = computed(() => {
  if (forceNavigationMode.value) {
    return false // Forzar legacy view
  }
  const routerPaths = ['/secciones', '/tipos', '/documentos']
  return routerPaths.some(path => route.path.startsWith(path))
})

const can = (permission) => {
  if (!user.value) return false
  
  const role = user.value.role
  
  // Admin can do everything
  if (role === 'superadmin') return true
  
  // Archivist permissions
  if (role === 'archivist') {
    return ['doc.upload', 'doc.edit', 'doc.validate', 'doc.seal', 'doc.move', 'doc.delete'].includes(permission)
  }
  
  // Reader permissions (read-only)
  if (role === 'reader') {
    return ['doc.view'].includes(permission)
  }
  
  return false
}

async function loadUserAndCatalogs() {
  loading.value = true
  errorMsg.value = ''
  try {
    const rUser = await fetch('/api/me', {
      credentials: 'include',
      headers: apiHeaders.value,
    })
    if (!rUser.ok) {
      throw new Error('No se pudo obtener el usuario actual (HTTP ' + rUser.status + ')')
    }
    user.value = await rUser.json()
    
    // Si es SuperAdmin, redirigir a gestión de usuarios
    if (user.value.role === 'superadmin') {
      currentView.value = 'users'
    }

    const rCat = await fetch('/api/catalogs', {
      credentials: 'include',
      headers: apiHeaders.value,
    })
    if (!rCat.ok) {
      throw new Error('No se pudieron obtener los catálogos (HTTP ' + rCat.status + ')')
    }
    catalogs.value = await rCat.json()
    
    // Mostrar notificación de bienvenida
    info(`Bienvenido, ${user.value.name}`, 'Sesión iniciada correctamente')
    
  } catch (e) {
    console.error(e)
    errorMsg.value = e.message || 'Error inesperado al cargar datos iniciales.'
    error('Error al cargar aplicación', errorMsg.value)
  } finally {
    loading.value = false
  }
}

/**
 * Maneja navegación del sidebar
 * SOLUCIÓN DEFINITIVA: Fuerza navegación a /dashboard y activa modo legacy
 */
async function handleNavigation(viewId) {
  // SuperAdmin solo puede navegar a 'users'
  if (user.value?.role === 'superadmin' && viewId !== 'users') {
    return
  }
  
  // PASO 1: Activar modo de navegación forzada (esto desactiva router-view)
  forceNavigationMode.value = true
  
  // PASO 2: Cambiar la vista INMEDIATAMENTE
  currentView.value = viewId
  
  // PASO 3: Forzar navegación del router a /dashboard (en background)
  // Esto es asíncrono, no bloqueamos la UI
  const currentPath = route.path
  if (currentPath !== '/dashboard') {
    // Usar nextTick para asegurar que el cambio de vista se procese primero
    await nextTick()
    
    try {
      await router.push('/dashboard')
    } catch (err) {
      // Ignorar errores de navegación
      if (err.name !== 'NavigationDuplicated') {
        console.warn('Router navigation warning:', err)
      }
    }
  }
  
  // PASO 4: Cerrar sidebar móvil
  if (mobileSidebarOpen.value) {
    mobileSidebarOpen.value = false
  }
  
  // PASO 5: Desactivar modo forzado después de un breve delay
  // Esto permite que el router se estabilice
  setTimeout(() => {
    forceNavigationMode.value = false
  }, 100)
}

function handleToggleCollapsed(collapsed) {
  sidebarCollapsed.value = collapsed
}

function handleToggleMobile() {
  mobileSidebarOpen.value = !mobileSidebarOpen.value
}

function handleCloseMobile() {
  mobileSidebarOpen.value = false
}

async function handleLogout() {
  try {
    info('Cerrando sesión...', 'Por favor espera')
    
    const response = await fetch('/logout', {
      method: 'POST',
      headers: apiHeaders.value,
      credentials: 'include'
    })
    
    if (response.ok) {
      success('Sesión cerrada correctamente', 'Hasta pronto!')
      // Esperar un poco para que el usuario vea la notificación
      setTimeout(() => {
        window.location.href = '/login'
      }, 1000)
    } else {
      throw new Error('Error al cerrar sesión')
    }
  } catch (e) {
    console.error('Logout error:', e)
    error('No se pudo cerrar sesión', 'Por favor, intenta de nuevo')
  }
}

onMounted(loadUserAndCatalogs)
</script>

<template>
  <div class="app-container">
    <!-- TopBar always visible -->
    <AppTopbar
      :user="user"
      @toggle-menu="handleToggleMobile"
      @navigate="handleNavigation"
      @logout="handleLogout"
    />

    <!-- Main content area -->
    <div v-if="loading" class="app-loading">
      <div class="spinner"></div>
      <p>Cargando sistema...</p>
    </div>

    <div v-else-if="errorMsg" class="app-error">
      <h2>⚠️ Error</h2>
      <p>{{ errorMsg }}</p>
      <button @click="loadUserAndCatalogs">Reintentar</button>
    </div>

    <div v-else class="app-layout">
      <AppSidebar
        :current-view="currentView"
        :user="user"
        :mobile-open="mobileSidebarOpen"
        @navigate="handleNavigation"
        @logout="handleLogout"
        @toggle-collapsed="handleToggleCollapsed"
        @close-mobile="handleCloseMobile"
      />
      
      <main class="main-content">
      <!-- 
        SISTEMA HÍBRIDO DE NAVEGACIÓN:
        - Si la ruta actual es del router (/secciones, /tipos, /documentos) → usa router-view
        - Si no → usa sistema legacy de componentes
      -->
      
      <!-- Router View para vistas jerárquicas -->
      <router-view
        v-show="isRouterView"
        :user="user"
        :headers="apiHeaders"
        :catalogs="catalogs"
      />
      
      <!-- Component View para vistas legacy -->
      <component
        v-show="!isRouterView"
        :is="currentViewComponent"
        :user="user"
        :headers="apiHeaders"
        :catalogs="catalogs"
        :can="can"
      />
    </main>
    </div>
    
    <!-- Toast Container Global -->
    <ToastContainer />
  </div>
</template>

<style>
* {
  box-sizing: border-box;
}

body {
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
  background: #f3f4f6;
}

.app-container {
  width: 100%;
  height: 100vh;
  overflow: hidden;
}

.app-loading,
.app-error {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: calc(100vh - 75px);
  margin-top: 75px;
  gap: 1rem;
}

.spinner {
  width: 50px;
  height: 50px;
  border: 4px solid #e5e7eb;
  border-top-color: #556b2f;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.app-loading p {
  color: #6b7280;
  font-size: 1rem;
}

.app-error {
  text-align: center;
  color: #dc2626;
}

.app-error button {
  padding: 0.75rem 1.5rem;
  background: #dc2626;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

.app-layout {
  display: flex;
  height: 100vh;
  overflow: hidden;
}

.main-content {
  flex: 1;
  margin-left: 280px;
  margin-top: 75px; /* Topbar height */
  overflow-y: auto;
  background: #f9fafb;
  transition: margin-left 0.3s ease;
  min-height: calc(100vh - 75px);
}

.main-content.sidebar-collapsed {
  margin-left: 80px;
}

/* Responsive */
@media (max-width: 768px) {
  .main-content {
    margin-left: 0 !important;
  }
  
  .main-content.sidebar-collapsed {
    margin-left: 0;
  }
}
</style>
