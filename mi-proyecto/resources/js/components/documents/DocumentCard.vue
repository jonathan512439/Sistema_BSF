<template>
  <div class="document-card-modern">
    <!-- Header Section -->
    <div class="card-header">
      <div class="header-content">
        <div class="doc-title-section">
          <h3 class="doc-title">{{ doc.titulo || '(sin título)' }}</h3>
          <div class="doc-meta">
            <span class="doc-id">#{{ doc.id }}</span>
            <span class="dot">•</span>
            <span class="estado-badge" :class="`estado-${estadoActual}`">
              {{ formatEstado(estadoActual) }}
            </span>
            <span v-if="doc.is_confidential" class="confidential-badge">
              🔒 Confidencial
            </span>
          </div>
      </div>
    <div class="header-actions">
        <button @click="$emit('view', doc.id)" class="btn-action btn-primary">
          <span class="btn-icon"></span>
          Ver PDF
        </button>
        <button v-if="open && ocrFullText" @click="showOcrModal = true" class="btn-action btn-secondary">
          🔍 Ver Texto OCR
        </button>
      </div>
    </div>
  </div>

    <!-- Details Section (Expandable) -->
    <transition name="expand">
      <div v-if="open" class="card-details">
        <!-- OCR Confidence Indicator -->
        <div v-if="doc.ocr_confidence" class="ocr-banner" :class="getConfidenceClass(doc.ocr_confidence)">
          <span class="ocr-icon">🔍</span>
          <span class="ocr-text">Confianza OCR: <strong>{{ doc.ocr_confidence }}%</strong></span>
        </div>

        <!-- Details Grid -->
        <div class="details-grid">
          <!-- Metadatos Archivísticos -->
          <div class="detail-section">
            <h4 class="section-header">
              Metadatos Archivísticos
            </h4>
            <div class="detail-rows">
              <div class="detail-row">
                <span class="detail-label">Tipo de Documento:</span>
                <span class="detail-value">{{ tipoDocumentoNombre }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Sección:</span>
                <span class="detail-value">{{ seccionNombre }}</span>
              </div>
              <div v-if="subseccionNombre && subseccionNombre !== '—'" class="detail-row">
                <span class="detail-label">Subsección:</span>
                <span class="detail-value">{{ subseccionNombre }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Gestión:</span>
                <span class="detail-value">{{ gestionTexto }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Fecha del Documento:</span>
                <span class="detail-value">{{ det.documento?.fecha_documento ?? doc.fecha_documento ?? '—' }}</span>
              </div>
              <div v-if="det.documento?.descripcion || doc.descripcion" class="detail-row full-width">
                <span class="detail-label">Descripción:</span>
                <span class="detail-value description">{{ det.documento?.descripcion ?? doc.descripcion }}</span>
              </div>
            </div>
          </div>

          <!-- Custodia Digital -->
          <div class="detail-section">
            <h4 class="section-header">
              Custodia Digital
            </h4>
            <div class="detail-rows">
              <div class="detail-row">
                <span class="detail-label">Estado Actual:</span>
                <span class="detail-value">
                  <span class="estado-badge" :class="`estado-${estadoActual}`">
                    {{ formatEstado(estadoActual) }}
                  </span>
                </span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Hash de Custodia:</span>
                <span v-if="custodiaHash" class="detail-value mono">
                  {{ custodiaHash.slice(0, 32) }}...
                </span>
                <span v-else class="detail-value muted">(aún no sellado)</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Validado por:</span>
                <span class="detail-value">{{ det.documento?.validado_por ?? '—' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Fecha de Validación:</span>
                <span class="detail-value">{{ det.documento?.validado_en ?? '—' }}</span>
              </div>
            </div>
          </div>

          <!-- Archivo Digital -->
          <div class="detail-section">
            <h4 class="section-header">
              Archivo Digital
            </h4>
            <div class="detail-rows">
              <div class="detail-row">
                <span class="detail-label">Versión:</span>
                <span class="detail-value">{{ det.archivo?.version ?? '—' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Ruta Relativa:</span>
                <span class="detail-value mono small">{{ det.archivo?.ruta_relativa ?? '—' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">SHA256:</span>
                <span v-if="det.archivo?.sha256" class="detail-value mono small">
                  {{ det.archivo.sha256.slice(0, 32) }}...
                </span>
                <span v-else class="detail-value muted">—</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Tamaño:</span>
                <span class="detail-value">{{ formatBytes(det.archivo?.bytes) }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Almacén:</span>
                <span class="detail-value">{{ det.almacen?.nombre || '—' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Tipo Almacén:</span>
                <span class="detail-value">{{ det.almacen?.tipo || '—' }}</span>
              </div>
            </div>
          </div>

          <!-- Ubicación Física -->
          <div class="detail-section">
            <h4 class="section-header">
              <span class="header-icon">📍</span>
              Ubicación Física
            </h4>
            <div v-if="det.ubicacion" class="detail-rows">
              <div class="detail-row">
                <span class="detail-label">Ubicación Actual:</span>
                <span class="detail-value">{{ det.ubicacion.codigo }} — {{ det.ubicacion.descripcion }}</span>
              </div>
              <div v-if="det.ubicacion.codigo_fisico" class="detail-row">
                <span class="detail-label">Código Físico:</span>
                <span class="detail-value">{{ det.ubicacion.codigo_fisico }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Estado Físico:</span>
                <span class="detail-value">{{ det.ubicacion.estado_fisico || '—' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Asignado Desde:</span>
                <span class="detail-value">{{ det.ubicacion.desde || '—' }}</span>
              </div>
            </div>
            <div v-else class="empty-state">
              Sin ubicación física registrada
            </div>

            <!-- Formulario de Movimiento -->
            <div v-if="can('doc.move')" class="location-form">
              <div class="form-header">Mover a Nueva Ubicación</div>
              <div class="form-grid">
                <div class="form-field">
                  <label>Nueva Ubicación *</label>
                  <select v-model="nuevaUbicacionId">
                    <option :value="null">— Seleccionar —</option>
                    <option 
                      v-for="u in (catalogs.ubicaciones || [])" 
                      :key="u.id" 
                      :value="u.id"
                    >
                      {{ u.codigo }} — {{ u.nombre || u.descripcion }}
                    </option>
                  </select>
                </div>
                <div class="form-field">
                  <label>Motivo</label>
                  <input 
                    v-model="nuevaUbicacionMotivo" 
                    placeholder="Ej: Reorganización"
                  />
                </div>
                <div class="form-field">
                  <label>Código Físico</label>
                  <input 
                    v-model="nuevaUbicacionCodigoFisico" 
                    placeholder="Ej: CAJA-001"
                  />
                </div>
                <div class="form-field">
                  <label>Estado Físico</label>
                  <select v-model="nuevaUbicacionEstadoFisico">
                    <option value="">— Sin especificar —</option>
                    <option value="excelente">Excelente</option>
                    <option value="bueno">Bueno</option>
                    <option value="regular">Regular</option>
                    <option value="deteriorado">Deteriorado</option>
                    <option value="critico">Crítico</option>
                  </select>
                </div>
              </div>
              <button 
                @click="guardarUbicacion" 
                class="btn-action btn-primary"
                :disabled="ubicacionGuardando || !nuevaUbicacionId"
              >
                {{ ubicacionGuardando ? '⏳ Guardando...' : '✓ Actualizar Ubicación' }}
              </button>
            </div>
          </div>
        </div>

       

        <!-- OCR Text Display -->
        <div v-if="ocrFullText && ocrFullText.trim()" class="ocr-text-section">
          <h4 class="section-header">
            <span class="header-icon"></span>
            Texto Extraído (OCR)
          </h4>
          <div class="ocr-text-preview">
            {{ ocrFullText.substring(0, 500) }}{{ ocrFullText.length > 500 ? '...' : '' }}
          </div>
          <button @click="showOcrModal = true" class="btn-action btn-secondary" style="margin-top: 1rem;">
            📄 Ver Texto Completo
          </button>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
          <button 
            @click="verHistorialVersiones" 
            class="btn-action btn-secondary"
            title="Ver historial de versiones"
          >
             Historial
          </button>
          <button 
            v-if="can('doc.edit')" 
            @click="showEditModal = true" 
            class="btn-action btn-secondary"
            :disabled="det.documento?.estado === 'validado' || det.documento?.estado === 'custodio'"
            :title="det.documento?.estado === 'validado' || det.documento?.estado === 'custodio' ? 'No se puede editar un documento validado o custodiado' : 'Editar documento'"
          >
             Editar
          </button>
          <button 
            v-if="can('doc.validate')" 
            @click="emitOcr" 
            class="btn-action btn-secondary"
          >
            🔍 Procesar OCR
          </button>
          <button 
            v-if="mostrarValidar" 
            @click="showValidateModal = true" 
            class="btn-action btn-olive"
          >
            ✓ Validar Documento
          </button>
          <button 
            v-if="can('doc.seal') && estadoActual === 'validado'" 
            @click="showSealModal = true" 
            class="btn-action btn-olive"
          >
             Sellar Custodia
          </button>
          <button 
  v-if="puedeEliminar && can('doc.delete')"
  @click="showDeleteModal = true"
  class="btn-action btn-danger"
>
  Eliminar
</button>
        </div>
      </div>
    </transition>

    <!-- OCR Text Modal -->
    <BaseModal
      :open="showOcrModal"
      title="Texto Completo Extraído por OCR"
      size="large"
      @close="showOcrModal = false"
    >
      <div class="ocr-modal-content">
        <pre class="ocr-full-text">{{ ocrFullText }}</pre>
      </div>
    </BaseModal>

    <!-- Validate Modal - Editable Fields -->
    <BaseModal
      :open="showValidateModal"
      title="Validar Documento - Revisión de Campos"
      size="large"
      @close="showValidateModal = false"
    >
      <div class="validate-form-content">
        <p class="modal-description">
          📋 Revisa y corrige los campos del documento antes de validarlo.
        </p>
        
        <div class="form-grid">
          <!-- Título -->
          <div class="form-group full-width">
            <label class="form-label">Título *</label>
            <input 
              v-model="validationForm.titulo" 
              type="text" 
              class="form-input"
              placeholder="Título del documento"
            />
          </div>
          
          <!-- Descripción -->
          <div class="form-group full-width">
            <label class="form-label">Descripción</label>
            <textarea 
              v-model="validationForm.descripcion" 
              class="form-textarea"
              rows="3"
              placeholder="Descripción breve del contenido"
            ></textarea>
          </div>
          
          <!-- Tipo Documento y Sección (2 columnas) -->
          <div class="form-group">
            <label class="form-label">Tipo de Documento</label>
            <select v-model="validationForm.tipo_documento_id" class="form-select">
              <option :value="null">Seleccionar...</option>
              <option 
                v-for="tipo in catalogs.tipos_documento" 
                :key="tipo.id" 
                :value="tipo.id"
              >
                {{ tipo.nombre }}
              </option>
            </select>
          </div>
          
          <div class="form-group">
            <label class="form-label">Sección</label>
            <select v-model="validationForm.seccion_id" class="form-select">
              <option :value="null">Seleccionar...</option>
              <option 
                v-for="seccion in catalogs.secciones" 
                :key="seccion.id" 
                :value="seccion.id"
              >
                {{ seccion.nombre }}
              </option>
            </select>
          </div>
          
          <!-- Subsección y Gestión (2 columnas) -->
          <div class="form-group">
            <label class="form-label">Subsección</label>
            <select v-model="validationForm.subseccion_id" class="form-select">
              <option :value="null">Ninguna</option>
              <option 
                v-for="sub in filteredSubsecciones" 
                :key="sub.id" 
                :value="sub.id"
              >
                {{ sub.nombre }}
              </option>
            </select>
          </div>
          
          <div class="form-group">
            <label class="form-label">Gestión *</label>
            <input 
              v-model="validationForm.gestion" 
              type="text" 
              class="form-input"
              placeholder="Ej: 2025"
              maxlength="4"
            />
            <small style="color: #6b7280; font-size: 0.875rem;">Año de gestión (4 dígitos)</small>
          </div>
          
          <!-- Campos de Validación Obligatorios -->
          <div class="form-group full-width" style="margin-top: 1rem; padding-top: 1rem; border-top: 2px solid #556b2f;">
            <h4 style="margin: 0 0 0.75rem; color: #556b2f; font-size: 1rem;">📝 Campos de Validación Requeridos</h4>
          </div>
          
          <div class="form-group">
            <label class="form-label">Oficial Firmante *</label>
            <input 
              v-model="validationForm.oficial" 
              type="text" 
              class="form-input"
              placeholder="Nombre del oficial que firma"
            />
          </div>
          
          <div class="form-group">
            <label class="form-label">Fecha del Documento *</label>
            <input 
              v-model="validationForm.fecha" 
              type="date" 
              class="form-input"
            />
          </div>
          
          <!-- Confidencial checkbox -->
          <div class="form-group full-width">
            <label class="form-checkbox">
              <input 
                v-model="validationForm.is_confidential" 
                type="checkbox"
              />
              <span>🔒 Marcar como confidencial</span>
            </label>
          </div>
        </div>
        
        <!-- Error Display -->
        <div v-if="validationError" class="validation-error" style="margin: 1rem 0; padding: 0.75rem; background: #fee; border-left: 4px solid #c00; color: #c00; border-radius: 4px;">
          <strong>⚠️ Error:</strong> {{ validationError }}
        </div>
        
        <p class="modal-warning">
          ⚠️ Al validar, confirmas que todos los campos son correctos. Esta acción cambiará el estado del documento a "validado".
        </p>
        
        <div class="modal-actions">
          <button @click="showValidateModal = false" class="btn-cancel">Cancelar</button>
          <button @click="saveAndValidate" class="btn-confirm" :disabled="validating">
            {{ validating ? '⏳ Guardando y Validando...' : '✓ Guardar y Validar' }}
          </button>
        </div>
      </div>
    </BaseModal>

    <!-- Seal Modal -->
    <BaseModal
      :open="showSealModal"
      title="Sellar Custodia Digital"
      size="normal"
      @close="showSealModal = false"
    >
      <div class="seal-modal-content">
        <p class="modal-description">¿Está seguro de que desea sellar la custodia de este documento?</p>
        <p class="modal-info"><strong>Documento:</strong> {{ doc.titulo || '#' + doc.id }}</p>
        <p class="modal-warning">🔐 <strong>Esta acción es irreversible.</strong> El documento quedará sellado con un hash de custodia permanente.</p>
        
        <div class="modal-actions">
          <button @click="showSealModal = false" class="btn-cancel">Cancelar</button>
          <button @click="handleSeal" class="btn-confirm btn-seal" :disabled="sealing">
            {{ sealing ? '⏳ Sellando...' : '🔐 Confirmar Sellado' }}
          </button>
        </div>
      </div>
    </BaseModal>

    <!-- Delete Modal -->
    <BaseModal
      :open="showDeleteModal"
      title="Eliminar Documento"
      size="normal"
      @close="showDeleteModal = false"
    >
      <div class="delete-modal-content">
        <p class="modal-description">
          ¿Está seguro de que desea eliminar este documento?
        </p>
        
        <p class="modal-info">
          <strong>Documento:</strong> {{ doc.titulo || '#' + doc.id }}
        </p>
        
        <div class="form-group" style="margin: 1rem 0;">
          <label class="form-label">Razón de eliminación *</label>
          <textarea 
            v-model="deleteReason" 
            class="form-textarea"
            rows="3"
            placeholder="Explique por qué está eliminando este documento (mínimo 10 caracteres)"
            style="width: 100%; padding: 0.625rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem;"
          ></textarea>
          <small v-if="deleteError" style="color: #dc2626; font-size: 0.875rem; display: block; margin-top: 0.25rem;">{{ deleteError }}</small>
        </div>
        
        <p class="modal-warning">
          ⚠️ <strong>Borrado Lógico:</strong> El documento NO se eliminará permanentemente. 
          Podrá ser restaurado desde el módulo de "Documentos Eliminados".
        </p>
        
        <div class="modal-actions">
          <button @click="showDeleteModal = false" class="btn-cancel">Cancelar</button>
          <button 
            @click="handleDelete" 
            class="btn-confirm btn-danger" 
            :disabled="deleting || !deleteReason || deleteReason.length < 10"
          >
            {{ deleting ? '⏳ Eliminando...' : '🗑️ Confirmar Eliminación' }}
          </button>
        </div>
      </div>
    </BaseModal>

    <!-- Edit Document Modal -->
    <DocumentEditModal
      :open="showEditModal"
      :documento="doc"
      :catalogs="catalogs"
      :headers="headers"
      @close="showEditModal = false"
      @success="handleEditSuccess"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from '@/composables/useToast'
import BaseModal from '../ui/BaseModal.vue'
import DocumentEditModal from './DocumentEditModal.vue'

const props = defineProps({
  doc: { type: Object, required: true },
  headers: { type: Object, required: true },
  can: { type: Function, required: true },
  catalogs: { type: Object, default: () => ({}) },
  expanded: { type: Boolean, default: false }
})

const emit = defineEmits(['view', 'validate', 'seal', 'delete', 'ocr'])

const router = useRouter()

const open = ref(true) // Siempre abierto
const det = ref({})
const mostrarTextoCompleto = ref(false)
const showOcrModal = ref(false)
const showValidateModal = ref(false)
const showSealModal = ref(false)
const showEditModal = ref(false)
const validating = ref(false)
const validationError = ref('')
const sealing = ref(false)

// Validation Form State
const validationForm = ref({
  titulo: '',
  descripcion: '',
  tipo_documento_id: null,
  seccion_id: null,
  subseccion_id: null,
  gestion_id: null,
  fecha_documento: '',
  numero_documento: '',
  is_confidential: false
})

// Ubicación
const nuevaUbicacionId = ref(null)
const nuevaUbicacionMotivo = ref('')
const nuevaUbicacionCodigoFisico = ref('')
const nuevaUbicacionEstadoFisico = ref('')
const ubicacionGuardando = ref(false)

// Delete
const showDeleteModal = ref(false)
const deleting = ref(false)
const deleteReason = ref('')
const deleteError = ref('')

const { success, error, warning } = useToast()

// Computed Properties
const estadoActual = computed(() => det.value?.documento?.estado || props.doc.estado)

const mostrarValidar = computed(() => {
  if (!props.can('doc.validate')) return false
  const e = estadoActual.value
  if (!e) return false
  const lowered = String(e).toLowerCase()
  return !lowered.includes('custodi') && !lowered.includes('sellad')
})

const puedeEliminar = computed(() => {
  const estadosPermitidos = ['capturado', 'pendiente']
  return estadosPermitidos.includes(det.value?.documento?.estado || props.doc.estado)
})

const custodiaHash = computed(() => {
  return det.value?.watermark?.custodia_hash || 
         det.value?.documento?.custodia_hash || 
         props.doc.custodia_hash || ''
})

const tipoDocumentoNombre = computed(() => {
  const id = det.value?.documento?.tipo_documento_id ?? props.doc.tipo_documento_id
  if (!id) return '—'
  const found = (props.catalogs?.tipos_documento || []).find(t => t.id === Number(id))
  return found?.nombre || `ID ${id}`
})

const seccionNombre = computed(() => {
  const id = det.value?.documento?.seccion_id ?? props.doc.seccion_id
  if (!id) return '—'
  const found = (props.catalogs?.secciones || []).find(s => s.id === Number(id))
  return found?.nombre || `ID ${id}`
})

const subseccionNombre = computed(() => {
  const id = det.value?.documento?.subseccion_id ?? props.doc.subseccion_id
  if (!id) return '—'
  const found = (props.catalogs?.subsecciones || []).find(ss => ss.id === Number(id))
  return found?.nombre || `ID ${id}`
})

const gestionTexto = computed(() => {
  const id = det.value?.documento?.gestion_id ?? props.doc.gestion_id
  if (!id) return '—'
  const found = (props.catalogs?.gestiones || []).find(g => g.id === Number(id))
  return found?.anio || `ID ${id}`
})

const ocrCamposMerge = computed(() => {
  const map = new Map()
  const camposOcr = det.value?.campos_ocr || det.value?.ocr_campos || []
  const camposVal = det.value?.campos_validados || det.value?.documento_campos || det.value?.campos || []

  camposOcr.forEach(c => {
    map.set(c.campo, {
      campo: c.campo,
      ocr: c.valor ?? '',
      ocr_conf: c.confidence ?? null,
      final: null
    })
  })

  camposVal.forEach(c => {
    const existing = map.get(c.campo) || {
      campo: c.campo,
      ocr: null,
      ocr_conf: null,
      final: null
    }
    if (!existing.ocr && c.valor_ocr) existing.ocr = c.valor_ocr
    if (existing.ocr_conf === null && c.confidence != null) existing.ocr_conf = c.confidence
    if (c.valor_final) existing.final = c.valor_final
    map.set(c.campo, existing)
  })

  return Array.from(map.values()).sort((a, b) => a.campo.localeCompare(b.campo))
})

const ocrFullText = computed(() => {
  return det.value?.ocr_full_text || det.value?.full_text || det.value?.ocr_text || ''
})

// Filtered subsecciones based on selected seccion
const filteredSubsecciones = computed(() => {
  if (!validationForm.value.seccion_id || !props.catalogs.subsecciones) {
    return []
  }
  return props.catalogs.subsecciones.filter(sub => sub.seccion_id === validationForm.value.seccion_id)
})

// Methods
async function emitOcr() {
  try {
    const response = await fetch(`/api/documentos/${props.doc.id}/ocr`, {
      method: 'POST',
      headers: props.headers,
      credentials: 'include'
    })
    
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(errorData.message || `HTTP ${response.status}`)
    }
    
    success('OCR Iniciado', 'El procesamiento OCR ha comenzado. Recarga la página en unos momentos.')
    
    // Recargar detalles después de 3 segundos
    setTimeout(async () => {
      await loadDetails()
    }, 3000)
    
  } catch (e) {
    error('Error al procesar OCR', e.message)
  }
}

async function handleValidate() {
  validating.value = true
  try {
    const response = await fetch(`/api/documentos/${props.doc.id}/validar`, {
      method: 'POST',
      headers: props.headers,
      credentials: 'include'
    })
    
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(errorData.message || `HTTP ${response.status}`)
    }
    
    success('Documento Validado', 'El documento ha sido validado exitosamente')
    showValidateModal.value = false
    await loadDetails()
    
  } catch (e) {
    error('Error al validar', e.message)
  } finally {
    validating.value = false
  }
}

async function saveAndValidate() {
  validating.value = true
  validationError.value = ''
  
  try {
    // 1. Preparar datos con auto-población desde OCR si están disponibles
    // 2. Construir datos para validación (merge form + OCR)
    const datosValidacion = {
      titulo: validationForm.value.titulo || getCampoOCR('titulo'),
      oficial: validationForm.value.oficial || getCampoOCR('oficial'),
      fecha: validationForm.value.fecha || getCampoOCR('fecha'),
      gestion: validationForm.value.gestion || getCampoOCR('gestion'),
    }

    // 3. Validar en frontend antes de enviar
    const errores = []
    if (!datosValidacion.titulo || datosValidacion.titulo.length < 3) {
      errores.push('El título debe tener al menos 3 caracteres')
    }
    if (!datosValidacion.oficial || datosValidacion.oficial.length < 3) {
      errores.push('El nombre del oficial debe tener al menos 3 caracteres')
    }
    if (!datosValidacion.fecha) {
      errores.push('La fecha es obligatoria')
    }
    if (!datosValidacion.gestion || !/^\d{4}$/.test(datosValidacion.gestion)) {
      errores.push('La gestión debe ser un año de 4 dígitos (ej. 2025)')
    }

    if (errores.length > 0) {
      validationError.value = errores.join('. ')
      error('Validación incompleta', errores.join('. '))
      return
    }
    
    // 4. Enviar al endpoint de validación con los datos
    const validateResponse = await fetch(`/api/documentos/${props.doc.id}/validar`, {
      method: 'POST',
      headers: {
        ...props.headers,
        'Content-Type': 'application/json'
      },
      credentials: 'include',
      body: JSON.stringify(datosValidacion)
    })
    
    const result = await validateResponse.json()
    
    if (!result.ok) {
      // Mostrar errores detallados del backend
      if (result.errors) {
        const mensajes = Object.values(result.errors).flat()
        validationError.value = mensajes.join('. ')
        error('Error al validar', mensajes.join('. '))
      } else {
        validationError.value = result.message || 'Error desconocido'
        error('Error al validar', result.message || `HTTP ${validateResponse.status}`)
      }
      return
    }
    
    // 5. Éxito
    success('Documento Validado', result.message || 'El documento ha sido validado exitosamente')
    showValidateModal.value = false
    validationError.value = ''
    await loadDetails()
    
  } catch (e) {
    console.error('Validation error:', e)
    validationError.value = e.message || 'Error al validar documento'
    error('Error al validar', e.message)
  } finally {
    validating.value = false
  }
}

async function handleSeal() {
  sealing.value = true
  try {
    const response = await fetch(`/api/documentos/${props.doc.id}/sellar`, {
      method: 'POST',
      headers: props.headers,
      credentials: 'include'
    })
    
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(errorData.message || `HTTP ${response.status}`)
    }
    
    const data = await response.json()
    success('Documento Sellado', 'La custodia digital ha sido sellada con hash: ' + (data.custodia_hash?.substring(0, 16) || 'generado'))
    showSealModal.value = false
    await loadDetails()
    
  } catch (e) {
    error('Error al sellar', e.message)
  } finally {
    sealing.value = false
  }
}

async function handleDelete() {
  deleteError.value = ''
  
  // Validar razón
  if (!deleteReason.value || deleteReason.value.length < 10) {
    deleteError.value = 'La razón debe tener al menos 10 caracteres'
    return
  }
  
  deleting.value = true
  try {
    const response = await fetch(`/api/documentos/${props.doc.id}`, {
      method: 'DELETE',
      headers: {
        ...props.headers,
        'Content-Type': 'application/json'
      },
      credentials: 'include',
      body: JSON.stringify({ razon: deleteReason.value })
    })
    
    const result = await response.json()
    
    if (!result.ok) {
      if (result.errors) {
        const mensajes = Object.values(result.errors).flat()
        deleteError.value = mensajes.join('. ')
        error('Error al eliminar', mensajes.join('. '))
      } else {
        deleteError.value = result.message || 'Error al eliminar'
        error('Error al eliminar', result.message)
      }
      return
    }
    
    success('Documento Eliminado', 'El documento ha sido eliminado correctamente y puede ser restaurado desde "Documentos Eliminados".')
    showDeleteModal.value = false
    deleteReason.value = ''
    emit('refresh') // Notificar al padre para refrescar la lista
    
  } catch (e) {
    console.error('Delete error:', e)
    deleteError.value = e.message || 'Error al eliminar documento'
    error('Error', deleteError.value)
  } finally {
    deleting.value = false
  }
}

function emitDelete() {
  emit('delete', props.doc.id)
}

function formatEstado(estado) {
  const map = {
    'capturado': 'Capturado',
    'procesado_ocr': 'Procesado OCR',
    'validado': 'Validado',
    'custodio': 'En Custodia',
    'sellado': 'Sellado'
  }
  return map[estado] || estado
}

function formatBytes(bytes) {
  if (!bytes) return '—'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(2) + ' MB'
}

function getConfidenceClass(conf) {
  if (!conf) return ''
  const num = Number(conf)
  if (num >= 90) return 'conf-high'
  if (num >= 70) return 'conf-medium'
  return 'conf-low'
}

function verHistorialVersiones() {
  router.push(`/documentos/${props.doc.id}/versiones`)
}

async function loadDetails() {
  const r = await fetch(`/api/documentos/${props.doc.id}`, {
    headers: props.headers,
    credentials: 'include'
  })
  if (!r.ok) throw new Error('HTTP ' + r.status)
  det.value = await r.json()
}

async function toggle() {
  open.value = !open.value
  if (open.value && !det.value.archivo) {
    try {
      await loadDetails()
    } catch (e) {
      error('Error al cargar detalles', e.message)
    }
  }
}

async function handleEditSuccess(updatedDoc) {
  // Reload document details after successful edit
  await loadDetails()
}

async function guardarUbicacion() {
  if (!nuevaUbicacionId.value) {
    warning('Debe seleccionar una ubicación')
    return
  }
  
  ubicacionGuardando.value = true
  try {
    const r = await fetch(`/api/documentos/${props.doc.id}/ubicacion`, {
      method: 'POST',
      headers: {
        ...props.headers,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        ubicacion_fisica_id: nuevaUbicacionId.value,
        motivo: nuevaUbicacionMotivo.value || 'Actualización manual',
        codigo_fisico: nuevaUbicacionCodigoFisico.value,
        estado_fisico: nuevaUbicacionEstadoFisico.value
      }),
      credentials: 'include'
    })
    if (!r.ok) {
      const j = await r.json().catch(() => ({}))
      error('Error al actualizar ubicación', j.message || `HTTP ${r.status}`)
      return
    }
    
    success('Ubicación actualizada', 'El documento se movió correctamente')
    await loadDetails()
    
    nuevaUbicacionMotivo.value = ''
    nuevaUbicacionCodigoFisico.value = ''
    nuevaUbicacionEstadoFisico.value = ''
  } catch (e) {
    error('Error inesperado', e.message)
  } finally {
    ubicacionGuardando.value = false
  }
}

// Helper functions for validation form
function getCampoOCR(nombre) {
  const camposValidados = det.value?.campos_validados || []
  const campo = camposValidados.find(c => c.campo === nombre)
  return campo?.valor_final || campo?.valor_ocr || ''
}

function getGestionAnio() {
  const gestionId = det.value?.documento?.gestion_id || props.doc.gestion_id
  if (!gestionId) return ''
  const gestion = props.catalogs?.gestiones?.find(g => g.id === gestionId)
  return gestion?.anio?.toString() || ''
}

// Load document details on mount
onMounted(async () => {
  if (!props.expanded) {
    open.value = false
  }
  await loadDetails()
})

// Watch for validation modal opening to populate form
watch(showValidateModal, (newValue) => {
  if (newValue) {
    // Populate form with current document data
  validationForm.value = {
    titulo: det.value.documento?.titulo || props.doc.titulo || '',
    descripcion: det.value.documento?.descripcion || props.doc.descripcion || '',
    tipo_documento_id: det.value.documento?.tipo_documento_id || props.doc.tipo_documento_id || null,
    seccion_id: det.value.documento?.seccion_id || props.doc.seccion_id || null,
    subseccion_id: det.value.documento?.subseccion_id || props.doc.subseccion_id || null,
    gestion_id: det.value.documento?.gestion_id || props.doc.gestion_id || null,
    fecha_documento: det.value.documento?.fecha_documento || props.doc.fecha_documento || '',
    is_confidential: det.value.documento?.is_confidential || props.doc.is_confidential || false,
    
    // Campos de validación (auto-poblar desde OCR si existen)
    oficial: getCampoOCR('oficial'),
    fecha: getCampoOCR('fecha'),
    gestion: getCampoOCR('gestion') || getGestionAnio(), // Auto-poblar desde tabla gestiones o OCR
  }
  }
})
</script>

<style scoped>
.document-card-modern {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.3s;
}

.document-card-modern:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Header */
.card-header {
  background: linear-gradient(135deg, #556b2f 0%, #4b5f2a 100%);
  color: white;
  padding: 1.5rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.doc-title-section {
  flex: 1;
}

.doc-title {
  margin: 0 0 0.5rem 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: white;
}

.doc-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  opacity: 0.95;
}

.doc-id {
  font-family: monospace;
  font-weight: 600;
}

.dot {
  opacity: 0.6;
}

.estado-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
}

.estado-capturado { background: #dbeafe; color: #1e40af; }
.estado-procesado_ocr { background: #fef3c7; color: #92400e; }
.estado-validado { background: #d1fae5; color: #065f46; }
.estado-custodio,
.estado-sellado { background: #e0e7ff; color: #3730a3; }

.confidential-badge {
  padding: 0.25rem 0.75rem;
  background: #fee2e2;
  color: #991b1b;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
}

.header-actions {
  display: flex;
  gap: 0.75rem;
}

/* Details Section */
.card-details {
  padding: 1.5rem;
  background: #f9fafb;
}

.expand-enter-active,
.expand-leave-active {
  transition: all 0.3s ease;
  max-height: 2000px;
  overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
  max-height: 0;
  opacity: 0;
  padding: 0 1.5rem;
}

/* OCR Banner */
.ocr-banner {
  padding: 0.75rem 1rem;
  border-radius: 8px;
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
}

.ocr-banner.conf-high {
  background: #d1fae5;
  color: #065f46;
}

.ocr-banner.conf-medium {
  background: #fef3c7;
  color: #92400e;
}

.ocr-banner.conf-low {
  background: #fee2e2;
  color: #991b1b;
}

/* Details Grid */
.details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.detail-section {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1rem;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0 0 1rem 0;
  padding-bottom: 0.75rem;
  border-bottom: 2px solid #e5e7eb;
  font-size: 1rem;
  font-weight: 700;
  color: #374151;
}

.header-icon {
  font-size: 1.25rem;
}

.detail-rows {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.detail-row {
  display: grid;
  grid-template-columns: 140px 1fr;
  gap: 1rem;
  align-items: baseline;
}

.detail-row.full-width {
  grid-template-columns: 1fr;
}

.detail-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #6b7280;
}

.detail-value {
  font-size: 0.875rem;
  color: #1f2937;
  word-break: break-word;
}

.detail-value.description {
  line-height: 1.6;
}

.detail-value.mono {
  font-family: 'Courier New', monospace;
  font-size: 0.8rem;
}

.detail-value.mono.small {
  font-size: 0.75rem;
}

.detail-value.muted {
  color: #9ca3af;
  font-style: italic;
}

.empty-state {
  padding: 2rem;
  text-align: center;
  color: #9ca3af;
  font-style: italic;
}

/* Location Form */
.location-form {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1.5rem;
  margin-top: 1rem;
  max-width: 100%;
  overflow: hidden;
}

.form-header {
  font-size: 1rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #e5e7eb;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-width: 0;
}

.form-field label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #6b7280;
}

.form-field input,
.form-field select {
  padding: 0.625rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.875rem;
  width: 100%;
  box-sizing: border-box;
}

.form-field input:focus,
.form-field select:focus {
  outline: none;
  border-color: #556b2f;
  box-shadow: 0 0 0 3px rgba(85, 107, 47, 0.1);
}

/* OCR Table */
.ocr-section {
  margin-bottom: 1.5rem;
}

.ocr-table {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  margin-top: 1rem;
}

.ocr-table-header,
.ocr-table-row {
  display: grid;
  grid-template-columns: 1.5fr 2fr 1fr 2fr;
  gap: 1rem;
  padding: 0.75rem 1rem;
}

.ocr-table-header {
  background: #f3f4f6;
  font-weight: 700;
  font-size: 0.875rem;
  color: #374151;
  border-bottom: 2px solid #e5e7eb;
}

.ocr-table-row {
  font-size: 0.875rem;
  border-bottom: 1px solid #f3f4f6;
}

.ocr-table-row:last-child {
  border-bottom: none;
}

.ocr-table-row:hover {
  background: #f9fafb;
}

.ocr-conf .conf-high { color: #065f46; font-weight: 600; }
.ocr-conf .conf-medium { color: #92400e; font-weight: 600; }
.ocr-conf .conf-low { color: #991b1b; font-weight: 600; }

.ocr-fulltext {
  margin-top: 1rem;
}

.fulltext-box {
  margin-top: 0.75rem;
  padding: 1rem;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-family: monospace;
  font-size: 0.8rem;
  max-height: 300px;
  overflow: auto;
  white-space: pre-wrap;
}

/* Action Buttons */
.action-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  padding-top: 1.5rem;
  border-top: 2px solid #e5e7eb;
}

.btn-action {
  padding: 0.625rem 1.25rem;
  border-radius: 8px;
  font-size: 0.938rem;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-primary {
  background: white;
  color: #556b2f;
  border: 2px solid #556b2f;
}

.btn-primary:hover:not(:disabled) {
  background: #556b2f;
  color: white;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(85, 107, 47, 0.3);
}

.btn-secondary {
  background: white;
  color: #4b5563;
  border: 2px solid #d1d5db;
}

.btn-secondary:hover:not(:disabled) {
  background: #f3f4f6;
  border-color: #9ca3af;
}

.btn-olive {
  background: #556b2f;
  color: white;
  border: 2px solid #556b2f;
}

.btn-olive:hover:not(:disabled) {
  background: #4b5f2a;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(85, 107, 47, 0.3);
}

.btn-danger {
  background: white;
  color: #dc2626;
  border: 2px solid #dc2626;
}

.btn-danger:hover:not(:disabled) {
  background: #dc2626;
  color: white;
}

.btn-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .header-content {
    flex-direction: column;
    align-items: stretch;
  }
  
  .header-actions {
    width: 100%;
  }
  
  .btn-action {
    flex: 1;
  }
  
  .details-grid {
    grid-template-columns: 1fr;
  }
  
  .detail-row {
    grid-template-columns: 1fr;
  }
  
  .ocr-table-header,
  .ocr-table-row {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }
}

/* OCR Text Section */
.ocr-text-section {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1rem;
  margin-bottom: 1.5rem;
}

.ocr-text-preview {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 1rem;
  font-family: 'Courier New', monospace;
  font-size: 0.875rem;
  line-height: 1.6;
  color: #374151;
  white-space: pre-wrap;
  word-break: break-word;
  max-height: 200px;
  overflow-y: auto;
}

/* OCR Modal Content */
.ocr-modal-content {
  max-height: 70vh;
  overflow-y: auto;
}

.ocr-full-text {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 1.5rem;
  font-family: 'Courier New', monospace;
  font-size: 0.875rem;
  line-height: 1.8;
  color: #1f2937;
  white-space: pre-wrap;
  word-break: break-word;
  margin: 0;
}

/* Validate and Seal Modals */
.validate-modal-content,
.seal-modal-content {
  padding: 1rem 0;
}

.modal-description {
  font-size: 1rem;
  color: #374151;
  margin-bottom: 1rem;
}

.modal-info {
  font-size: 0.938rem;
  color: #1f2937;
  margin-bottom: 1rem;
  padding: 0.75rem;
  background: #f3f4f6;
  border-radius: 6px;
}

.modal-warning {
  font-size: 0.875rem;
  color: #92400e;
  background: #fef3c7;
  border: 1px solid #fbbf24;
  border-radius: 6px;
  padding: 0.75rem;
  margin-bottom: 1.5rem;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e5e7eb;
}

.btn-cancel {
  padding: 0.75rem 1.5rem;
  background: white;
  color: #6b7280;
  border: 2px solid #d1d5db;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-cancel:hover {
  background: #f3f4f6;
  border-color: #9ca3af;
}

.btn-confirm {
  padding: 0.75rem 1.5rem;
  background: #556b2f;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-confirm:hover:not(:disabled) {
  background: #4b5f2a;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(85, 107, 47, 0.3);
}

.btn-confirm:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-seal {
  background: #7c3aed;
}

.btn-seal:hover:not(:disabled) {
  background: #6d28d9;
  box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
}

/* Validation Form Styles */
.validate-form-content {
  padding: 1rem 0;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin: 1.5rem 0;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.938rem;
  color: #1f2937;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: #556b2f;
  box-shadow: 0 0 0 3px rgba(85, 107, 47, 0.1);
}

.form-textarea {
  resize: vertical;
  font-family: inherit;
}

.form-checkbox {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  cursor: pointer;
  padding: 0.75rem;
  background: #f9fafb;
  border-radius: 6px;
  transition: background 0.2s;
}

.form-checkbox:hover {
  background: #f3f4f6;
}

.form-checkbox input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.form-checkbox span {
  font-size: 0.938rem;
  font-weight: 500;
  color: #374151;
}

@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .form-group.full-width {
    grid-column: 1;
  }
}

</style>
