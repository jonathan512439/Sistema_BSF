<template>
  <div class="version-history-view">
    <div class="header">
      <div class="header-left">
        <button @click="$router.back()" class="btn-back" title="Volver">
          ← Atrás
        </button>
        <div class="header-text">
          <h2>Historial de Versiones</h2>
          <p class="document-title">{{ documento.titulo }}</p>
        </div>
      </div>
      <div class="document-info">
        <span>Versión actual: <strong>V{{ documento.version_actual }}</strong></span>
        <span>Total versiones: <strong>{{ documento.total_versiones }}</strong></span>
        <span class="estado-badge" :class="'estado-' + documento.estado">{{ formatEstado(documento.estado) }}</span>
      </div>
    </div>
    
    <!-- Botón Crear Nueva Versión -->
    <button 
      v-if="can('doc.version') && !isSealed"
      @click="showUploadModal = true"
      class="btn-new-version"
    >
      ➕ Subir Nueva Versión
    </button>
    
    <div v-if="isSealed" class="warning-sealed">
      🔒 <strong>Documento Sellado:</strong> No se pueden crear nuevas versiones. El sellado certifica la inmutabilidad del documento.
    </div>
    
    <!-- Timeline de Versiones -->
    <div class="versions-timeline">
      <div 
        v-for="(version, index) in versions" 
        :key="version.id"
        class="version-item"
        :class="{ 'is-current': version.es_version_actual }"
      >
        <!-- Marcador Timeline -->
        <div class="timeline-marker">
          <div class="version-badge">V{{ version.version_numero }}</div>
          <div v-if="index < versions.length - 1" class="timeline-line"></div>
        </div>
        
        <!-- Contenido -->
        <div class="version-content">
          <div class="version-header">
            <h3>Versión {{ version.version_numero }}</h3>
            <span v-if="version.es_version_actual" class="badge-current">ACTUAL</span>
            <span class="version-type">{{ version.version_tipo === 'manual' ? '👤 Manual' : '🤖 Automática' }}</span>
          </div>
          
          <div class="version-details">
            <div class="detail-row">
              <span class="label">📅 Fecha:</span>
              <span class="value">{{ formatDateTime(version.creado_en) }}</span>
            </div>
            
            <div class="detail-row">
              <span class="label">👤 Usuario:</span>
              <span class="value">{{ version.creado_por_name }}</span>
            </div>
            
            <div class="detail-row">
              <span class="label">📄 Páginas:</span>
              <span class="value">
                {{ getPaginas(version) }}
                <span v-if="index < versions.length - 1" class="change-indicator">
                  {{ getDiferenciaPaginas(version, versions[index + 1]) }}
                </span>
              </span>
            </div>
            
            <div class="detail-row">
              <span class="label">💾 Tamaño:</span>
              <span class="value">{{ formatBytes(version.archivo_size_bytes) }}</span>
            </div>
            
            <div class="detail-row">
              <span class="label">🔒 Hash:</span>
              <span class="value mono">{{ version.archivo_hash?.substring(0, 16) }}...</span>
            </div>
            
            <div class="detail-row full-width">
              <span class="label">📝 Motivo:</span>
              <p class="motivo">{{ version.version_motivo }}</p>
            </div>
          </div>
          
          <!-- Cambios Detallados -->
          <div v-if="version.cambios && version.cambios.length" class="cambios-detalle">
            <h4>Cambios Realizados:</h4>
            <ul>
              <li v-for="cambio in version.cambios" :key="cambio.id">
                <strong>{{ formatCampo(cambio.campo) }}:</strong>
                <span class="valor-anterior">{{ cambio.valor_anterior }}</span>
                <span class="arrow">→</span>
                <span class="valor-nuevo">{{ cambio.valor_nuevo }}</span>
              </li>
            </ul>
          </div>
          
          <!-- Acciones -->
          <div class="version-actions">
            <button @click="verVersion(version)" class="btn-secondary">
              👁️ Ver
            </button>
            <button @click="descargarVersion(version)" class="btn-primary">
              📥 Descargar
            </button>
            <button 
              v-if="!version.es_version_actual && can('doc.version') && !isSealed"
              @click="showRestoreModal(version)"
              class="btn-warning"
            >
              ♻️ Restaurar
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Modal: Subir Nueva Versión -->
    <BaseModal
      :open="showUploadModal"
      title="Subir Nueva Versión del Documento"
      size="large"
      @close="showUploadModal = false"
    >
      <div class="upload-version-form">
        <div v-if="documento.estado === 'validado'" class="warning-validado">
          ⚠️ <strong>Documento Validado:</strong> Al agregar una nueva versión, se registrará en auditoría como "Versión posterior a validación".
        </div>
        
        <!-- TABS: Reemplazar PDF vs Agregar Páginas -->
        <div class="upload-tabs">
          <button 
            @click="uploadMode = 'pdf'" 
            :class="['tab-btn', { 'active': uploadMode === 'pdf' }]"
          >
            📄 Subir Nuevo PDF
          </button>
          <button 
            @click="uploadMode = 'agregar-paginas'" 
            :class="['tab-btn', { 'active': uploadMode === 'agregar-paginas' }]"
          >
            🖼️ Agregar Páginas (Imágenes)
          </button>
        </div>
        
        <!-- Información del documento actual -->
        <div class="info-actual">
          <p><strong>Documento:</strong> {{ documento.titulo }}</p>
          <p><strong>Versión actual:</strong> V{{ documento.version_actual }}</p>
          <p><strong>Páginas actuales:</strong> {{ paginasActuales }}</p>
        </div>
        
        <!-- MODO 1: Reemplazar PDF Completo -->
        <div v-if="uploadMode === 'pdf'" class="upload-mode">
          <!-- Upload de PDF directo -->
          <div class="form-group">
            <label>Archivo PDF *</label>
            <input 
              type="file"
              ref="fileInput"
              accept="application/pdf"
              @change="handleFileSelect"
            />
            <small v-if="uploadForm.archivo">
              {{ uploadForm.archivo.name }} - {{ formatBytes(uploadForm.archivo.size) }}
            </small>
          </div>
        </div>
        
        <!-- MODO 2: Agregar Páginas desde Imágenes -->
        <div v-else-if="uploadMode === 'agregar-paginas'" class="upload-mode">
          <div class="info-agregar-paginas">
            <p>📌 Las imágenes seleccionadas se <strong>agregarán al final</strong> del PDF actual, creando una nueva versión.</p>
          </div>
          
          <div class="form-group">
            <label>Imágenes a agregar *</label>
            <input 
              type="file"
              ref="imagenesInput"
              multiple
              accept="image/jpeg,image/jpg,image/png"
              @change="handleImagenesSelect"
            />
            <small class="image-count">
              {{ imagenesSeleccionadas.length }} imagen(es) seleccionada(s)
            </small>
          </div>
          
          <!-- Preview visual de páginas -->
          <div v-if="imagenesSeleccionadas.length > 0" class="preview-paginas">
            <div class="contador-visual">
              <div class="paginas-antes">
                <div class="numero">{{ paginasActuales }}</div>
                <div class="label">páginas actuales</div>
              </div>
              <div class="arrow">→</div>
              <div class="paginas-despues">
                <div class="numero">{{ paginasActuales + imagenesSeleccionadas.length }}</div>
                <div class="label">páginas nuevas</div>
                <span class="badge-added">(+{{ imagenesSeleccionadas.length }})</span>
              </div>
            </div>
          </div>
        </div>
        
        <div class="form-group">
          <label>Motivo del cambio *</label>
          <textarea
            v-model="uploadForm.motivo"
            rows="4"
            :placeholder="uploadMode === 'pdf' 
              ? 'Ejemplo: Se reemplazó el documento con versión actualizada (mínimo 10 caracteres)'
              : 'Ejemplo: Se agregaron certificados de capacitación realizados en 2024 (mínimo 10 caracteres)'"
          ></textarea>
          <small>{{ uploadForm.motivo.length }} / 10 caracteres mínimo</small>
        </div>
        
        <div class="modal-actions">
          <button @click="showUploadModal = false" class="btn-cancel">Cancelar</button>
          <button 
            @click="subirVersion"
            class="btn-confirm"
            :disabled="uploading || !puedeSubir"
          >
            {{ uploading ? '⏳ Procesando...' : (uploadMode === 'pdf' ? '📤 Subir PDF' : '➕ Agregar Páginas') }}
          </button>
        </div>
      </div>
    </BaseModal>
    
    <!-- Modal: Generador de PDF desde Imágenes -->
    <ImageToPDFGenerator
      :open="showImageToPDF"
      :headers="headers"
      @close="showImageToPDF = false"
      @pdf-generated="handlePDFGenerated"
    />
    
    <!-- Modal: Restaurar Versión -->
    <BaseModal
      :open="showRestoreConfirm"
      title="Restaurar Versión Anterior"
      @close="showRestoreConfirm = false"
    >
      <div class="restore-form">
        <p class="warning">
          ⚠️ <strong>Atención:</strong> Esto creará una nueva versión (V{{ documento.total_versiones + 1 }}) 
          con el contenido de la versión {{ selectedVersion?.version_numero }}.
        </p>
        
        <div class="version-comparison">
          <div class="version-info">
            <h4>Versión actual (V{{ documento.version_actual }})</h4>
            <p>Páginas: {{ paginasActuales }}</p>
          </div>
          <div class="arrow">→</div>
          <div class="version-info">
            <h4>Se restaurará (V{{ selectedVersion?.version_numero }})</h4>
            <p>Páginas: {{ getPaginas(selectedVersion) }}</p>
          </div>
        </div>
        
        <div class="form-group">
          <label>Motivo de la restauración *</label>
          <textarea
            v-model="restoreForm.motivo"
            rows="3"
            placeholder="Explique por qué está restaurando esta versión (mínimo 10 caracteres)"
          ></textarea>
        </div>
        
        <div class="modal-actions">
          <button @click="showRestoreConfirm = false" class="btn-cancel">Cancelar</button>
          <button 
            @click="restaurarVersion"
            class="btn-confirm"
            :disabled="restoring || restoreForm.motivo.length < 10"
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
import { useRoute, useRouter } from 'vue-router'
import { useToast } from '@/composables/useToast'
import BaseModal from '@/components/ui/BaseModal.vue'
import ImageToPDFGenerator from '@/components/documents/ImageToPDFGenerator.vue'

const route = useRoute()
const router = useRouter()
const { success, error } = useToast()

const documento = ref({})  // Renombrado de 'document' a 'documento'
const versions = ref([])
const showUploadModal = ref(false)
const showRestoreConfirm = ref(false)
const showImageToPDF = ref(false)  // Modal de conversor de imágenes
const selectedVersion = ref(null)
const uploading = ref(false)
const restoring = ref(false)

const uploadMode = ref('pdf') // 'pdf' o 'agregar-paginas'
const imagenesSeleccionadas = ref([])

// Headers para el generador de PDF (usa window.document explícitamente)
const headers = computed(() => {
  const csrfToken = window.document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  return csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}
})

const uploadForm = ref({
  archivo: null,
  motivo: ''
})

const restoreForm = ref({
  motivo: ''
})

const isSealed = computed(() => {
  return ['custodio', 'sellado'].includes(documento.value.estado)
})

const paginasActuales = computed(() => {
  const versionActual = versions.value.find(v => v.es_version_actual)
  return getPaginas(versionActual)
})

const puedeSubir = computed(() => {
  if (uploadMode.value === 'pdf') {
    return uploadForm.value.archivo && uploadForm.value.motivo.length >= 10
  } else if (uploadMode.value === 'agregar-paginas') {
    return imagenesSeleccionadas.value.length > 0 && uploadForm.value.motivo.length >= 10
  }
  return false
})

function can(permission) {
  // Placeholder - integrar sistema de permisos real
  return true
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

function formatBytes(bytes) {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}

function formatEstado(estado) {
  const map = {
    'capturado': 'Capturado',
    'pendiente': 'Pendiente',
    'validado': 'Validado',
    'custodio': 'En Custodia',
    'sellado': 'Sellado'
  }
  return map[estado] || estado
}

function getPaginas(version) {
  if (!version) return '?'
  
  // PRIORIDAD 1: Usar campo dedicado numero_paginas si existe
  if (version.numero_paginas != null && version.numero_paginas > 0) {
    return version.numero_paginas
  }
  
  // PRIORIDAD 2: Parsear desde version_motivo (fallback para versiones antiguas)
  if (version.version_motivo) {
    // Patrón principal: "Páginas: XX"
    const matchPaginas = version.version_motivo.match(/Páginas:\s*(\d+)/i)
    if (matchPaginas) return parseInt(matchPaginas[1])
    
    // Patrón alternativo: "XX → YY" (para incrementos)
    const matchIncremento = version.version_motivo.match(/(\d+)\s*→\s*(\d+)/)
    if (matchIncremento) return parseInt(matchIncremento[2]) // Devolver el valor final
    
    // Patrón de suma: "(+XX)"
    const matchSuma = version.version_motivo.match(/\(\+(\d+)\)/)
    if (matchSuma && paginasActuales.value) {
      return parseInt(paginasActuales.value) + parseInt(matchSuma[1])
    }
  }
  
  // Si no se encuentra nada, devolver interrogante
  return '?'
}

function getDiferenciaPaginas(versionNueva, versionAnterior) {
  if (!versionNueva || !versionAnterior) return ''
  
  const nuevas = parseInt(getPaginas(versionNueva))
  const anteriores = parseInt(getPaginas(versionAnterior))
  
  // Validar que ambos son números válidos
  if (isNaN(nuevas) || isNaN(anteriores)) return ''
  
  const diff = nuevas - anteriores
  
  if (diff > 0) return `(+${diff} 📄)`
  if (diff < 0) return `(${diff} 📄)`
  return ''
}

function formatCampo(campo) {
  const nombres = {
    'numero_paginas': 'Número de páginas',
    'archivo_pdf': 'Archivo PDF',
    'titulo': 'Título',
    'descripcion': 'Descripción',
    'estado': 'Estado',
    'tamano_bytes': 'Tamaño'
  }
  return nombres[campo] || campo
}

function handleFileSelect(event) {
  uploadForm.value.archivo = event.target.files[0]
}

function handleImagenesSelect(event) {
  imagenesSeleccionadas.value = Array.from(event.target.files)
}

async function subirVersion() {
  if (uploadMode.value === 'pdf') {
    await subirNuevaVersion()
  } else if (uploadMode.value === 'agregar-paginas') {
    await agregarPaginasConImagenes()
  }
}

// Manejar PDF generado desde imágenes
function handlePDFGenerated(pdfFile) {
  // Asignar el PDF generado al formulario
  uploadForm.value.archivo = pdfFile
  
  // Cambiar a modo PDF y abrir modal de upload
  uploadMode.value = 'pdf'
  showUploadModal.value = true
  
  // Pre-rellenar motivo sugerido
  uploadForm.value.motivo = `Nueva versión generada desde ${pdfFile.name.includes('imagen') ? 'imágenes escaneadas' : 'archivos de imagen'}`
}

// Agregar páginas desde imágenes al PDF actual
async function agregarPaginasConImagenes() {
  uploading.value = true
  
  try {
    const formData = new FormData()
    
    // Agregar todas las imágenes
    imagenesSeleccionadas.value.forEach((imagen, index) => {
      formData.append(`imagenes[${index}]`, imagen)
    })
    
    formData.append('motivo', uploadForm.value.motivo)
    
    // Usar axios que maneja CSRF automáticamente
    const response = await window.axios.post(
      `/api/documentos/${route.params.id}/versiones/agregar-paginas`,
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      }
    )
    
    const result = response.data
    
    if (result.ok) {
      success('Páginas Agregadas', result.message || 'Se agregaron las páginas al documento')
      await loadVersions()
      showUploadModal.value = false
      uploadForm.value = { archivo: null, motivo: '' }
      imagenesSeleccionadas.value = []
    } else {
      error('Error', result.message || 'No se pudieron agregar las páginas')
    }
  } catch (e) {
    const errorMsg = e.response?.data?.message || e.message
    error('Error', errorMsg)
    console.error('[AGREGAR_PAGINAS] Error:', e)
  } finally {
    uploading.value = false
  }
}



async function loadVersions() {
  try {
    const response = await fetch(`/api/documentos/${route.params.id}/versiones`, {
      credentials: 'include'
    })
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`)
    }
    
    const data = await response.json()
    
    if (data.ok) {
      versions.value = data.versiones || []
      documento.value = data.documento || {}
      
      // Validar que haya al menos una versión
      if (versions.value.length === 0) {
        error('Advertencia', 'Este documento no tiene versiones registradas. Esto puede indicar un problema en la creación del documento.')
      }
      
      console.log(`[VERSION_HISTORY] Cargadas ${versions.value.length} versiones para documento ${route.params.id}`)
    } else {
      throw new Error(data.message || 'No se pudieron cargar las versiones')
    }
  } catch (e) {
    console.error('[VERSION_HISTORY] Error cargando versiones:', e)
    error('Error de Conexión', `No se pudo cargar el historial de versiones: ${e.message}`)
    
    
    versions.value = []
    documento.value = {}
  }
}

async function subirNuevaVersion() {
  uploading.value = true
  
  try {
    const formData = new FormData()
    formData.append('archivo', uploadForm.value.archivo)
    formData.append('motivo', uploadForm.value.motivo)
    
    const response = await fetch(`/api/documentos/${route.params.id}/versiones`, {
      method: 'POST',
      credentials: 'include',
      body: formData
    })
    
    const result = await response.json()
    
    if (result.ok) {
      success('Nueva Versión Creada', result.message)
      await loadVersions()
      showUploadModal.value = false
      uploadForm.value = { archivo: null, motivo: '' }
    } else {
      error('Error', result.message)
    }
  } catch (e) {
    error('Error', e.message)
  } finally {
    uploading.value = false
  }
}

function showRestoreModal(version) {
  selectedVersion.value = version
  showRestoreConfirm.value = true
}

async function restaurarVersion() {
  restoring.value = true
  
  try {
    const response = await fetch(
      `/api/documentos/${route.params.id}/versiones/${selectedVersion.value.version_numero}/restaurar`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ motivo: restoreForm.value.motivo })
      }
    )
    
    const result = await response.json()
    
    if (result.ok) {
      success('Versión Restaurada', result.message)
      await loadVersions()
      showRestoreConfirm.value = false
      restoreForm.value.motivo = ''
    } else {
      error('Error', result.message)
    }
  } catch (e) {
    error('Error', e.message)
  } finally {
    restoring.value = false
  }
}

async function descargarVersion(version) {
  window.open(`/api/versiones/${version.id}/descargar`, '_blank')
}

function verVersion(version) {
  window.open(`/api/versiones/${version.id}/ver`, '_blank')
}

onMounted(() => {
  loadVersions()
})
</script>

<style scoped>
.version-history-view {
  max-width: 1200px;
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

.document-title {
  font-size: 1.2rem;
  color: #6b7280;
  margin-bottom: 1rem;
}

.document-info {
  display: flex;
  gap: 2rem;
  font-size: 0.9rem;
}

.estado-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 4px;
  font-weight: 600;
}

.estado-capturado { background: #dbeafe; color: #1e40af; }
.estado-validado { background: #dcfce7; color: #166534; }
.estado-sellado { background: #fef3c7; color: #92400e; }

.btn-new-version {
  background: #3b82f6;
  color: white;
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 6px;
  font-size: 1rem;
  cursor: pointer;
  margin-bottom: 2rem;
}

.btn-new-version:hover {
  background: #2563eb;
}

.warning-sealed,
.warning-validado {
  background: #fef3c7;
  border-left: 4px solid #f59e0b;
  padding: 1rem;
  margin-bottom: 2rem;
  border-radius: 4px;
}

.versions-timeline {
  position: relative;
}

.version-item {
  display: flex;
  margin-bottom: 2rem;
  position: relative;
}

.version-item.is-current .version-content {
  border: 2px solid #3b82f6;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

.timeline-marker {
  flex-shrink: 0;
  width: 80px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.version-badge {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: #3b82f6;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.timeline-line {
  width: 2px;
  flex: 1;
  background: #d1d5db;
  margin-top: 0.5rem;
}

.version-content {
  flex: 1;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1.5rem;
  margin-left: 1rem;
}

.version-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e5e7eb;
}

.version-header h3 {
  margin: 0;
  font-size: 1.3rem;
  color: #1f2937;
}

.badge-current {
  background: #3b82f6;
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 700;
}

.version-type {
  margin-left: auto;
  color: #6b7280;
  font-size: 0.9rem;
}

.version-details {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.detail-row {
  display: flex;
  gap: 0.5rem;
}

.detail-row.full-width {
  grid-column: 1 / -1;
  flex-direction: column;
}

.detail-row .label {
  font-weight: 600;
  color: #374151;
  min-width: 100px;
}

.detail-row .value {
  color: #6b7280;
}

.detail-row .mono {
  font-family: 'Courier New', monospace;
  font-size: 0.85rem;
}

.change-indicator {
  color: #16a34a;
  font-weight: 600;
  margin-left: 0.5rem;
}

.motivo {
  margin: 0.5rem 0 0;
  color: #4b5563;
  line-height: 1.5;
}

.cambios-detalle {
  background: #f9fafb;
  padding: 1rem;
  border-radius: 6px;
  margin-bottom: 1rem;
}

.cambios-detalle h4 {
  margin: 0 0 0.75rem;
  font-size: 0.95rem;
  color: #374151;
}

.cambios-detalle ul {
  margin: 0;
  padding-left: 1.5rem;
}

.cambios-detalle li {
  margin-bottom: 0.5rem;
  color: #6b7280;
  font-size: 0.9rem;
}

.valor-anterior {
  color: #dc2626;
  text-decoration: line-through;
  margin-left: 0.5rem;
}

.arrow {
  margin: 0 0.5rem;
  color: #9ca3af;
}

.valor-nuevo {
  color: #16a34a;
  font-weight: 600;
}

.version-actions {
  display: flex;
  gap: 0.75rem;
  padding-top: 1rem;
  border-top: 1px solid #e5e7eb;
}

.btn-secondary,
.btn-primary,
.btn-warning {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 500;
}

.btn-secondary {
  background: #f3f4f6;
  color: #374151;
}

.btn-secondary:hover {
  background: #e5e7eb;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-primary:hover {
  background: #2563eb;
}

.btn-warning {
  background: #f59e0b;
  color: white;
}

.btn-warning:hover {
  background: #d97706;
}

/* Modal styles */
.upload-version-form,
.restore-form {
  padding: 1rem 0;
}

.info-actual {
  background: #f0f9ff;
  padding: 1rem;
  border-radius: 6px;
  margin-bottom: 1.5rem;
}

.info-actual p {
  margin: 0.5rem 0;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #374151;
}

.form-group input[type="file"],
.form-group textarea {
  width: 100%;
  padding: 0.625rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.9rem;
}

.form-group small {
  display: block;
  margin-top: 0.25rem;
  color: #6b7280;
  font-size: 0.85rem;
}

.modal-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  margin-top: 1.5rem;
}

.btn-cancel,
.btn-confirm,
.btn-danger {
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
  background: #3b82f6;
  color: white;
}

.btn-confirm:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.warning {
  background: #fef3c7;
  border-left: 4px solid #f59e0b;
  padding: 1rem;
  margin-bottom: 1rem;
}

.version-comparison {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin: 1.5rem 0;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 6px;
}

.version-info {
  flex: 1;
}

.version-info h4 {
  margin: 0 0 0.5rem;
  font-size: 0.95rem;
}

.version-info p {
  margin: 0;
  color: #6b7280;
}

.version-comparison .arrow {
  font-size: 2rem;
  color: #9ca3af;
}

/* === GENERATOR OPTION === */
.generator-option {
  text-align: center;
  padding: 1.5rem;
  background: linear-gradient(135deg, #556b2f 0%, #6b8e23 100%);
  border-radius: 12px;
  margin-bottom: 1.5rem;
}

.option-text {
  color: white;
  font-size: 1rem;
  margin: 0 0 1rem 0;
  font-weight: 500;
}

.btn-generator {
  background: white;
  color: #556b2f;
  padding: 0.75rem 2rem;
  border: 2px solid white;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.938rem;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.btn-generator:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
  background: #f0f4e8;
}

.option-hint {
  color: rgba(255, 255, 255, 0.9);
  font-size: 0.813rem;
  margin: 0.75rem 0 0 0;
  font-style: italic;
}

/* === TABS DE UPLOAD === */
.upload-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  border-bottom: 2px solid #e5e7eb;
}

.tab-btn {
  flex: 1;
  padding: 0.75rem 1rem;
  border: none;
  border-bottom: 3px solid transparent;
  background: transparent;
  cursor: pointer;
  font-weight: 500;
  color: #6b7280;
  transition: all 0.2s;
  font-size: 1rem;
}

.tab-btn:hover {
  color: #3b82f6;
  background: #f3f4f6;
}

.tab-btn.active {
  color: #3b82f6;
  border-bottom-color: #3b82f6;
  margin-bottom: -2px;
  font-weight: 600;
}

/* === INFO ACTUAL === */
.info-actual {
  background: #f9fafb;
  padding: 1rem;
  border-radius: 6px;
  margin-bottom: 1.5rem;
}

.info-actual p {
  margin: 0.5rem 0;
}

/* === INFO AGREGAR PÁGINAS === */
.info-agregar-paginas {
  background: #eff6ff;
  border-left: 4px solid #3b82f6;
  padding: 1rem;
  margin-bottom: 1.5rem;
  border-radius: 4px;
}

.info-agregar-paginas p {
  margin: 0;
  color: #1e40af;
  font-size: 0.938rem;
}

.image-count {
  color: #3b82f6;
  font-weight: 600;
}

/* === PREVIEW DE PÁGINAS === */
.preview-paginas {
  background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
  border: 2px solid #bfdbfe;
  border-radius: 12px;
  padding: 2rem;
  margin-top: 1.5rem;
}

.contador-visual {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2.5rem;
}

.paginas-antes,
.paginas-despues {
  flex: 1;
  text-align: center;
  padding: 1rem;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.paginas-antes .numero,
.paginas-despues .numero {
  font-size: 3rem;
  font-weight: 700;
  color: #1f2937;
  line-height: 1;
  margin-bottom: 0.5rem;
}

.paginas-despues .numero {
  color: #3b82f6;
}

.paginas-antes .label,
.paginas-despues .label {
  font-size: 0.875rem;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 600;
}

.contador-visual .arrow {
  font-size: 2.5rem;
  color: #3b82f6;
}

.badge-added {
  background: #16a34a;
  color: white;
  padding: 0.35rem 0.75rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 700;
  margin-top: 0.75rem;
  display: inline-block;
}

/* === WARNING === */
.warning-validado {
  background: #fef3c7;
  border-left: 4px solid #f59e0b;
  padding: 1rem;
  margin-bottom: 1.5rem;
  border-radius: 4px;
}
</style>
