<script setup>
import { ref, computed } from 'vue'
import { useToast } from '@/composables/useToast'
import ImagePreviewCard from './ImagePreviewCard.vue'
import BaseModal from '../ui/BaseModal.vue'

const props = defineProps({
  headers: {
    type: Object,
    required: true
  },
  open: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'pdf-generated'])

const { success, error: showError } = useToast()

// State
const images = ref([])
const generating = ref(false)
const paperSize = ref('A4')
const orientation = ref('portrait')
const quality = ref(85)

// File input
const fileInput = ref(null)

// Computed
const canGenerate = computed(() => images.value.length > 0 && !generating.value)
const totalSize = computed(() => {
  return images.value.reduce((sum, img) => sum + img.file.size, 0)
})

const totalSizeMB = computed(() => (totalSize.value / 1024 / 1024).toFixed(2))

// Methods
function handleFileSelect(event) {
  const files = Array.from(event.target.files)
  addImages(files)
  // Reset input
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

function handleDrop(event) {
  const files = Array.from(event.dataTransfer.files)
  addImages(files)
}

function addImages(files) {
  // Validar archivos
  const validFiles = files.filter(file => {
    // Solo imágenes
    if (!file.type.match(/^image\/(jpeg|jpg|png)$/)) {
      showError('Archivo no válido', `${file.name} no es una imagen JPG o PNG`)
      return false
    }
    
    // Máximo 10MB por archivo
    if (file.size > 10 * 1024 * 1024) {
      showError('Archivo muy grande', `${file.name} supera los 10MB`)
      return false
    }
    
    return true
  })
  
  // Verificar límite total
  if (images.value.length + validFiles.length > 50) {
    showError('Límite excedido', 'Máximo 50 imágenes permitidas')
    return
  }
  
  // Agregar imágenes con preview
  validFiles.forEach(file => {
    const reader = new FileReader()
    reader.onload = (e) => {
      images.value.push({
        id: Date.now() + Math.random(),
        file: file,
        name: file.name,
        size: file.size,
        preview: e.target.result,
        rotation: 0
      })
    }
    reader.readAsDataURL(file)
  })
}

function removeImage(id) {
  images.value = images.value.filter(img => img.id !== id)
}

function rotateImage(id) {
  const img = images.value.find(i => i.id === id)
  if (img) {
    img.rotation = (img.rotation + 90) % 360
  }
}

function moveUp(index) {
  if (index > 0) {
    const temp = images.value[index]
    images.value[index] = images.value[index - 1]
    images.value[index - 1] = temp
  }
}

function moveDown(index) {
  if (index < images.value.length - 1) {
    const temp = images.value[index]
    images.value[index] = images.value[index + 1]
    images.value[index + 1] = temp
  }
}

async function generatePDF() {
  if (!canGenerate.value) return
  
  generating.value = true
  
  try {
    // Crear FormData
    const formData = new FormData()
    
    images.value.forEach((img, idx) => {
      formData.append(`images[${idx}]`, img.file)
      formData.append(`rotations[${idx}]`, img.rotation)
    })
    
    formData.append('paper_size', paperSize.value)
    formData.append('orientation', orientation.value)
    formData.append('quality', quality.value)
    
    // Llamar API
    const response = await fetch('/api/generate-pdf', {
      method: 'POST',
      headers: props.headers,
      body: formData
    })
    
    if (!response.ok) {
      const data = await response.json().catch(() => ({}))
      throw new Error(data.message || `HTTP ${response.status}`)
    }
    
    // Obtener blob del PDF
    const blob = await response.blob()
    
    // Crear nombre descriptivo con timestamp
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19)
    const filename = `BSF_Documento_${timestamp}.pdf`
    
    // Descargar archivo (método compatible sin user gesture)
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.style.display = 'none'
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    window.URL.revokeObjectURL(url)
    
    // Crear FILE OBJECT para adjuntar al formulario
    const pdfFile = new File([blob], filename, {
      type: 'application/pdf',
      lastModified: Date.now()
    })
    
    // Notificar éxito con detalles
    const sizeKB = (blob.size / 1024).toFixed(2)
    success(
      '📎 PDF Generado',
      `${images.value.length} páginas • ${sizeKB} KB • Descargado y listo para subir`
    )
    
    // EMITIR para que se adjunte al formulario
    emit('pdf-generated', pdfFile)
    
    // CERRAR modal
    handleClose()
    
  } catch (err) {
    console.error('Error generando PDF:', err)
    showError('❌ Error al Generar PDF', err.message || 'Error desconocido')
  } finally {
    generating.value = false
  }
}

function handleClose() {
  // Limpiar estado
  images.value = []
  paperSize.value = 'A4'
  orientation.value = 'portrait'
  quality.value = 85
  
  emit('close')
}

function openFileSelector() {
  fileInput.value?.click()
}
</script>

<template>
  <BaseModal
    :open="open"
    title="📄 Generar PDF desde Imágenes"
    size="large"
    @close="handleClose"
  >
    <div class="pdf-generator">
      <!-- Dropzone -->
      <div 
        v-if="images.length === 0"
        class="dropzone"
        @drop.prevent="handleDrop"
        @dragover.prevent
        @click="openFileSelector"
      >
        <input 
          ref="fileInput"
          type="file" 
          multiple 
          accept="image/jpeg,image/png,image/jpg"
          @change="handleFileSelect"
          style="display: none"
        />
        
        <svg width="64" height="64" fill="currentColor" viewBox="0 0 16 16">
          <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
          <path d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708l3-3z"/>
        </svg>
        
        <h3>Arrastra imágenes aquí</h3>
        <p>o haz clic para seleccionar archivos</p>
        <p class="hint">JPG, PNG · Máximo 50 imágenes · 10MB por imagen</p>
      </div>
      
      <!-- Lista de imágenes -->
      <div v-else class="images-section">
        <div class="images-header">
          <h4>{{ images.length }} imagen{{ images.length !== 1 ? 'es' : '' }} ({{ totalSizeMB }} MB)</h4>
          <button @click="openFileSelector" class="btn-add">
            + Agregar más
          </button>
          <input 
            ref="fileInput"
            type="file" 
            multiple 
            accept="image/jpeg,image/png,image/jpg"
            @change="handleFileSelect"
            style="display: none"
          />
        </div>
        
        <div class="images-grid">
          <ImagePreviewCard
            v-for="(img, idx) in images"
            :key="img.id"
            :image="img"
            :index="idx"
            :total="images.length"
            @remove="removeImage(img.id)"
            @rotate="rotateImage(img.id)"
            @move-up="moveUp(idx)"
            @move-down="moveDown(idx)"
          />
        </div>
        
        <!-- Configuración -->
        <div class="config-section">
          <h4>Configuración del PDF</h4>
          
          <div class="config-grid">
            <div class="config-field">
              <label>Tamaño de Papel</label>
              <select v-model="paperSize">
                <option value="A4">A4 (210 × 297 mm)</option>
                <option value="Letter">Letter (216 × 279 mm)</option>
                <option value="Legal">Legal (216 × 356 mm)</option>
              </select>
            </div>
            
            <div class="config-field">
              <label>Orientación</label>
              <select v-model="orientation">
                <option value="portrait">Vertical</option>
                <option value="landscape">Horizontal</option>
              </select>
            </div>
            
            <div class="config-field">
              <label>Calidad ({{ quality }}%)</label>
              <input 
                v-model.number="quality" 
                type="range" 
                min="1" 
                max="100"
                class="quality-slider"
              />
              <div class="quality-labels">
                <span>Baja</span>
                <span>Media</span>
                <span>Alta</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Footer con botones -->
      <div class="modal-footer">
        <button
          @click="handleClose"
          class="btn btn-secondary"
          :disabled="generating"
        >
          Cancelar
        </button>
        <button
          @click="generatePDF"
          class="btn btn-primary"
          :disabled="!canGenerate"
        >
          <svg v-if="generating" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="spin">
            <path d="M8 0c-4.418 0-8 3.582-8 8s3.582 8 8 8 8-3.582 8-8-3.582-8-8-8zm0 14c-3.309 0-6-2.691-6-6s2.691-6 6-6 6 2.691 6 6-2.691 6-6 6z" opacity="0.3"/>
            <path d="M8 2c3.309 0 6 2.691 6 6h2c0-4.418-3.582-8-8-8v2z"/>
          </svg>
          <svg v-else width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
            <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/>
          </svg>
          {{ generating ? 'Generando PDF...' : 'Generar PDF' }}
        </button>
      </div>
    </div>
  </BaseModal>
</template>

<style scoped>
.pdf-generator {
  min-height: 400px;
}

.dropzone {
  border: 3px dashed #cbd5e1;
  border-radius: 12px;
  padding: 4rem 2rem;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s;
  background: #f8fafc;
}

.dropzone:hover {
  border-color: #2563eb;
  background: #eff6ff;
}

.dropzone svg {
  color: #94a3b8;
  margin-bottom: 1rem;
}

.dropzone h3 {
  margin: 0 0 0.5rem;
  color: #1e293b;
  font-size: 1.25rem;
}

.dropzone p {
  margin: 0.25rem 0;
  color: #64748b;
}

.dropzone .hint {
  font-size: 0.875rem;
  color: #94a3b8;
}

.images-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.images-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.images-header h4 {
  margin: 0;
  color: #1e293b;
}

.btn-add {
  padding: 0.5rem 1rem;
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-add:hover {
  background: #e2e8f0;
}

.images-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
  max-height: 400px;
  overflow-y: auto;
  padding: 0.5rem;
}

.config-section {
  border-top: 1px solid #e2e8f0;
  padding-top: 1.5rem;
}

.config-section h4 {
  margin: 0 0 1rem;
  color: #1e293b;
}

.config-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}

.config-field label {
  display: block;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #475569;
}

.config-field select,
.config-field input[type="range"] {
  width: 100%;
}

.config-field select {
  padding: 0.5rem;
  border: 2px solid #e2e8f0;
  border-radius: 6px;
  font-size: 0.9375rem;
}

.quality-slider {
  margin-bottom: 0.25rem;
}

.quality-labels {
  display: flex;
  justify-content: space-between;
  font-size: 0.75rem;
  color: #94a3b8;
}

.modal-footer {
  display: flex;
  gap: 0.75rem;
  justify-content: flex-end;
  padding-top: 1.5rem;
  border-top: 1px solid #e2e8f0;
  margin-top: 1.5rem;
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 6px;
  font-size: 0.9375rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: #f1f5f9;
  color: #475569;
}

.btn-secondary:hover:not(:disabled) {
  background: #e2e8f0;
}

.btn-primary {
  background: #2563eb;
  color: #fff;
}

.btn-primary:hover:not(:disabled) {
  background: #1d4ed8;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
