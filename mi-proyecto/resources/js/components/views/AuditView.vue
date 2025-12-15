<template>
  <div class="audit-view">
    <div class="view-header">
      <div>
        <h1 class="view-title">Auditoría del Sistema</h1>
        <p class="view-subtitle">Registro completo de todas las acciones realizadas</p>
      </div>
    </div>

    <!-- Filters Panel -->
    <div class="filters-panel">
      <div class="filter-grid">
        <div class="filter-group">
          <label>Fecha Inicio</label>
          <input 
            v-model="filters.startDate" 
            type="date" 
            class="filter-input"
            @change="applyFilters"
          />
        </div>
        
        <div class="filter-group">
          <label>Fecha Fin</label>
          <input 
            v-model="filters.endDate" 
            type="date" 
            class="filter-input"
            @change="applyFilters"
          />
        </div>
        
        <div class="filter-group">
          <label>Tipo de Acción</label>
          <select 
            v-model="filters.actionType" 
            class="filter-input"
            @change="applyFilters"
          >
            <option value="">Todas</option>
            <option value="view">Ver</option>
            <option value="print">Imprimir</option>
            <option value="download">Descargar</option>
            <option value="upload">Subir</option>
            <option value="update">Actualizar</option>
            <option value="delete">Eliminar</option>
          </select>
        </div>
        
        <div class="filter-group" v-if="catalogs.motivos?.length">
          <label>Motivo</label>
          <select 
            v-model="filters.motivoId" 
            class="filter-input"
            @change="applyFilters"
          >
            <option value="">Todos</option>
            <option 
              v-for="motivo in catalogs.motivos" 
              :key="motivo.id" 
              :value="motivo.id"
            >
              {{ motivo.descripcion }}
            </option>
          </select>
        </div>
        
        <div class="filter-group">
          <label>Búsqueda</label>
          <input 
            v-model="filters.search" 
            type="text" 
            placeholder="Usuario, acción..."
            class="filter-input"
            @input="debounceSearch"
          />
        </div>
      </div>
      
      <div class="filter-actions">
        <button @click="applyFilters" class="btn-apply">
           Aplicar Filtros
        </button>
        <button @click="clearFilters" class="btn-clear">
          ✕ Limpiar
        </button>
      </div>
    </div>

    <!-- Table -->
    <div v-if="loading" class="loading">
      <div class="spinner"></div>
      <p>Cargando registros de auditoría...</p>
    </div>

    <div v-else-if="error" class="error">
      <p>⚠️ Error al cargar auditoría: {{ error }}</p>
      <button @click="loadAuditLogs" class="btn-retry">Reintentar</button>
    </div>

    <div v-else class="audit-table-container">
      <table class="audit-table">
        <thead>
          <tr>
            <th @click="sortBy('created_at')" class="sortable">
              Fecha/Hora
              <span v-if="sortColumn === 'created_at'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th @click="sortBy('actor_name')" class="sortable">
              Usuario
              <span v-if="sortColumn === 'actor_name'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th @click="sortBy('evento')" class="sortable">
              Acción
              <span v-if="sortColumn === 'evento'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th>Documento</th>
            <th>Motivo</th>
            <th>IP</th>
            <th>Detalles</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in paginatedLogs" :key="log.id" class="audit-row">
            <td class="col-date">{{ formatDate(log.created_at) }}</td>
            <td class="col-user">{{ log.actor_name || 'Sistema' }}</td>
            <td class="col-action">
              <span :class="['badge-action', `action-${getActionType(log.evento)}`]">
                {{ formatAction(log.evento) }}
              </span>
              <span v-if="hasChanges(log)" class="has-changes-indicator" title="Tiene cambios detallados">
                📝
              </span>
            </td>
            <td class="col-document">
              <span v-if="log.doc_titulo" class="doc-title">{{ log.doc_titulo }}</span>
              <span v-else class="doc-id">{{ formatObject(log) }}</span>
            </td>
            <td class="col-motivo">
              <span v-if="log.motivo" class="motivo-badge">{{ log.motivo }}</span>
              <span v-else class="motivo-empty">—</span>
            </td>
            <td class="col-ip">{{ log.ip || '—' }}</td>
            <td class="col-details">
              <button @click="showDetails(log)" class="btn-details">
                Ver
              </button>
            </td>
          </tr>
          <tr v-if="!paginatedLogs.length">
            <td colspan="7" class="empty">No se encontraron registros</td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="pagination">
        <button 
          @click="currentPage--" 
          :disabled="currentPage === 1"
          class="page-btn"
        >
          ←
        </button>
        <span class="page-info">
          Página {{ currentPage }} de {{ totalPages }}
          ({{ filteredLogs.length }} registros)
        </span>
        <button 
          @click="currentPage++" 
          :disabled="currentPage === totalPages"
          class="page-btn"
        >
          →
        </button>
      </div>
    </div>

    <!-- Detail Modal -->
    <BaseModal
      :open="showDetailModal"
      title="Detalles del Registro"
      size="large"
      @close="showDetailModal = false"
    >
      <div v-if="selectedLog" class="detail-modal">
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-label">ID:</span>
            <span class="detail-value">{{ selectedLog.id }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Fecha:</span>
            <span class="detail-value">{{ formatDate(selectedLog.created_at) }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Usuario:</span>
            <span class="detail-value">{{ selectedLog.actor_name || 'Sistema' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Acción:</span>
            <span class="detail-value">{{ selectedLog.evento }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">IP:</span>
            <span class="detail-value">{{ selectedLog.ip }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">User Agent:</span>
            <span class="detail-value">{{ selectedLog.user_agent || '—' }}</span>
          </div>
        </div>
        
        <!-- Cambios section (solo para documento.update) -->
        <div v-if="selectedLog.evento === 'documento.update' && getChanges(selectedLog)" class="changes-section">
          <h4>📝 Cambios Realizados</h4>
          <div class="changes-grid">
            <div v-for="(change, field) in getChanges(selectedLog)" :key="field" class="change-item">
              <div class="change-field">{{ formatFieldName(field) }}</div>
              <div class="change-values">
                <div class="change-before">
                  <span class="change-label">Antes:</span>
                  <span class="change-value">{{ change.antes }}</span>
                </div>
                <div class="change-arrow">→</div>
                <div class="change-after">
                  <span class="change-label">Después:</span>
                  <span class="change-value">{{ change.despues }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import BaseModal from '../ui/BaseModal.vue'

const props = defineProps({
  headers: {
    type: Object,
    required: true
  },
  catalogs: {
    type: Object,
    default: () => ({})
  }
})

const logs = ref([])
const loading = ref(true)
const error = ref(null)
const currentPage = ref(1)
const perPage = ref(20)
const sortColumn = ref('created_at')
const sortDirection = ref('desc')
const showDetailModal = ref(false)
const selectedLog = ref(null)

const filters = ref({
  startDate: '',
  endDate: '',
  actionType: '',
  motivoId: '',
  search: ''
})

// Computed
const filteredLogs = computed(() => {
  let result = [...logs.value]
  
  if (filters.value.startDate) {
    result = result.filter(log => log.created_at >= filters.value.startDate)
  }
  
  if (filters.value.endDate) {
    result = result.filter(log => log.created_at <= filters.value.endDate)
  }
  
  if (filters.value.actionType) {
    result = result.filter(log => log.evento.includes(filters.value.actionType))
  }
  
  if (filters.value.motivoId) {
    result = result.filter(log => log.motivo_id === filters.value.motivoId)
  }
  
  if (filters.value.search) {
    const search = filters.value.search.toLowerCase()
    result = result.filter(log => 
      (log.actor_name?.toLowerCase().includes(search)) ||
      (log.evento?.toLowerCase().includes(search)) ||
      (log.objeto_tipo?.toLowerCase().includes(search))
    )
  }
  
  return result
})

const sortedLogs = computed(() => {
  return [...filteredLogs.value].sort((a, b) => {
    let aVal = a[sortColumn.value]
    let bVal = b[sortColumn.value]
    
    if (sortDirection.value === 'asc') {
      return aVal > bVal ? 1 : -1
    } else {
      return aVal < bVal ? 1 : -1
    }
  })
})

const paginatedLogs = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  const end = start + perPage.value
  return sortedLogs.value.slice(start, end)
})

const totalPages = computed(() => {
  return Math.ceil(filteredLogs.value.length / perPage.value)
})

// Methods
async function loadAuditLogs() {
  loading.value = true
  error.value = null
  
  try {
    // Construir query params
    const params = new URLSearchParams()
    if (filters.value.startDate) params.append('start_date', filters.value.startDate)
    if (filters.value.endDate) params.append('end_date', filters.value.endDate)
    if (filters.value.actionType) params.append('action_type', filters.value.actionType)
    if (filters.value.motivoId) params.append('motivo_id', filters.value.motivoId)
    if (filters.value.search) params.append('search', filters.value.search)
    
    const url = `/api/audit/comprehensive${params.toString() ? '?' + params.toString() : ''}`
    const response = await fetch(url, {
      headers: props.headers,
      credentials: 'include'
    })
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`)
    }
    
    const data = await response.json()
    logs.value = data.logs || []
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  currentPage.value = 1
  loadAuditLogs()
}

let searchTimeout = null
function debounceSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    applyFilters()
  }, 500)
}

function clearFilters() {
  filters.value = {
    startDate: '',
    endDate: '',
    actionType: '',
    motivoId: '',
    search: ''
  }
  currentPage.value = 1
  loadAuditLogs()
}

function sortBy(column) {
  if (sortColumn.value === column) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortColumn.value = column
    sortDirection.value = 'desc'
  }
}

function showDetails(log) {
  selectedLog.value = log
  showDetailModal.value = true
}

function formatDate(dateString) {
  if (!dateString) return '—'
  const date = new Date(dateString)
  return date.toLocaleString('es-ES', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}

function formatAction(evento) {
  const translations = {
    'view': 'Ver',
    'print': 'Imprimir',
    'download': 'Descargar',
    'upload': 'Subir',
    'validate': 'Validar',
    'seal': 'Sellar',
    'delete': 'Eliminar',
    'update': 'Actualizar'
  }
  
  const action = evento.replace('documento.', '')
  return translations[action] || action
}

function getActionType(evento) {
  if (evento.includes('view')) return 'view'
  if (evento.includes('print')) return 'print'
  if (evento.includes('download')) return 'download'
  if (evento.includes('upload')) return 'create'
  if (evento.includes('validate')) return 'update'
  if (evento.includes('seal')) return 'seal'
  if (evento.includes('delete')) return 'delete'
  return 'other'
}

function formatObject(log) {
  return `${log.objeto_tipo || 'objeto'} #${log.objeto_id || '—'}`
}

function formatJSON(payload) {
  if (!payload) return '{}'
  try {
    if (typeof payload === 'string') {
      return JSON.stringify(JSON.parse(payload), null, 2)
    }
    return JSON.stringify(payload, null, 2)
  } catch {
    return payload
  }
}

function getChanges(log) {
  if (!log || !log.payload) return null
  
  try {
    const payload = typeof log.payload === 'string' ? JSON.parse(log.payload) : log.payload
    return payload.cambios || null
  } catch {
    return null
  }
}

function formatFieldName(field) {
  const translations = {
    'titulo': 'Título',
    'descripcion': 'Descripción',
    'tipo_documento_id': 'Tipo de Documento',
    'seccion_id': 'Sección',
    'subseccion_id': 'Subsección',
    'gestion_id': 'Gestión',
    'fecha_documento': 'Fecha del Documento',
    'numero_documento': 'Número de Documento',
    'is_confidential': 'Confidencialidad'
  }
  
  return translations[field] || field
}

function hasChanges(log) {
  const changes = getChanges(log)
  return changes && Object.keys(changes).length > 0
}

function exportToCSV() {
  const headers = ['ID', 'Fecha', 'Usuario', 'Acción', 'Objeto', 'IP']
  const rows = filteredLogs.value.map(log => [
    log.id,
    formatDate(log.created_at),
    log.actor_name || 'Sistema',
    log.evento,
    formatObject(log),
    log.ip || ''
  ])
  
  const csv = [
    headers.join(','),
    ...rows.map(row => row.map(cell => `"${cell}"`).join(','))
  ].join('\n')
  
  const blob = new Blob([csv], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `auditoria_${new Date().toISOString().split('T')[0]}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

onMounted(() => {
  loadAuditLogs()
})
</script>

<style scoped>
.audit-view {
  padding: 2rem;
  max-width: 1600px;
  margin: 0 auto;
}

.view-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
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

.btn-export {
  padding: 0.75rem 1.5rem;
  background: #556b2f;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-export:hover:not(:disabled) {
  background: #4b5f2a;
  transform: translateY(-2px);
}

.btn-export:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Filters */
.filters-panel {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.filter-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 1rem;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.filter-group label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
}

.filter-input {
  padding: 0.625rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.875rem;
}

.filter-input:focus {
  outline: none;
  border-color: #556b2f;
  box-shadow: 0 0 0 3px rgba(85, 107, 47, 0.1);
}

.filter-actions {
  display: flex;
  gap: 0.75rem;
}

.btn-apply,
.btn-clear {
  padding: 0.625rem 1.25rem;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.btn-apply {
  background: #556b2f;
  color: white;
}

.btn-apply:hover:not(:disabled) {
  background: #4b5f2a;
}

.btn-clear {
  background: white;
  color: #6b7280;
  border: 1px solid #d1d5db;
}

.btn-clear:hover {
  background: #f3f4f6;
}

/* Table */
.audit-table-container {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.audit-table {
  width: 100%;
  border-collapse: collapse;
}

.audit-table thead {
  background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
  border-bottom: 2px solid #e5e7eb;
}

.audit-table th {
  padding: 1rem;
  text-align: left;
  font-size: 0.813rem;
  font-weight: 700;
  color: #374151;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.audit-table th.sortable {
  cursor: pointer;
  user-select: none;
}

.audit-table th.sortable:hover {
  background: #f3f4f6;
}

.audit-table tbody tr {
  border-bottom: 1px solid #f3f4f6;
  transition: background 0.2s;
}

.audit-table tbody tr:hover {
  background: #f9fafb;
}

.audit-table td {
  padding: 1rem;
  font-size: 0.875rem;
  color: #4b5563;
}

.col-date {
  white-space: nowrap;
  font-family: 'Courier New', monospace;
}

.col-user {
  font-weight: 500;
  color: #1f2937;
}

.badge-action {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: capitalize;
}

.has-changes-indicator {
  margin-left: 0.5rem;
  font-size: 0.875rem;
  cursor: help;
}

.action-create {
  background: #dbeafe;
  color: #1e40af;
}

.action-update {
  background: #fef3c7;
  color: #92400e;
}

.action-seal {
  background: #dcfce7;
  color: #166534;
}

.action-delete {
  background: #fee2e2;
  color: #991b1b;
}

.action-other {
  background: #f3f4f6;
  color: #4b5563;
}

.btn-details {
  padding: 0.375rem 0.75rem;
  background: white;
  color: #556b2f;
  border: 1px solid #556b2f;
  border-radius: 6px;
  font-size: 0.813rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-details:hover {
  background: #556b2f;
  color: white;
}

.empty {
  text-align: center;
  padding: 3rem;
  color: #9ca3af;
  font-style: italic;
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid #e5e7eb;
}

.page-btn {
  padding: 0.5rem 1rem;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.page-btn:hover:not(:disabled) {
  background: #f3f4f6;
  border-color: #556b2f;
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.page-info {
  font-size: 0.875rem;
  color: #6b7280;
}

/* Detail Modal */
.detail-modal {
  padding: 1rem 0;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-label {
  font-size: 0.75rem;
  font-weight: 700;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* Changes Section */
.changes-section {
  margin-bottom: 1.5rem;
  padding: 1.5rem;
  background: #f9fafb;
  border-radius: 8px;
  border-left: 4px solid #556b2f;
}

.changes-section h4 {
  font-size: 0.938rem;
  font-weight: 700;
  color: #374151;
  margin: 0 0 1rem 0;
}

.changes-grid {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.change-item {
  background: white;
  padding: 1rem;
  border-radius: 6px;
  border: 1px solid #e5e7eb;
}

.change-field {
  font-size: 0.813rem;
  font-weight: 700;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.75rem;
}

.change-values {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: 1rem;
  align-items: center;
}

.change-before,
.change-after {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.75rem;
  border-radius: 4px;
}

.change-before {
  background: #fee2e2;
  border-left: 3px solid #ef4444;
}

.change-after {
  background: #d1fae5;
  border-left: 3px solid #10b981;
}

.change-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
}

.change-value {
  font-size: 0.875rem;
  color: #1f2937;
  font-weight: 500;
  word-break: break-word;
}

.change-arrow {
  font-size: 1.5rem;
  color: #9ca3af;
  font-weight: bold;
}

/* Payload Section */
.detail-value {
  font-size: 0.938rem;
  color: #1f2937;
  font-weight: 500;
}

.payload-section h4 {
  font-size: 0.875rem;
  font-weight: 700;
  color: #374151;
  margin: 0 0 0.75rem 0;
}

.payload-json {
  background: #1f2937;
  color: #10b981;
  padding: 1rem;
  border-radius: 8px;
  font-family: 'Courier New', monospace;
  font-size: 0.813rem;
  line-height: 1.6;
  overflow-x: auto;
  max-height: 400px;
}

/* Loading & Error */
.loading,
.error {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem;
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

.btn-retry {
  padding: 0.75rem 1.5rem;
  background: #dc2626;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

@media (max-width: 768px) {
  .audit-view {
    padding: 1rem;
  }
  
  .view-header {
    flex-direction: column;
    gap: 1rem;
  }
  
  .btn-export {
    width: 100%;
  }
  
  .filter-grid {
    grid-template-columns: 1fr;
  }
  
  .audit-table {
    font-size: 0.75rem;
  }
  
  .audit-table th,
  .audit-table td {
    padding: 0.5rem;
  }
  
  .detail-grid {
    grid-template-columns: 1fr;
  }
}
</style>
