<template>
  <div class="deleted-documents-view">
    <div class="header">
      <h2>Documentos Eliminados</h2>
      <p class="subtitle">Gestión y restauración de documentos eliminados lógicamente</p>
    </div>
    
    <!-- Filtros -->
    <div class="filters-section">
      <div class="filter-group">
        <label>Buscar:</label>
        <input 
          v-model="filters.search"
          type="text"
          placeholder="Buscar por título, ID o usuario..."
          class="filter-input"
        />
      </div>
      
      <div class="filter-group">
        <label>Desde:</label>
        <input 
          v-model="filters.dateFrom"
          type="date"
          class="filter-input"
        />
      </div>
      
      <div class="filter-group">
        <label>Hasta:</label>
        <input 
          v-model="filters.dateTo"
          type="date"
          class="filter-input"
        />
      </div>
      
      <button @click="loadDocuments" class="btn-filter">
        Filtrar
      </button>
      
      <button @click="resetFilters" class="btn-reset">
        Limpiar
      </button>
    </div>
    
    <!-- Tabla de documentos eliminados -->
    <div class="table-section">
      <div v-if="loading" class="loading-state">
        <p>Cargando documentos eliminados...</p>
      </div>
      
      <div v-else-if="documents.length === 0" class="empty-state">
        <p>No hay documentos eliminados para mostrar</p>
      </div>
      
      <table v-else class="documents-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Título</th>
            <th>Tipo</th>
            <th>Estado Original</th>
            <th>Eliminado el Por</th>
            <th>Eliminado Por</th>
            <th>Razón</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="doc in paginatedDocuments" :key="doc.id">
            <td><span class="doc-id">#{{ doc.id }}</span></td>
            <td>
              <div class="doc-title">{{ doc.titulo || '—' }}</div>
              <div class="doc-section" v-if="doc.seccion_nombre">{{ doc.seccion_nombre }}</div>
            </td>
            <td>{{ doc.tipo_documento_nombre || '—' }}</td>
            <td>
              <span class="estado-badge" :class="'estado-' + doc.estado">
                {{ formatEstado(doc.estado) }}
              </span>
            </td>
            <td>{{ formatDateTime(doc.deleted_at) }}</td>
            <td>{{ doc.deleted_by_name || '—' }}</td>
            <td>
              <span class="reason-text" :title="doc.delete_reason">
                {{ truncate(doc.delete_reason, 50) }}
              </span>
            </td>
            <td>
              <button 
                @click="showRestoreModal(doc)"
                class="btn-restore"
                title="Restaurar documento"
              >
                ♻️ Restaurar
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    
    <!-- Paginación -->
    <div v-if="totalPages > 1" class="pagination">
      <button 
        @click="currentPage--" 
        :disabled="currentPage === 1"
        class="btn-page"
      >
        Anterior
      </button>
      
      <span class="page-info">
        Página {{ currentPage }} de {{ totalPages  }}
        ({{ documents.length }} documentos)
      </span>
      
      <button 
        @click="currentPage++" 
        :disabled="currentPage === totalPages"
        class="btn-page"
      >
        Siguiente
      </button>
    </div>
    
    <!-- Modal: Restaurar Documento -->
    <BaseModal
      :open="showRestore"
      title="Restaurar Documento"
      @close="showRestore = false"
    >
      <div class="restore-modal-content">
        <p class="modal-info">
          ¿Está seguro de que desea restaurar este documento?
        </p>
        
        <div class="doc-info-box">
          <p><strong>ID:</strong> #{{ selectedDoc?.id }}</p>
          <p><strong>Título:</strong> {{ selectedDoc?.titulo }}</p>
          <p><strong>Eliminado el:</strong> {{ formatDateTime(selectedDoc?.deleted_at) }}</p>
          <p><strong>Razón de eliminación:</strong> {{ selectedDoc?.delete_reason }}</p>
        </div>
        
        <p class="modal-warning">
          ℹ️ El documento será restaurado a su estado original y volverá a estar disponible en el sistema.
        </p>
        
        <div class="modal-actions">
          <button @click="showRestore = false" class="btn-cancel">Cancelar</button>
          <button 
            @click="restaurarDocumento"
            class="btn-confirm"
            :disabled="restoring"
          >
            {{ restoring ? '⏳ Restaurando...' : '♻️ Confirmar Restauración' }}
          </button>
        </div>
      </div>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from '@/composables/useToast'
import BaseModal from '@/components/ui/BaseModal.vue'

const { success, error } = useToast()

const documents = ref([])
const loading = ref(false)
const showRestore = ref(false)
const selectedDoc = ref(null)
const restoring = ref(false)

const currentPage = ref(1)
const itemsPerPage = 20

const filters = ref({
  search: '',
  dateFrom: '',
  dateTo: ''
})

const filteredDocuments = computed(() => {
  let result = documents.value
  
  if (filters.value.search) {
    const search = filters.value.search.toLowerCase()
    result = result.filter(doc => 
      doc.titulo?.toLowerCase().includes(search) ||
      doc.id?.toString().includes(search) ||
      doc.deleted_by_name?.toLowerCase().includes(search)
    )
  }
  
  if (filters.value.dateFrom) {
    result = result.filter(doc => 
      new Date(doc.deleted_at) >= new Date(filters.value.dateFrom)
    )
  }
  
  if (filters.value.dateTo) {
    result = result.filter(doc => 
      new Date(doc.deleted_at) <= new Date(filters.value.dateTo)
    )
  }
  
  return result
})

const paginatedDocuments = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredDocuments.value.slice(start, end)
})

const totalPages = computed(() => {
  return Math.ceil(filteredDocuments.value.length / itemsPerPage)
})

async function loadDocuments() {
  loading.value = true
  try {
    const response = await fetch('/api/documentos/eliminados', {
      credentials: 'include'
    })
    
    const data = await response.json()
    
    if (data.ok) {
      documents.value = data.documentos
    } else {
      error('Error', 'No se pudieron cargar los documentos eliminados')
    }
  } catch (e) {
    error('Error', 'Error al conectar con el servidor')
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.value = {
    search: '',
    dateFrom: '',
    dateTo: ''
  }
  currentPage.value = 1
}

function showRestoreModal(doc) {
  selectedDoc.value = doc
  showRestore.value = true
}

async function restaurarDocumento() {
  restoring.value = true
  
  try {
    const response = await fetch(`/api/documentos/${selectedDoc.value.id}/restaurar`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include'
    })
    
    const result = await response.json()
    
    if (result.ok) {
      success('Documento Restaurado', result.message || 'El documento ha sido restaurado exitosamente')
      await loadDocuments()
      showRestore.value = false
      selectedDoc.value = null
    } else {
      error('Error', result.message || 'No se pudo restaurar el documento')
    }
  } catch (e) {
    error('Error', e.message)
  } finally {
    restoring.value = false
  }
}

function formatDateTime(date) {
  if (!date) return '—'
  return new Date(date).toLocaleString('es-BO', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatEstado(estado) {
  const map = {
    'capturado': 'Capturado',
    'pendiente': 'Pendiente',
    'validado': ' Validado',
    'custodio': 'En Custodia',
    'sellado': 'Sellado'
  }
  return map[estado] || estado
}

function truncate(text, maxLength) {
  if (!text) return '—'
  if (text.length <= maxLength) return text
  return text.substring(0, maxLength) + '...'
}

onMounted(() => {
  loadDocuments()
})
</script>

<style scoped>
.deleted-documents-view {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem;
}

.header {
  margin-bottom: 2rem;
}

.header h2 {
  font-size: 1.8rem;
  color: #1f2937;
  margin-bottom: 0.5rem;
}

.subtitle {
  color: #6b7280;
  font-size: 1rem;
}

.filters-section {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
  padding: 1.5rem;
  background: #f9fafb;
  border-radius: 8px;
  flex-wrap: wrap;
  align-items: flex-end;
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
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.875rem;
  min-width: 200px;
}

.btn-filter,
.btn-reset {
  padding: 0.5rem 1.5rem;
  border: none;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
}

.btn-filter {
  background: #3b82f6;
  color: white;
}

.btn-filter:hover {
  background: #2563eb;
}

.btn-reset {
  background: #f3f4f6;
  color: #374151;
}

.btn-reset:hover {
  background: #e5e7eb;
}

.table-section {
  background: white;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e5e7eb;
}

.loading-state,
.empty-state {
  padding: 3rem;
  text-align: center;
  color: #6b7280;
}

.documents-table {
  width: 100%;
  border-collapse: collapse;
}

.documents-table thead {
  background: #f9fafb;
}

.documents-table th {
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #374151;
  border-bottom: 2px solid #e5e7eb;
  font-size: 0.875rem;
}

.documents-table td {
  padding: 1rem;
  border-bottom: 1px solid #e5e7eb;
  font-size: 0.875rem;
}

.documents-table tbody tr:hover {
  background: #f9fafb;
}

.doc-id {
  font-family: 'Courier New', monospace;
  font-weight: 600;
  color: #6b7280;
}

.doc-title {
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 0.25rem;
}

.doc-section {
  font-size: 0.75rem;
  color: #6b7280;
}

.estado-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
}

.estado-capturado { background: #dbeafe; color: #1e40af; }
.estado-pendiente { background: #fef3c7; color: #92400e; }
.estado-validado { background: #dcfce7; color: #166534; }

.reason-text {
  display: block;
  color: #6b7280;
  font-style: italic;
}

.btn-restore {
  background: #10b981;
  color: white;
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.875rem;
  font-weight: 600;
}

.btn-restore:hover {
  background: #059669;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  margin-top: 2rem;
}

.btn-page {
  padding: 0.5rem 1rem;
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
}

.btn-page:hover:not(:disabled) {
  background: #e5e7eb;
}

.btn-page:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-info {
  color: #6b7280;
  font-size: 0.875rem;
}

/* Modal Styles */
.restore-modal-content {
  padding: 1rem 0;
}

.modal-info {
  font-size: 1rem;
  margin-bottom: 1rem;
  color: #374151;
}

.doc-info-box {
  background: #f9fafb;
  padding: 1rem;
  border-radius: 6px;
  margin: 1rem 0;
}

.doc-info-box p {
  margin: 0.5rem 0;
  font-size: 0.875rem;
}

.modal-warning {
  background: #e0f2fe;
  border-left: 4px solid #0284c7;
  padding: 1rem;
  border-radius: 4px;
  margin: 1rem 0;
  font-size: 0.875rem;
}

.modal-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  margin-top: 1.5rem;
}

.btn-cancel,
.btn-confirm {
  padding: 0.625rem 1.25rem;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
}

.btn-cancel {
  background: #f3f4f6;
  color: #374151;
}

.btn-confirm {
  background: #10b981;
  color: white;
}

.btn-confirm:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
