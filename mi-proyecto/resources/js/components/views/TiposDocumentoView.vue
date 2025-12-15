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
const tipos = ref([])
const agrupados = ref([])
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
  await loadTipos()
})

async function loadTipos() {
  try {
    loading.value = true
    const response = await fetch('/api/categories/tipos-documento', {
      headers: props.headers
    })
    
    const data = await response.json()
    
    if (data.ok) {
      tipos.value = data.data
      agrupados.value = data.agrupados
    } else {
      error.value = data.message || 'Error al cargar tipos de documento'
    }
  } catch (err) {
    error.value = err.message
    console.error('Error loading tipos:', err)
  } finally {
    loading.value = false
  }
}

function navigateToDocumentos(tipo) {
  router.push({
    name: 'documentos-filtered',
    query: {
      tipo_documento_id: tipo.id
    }
  })
}

function getCategoriaIcon(categoria) {
  const icons = {
    'Dirección': 'DR',
    'Administrativo': 'AD',
    'Informe': 'IN',
    'Operativo': 'OP',
    'Planeamiento': 'PL',
    'Legal': 'LG',
    'Personal': 'PS',
    'Logístico': 'LO'
  }
  return icons[categoria] || 'DC'
}
</script>

<template>
  <div class="tipos-view">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
      <router-link to="/dashboard">Inicio</router-link>
      <span class="separator">›</span>
      <span class="current">Tipos de Documento</span>
    </nav>
    
    <!-- Header -->
    <div class="view-header">
      <h1>Explorar por Tipo de Documento</h1>
      <p class="subtitle">Selecciona un tipo de documento para ver todos los documentos de esa clase</p>
    </div>
    
    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Cargando tipos de documento...</p>
    </div>
    
    <!-- Error -->
    <div v-else-if="error" class="error-state">
      <p>⚠️ {{ error }}</p>
      <button @click="loadTipos" class="btn-retry">Reintentar</button>
    </div>
    
    <!-- Tipos Agrupados por Categoría -->
    <div v-else class="tipos-content">
      <div 
        v-for="grupo in agrupados" 
        :key="grupo.categoria"
        class="categoria-group"
      >
        <div class="categoria-header">
          <span class="categoria-icon">{{ getCategoriaIcon(grupo.categoria) }}</span>
          <h2>{{ grupo.categoria }}</h2>
          <span class="categoria-count">{{ grupo.total_documentos }} documentos</span>
        </div>
        
        <div class="tipos-grid">
          <CategoryCard
            v-for="tipo in grupo.tipos"
            :key="tipo.id"
            :title="tipo.nombre"
            :subtitle="tipo.descripcion"
            :icon="getCategoriaIcon(grupo.categoria)"
            :count="tipo.documentos_count"
            @click="navigateToDocumentos(tipo)"
          />
        </div>
      </div>
    </div>
    
    <!-- Empty State -->
    <div v-if="!loading && !error && tipos.length === 0" class="empty-state">
      <p>No hay tipos de documento disponibles</p>
    </div>
  </div>
</template>

<style scoped>
.tipos-view {
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

.tipos-content {
  display: flex;
  flex-direction: column;
  gap: 3rem;
}

.categoria-group {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 1.5rem;
  background: #fafafa;
}

.categoria-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #e5e7eb;
}

.categoria-icon {
  font-size: 2rem;
  width: 48px;
  height: 48px;
  background: white;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #e5e7eb;
}

.categoria-header h2 {
  flex: 1;
  font-size: 1.5rem;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.categoria-count {
  font-size: 0.875rem;
  color: #6b7280;
  background: white;
  padding: 0.25rem 0.75rem;
  border-radius: 6px;
  border: 1px solid #e5e7eb;
}

.tipos-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
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
  .tipos-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .tipos-view {
    padding: 1rem;
  }
  
  .tipos-grid {
    grid-template-columns: 1fr;
  }
  
  .view-header h1 {
    font-size: 1.5rem;
  }
  
  .categoria-header {
    flex-wrap: wrap;
  }
}
</style>
