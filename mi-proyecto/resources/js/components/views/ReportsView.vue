<template>
  <div class="reports-view">
    <div class="view-header">
      <div>
        <h1 class="view-title">Generación de Reportes</h1>
        <p class="view-subtitle">Reportes personalizados del sistema documental</p>
      </div>
    </div>

    <!-- Report Selection -->
    <div class="report-selection">
      <div class="report-grid">
        <div 
          v-for="report in reportTypes" 
          :key="report.id"
          @click="selectReport(report)"
          :class="['report-card', { selected: selectedReport?.id === report.id }]"
        >
          <div class="report-icon">{{ report.icon }}</div>
          <div class="report-info">
            <h3 class="report-title">{{ report.title }}</h3>
            <p class="report-description">{{ report.description }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Report Configuration -->
    <div v-if="selectedReport" class="report-config">
      <h3 class="config-title">Configurar Reporte</h3>
      
      <div class="config-grid">
        <div class="config-group">
          <label>Fecha Inicio</label>
          <input v-model="config.startDate" type="date" class="config-input" />
        </div>
        
        <div class="config-group">
          <label>Fecha Fin</label>
          <input v-model="config.endDate" type="date" class="config-input" />
        </div>
        
        <div class="config-group">
          <label>Formato</label>
          <select v-model="config.format" class="config-input">
            <option value="pdf">PDF</option>
            <option value="excel">Excel</option>
            <option value="csv">CSV</option>
          </select>
        </div>
      </div>

      <div class="config-actions">
        <button @click="generateReport" class="btn-generate" :disabled="generating">
          {{ generating ? 'Generando...' : 'Generar Reporte' }}
        </button>
      </div>
    </div>

    <!-- Recent Reports -->
    <div class="recent-reports">
      <h3 class="section-title">Reportes Recientes</h3>
      <div class="reports-list">
        <div v-for="report in recentReports" :key="report.id" class="report-item">
          <div class="report-item-info">
            <span class="report-name">{{ report.name }}</span>
            <span class="report-date">{{ formatDate(report.created_at) }}</span>
          </div>
          <button @click="downloadReport(report)" class="btn-download">
            Descargar
          </button>
        </div>
        <div v-if="!recentReports.length" class="empty-state">
          No hay reportes generados aún
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  headers: {
    type: Object,
    required: true
  }
})

const { success, error } = useToast()

const selectedReport = ref(null)
const generating = ref(false)
const recentReports = ref([])

const config = ref({
  startDate: '',
  endDate: '',
  format: 'pdf'
})

const reportTypes = [
  {
    id: 'by-status',
    title: 'Documentos por Estado',
    description: 'Distribución de documentos según su estado actual',
    icon: ''
  },
  {
    id: 'by-user',
    title: 'Actividad por Usuario',
    description: 'Resumen de acciones realizadas por cada usuario',
    icon: ''
  },
  {
    id: 'confidential',
    title: 'Documentos Confidenciales',
    description: 'Listado completo de documentos marcados como confidenciales',
    icon: ''
  },
  {
    id: 'by-section',
    title: 'Distribución por Sección',
    description: 'Cantidad de documentos por sección y subsección',
    icon: ''
  },
  {
    id: 'audit-summary',
    title: 'Resumen de Auditoría',
    description: 'Consolidado de movimientos en el sistema',
    icon: ''
  }
]

function selectReport(report) {
  selectedReport.value = report
  // Set default dates (last 30 days)
  const endDate = new Date()
  const startDate = new Date()
  startDate.setDate(startDate.getDate() - 30)
  
  config.value.startDate = startDate.toISOString().split('T')[0]
  config.value.endDate = endDate.toISOString().split('T')[0]
}

async function generateReport() {
  generating.value = true
  
  try {
    const response = await fetch('/api/reports/generate', {
      method: 'POST',
      headers: {
        ...props.headers,
        'Content-Type': 'application/json'
      },
      credentials: 'include',
      body: JSON.stringify({
        type: selectedReport.value.id,
        start_date: config.value.startDate,
        end_date: config.value.endDate,
        format: config.value.format
      })
    })
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`)
    }
    
    // Download the file
    const blob = await response.blob()
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `reporte_${selectedReport.value.id}_${new Date().toISOString().split('T')[0]}.${config.value.format}`
    link.click()
    URL.revokeObjectURL(url)
    
    success('Reporte generado', `Reporte ${selectedReport.value.title} descargado exitosamente`)
  } catch (e) {
    error('Error al generar reporte', e.message || 'No se pudo conectar con el servidor')
  } finally {
    generating.value = false
  }
}

function downloadReport(report) {
  // Implementation for downloading existing reports
  console.log('Download report:', report.id)
}

function formatDate(dateString) {
  if (!dateString) return '—'
  const date = new Date(dateString)
  return date.toLocaleDateString('es-ES', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>

<style scoped>
.reports-view {
  padding: 2rem;
  max-width: 1400px;
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

/* Report Selection */
.report-selection {
  margin-bottom: 2rem;
}

.report-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.25rem;
}

.report-card {
  background: white;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  padding: 1.5rem;
  display: flex;
  gap: 1rem;
  cursor: pointer;
  transition: all 0.2s;
}

.report-card:hover {
  border-color: #556b2f;
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

.report-card.selected {
  border-color: #556b2f;
  background: linear-gradient(135deg, #ffffff 0%, #f0f4e8 100%);
  box-shadow: 0 4px 12px rgba(85, 107, 47, 0.2);
}

.report-icon {
  font-size: 2.5rem;
  flex-shrink: 0;
}

.report-info {
  flex: 1;
  min-width: 0;
}

.report-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 0.5rem 0;
}

.report-description {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0;
  line-height: 1.5;
}

/* Configuration */
.report-config {
  background: white;
  border-radius: 12px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.config-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #374151;
  margin: 0 0 1.5rem 0;
}

.config-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.25rem;
  margin-bottom: 1.5rem;
}

.config-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.config-group label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
}

.config-input {
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.938rem;
}

.config-input:focus {
  outline: none;
  border-color: #556b2f;
  box-shadow: 0 0 0 3px rgba(85, 107, 47, 0.1);
}

.config-actions {
  display: flex;
  justify-content: flex-end;
}

.btn-generate {
  padding: 0.875rem 2rem;
  background: #556b2f;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 700;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-generate:hover:not(:disabled) {
  background: #4b5f2a;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(85, 107, 47, 0.3);
}

.btn-generate:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Recent Reports */
.recent-reports {
  background: white;
  border-radius: 12px;
  padding: 2rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.section-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #374151;
  margin: 0 0 1.5rem 0;
}

.reports-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.report-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 8px;
  transition: background 0.2s;
}

.report-item:hover {
  background: #f3f4f6;
}

.report-item-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.report-name {
  font-size: 0.938rem;
  font-weight: 600;
  color: #1f2937;
}

.report-date {
  font-size: 0.813rem;
  color: #9ca3af;
}

.btn-download {
  padding: 0.5rem 1rem;
  background: white;
  color: #556b2f;
  border: 1px solid #556b2f;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-download:hover {
  background: #556b2f;
  color: white;
}

.empty-state {
  text-align: center;
  padding: 3rem;
  color: #9ca3af;
  font-style: italic;
}

@media (max-width: 768px) {
  .reports-view {
    padding: 1rem;
  }
  
  .report-grid {
    grid-template-columns: 1fr;
  }
  
  .config-grid {
    grid-template-columns: 1fr;
  }
  
  .report-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }
  
  .btn-download {
    width: 100%;
  }
}
</style>
