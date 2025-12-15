<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import CategoryCard from '../ui/CategoryCard.vue'

const props = defineProps({
  headers: {
    type: Object,
    required: true
  }
})

const router = useRouter()
const route = useRoute()

const seccionId = computed(() => route.params.seccionId)
const seccion = ref(null)
const subsecciones = ref([])
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
  await loadSubsecciones()
})

async function loadSubsecciones() {
  try {
    loading.value = true
    const response = await fetch(`/api/categories/secciones/${seccionId.value}/subsecciones`, {
      headers: props.headers
    })
    
    const data = await response.json()
    
    if (data.ok) {
      seccion.value = data.seccion
      subsecciones.value = data.data
    } else {
      error.value = data.message || 'Error al cargar subsecciones'
    }
  } catch (err) {
    error.value = err.message
    console.error('Error loading subsecciones:', err)
  } finally {
    loading.value = false
  }
}

function navigateToDocumentos(subseccion) {
  router.push({
    name: 'documentos-filtered',
    query: {
      seccion_id: seccionId.value,
      subseccion_id: subseccion.id
    }
  })
}
</script>

<template>
  <div class="subsecciones-view">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
      <router-link to="/dashboard">Inicio</router-link>
      <span class="separator">›</span>
      <router-link to="/secciones">Secciones</router-link>
      <span class="separator">›</span>
      <span class="current">{{ seccion?.nombre || '...' }}</span>
    </nav>
    
    <!-- Header -->
    <div class="view-header">
      <h1>{{ seccion?.nombre || 'Subsecciones' }}</h1>
      <p class="subtitle">Selecciona una subsección para ver sus documentos</p>
    </div>
    
    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Cargando subsecciones...</p>
    </div>
    
    <!-- Error -->
    <div v-else-if="error" class="error-state">
      <p>Error: {{ error }}</p>
      <button @click="loadSubsecciones" class="btn-retry">Reintentar</button>
    </div>
    
    <!-- Grid de Subsecciones -->
    <div v-else class="subsecciones-grid">
      <CategoryCard
        v-for="subseccion in subsecciones"
        :key="subseccion.id"
        :title="subseccion.nombre"
        :subtitle="subseccion.descripcion"
        icon="📁"
        :count="subseccion.documentos_count"
        @click="navigateToDocumentos(subseccion)"
      />
    </div>
    
    <!-- Empty State -->
    <div v-if="!loading && !error && subsecciones.length === 0" class="empty-state">
      <p>Esta sección no tiene subsecciones disponibles</p>
    </div>
  </div>
</template>

<style scoped>
.subsecciones-view {
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

.subsecciones-grid {
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
  .subsecciones-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .subsecciones-view {
    padding: 1rem;
  }
  
  .subsecciones-grid {
    grid-template-columns: 1fr;
  }
  
  .view-header h1 {
    font-size: 1.5rem;
  }
}
</style>
