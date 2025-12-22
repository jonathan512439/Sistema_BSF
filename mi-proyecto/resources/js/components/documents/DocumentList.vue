<template>
  <div class="document-list-container">
    <!-- Estadísticas (solo si no hay filtros activos) -->
    <DocumentStatistics 
      v-if="!hasActiveFilters"
      :headers="headers"
      :user-role="role"
    />
    
    <!-- Filtros avanzados -->
    <AdvancedSearchFilters 
      :catalogs="catalogs"
      :user-role="role"
      @search="handleSearch"
    />
    
    <!-- Encabezado de resultados y acciones -->
    <div class="results-header">
      <div class="results-info">
        <h2 class="results-title"> Documentos</h2>
        <p v-if="pagination.total" class="results-count">
          Mostrando {{ pagination.from }}-{{ pagination.to }} de {{ pagination.total }} documento{{ pagination.total >1 ? 's' : '' }}
        </p>
      </div>
      
      <!-- Botón de upload (solo para archivist/superadmin) -->
      <PrimaryButton v-if="can('doc.upload')" @click="$emit('show-upload')">
        ➕ Subir Documento
      </PrimaryButton>
    </div>
    
    <!-- Estado de carga -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Cargando documentos...</p>
    </div>
    
    <!-- Mensaje de error -->
    <div v-else-if="errorMsg" class="error-state">
      <p>❌ {{ errorMsg }}</p>
      <button @click="fetchDocs" class="btn-retry">Reintentar</button>
    </div>
    
    <!-- Grid de documentos -->
    <div v-else-if="documentos.length > 0" class="documents-grid">
      <DocumentGridCard
        v-for="doc in documentos"
        :key="doc.id"
        :documento="doc"
        :catalogs="catalogs"
        @show-details="handleShowDetails"
        @open-viewer="openViewer"
      />
    </div>
    
    <!-- Estado vacío -->
    <div v-else class="empty-state">
      <h3>No se encontraron documentos</h3>
      <p v-if="hasActiveFilters">
        Intenta ajustar los filtros de búsqueda
      </p>
      <p v-else>
        {{ can('doc.upload') ? 'Comienza subiendo tu primer documento' : 'No hay documentos disponibles' }}
      </p>
    </div>
    
    <!-- Paginación -->
    <div v-if="pagination.last_page > 1" class="pagination">
      <button 
        @click="changePage(pagination.current_page - 1)"
        :disabled="pagination.current_page === 1"
        class="pagination-btn"
      >
        ← Anterior
      </button>
      
      <span class="pagination-info">
        Página {{ pagination.current_page }} de {{ pagination.last_page }}
      </span>
      
      <button 
        @click="changePage(pagination.current_page + 1)"
        :disabled="pagination.current_page === pagination.last_page"
        class="pagination-btn"
      >
        Siguiente →
      </button>
    </div>

    <!-- MODAL DE DETALLES / VISOR -->
    <BaseModal
      :open="showDetailsModal"
      title="Detalle del Documento"
      max-width="4xl"
      @close="closeDetails"
    >
      <div v-if="selectedDoc">
        <!-- Usamos el componente DocumentCard antiguo para los detalles completos -->
        <DocumentCard 
          :doc="selectedDoc"
          :headers="headers"
          :catalogs="catalogs"
          :can="can"
          :expanded="true"
          @view="openViewer"
          @ocr="handleOcr"
          @validate="handleValidate"
          @seal="handleSeal"
          @delete="handleDelete"
        />
      </div>
    </BaseModal>

    <!-- VISOR DE PDF (STREAM) -->
    <DocumentViewer
      v-if="viewerId && selectedMotivoId"
      :documento-id="viewerId"
      :headers="headers"
      :motivos="catalogs.motivos_acceso || []"
      :initial-motivo-id="selectedMotivoId"
      @close="closeViewer"
    />
    
    <!-- MODAL DE SELECCIÓN DE MOTIVO -->
    <BaseModal
      :open="showMotivoModal"
      title="Motivo de Acceso al Documento"
      @close="closeMotivoModal"
    >
      <template #body>
        <div class="motivo-modal-content">
          <p class="motivo-description">
            Por favor seleccione el motivo por el cual desea acceder a este documento.
            Todos los accesos quedan registrados en la auditoría del sistema.
          </p>
          
          <div class="motivo-options">
            <label 
              v-for="motivo in (catalogs.motivos_acceso || [])"
              :key="motivo.id"
              class="motivo-option"
            >
              <input 
                type="radio" 
                name="motivo" 
                :value="motivo.id"
                v-model="tempMotivoId"
              />
              <div class="motivo-info">
                <span class="motivo-text">{{ motivo.descripcion }}</span>
                <div class="motivo-permissions">
                  <span v-if="motivo.can_view" class="permission-badge view" title="Permite visualizar">
                    Ver
                  </span>
                  <span v-if="motivo.can_print" class="permission-badge print" title="Permite imprimir">
                    Imprimir
                  </span>
                  <span v-if="motivo.can_download" class="permission-badge download" title="Permite descargar">
                    Descargar
                  </span>
                </div>
              </div>
            </label>
          </div>
          
          <div v-if="!tempMotivoId" class="motivo-warning">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
              <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
            </svg>
            Debe seleccionar un motivo para continuar
          </div>
        </div>
      </template>
      
      <template #footer>
        <BaseButton @click="closeMotivoModal">Cancelar</BaseButton>
        <PrimaryButton 
          @click="confirmMotivo" 
          :disabled="!tempMotivoId"
        >
          Abrir Documento
        </PrimaryButton>
      </template>
    </BaseModal>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import DocumentStatistics from './DocumentStatistics.vue'
import AdvancedSearchFilters from './AdvancedSearchFilters.vue'
import DocumentGridCard from './DocumentGridCard.vue'
import DocumentCard from './DocumentCard.vue' // El componente antiguo para detalles
import DocumentViewer from './DocumentViewer.vue'
import PrimaryButton from '../ui/PrimaryButton.vue'
import BaseButton from '../ui/BaseButton.vue'
import BaseModal from '../ui/BaseModal.vue'

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
    required: true
  },
  initialFilters: {
    type: Object,
    default: () => ({})
  },
  readonly: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['show-upload', 'show-details'])

const documentos = ref([])
const loading = ref(true)
const errorMsg = ref('')
const activeFilters = ref({})
const selectedDoc = ref(null)
const showDetailsModal = ref(false)
const viewerId = ref(null)

// Estado del modal de motivo
const showMotivoModal = ref(false)
const pendingDocId = ref(null)
const tempMotivoId = ref(null)
const selectedMotivoId = ref(null)

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0,
  from: 0,
  to: 0
})

const rolePermissions = {
  superadmin: ['doc.view', 'doc.upload', 'doc.edit', 'doc.validate', 'doc.seal', 'doc.delete', 'doc.move'],
  archivist: ['doc.view', 'doc.upload', 'doc.edit', 'doc.validate', 'doc.seal', 'doc.delete', 'doc.move'],
  reader: ['doc.view'],
}

const can = (perm) => {
  const perms = rolePermissions[props.role] || []
  return perms.includes(perm)
}

const hasActiveFilters = computed(() => {
  return Object.keys(activeFilters.value).length > 0
})

const handleSearch = (searchFilters) => {
  activeFilters.value = searchFilters
  fetchDocs(1) // Reset to page 1
}

async function fetchDocs(page = 1) {
  loading.value = true
  errorMsg.value = ''
  
  try {
    // Construir query params
    const params = new URLSearchParams({
      page: page,
      per_page: pagination.value.per_page,
      ...activeFilters.value
    })
    
    const url = `/api/documentos?${params.toString()}`
    
    const response = await fetch(url, {
      credentials: 'include',
      headers: props.headers
    })
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`)
    }
    
    const data = await response.json()
    
    // Manejar paginación de Laravel
    if (data.data) {
      documentos.value = data.data
      pagination.value = {
        current_page: data.current_page,
        last_page: data.last_page,
        per_page: data.per_page,
        total: data.total,
        from: data.from || 0,
        to: data.to || 0
      }
    } else {
      // Fallback si no hay paginación
      documentos.value = Array.isArray(data) ? data : []
    }
  } catch (e) {
    console.error('Fetch docs error:', e)
    errorMsg.value = e.message || 'Error al cargar documentos'
    documentos.value = []
  } finally {
    loading.value = false
  }
}

function changePage(page) {
  if (page >= 1 && page <= pagination.value.last_page) {
    fetchDocs(page)
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

// Apply initial filters on mount
onMounted(() => {
  if (Object.keys(props.initialFilters).length > 0) {
    activeFilters.value = { ...props.initialFilters }
  }
  fetchDocs()
})

const openViewer = (documento) =>
{
  console.log('openViewer called with:', documento)
  // Interceptar apertura para pedir motivo primero
  // Manejar tanto objeto como ID directo
  const docId = typeof documento === 'object' ? documento.id : documento
  pendingDocId.value = docId
  tempMotivoId.value = null
  showMotivoModal.value = true
}

function confirmMotivo() {
  if (!tempMotivoId.value) return
  
  selectedMotivoId.value = tempMotivoId.value
  viewerId.value = pendingDocId.value
  showMotivoModal.value = false
}

function closeMotivoModal() {
  showMotivoModal.value = false
  pendingDocId.value = null
  tempMotivoId.value = null
}

const handleShowDetails = (documento) => {
  console.log('handleShowDetails called with:', documento)
  selectedDoc.value = documento
  showDetailsModal.value = true
}

function closeDetails() {
  selectedDoc.value = null
  showDetailsModal.value = false
}

function closeViewer() {
  viewerId.value = null
  selectedMotivoId.value = null
}

// Handlers para acciones del DocumentCard
function handleOcr() {
  // Implementar lógica OCR si es necesario, o emitir evento
  console.log('OCR requested')
}

function handleValidate() {
  console.log('Validate requested')
}

function handleSeal() {
  console.log('Seal requested')
}

function handleDelete() {
  console.log('Delete requested')
  // Refrescar lista después de borrar
  fetchDocs(pagination.value.current_page)
  closeDetails()
}

onMounted(() => {
  fetchDocs()
})

// Exponer fetchDocs para que el componente padre pueda refrescar
defineExpose({
  refresh: () => fetchDocs(pagination.value.current_page),
  refreshFromStart: () => fetchDocs(1)
})
</script>

<style scoped>
.document-list-container {
  width: 100%;
  padding: 0 1rem;
}

.results-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.results-info {
  flex: 1;
}

.results-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 0.5rem 0;
}

.results-count {
  font-size: 0.938rem;
  color: #6b7280;
  margin: 0;
}

.loading-state,
.error-state,
.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.spinner {
  width: 50px;
  height: 50px;
  border: 4px solid #e5e7eb;
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.loading-state p,
.error-state p,
.empty-state p {
  color: #6b7280;
  margin: 0.5rem 0;
}

.error-state p {
  color: #dc2626;
  font-weight: 600;
  margin-bottom: 1rem;
}

.btn-retry {
  padding: 0.625rem 1.25rem;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-retry:hover {
  background: #2563eb;
}

.empty-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
  opacity: 0.5;
}

.empty-state h3 {
  font-size: 1.5rem;
  color: #374151;
  margin: 0 0 0.5rem 0;
}

.documents-grid {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 2rem;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1.5rem;
  padding: 2rem 0;
}

.pagination-btn {
  padding: 0.75rem 1.5rem;
  background: white;
  color: #556b2f; /* Olive */
  border: 2px solid #556b2f;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.pagination-btn:hover:not(:disabled) {
  background: #556b2f;
  color: white;
  transform: translateY(-1px);
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  border-color: #d1d5db;
  color: #9ca3af;
}

.pagination-info {
  font-size: 0.938rem;
  color: #6b7280;
  font-weight: 500;
}

@media (max-width: 1024px) {
  /* No changes needed for tablet in flex column layout */
}

@media (max-width: 640px) {
  .results-header {
    flex-direction: column;
    align-items: stretch;
  }
  
  .pagination {
    flex-direction: column;
    gap: 1rem;
  }
  
  .pagination-btn {
    width: 100%;
  }
}

/* Estilos del modal de motivo */
.motivo-modal-content {
  padding: 0.5rem 0;
}

.motivo-description {
  color: #374151;
  font-size: 0.938rem;
  line-height: 1.5;
  margin-bottom: 1.5rem;
  padding: 1rem;
  background: #f3f4f6;
  border-left: 4px solid #3b82f6;
  border-radius: 0.375rem;
}

.motivo-options {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.motivo-option {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1rem;
  border: 2px solid #e5e7eb;
  border-radius: 0.5rem;
  cursor: pointer;
  transition: all 0.2s;
}

.motivo-option:hover {
  background: #f9fafb;
  border-color: #3b82f6;
}

.motivo-option input[type="radio"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  margin-top: 2px;
  flex-shrink: 0;
}

.motivo-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.motivo-option input[type="radio"]:checked ~ .motivo-info .motivo-text {
  font-weight: 600;
  color: #1f2937;
}

.motivo-text {
  flex: 1;
  color: #4b5563;
  font-size: 0.938rem;
  font-weight: 500;
}

.motivo-permissions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.permission-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 500;
}

.permission-badge.view {
  background: #dbeafe;
  color: #1e40af;
}

.permission-badge.print {
  background: #fef3c7;
  color: #92400e;
}

.permission-badge.download {
  background: #d1fae5;
  color: #065f46;
}

.motivo-warning {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: #fef3c7;
  border: 1px solid #fbbf24;
  border-radius: 0.375rem;
  color: #92400e;
  font-size: 0.875rem;
  font-weight: 500;
}

.motivo-warning svg {
  flex-shrink: 0;
}
</style>
