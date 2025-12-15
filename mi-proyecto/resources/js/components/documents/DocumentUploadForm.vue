<template>
  <div class="doc-upload-container">
    <!-- Header -->
    <div class="upload-header">
      <h3 class="upload-title"> Subir Nuevo Documento</h3>
      <p class="upload-subtitle">Complete la información del documento para facilitar su búsqueda y gestión</p>
    </div>

    <!-- PDF Generator Option -->
    <div class="generator-option">
      <p class="option-text">¿Necesitas crear un PDF desde imágenes escaneadas?</p>
      <button type="button" @click="showPDFGenerator = true" class="btn-generator">
        📷 Crear PDF desde Imágenes
      </button>
    </div>

    <!-- Upload Area -->
    <div 
      class="upload-dropzone"
      :class="{ 'dragover': isDragging, 'has-file': selectedFile }"
      @drop.prevent="handleDrop"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
    >
      <div v-if="!selectedFile" class="dropzone-content">
        <div class="dropzone-icon">📎</div>
        <p class="dropzone-text">Arrastra un archivo PDF aquí o haz clic para seleccionar</p>
        <input 
          ref="fileInput"
          type="file"
          accept="application/pdf"
          @change="handleFileSelect"
          class="file-input"
        />
        <button type="button" @click="$refs.fileInput.click()" class="btn-browse">
          Buscar Archivo
        </button>
      </div>
      
      <div v-else class="file-preview">
        <div class="file-icon">📄</div>
        <div class="file-info">
          <p class="file-name">{{ selectedFile.name }}</p>
          <p class="file-size">{{ formatFileSize(selectedFile.size) }}</p>
        </div>
        <button type="button" @click="removeFile" class="btn-remove">✕</button>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="error-message">
      ⚠️ {{ error }}
    </div>

    <!-- Form Fields -->
    <form @submit.prevent="handleSubmit" class="upload-form">
      <!-- Metadatos Básicos -->
      <div class="form-section">
        <h4 class="section-title"> Metadados Básicos</h4>
        
        <div class="form-grid">
          <div class="form-field required">
            <label class="field-label">Título del Documento</label>
            <input 
              v-model="form.titulo"
              type="text"
              class="field-input"
              placeholder="Ej: Acta de Reunión Directorio 2025"
              required
            />
          </div>

          <div class="form-field required">
            <label class="field-label">Tipo de Documento</label>
            <select v-model="form.tipo_documento_id" class="field-input" required>
              <option :value="null">— Seleccionar —</option>
              <option 
                v-for="tipo in catalogs.tipos_documento" 
                :key="tipo.id" 
                :value="tipo.id"
              >
                {{ tipo.nombre }}
              </option>
            </select>
          </div>

          <div class="form-field required">
            <label class="field-label">Sección</label>
            <select v-model="form.seccion_id" class="field-input" required @change="onSeccionChange">
              <option :value="null">— Seleccionar —</option>
              <option 
                v-for="seccion in catalogs.secciones" 
                :key="seccion.id" 
                :value="seccion.id"
              >
                {{ seccion.nombre }}
              </option>
            </select>
          </div>

          <div class="form-field">
            <label class="field-label">Subsección</label>
            <select v-model="form.subseccion_id" class="field-input" :disabled="!form.seccion_id">
              <option :value="null">— Sin subsección —</option>
              <option 
                v-for="sub in subseccionesFiltradas" 
                :key="sub.id" 
                :value="sub.id"
              >
                {{ sub.nombre }}
              </option>
            </select>
          </div>

          <div class="form-field required">
            <label class="field-label">Gestión</label>
            <select v-model="form.gestion_id" class="field-input" required>
              <option :value="null">— Seleccionar —</option>
              <option 
                v-for="gestion in catalogs.gestiones" 
                :key="gestion.id" 
:value="gestion.id"
              >
                {{ gestion.anio }}
              </option>
            </select>
          </div>

          <div class="form-field">
            <label class="field-label">Fecha del Documento</label>
            <input 
              v-model="form.fecha_documento"
              type="date"
              class="field-input"
            />
          </div>
        </div>

        <div class="form-field full-width">
          <label class="field-label">Descripción</label>
          <textarea 
            v-model="form.descripcion"
            class="field-textarea"
            rows="3"
            placeholder="Descripción breve del contenido del documento..."
          ></textarea>
        </div>
      </div>

      <!-- Ubicación Física -->
      <div class="form-section">
        <h4 class="section-title">📍 Ubicación Física (Opcional)</h4>
        
        <div class="form-grid">
          <div class="form-field">
            <label class="field-label">Ubicación</label>
            <select v-model="form.ubicacion_fisica_id" class="field-input">
              <option :value="null">— Sin ubicación —</option>
              <option 
                v-for="ubicacion in catalogs.ubicaciones" 
                :key="ubicacion.id" 
                :value="ubicacion.id"
              >
                {{ ubicacion.codigo }} — {{ ubicacion.nombre || ubicacion.descripcion }}
              </option>
            </select>
          </div>

          <div class="form-field">
            <label class="field-label">Código Físico</label>
            <input 
              v-model="form.codigo_fisico"
              type="text"
              class="field-input"
              placeholder="Ej: CAJA-001-A"
            />
          </div>

          <div class="form-field">
            <label class="field-label">Estado Físico</label>
            <select v-model="form.estado_fisico" class="field-input">
              <option value="">— No especificar —</option>
              <option value="excelente">Excelente</option>
              <option value="bueno">Bueno</option>
              <option value="regular">Regular</option>
              <option value="deteriorado">Deteriorado</option>
              <option value="critico">Crítico</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Opciones Adicionales -->
      <div class="form-section">
        <h4 class="section-title">⚙️ Opciones</h4>
        
        <div class="form-options">
          <label class="checkbox-field">
            <input type="checkbox" v-model="form.is_confidential" />
            <span class="checkbox-label">🔒 Marcar como confidencial</span>
          </label>

          <label class="checkbox-field">
            <input type="checkbox" v-model="form.auto_ocr" />
            <span class="checkbox-label">🔍 Procesar OCR automáticamente</span>
          </label>
        </div>
      </div>

      <!-- Actions -->
      <div class="form-actions">
        <button 
          type="button" 
          @click="$emit('close')" 
          class="btn-cancel"
          :disabled="uploading || processingOCR"
        >
          Cancelar
        </button>
        <button 
          type="button"
          @click="processOCR" 
          class="btn-ocr"
          :disabled="!selectedFile || uploading || processingOCR"
        >
          {{ processingOCR ? '🔍 Procesando...' : '🔍 Procesar OCR' }}
        </button>
        <button 
          type="submit" 
          class="btn-submit"
          :disabled="!selectedFile || uploading || processingOCR"
        >
          {{ uploading ? '⏳ Subiendo...' : '✓ Subir Documento' }}
        </button>
      </div>
    </form>

    <!-- PDF Generator Modal -->
    <ImageToPDFGenerator 
      :open="showPDFGenerator"
      :headers="headers"
      @close="showPDFGenerator = false"
      @pdf-generated="handlePDFGenerated"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useToast } from '@/composables/useToast'
import ImageToPDFGenerator from './ImageToPDFGenerator.vue'

const props = defineProps({
  catalogs: {
    type: Object,
    required: true
  },
  headers: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'success'])

const { success: toastSuccess, warning: toastWarning, error: toastError } = useToast()

const fileInput = ref(null)
const selectedFile = ref(null)
const isDragging = ref(false)
const uploading = ref(false)
const processingOCR = ref(false)
const error = ref('')
const showPDFGenerator = ref(false)

const form = ref({
  titulo: '',
  descripcion: '',
  tipo_documento_id: null,
  seccion_id: null,
  subseccion_id: null,
  gestion_id: null,
  fecha_documento: '',
  ubicacion_fisica_id: null,
  codigo_fisico: '',
  estado_fisico: '',
  is_confidential: false,
  auto_ocr: true
})

const subseccionesFiltradas = computed(() => {
  if (!form.value.seccion_id) return []
  return (props.catalogs.subsecciones || []).filter(
    sub => sub.seccion_id === form.value.seccion_id
  )
})

function onSeccionChange() {
  // Reset subsección si cambia la sección
  form.value.subseccion_id = null
}

function handleFileSelect(event) {
  const file = event.target.files[0]
  if (file) {
    validateAndSetFile(file)
  }
}

function handleDrop(event) {
  isDragging.value = false
  const file = event.dataTransfer.files[0]
  if (file) {
    validateAndSetFile(file)
  }
}

function validateAndSetFile(file) {
  error.value = ''
  
  if (file.type !== 'application/pdf') {
    error.value = 'Solo se permiten archivos PDF'
    return
  }

  if (file.size > 50 * 1024 * 1024) { // 50MB
    error.value = 'El archivo no debe exceder 50MB'
    return
  }

  selectedFile.value = file
}

function removeFile() {
  selectedFile.value = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

function handlePDFGenerated(pdfFile) {
  // Usar el PDF generado como archivo seleccionado
  selectedFile.value = pdfFile
  showPDFGenerator.value = false
  error.value = ''
  
  // Log para debugging
  console.log('✅ PDF adjuntado al formulario:', {
    nombre: pdfFile.name,
    tamaño: (pdfFile.size / 1024).toFixed(2) + ' KB',
    tipo: pdfFile.type
  })
  
  // Toast de confirmación
  toastSuccess(
    '✅ Archivo Listo',
    `El PDF "${pdfFile.name}" está adjunto. Completa los metadatos para subirlo.`
  )
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(2) + ' MB'
}

async function handleSubmit() {
  if (!selectedFile.value) {
    error.value = 'Debe seleccionar un archivo PDF'
    return
  }

  uploading.value = true
  error.value = ''

  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value) // Backend espera 'file'
    
    // Agregar campos del formulario
    Object.keys(form.value).forEach(key => {
      const value = form.value[key]
      if (value !== null && value !== '' && value !== false) {
        formData.append(key, value)
      }
    })

    const response = await fetch('/api/documentos', {
      method: 'POST',
      headers: {
        ...props.headers,
        // NO incluir Content-Type, el navegador lo configura automáticamente con boundary
      },
      body: formData,
      credentials: 'include'
    })

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(errorData.message || `HTTP ${response.status}`)
    }

    const data = await response.json()
    
    emit('success', data)
    emit('close')
    
  } catch (e) {
    error.value = e.message || 'Error al subir el documento'
    console.error('Upload error:', e)
  } finally {
    uploading.value = false
  }
}

async function processOCR() {
  if (!selectedFile.value) {
    toastWarning('Archivo requerido', 'Debe seleccionar un archivo PDF primero')
    return
  }

  processingOCR.value = true
  error.value = ''

  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)

    const response = await fetch('/api/ocr/preview', {
      method: 'POST',
      headers: props.headers,
      body: formData,
      credentials: 'include'
    })

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(errorData.message || `HTTP ${response.status}`)
    }

    const data = await response.json()
    
    // Auto-rellenar formulario
    const { rellenados, fallidos } = fillFormWithOCR(data)

    // Notificaciones
    if (rellenados.length > 0) {
      toastSuccess(
        'OCR Completado',
        `Campos rellenados: ${rellenados.join(', ')}`
      )
    }

    if (fallidos.length > 0) {
      toastWarning(
        'Algunos campos no se extrajeron',
        `Complete manualmente: ${fallidos.join(', ')}`
      )
    }

    if (rellenados.length === 0 && fallidos.length === 0) {
      toastWarning(
        'OCR sin resultados',
        'No se pudieron extraer campos del documento'
      )
    }

  } catch (e) {
    error.value = e.message || 'Error al procesar OCR'
    toastError('Error en OCR', error.value)
    console.error('OCR error:', e)
  } finally {
    processingOCR.value = false
  }
}

function fillFormWithOCR(data) {
  const rellenados = []
  const fallidos = []
  const campos = data.campos || {}

  // Título
  if (campos.titulo) {
    form.value.titulo = campos.titulo
    rellenados.push('Título')
  } else {
    fallidos.push('Título')
  }

  // Descripción (del full_text)
  if (data.full_text && data.full_text.trim()) {
    form.value.descripcion = data.full_text.substring(0, 500)
    rellenados.push('Descripción')
  } else {
    fallidos.push('Descripción')
  }

  // Fecha documento
  const fechaField = campos.fecha || campos.fecha_documento
  if (fechaField) {
    try {
      const parsedDate = parseDate(fechaField)
      if (parsedDate) {
        form.value.fecha_documento = parsedDate
        rellenados.push('Fecha')
      } else {
        fallidos.push('Fecha')
      }
    } catch (e) {
      fallidos.push('Fecha')
    }
  } else {
    fallidos.push('Fecha')
  }

  // Gestión (buscar en catálogos)
  if (campos.gestion) {
    const gestiónMatch = props.catalogs.gestiones?.find(g => 
      g.anio?.toString() === campos.gestion.toString()
    )
    if (gestiónMatch) {
      form.value.gestion_id = gestiónMatch.id
      rellenados.push('Gestión')
    } else {
      fallidos.push('Gestión')
    }
  } else {
    fallidos.push('Gestión')
  }

  return { rellenados, fallidos }
}

function parseDate(dateStr) {
  if (!dateStr) return null
  
  // Intentar varios formatos comunes
  const formats = [
    // ISO: 2024-12-01
    /^(\d{4})-(\d{1,2})-(\d{1,2})$/,
    // DD/MM/YYYY
    /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/,
    // DD-MM-YYYY
    /^(\d{1,2})-(\d{1,2})-(\d{4})$/
  ]

  for (const format of formats) {
    const match = dateStr.match(format)
    if (match) {
      if (format.toString().includes('YYYY')) {
        // Formato ISO
        return dateStr
      } else {
        // Formato DD/MM/YYYY o DD-MM-YYYY
        const [, day, month, year] = match
        return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`
      }
    }
  }

  // Intentar parseo directo
  const date = new Date(dateStr)
  if (!isNaN(date.getTime())) {
    return date.toISOString().split('T')[0]
  }

  return null
}
</script>

<style scoped>
.doc-upload-container {
  max-width: 100%; /* Usar todo el ancho disponible del modal */
  margin: 0 auto;
}

.upload-header {
  text-align: center;
  margin-bottom: 2rem;
}

.upload-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 0.5rem 0;
}

.upload-subtitle {
  font-size: 0.95rem;
  color: #6b7280;
  margin: 0;
}

/* PDF Generator Option */
.generator-option {
  text-align: center;
  padding: 1.5rem;
  background: linear-gradient(135deg, #556b2f 0%, #6b8e23 100%);
  border-radius: 12px;
  margin-bottom: 1.5rem;
}

.option-text {
  color: white;
  font-size: 0.938rem;
  margin: 0 0 1rem 0;
  font-weight: 500;
}

.btn-generator {
  padding: 0.75rem 2rem;
  background: white;
  color: #556b2f;
  border: 2px solid rgba(255, 255, 255, 0.3);
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
  border-color: white;
}

/* Dropzone */
.upload-dropzone {
  border: 3px dashed #cbd5e0;
  border-radius: 12px;
  padding: 3rem 2rem;
  text-align: center;
  background: #f9fafb;
  transition: all 0.3s;
  margin-bottom: 2rem;
}

.upload-dropzone.dragover {
  border-color: #556b2f;
  background: #f0f4e8;
  transform: scale(1.02);
}

.upload-dropzone.has-file {
  border-color: #556b2f;
  background: #f8faf7;
  padding: 1.5rem;
}

.dropzone-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.dropzone-icon {
  font-size: 4rem;
  opacity: 0.5;
}

.dropzone-text {
  font-size: 1.1rem;
  color: #4b5563;
  margin: 0;
}

.file-input {
  display: none;
}

.btn-browse {
  padding: 0.75rem 2rem;
  background: white;
  color: #556b2f;
  border: 2px solid #556b2f;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-browse:hover {
  background: #556b2f;
  color: white;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(85, 107, 47, 0.3);
}

/* File Preview */
.file-preview {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.file-icon {
  font-size: 3rem;
}

.file-info {
  flex: 1;
  text-align: left;
}

.file-name {
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 0.25rem 0;
  word-break: break-word;
}

.file-size {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0;
}

.btn-remove {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #ef4444;
  color: white;
  border: none;
  font-size: 1.2rem;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-remove:hover {
  background: #dc2626;
  transform: scale(1.1);
}

/* Error Message */
.error-message {
  padding: 1rem;
  background: #fee2e2;
  border: 1px solid #fca5a5;
  border-radius: 8px;
  color: #b91c1c;
  margin-bottom: 1.5rem;
  font-weight: 500;
}

/* Form */
.upload-form {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.form-section {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 1.5rem;
}

.section-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: #374151;
  margin: 0 0 1rem 0;
  padding-bottom: 0.75rem;
  border-bottom: 2px solid #e5e7eb;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.form-field {
  display: flex;
  flex-direction: column;
}

.form-field.full-width {
  grid-column: 1 / -1;
}

.form-field.required .field-label::after {
  content: ' *';
  color: #dc2626;
}

.field-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.375rem;
}

.field-input,
.field-textarea {
  padding: 0.625rem 0.75rem;
  border: 2px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.938rem;
  background: white;
  transition: all 0.2s;
  font-family: inherit;
}

.field-input:focus,
.field-textarea:focus {
  outline: none;
  border-color: #556b2f;
  box-shadow: 0 0 0 3px rgba(85, 107, 47, 0.1);
}

.field-input:disabled {
  background: #f3f4f6;
  cursor: not-allowed;
  opacity: 0.6;
}

.field-textarea {
  resize: vertical;
  min-height: 80px;
}

/* Options */
.form-options {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.checkbox-field {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  padding: 0.75rem;
  border-radius: 8px;
  transition: background 0.2s;
}

.checkbox-field:hover {
  background: #f3f4f6;
}

.checkbox-field input[type="checkbox"] {
  width: 20px;
  height: 20px;
  cursor: pointer;
  accent-color: #556b2f;
}

.checkbox-label {
  font-size: 0.938rem;
  font-weight: 500;
  color: #374151;
  user-select: none;
}

/* Actions */
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding-top: 1.5rem;
  border-top: 2px solid #e5e7eb;
}

.btn-cancel,
.btn-submit {
  padding: 0.75rem 2rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.2s;
    flex-direction: column;
  }
  
  .btn-cancel,
  .btn-submit {
    width: 100%;
  }
</style>
