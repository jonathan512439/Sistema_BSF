<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import CategoryCard from '../ui/CategoryCard.vue'

const props = defineProps({
  headers: {
    type: Object,
    required: true
  }
})

const router = useRouter()
const secciones = ref([])
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
  await loadSecciones()
})

async function loadSecciones() {
  try {
    loading.value = true
    const response = await fetch('/api/categories/secciones', {
      headers: props.headers
    })
    
    const data = await response.json()
    
    if (data.ok) {
      secciones.value = data.data
    } else {
      error.value = data.message || 'Error al cargar secciones'
    }
  } catch (err) {
    error.value = err.message
    console.error('Error loading secciones:', err)
  } finally {
    loading.value = false
  }
}

function navigateToSubsecciones(seccion) {
  router.push(`/secciones/${seccion.id}/subsecciones`)
}
</script>

<template>
  <div class="secciones-view">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
      <router-link to="/dashboard">Inicio</router-link>
      <span class="separator">›</span>
      <span class="current">Secciones</span>
    </nav>
    
    <!-- Header -->
    <div class="view-header">
      <h1>Explorar por Sección</h1>
      <p class="subtitle">Selecciona una sección para ver sus subsecciones y documentos</p>
    </div>
    
    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Cargando secciones...</p>
    </div>
    
    <!-- Error -->
    <div v-else-if="error" class="error-state">
      <p>⚠️ {{ error }}</p>
      <button @click="loadSecciones" class="btn-retry">Reintentar</button>
    </div>
    
    <!-- Grid de Secciones -->
    <div v-else class="secciones-grid">
      <CategoryCard
        v-for="seccion in secciones"
        :key="seccion.id"
        :title="seccion.nombre"
        :subtitle="seccion.descripcion"
        :icon="getSeccionIcon(seccion.id)"
        :count="seccion.documentos_count"
        :subsecciones="seccion.subsecciones_count"
        @click="navigateToSubsecciones(seccion)"
      />
    </div>
    
    <!-- Empty State -->
    <div v-if="!loading && !error && secciones.length === 0" class="empty-state">
      <p>No hay secciones disponibles</p>
    </div>
  </div>
</ template>

<script>
// Iconos por sección BSF (texto en lugar de emojis)
function getSeccionIcon(seccionId) {
  const icons = {
    1: 'S1', // Comando y Dirección
    2: 'S2', // Administrativa
    3: 'S3', // Personal
    4: 'S4', // Inteligencia
    5: 'S5', // Planeamiento y Operaciones
    6: 'S6', // Supervisión y Control
    7: 'S7', // Jurídica
    8: 'S8', // Bienestar Social
    9: 'S9'  // Operaciones de Seguridad
  }
  return icons[seccionId] || 'S'
}
</script>

<style scoped>
.secciones-view {
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
}

.view-header h1 {
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

.secciones-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
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

.error-state {
  text-align: center;
  padding: 4rem 0;
}

.error-state p {
  color: #dc2626;
  margin: 0 0 1rem;
  font-size: 1.125rem;
}

.btn-retry {
  padding: 0.75rem 1.5rem;
  background: #556b2f;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-retry:hover {
  background: #6b8e23;
}

.empty-state {
  text-align: center;
  padding: 4rem 0;
  color: #9ca3af;
  font-size: 1.125rem;
}

@media (max-width: 1024px) {
  .secciones-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .secciones-view {
    padding: 1rem;
  }
  
  .secciones-grid {
    grid-template-columns: 1fr;
  }
  
  .view-header h1 {
    font-size: 1.5rem;
  }
}
</style>
