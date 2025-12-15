<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import DocumentList from '../documents/DocumentList.vue'

const props = defineProps({
  headers: {
    type: Object,
    required: true
  },
  catalogs: {
    type: Object,
    required: true
  },
  role: {
    type: String,
    default: 'reader'
  }
})

const router = useRouter()
const route = useRoute()

const filterContext = ref({
  seccion: null,
  subseccion: null,
  tipo: null
})

const loading = ref(true)

// Computed filters for DocumentList
const activeFilters = computed(() => {
  const filters = {}
  if (route.query.seccion_id) filters.seccion_id = route.query.seccion_id
  if (route.query.subseccion_id) filters.subseccion_id = route.query.subseccion_id
  if (route.query.tipo_documento_id) filters.tipo_documento_id = route.query.tipo_documento_id
  return filters
})

const filterTitle = computed(() => {
  if (filterContext.value.subseccion) {
    return `Documentos en: ${filterContext.value.subseccion.nombre}`
  }
  if (filterContext.value.seccion) {
    return `Documentos de: ${filterContext.value.seccion.nombre}`
  }
  if (filterContext.value.tipo) {
    return `Documentos tipo: ${filterContext.value.tipo.nombre}`
  }
  return 'Documentos'
})

const filterSubtitle = computed(() => {
  if (filterContext.value.subseccion) {
    return `Sección: ${filterContext.value.seccion?.nombre || 'N/A'}`
  }
  if (filterContext.value.seccion) {
    return filterContext.value.seccion.descripcion || ''
  }
  if (filterContext.value.tipo) {
    return filterContext.value.tipo.descripcion || ''
  }
  return ''
})

onMounted(async () => {
  await loadContext()
})

async function loadContext() {
  try {
    loading.value = true
    
    // Cargar información de sección si existe
    if (route.query.seccion_id) {
      const secRes = await fetch(`/api/categories/secciones`, { headers: props.headers })
      const secData = await secRes.json()
      if (secData.ok) {
        filterContext.value.seccion = secData.data.find(s => s.id == route.query.seccion_id)
      }
    }
    
    // Cargar información de subsección si existe
    if (route.query.subseccion_id && route.query.seccion_id) {
      const subRes = await fetch(`/api/categories/secciones/${route.query.seccion_id}/subsecciones`, { 
        headers: props.headers 
      })
      const subData = await subRes.json()
      if (subData.ok) {
        filterContext.value.subseccion = subData.data.find(s => s.id == route.query.subseccion_id)
        if (subData.seccion) {
          filterContext.value.seccion = subData.seccion
        }
      }
    }
    
    // Cargar información de tipo de documento si existe
    if (route.query.tipo_documento_id) {
      const tipoRes = await fetch(`/api/categories/tipos-documento`, { headers: props.headers })
      const tipoData = await tipoRes.json()
      if (tipoData.ok) {
        filterContext.value.tipo = tipoData.data.find(t => t.id == route.query.tipo_documento_id)
      }
    }
  } catch (error) {
    console.error('Error loading context:', error)
  } finally {
    loading.value = false
  }
}

function navigateBack() {
  if (filterContext.value.subseccion) {
    router.push(`/secciones/${route.query.seccion_id}/subsecciones`)
  } else if (filterContext.value.seccion) {
    router.push('/secciones')
  } else if (filterContext.value.tipo) {
    router.push('/tipos')
  } else {
    router.push('/dashboard')
  }
}
</script>

<template>
  <div class="documentos-filtered-view">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
      <router-link to="/dashboard">Inicio</router-link>
      <span class="separator">›</span>
      
      <template v-if="filterContext.subseccion">
        <router-link to="/secciones">Secciones</router-link>
        <span class="separator">›</span>
        <router-link :to="`/secciones/${route.query.seccion_id}/subsecciones`">
          {{ filterContext.seccion?.nombre || 'Sección' }}
        </router-link>
        <span class="separator">›</span>
        <span class="current">{{ filterContext.subseccion.nombre }}</span>
      </template>
      
      <template v-else-if="filterContext.seccion">
        <router-link to="/secciones">Secciones</router-link>
        <span class="separator">›</span>
        <span class="current">{{ filterContext.seccion.nombre }}</span>
      </template>
      
      <template v-else-if="filterContext.tipo">
        <router-link to="/tipos">Tipos de Documento</router-link>
        <span class="separator">›</span>
        <span class="current">{{ filterContext.tipo.nombre }}</span>
      </template>
    </nav>

    <!-- Header con contexto -->
    <div class="view-header">
      <button @click="navigateBack" class="btn-back">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Volver
      </button>
      
      <div class="header-content">
        <h1>{{ filterTitle }}</h1>
        <p v-if="filterSubtitle" class="subtitle">{{ filterSubtitle }}</p>
      </div>
    </div>

    <!-- Filter Info Card -->
    <div class="filter-info-card">
      <div class="filter-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
        </svg>
      </div>
      <div class="filter-details">
        <span class="filter-label">Filtro activo:</span>
        <span class="filter-value">{{ filterTitle }}</span>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Cargando documentos...</p>
    </div>

    <!-- Document List con filtros -->
    <div v-else>
      <DocumentList
        :headers="headers"
        :catalogs="catalogs"
        :role="role"
        :initial-filters="activeFilters"
        :readonly="role === 'reader'"
      />
    </div>
  </div>
</template>

<style scoped>
.documentos-filtered-view {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  font-size: 0.875rem;
  color: #6b7280;
}

.breadcrumb a {
  color: #556b2f;
  text-decoration: none;
  transition: color 0.2s;
}

.breadcrumb a:hover {
  color: #6b8e23;
  text-decoration: underline;
}

.separator {
  color: #9ca3af;
}

.current {
  color: #1f2937;
  font-weight: 500;
}

.view-header {
  margin-bottom: 2rem;
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.btn-back {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  color: #556b2f;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-back:hover {
  background: #f9fafb;
  border-color: #556b2f;
}

.btn-back svg {
  flex-shrink: 0;
}

.header-content {
  flex: 1;
}

.header-content h1 {
  font-size: 2rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 0.5rem;
}

.subtitle {
  font-size: 1rem;
  color: #6b7280;
  margin: 0;
}

.filter-info-card {
  background: linear-gradient(135deg, #f0f4e8 0%, #ffffff 100%);
  border: 1px solid #556b2f;
  border-left: 4px solid #556b2f;
  border-radius: 12px;
  padding: 1rem 1.5rem;
  margin-bottom: 2rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.filter-icon {
  width: 48px;
  height: 48px;
  background: #556b2f;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  flex-shrink: 0;
}

.filter-details {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.filter-label {
  font-size: 0.875rem;
  color: #6b7280;
  font-weight: 500;
}

.filter-value {
  font-size: 1.125rem;
  color: #1f2937;
  font-weight: 600;
}

.loading-state {
  text-align: center;
  padding: 4rem 0;
}

.spinner {
  width: 48px;
  height: 48px;
  border: 4px solid #e5e7eb;
  border-top-color: #556b2f;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.loading-state p {
  color: #6b7280;
  margin: 0;
}

@media (max-width: 640px) {
  .documentos-filtered-view {
    padding: 1rem;
  }
  
  .view-header {
    flex-direction: column;
  }
  
  .header-content h1 {
    font-size: 1.5rem;
  }
}
</style>
