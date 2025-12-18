<template>
  <div class="stats-container">
    <!-- Header -->
    <div class="stats-header">
      <div>
        <h2 class="stats-title">Sistema de Gestión Documental</h2>
        <p class="stats-subtitle">Resumen Ejecutivo del Sistema</p>
      </div>
      <button v-if="!loading" @click="fetchStats" class="btn-refresh" title="Actualizar">
        Actualizar
      </button>
    </div>
    
    <div v-if="loading" class="stats-loading">
      <div class="spinner"></div>
      <p>Cargando estadísticas...</p>
    </div>
    
    <div v-else-if="error" class="stats-error">
      <p>⚠️ Error al cargar estadísticas</p>
      <button @click="fetchStats" class="btn-retry">Reintentar</button>
    </div>
    
    <div v-else class="stats-dashboard">
      <!-- Métricas Principales -->
      <div class="metrics-grid">
        <div class="metric-card primary">
          <div class="metric-icon">📊</div>
          <div class="metric-content">
            <div class="metric-value">{{ stats.total_documentos }}</div>
            <div class="metric-label">Total de Documentos</div>
          </div>
        </div>
        
        <div v-if="!isReader" class="metric-card success">
          <div class="metric-icon">🔓</div>
          <div class="metric-content">
            <div class="metric-value">{{ stats.no_confidenciales }}</div>
            <div class="metric-label">No Confidenciales</div>
          </div>
        </div>
        
        <div v-if="!isReader" class="metric-card warning">
          <div class="metric-icon">🔒</div>
          <div class="metric-content">
            <div class="metric-value">{{ stats.confidenciales }}</div>
            <div class="metric-label">Confidenciales</div>
          </div>
        </div>
      </div>

      <!-- Grids de Distribución 
      <div class="distribution-grid">
       
        <div class="info-card">
          <div class="card-header">
            <h3 class="card-title">Distribución por Estado</h3>
          </div>
          <div class="card-body">
            <div v-for="item in stats.por_estado" :key="item.estado" class="info-row">
              <span class="info-label">
                <span :class="['status-dot', `dot-${item.estado}`]"></span>
                {{ formatEstado(item.estado) }}
              </span>
              <span class="info-value">{{ item.total }}</span>
            </div>
            <div v-if="!stats.por_estado?.length" class="empty-state">
              No hay datos disponibles
            </div>
          </div>
        </div> 

       
        <div class="info-card">
          <div class="card-header">
            <h3 class="card-title">Por Sección</h3>
          </div>
          <div class="card-body">
            <div v-for="item in stats.por_seccion.slice(0, 5)" :key="item.seccion" class="info-row">
              <span class="info-label">{{ item.seccion || 'Sin sección' }}</span>
              <span class="info-value">{{ item.total }}</span>
            </div>
            <div v-if="!stats.por_seccion?.length" class="empty-state">
              No hay datos disponibles
            </div>
          </div>
        </div>

       
        <div class="info-card">
          <div class="card-header">
            <h3 class="card-title">Por Tipo de Documento</h3>
          </div>
          <div class="card-body">
            <div v-for="item in stats.por_tipo.slice(0, 5)" :key="item.tipo" class="info-row">
              <span class="info-label">{{ item.tipo || 'Sin tipo' }}</span>
              <span class="info-value">{{ item.total }}</span>
            </div>
            <div v-if="!stats.por_tipo?.length" class="empty-state">
              No hay datos disponibles
            </div>
          </div>
        </div>
      </div> -->

      <!-- Actividad Reciente -->
      <div class="activity-card">
        <div class="card-header">
          <h3 class="card-title">Actividad Reciente</h3>
          <span class="card-subtitle">Últimos documentos registrados en el sistema</span>
        </div>
        <div class="activity-list">
          <div v-for="doc in stats.ultimos_subidos" :key="doc.id" class="activity-item">
            <div class="activity-main">
              <div class="activity-title">
                {{ doc.titulo || doc.numero_documento || `Documento #${doc.id}` }}
              </div>
              <div class="activity-meta">
                <span :class="['badge-estado', `estado-${doc.estado}`]">
                  {{ formatEstado(doc.estado) }}
                </span>
                <span v-if="doc.is_confidential" class="badge-confidential">
                  Confidencial
                </span>
              </div>
            </div>
            <div class="activity-date">
              {{ formatDate(doc.created_at) }}
            </div>
          </div>
          <div v-if="!stats.ultimos_subidos?.length" class="empty-state">
            No hay actividad reciente
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const props = defineProps({
  headers: { type: Object, required: true },
  isReader: { type: Boolean, default: false }
})

const stats = ref({
  total_documentos: 0,
  no_confidenciales: 0,
  confidenciales: 0,
  por_estado: [],
  por_seccion: [],
  por_tipo: [],
  ultimos_subidos: []
})

const loading = ref(true)
const error = ref(null)

async function fetchStats() {
  loading.value = true
  error.value = null
  
  try {
    const response = await fetch('/api/documentos/statistics', {
      headers: props.headers,
      credentials: 'include'
    })
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`)
    }
    
    const data = await response.json()
    stats.value = data
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function formatEstado(estado) {
  const map = {
    'capturado': 'Capturado',
    'procesado_ocr': 'Procesado OCR',
    'validado': 'Validado',
    'sellado': 'Sellado',
    'custodio': 'En Custodia'
  }
  return map[estado] || estado
}

function formatDate(dateString) {
  if (!dateString) return '—'
  const date = new Date(dateString)
  return date.toLocaleDateString('es-ES', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

onMounted(() => {
  fetchStats()
})
</script>

<style scoped>
.stats-container {
  background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
  border-radius: 16px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 2rem;
  margin-bottom: 2rem;
}

.stats-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding-bottom: 1.5rem;
  margin-bottom: 2rem;
  border-bottom: 2px solid #556b2f;
}

.stats-title {
  font-size: 1.875rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 0.25rem 0;
  letter-spacing: -0.025em;
}

.stats-subtitle {
  font-size: 0.938rem;
  color: #6b7280;
  margin: 0;
  font-weight: 400;
}

.btn-refresh {
  padding: 0.625rem 1.25rem;
  background: white;
  color: #556b2f;
  border: 2px solid #556b2f;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-refresh:hover {
  background: #556b2f;
  color: white;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(85, 107, 47, 0.2);
}

.stats-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 1rem;
  gap: 1rem;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #e5e7eb;
  border-top-color: #556b2f;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.stats-loading p {
  color: #6b7280;
  font-size: 0.938rem;
}

.stats-error {
  text-align: center;
  padding: 3rem 1rem;
}

.stats-error p {
  color: #dc2626;
  font-weight: 600;
  margin-bottom: 1rem;
}

.btn-retry {
  padding: 0.75rem 1.5rem;
  background: #dc2626;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-retry:hover {
  background: #b91c1c;
  transform: translateY(-2px);
}

.stats-dashboard {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* Métricas Principales */
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.25rem;
}

.metric-card {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1.25rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
  border-left: 4px solid;
  transition: all 0.2s;
}

.metric-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

.metric-card.primary {
  border-color: #556b2f;
  background: linear-gradient(135deg, #ffffff 0%, #f0f4e8 100%);
}

.metric-card.success {
  border-color: #059669;
  background: linear-gradient(135deg, #ffffff 0%, #ecfdf5 100%);
}

.metric-card.warning {
  border-color: #d97706;
  background: linear-gradient(135deg, #ffffff 0%, #fffbeb 100%);
}

.metric-icon {
  font-size: 2.5rem;
  opacity: 0.8;
}

.metric-content {
  flex: 1;
}

.metric-value {
  font-size: 2.25rem;
  font-weight: 700;
  color: #1f2937;
  line-height: 1;
  margin-bottom: 0.25rem;
}

.metric-label {
  font-size: 0.875rem;
  color: #6b7280;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* Grid de Distribución */
.distribution-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.25rem;
}

.info-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
  overflow: hidden;
  transition: all 0.2s;
}

.info-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.card-header {
  padding: 1.25rem 1.5rem;
  background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
  border-bottom: 1px solid #e5e7eb;
}

.card-title {
  font-size: 1rem;
  font-weight: 700;
  color: #374151;
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-size: 0.813rem;
}

.card-body {
  padding: 1rem 1.5rem 1.5rem;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 0;
  border-bottom: 1px solid #f3f4f6;
}

.info-row:last-child {
  border-bottom: none;
}

.info-label {
  font-size: 0.938rem;
  color: #4b5563;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

.dot-capturado {
  background: #3b82f6;
}

.dot-procesado_ocr {
  background: #f59e0b;
}

.dot-validado {
  background: #10b981;
}

.dot-sellado,
.dot-custodio {
  background: #16a34a;
}

.info-value {
  font-size: 1.125rem;
  font-weight: 700;
  color: #1f2937;
}

/* Actividad Reciente */
.activity-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
  overflow: hidden;
}

.card-subtitle {
  font-size: 0.813rem;
  color: #9ca3af;
  font-weight: 400;
  margin-left: 0.5rem;
}

.activity-list {
  padding: 1rem 1.5rem 1.5rem;
}

.activity-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border-radius: 8px;
  margin-bottom: 0.75rem;
  background: #f9fafb;
  transition: all 0.2s;
}

.activity-item:last-child {
  margin-bottom: 0;
}

.activity-item:hover {
  background: #f3f4f6;
  transform: translateX(4px);
}

.activity-main {
  flex: 1;
  min-width: 0;
}

.activity-title {
  font-size: 0.938rem;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 0.5rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.activity-meta {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.badge-estado {
  display: inline-block;
  padding: 0.25rem 0.625rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}

.estado-capturado {
  background: #dbeafe;
  color: #1e40af;
}

.estado-procesado_ocr {
  background: #fef3c7;
  color: #92400e;
}

.estado-validado {
  background: #d1fae5;
  color: #065f46;
}

.estado-sellado,
.estado-custodio {
  background: #dcfce7;
  color: #166534;
}

.badge-confidential {
  display: inline-block;
  padding: 0.25rem 0.625rem;
  background: #fee2e2;
  color: #991b1b;
  font-size: 0.75rem;
  font-weight: 600;
  border-radius: 12px;
}

.activity-date {
  font-size: 0.813rem;
  color: #9ca3af;
  white-space: nowrap;
  margin-left: 1rem;
}

.empty-state {
  text-align: center;
  padding: 2rem 1rem;
  color: #9ca3af;
  font-style: italic;
  font-size: 0.875rem;
}

@media (max-width: 768px) {
  .stats-container {
    padding: 1.5rem;
  }
  
  .stats-header {
    flex-direction: column;
    gap: 1rem;
    align-items: flex-start;
  }
  
  .stats-title {
    font-size: 1.5rem;
  }
  
  .btn-refresh {
    width: 100%;
    justify-content: center;
  }
  
  .metrics-grid {
    grid-template-columns: 1fr;
  }
  
  .distribution-grid {
    grid-template-columns: 1fr;
  }
  
  .activity-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
  }
  
  .activity-date {
    margin-left: 0;
  }
}
</style>
