<template>
  <div class="certifications-view">
    <div class="view-header">
      <div>
        <h1 class="view-title">Certificaciones Generadas</h1>
        <p class="view-subtitle">Historial de todas las certificaciones oficiales</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">
      <div class="spinner"></div>
      <p>Cargando certificaciones...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="error">
      <p>⚠️ Error al cargar: {{ error }}</p>
      <button @click="loadCertifications" class="btn-retry">Reintentar</button>
    </div>

    <!-- Certifications List -->
    <div v-else class="certifications-container">
      <div v-if="!certifications.length" class="empty-state">
        <p>No hay certificaciones generadas</p>
      </div>

      <div v-else class="certifications-grid">
        <div 
          v-for="cert in certifications" 
          :key="cert.id" 
          class="cert-card"
        >
          <div class="cert-header">
            <h3>{{ cert.numero_certificacion }}</h3>
            <span class="cert-date">{{ formatDate(cert.fecha_emision) }}</span>
          </div>

          <div class="cert-body">
            <div class="cert-field">
              <strong>Personal:</strong>
              <span>{{ cert.nombre_personal }}</span>
            </div>
            <div class="cert-field">
              <strong>CI:</strong>
              <span>{{ cert.ci }} {{ cert.lugar_expedicion }}</span>
            </div>
            <div class="cert-field">
              <strong>Documento:</strong>
              <span>{{ cert.documento?.titulo || 'N/A' }}</span>
            </div>
            <div class="cert-field">
              <strong>Generada por:</strong>
              <span>{{ cert.usuario?.name || 'Sistema' }}</span>
            </div>
          </div>

          <div class="cert-actions">
            <button @click="editCertification(cert)" class="btn-view">
              Editar
            </button>
            <button @click="printCertification(cert)" class="btn-print">
              Imprimir
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Certification Editor for Reprinting -->
    <CertificationEditor
      v-if="showEditor"
      :documento="documentoForEditor"
      :headers="headers"
      :initialData="certificationDataForEditor"
      :certificationId="selectedCert?.id"
      @close="closeEditor"
      @printed="handleCertPrinted"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import CertificationEditor from '../documents/CertificationEditor.vue'

const props = defineProps({
  headers: {
    type: Object,
    required: true
  }
})

const certifications = ref([])
const loading = ref(true)
const error = ref(null)
const selectedCert = ref(null)
const showEditor = ref(false)

async function loadCertifications() {
  loading.value = true
  error.value = null

  try {
    const response = await fetch('/api/certificaciones/all', {
      headers: props.headers,
      credentials: 'include'
    })

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`)
    }

    const data = await response.json()
    certifications.value = data.certificaciones || []
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function formatDate(dateString) {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  return date.toLocaleDateString('es-BO', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

/**
 * Convert ISO 8601 date to yyyy-MM-dd format for date input
 */
function formatDateForInput(isoString) {
  if (!isoString) return new Date().toISOString().split('T')[0]
  
  // Handle both ISO 8601 format and simple date strings
  const date = new Date(isoString)
  if (isNaN(date.getTime())) {
    return new Date().toISOString().split('T')[0]
  }
  
  return date.toISOString().split('T')[0]
}

/**
 * Create a documento object for the editor from certification data
 */
const documentoForEditor = computed(() => {
  if (!selectedCert.value) return { id: null }
  
  return {
    id: selectedCert.value.documento_id,
    titulo: selectedCert.value.documento?.titulo || 'Certificación'
  }
})

/**
 * Map certification data to editor's expected format
 */
const certificationDataForEditor = computed(() => {
  if (!selectedCert.value) return null
  
  return {
    numero: selectedCert.value.numero_certificacion,
    textoIntroductorio: selectedCert.value.texto_introduccion || '',
    nombrePersonal: selectedCert.value.nombre_personal,
    ci: selectedCert.value.ci,
    ciLugar: selectedCert.value.lugar_expedicion,
    fechaIngreso: selectedCert.value.fecha_ingreso,
    letra: selectedCert.value.designacion,
    ultimoDestino: selectedCert.value.ultimo_destino,
    fechaEmision: formatDateForInput(selectedCert.value.fecha_emision),
    elaboradoPor: selectedCert.value.elaborado_por,
    cargoElaborador: selectedCert.value.cargo_elaborador,
    comandante: selectedCert.value.nombre_comandante,
    cargoComandante: selectedCert.value.cargo_comandante
  }
})

function editCertification(cert) {
  selectedCert.value = cert
  showEditor.value = true
}

function closeEditor() {
  showEditor.value = false
  selectedCert.value = null
}

function printCertification(cert) {
  editCertification(cert)
}

function handleCertPrinted() {
  closeEditor()
  // Optionally reload certifications to get latest data
  loadCertifications()
}



onMounted(() => {
  loadCertifications()
})
</script>

<style scoped>
.certifications-view {
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
  color: #1a202c;
  margin: 0 0 0.5rem 0;
}

.view-subtitle {
  color: #718096;
  margin: 0;
}

.loading, .error {
  text-align: center;
  padding: 3rem;
}

.spinner {
  width: 50px;
  height: 50px;
  border: 4px solid #e2e8f0;
  border-top-color: #3182ce;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  color: #a0aec0;
  font-size: 1.1rem;
}

.certifications-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1.25rem;
}

.cert-card {
  background: white;
  border-radius: 10px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.08);
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
  border: 1px solid #e8ede8;
}

.cert-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 14px rgba(85, 107, 47, 0.15);
}

.cert-header {
  background: linear-gradient(135deg, #556b2f 0%, #6b8e3a 100%);
  color: white;
  padding: 1rem 1.25rem;
}

.cert-header h3 {
  margin: 0 0 0.4rem 0;
  font-size: 1.1rem;
  font-weight: 600;
}

.cert-date {
  font-size: 0.8125rem;
  opacity: 0.95;
}

.cert-body {
  padding: 1rem 1.25rem;
}

.cert-field {
  margin-bottom: 0.65rem;
  display: flex;
  gap: 0.5rem;
  font-size: 0.9rem;
}

.cert-field strong {
  color: #556b2f;
  min-width: 95px;
  font-weight: 600;
}

.cert-field span {
  color: #2d3748;
  flex: 1;
}

.cert-actions {
  padding: 0.875rem 1.25rem;
  border-top: 1px solid #e8ede8;
  display: flex;
  gap: 0.65rem;
}

.btn-view, .btn-print {
  flex: 1;
  padding: 0.625rem 1rem;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-view {
  background: #edf2f7;
  color: #2d3748;
}

.btn-view:hover {
  background: #e2e8f0;
}

.btn-print {
  background: #3182ce;
  color: white;
}

.btn-print:hover {
  background: #2c5aa0;
}

/* Modal */
.cert-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.cert-modal-content {
  background: white;
  border-radius: 12px;
  max-width: 800px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
}

.cert-modal-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.cert-modal-header h2 {
  margin: 0;
  font-size: 1.5rem;
  color: #1a202c;
}

.btn-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #a0aec0;
  padding: 0;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  transition: all 0.2s;
}

.btn-close:hover {
  background: #f7fafc;
  color: #2d3748;
}

.cert-modal-body {
  padding: 1.5rem;
}

.cert-details-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.25rem;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-item.full-width {
  grid-column: 1 / -1;
}

.detail-item label {
  font-weight: 600;
  color: #4a5568;
  font-size: 0.875rem;
}

.detail-item p {
  margin: 0;
  color: #2d3748;
}

.cert-modal-footer {
  padding: 1.5rem;
  border-top: 1px solid #e2e8f0;
  text-align: right;
}

.btn-print-modal {
  padding: 0.75rem 2rem;
  background: #3182ce;
  color: white;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-print-modal:hover {
  background: #2c5aa0;
}

.btn-retry {
  margin-top: 1rem;
  padding: 0.75rem 1.5rem;
  background: #3182ce;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}

.btn-retry:hover {
  background: #2c5aa0;
}
</style>
