<template>
  <div class="certification-editor-overlay">
    <div class="editor-container">
      <!-- Header -->
      <div class="editor-header">
        <h2> Editor de Certificación</h2>
        <p>Complete los campos y luego imprima la certificación</p>
      </div>

      <!-- Editor Panel + Preview -->
      <div class="editor-body">
        <!-- Left Panel: Form Fields -->
        <div class="editor-form">
          <h3>Datos de la Certificación</h3>
          
          <div class="form-group">
            <label>Número de Certificación</label>
            <input v-model="certData.numero" type="text" placeholder="000/2025" />
          </div>

          <div class="form-group">
            <label>Texto Introductorio</label>
            <textarea v-model="certData.textoIntroductorio" rows="4"></textarea>
          </div>

          <h3>Datos del Personal</h3>

          <div class="form-group">
            <label>Nombre Completo</label>
            <input v-model="certData.nombrePersonal" type="text" placeholder="SR. SGTO. 2DO. NOMBRE APELLIDO" />
          </div>

          <div class="form-group">
            <label>Número de CI</label>
            <input v-model="certData.ci" type="text" placeholder="0000000" />
          </div>

          <div class="form-group">
            <label>Lugar de Expedición CI</label>
            <input v-model="certData.ciLugar" type="text" placeholder="ORURO" />
          </div>

          <div class="form-group">
            <label>Fecha de Ingreso</label>
            <input v-model="certData.fechaIngreso" type="text" placeholder="Según hoja de filiación 1 de abril de 1996" />
          </div>

          <div class="form-group">
            <label>Letra / Designación</label>
            <input v-model="certData.letra" type="text" placeholder="Según Memorándum cite: 055/2024..." />
          </div>

          <div class="form-group">
            <label>Último Destino</label>
            <input v-model="certData.ultimoDestino" type="text" placeholder="Y.P.F.B. ESTACION DE SERVICIO..." />
          </div>

          <h3>Firma y Validación</h3>

          <div class="form-group">
            <label>Fecha de Emisión</label>
            <input v-model="certData.fechaEmision" type="date" />
          </div>

          <div class="form-group">
            <label>Elaborado Por (Nombre y Cargo)</label>
            <input v-model="certData.elaboradoPor" type="text" placeholder="Sgto. My. Nombre Apellido" />
          </div>

          <div class="form-group">
            <label>Cargo del Elaborador</label>
            <input v-model="certData.cargoElaborador" type="text" placeholder="ENCARGADO DE LA SUB SECCIÓN ARCHIVO - KARDEX" />
          </div>

          <div class="form-group">
            <label>Nombre del Comandante</label>
            <input v-model="certData.comandante" type="text" placeholder="Cnl. DESP. Nombre Apellido" />
          </div>

          <div class="form-group">
            <label>Cargo del Comandante</label>
            <input v-model="certData.cargoComandante" type="text" placeholder="COMANDANTE" />
          </div>
        </div>

        <!-- Right Panel: Live Preview -->
        <div class="preview-panel">
          <div class="preview-header">
            <h3> Vista Previa</h3>
            <span class="preview-note">Esta es una representación aproximada</span>
          </div>
          
          <div id="certification-print-area" class="certification-preview">
            <!-- Encabezado Oficial -->
            <div class="cert-header">
              <div class="cert-logo">
                <img :src="logoUrl" alt="Logo Policía Boliviana" class="logo-img" />
              </div>
              <div class="cert-institution">
                <div class="inst-name">POLICÍA BOLIVIANA</div>
                <div class="inst-dept">COMANDO DEPARTAMENTAL</div>
                <div class="inst-unit">BATALLÓN DE SEGURIDAD FÍSICA</div>
                <div class="inst-location">ORURO/BOLIVIA</div>
              </div>
            </div>

            <!-- Título Certificación -->
            <div class="cert-title">
              <h1><u>CERTIFICACION</u></h1>
              <h2>N° {{ certData.numero || '___/2025' }}</h2>
            </div>

            <!-- Texto Introductorio -->
            <div class="cert-intro">
              <p>{{ certData.textoIntroductorio }}</p>
            </div>

            <!-- Cuerpo: Certifica -->
            <div class="cert-body">
              <p class="cert-certifica"><strong>CERTIFICA:</strong></p>
              
              <p class="cert-paragraph">
                QUE, REVISADOS LISTA DE REVISTA, FILE PERSONAL Y SECCION ARCHIVO – KARDEX DE LA DIVISION DE PERSONAL DEL BATALLON SE SEGURIDAD FISICA, DEPENDIENTE DEL COMANDO DEPARTAMENTAL DE POLICIA ORURO
              </p>

              <ul class="cert-list">
                <li><strong>EL {{ certData.nombrePersonal || '___________' }}</strong> CON N° DE <strong>CI. {{ certData.ci || '______' }}</strong> EXPEDIDO EN <strong>{{ certData.ciLugar || '______' }}</strong> CUMPLIÓ SERVICIOS EN EL BATALLÓN DE SEGURIDAD FÍSICA. DEPENDIENTE DE ACUERDO AL SIGUIENTE DETALLE:</li>
                <li><strong>INGRESO:</strong> {{ certData.fechaIngreso || '___________' }}</li>
                <li><strong>LETRA "A":</strong> {{ certData.letra || '___________' }}</li>
                <li><strong>ÚLTIMO DESTINO:</strong> {{ certData.ultimoDestino || '___________' }}</li>
              </ul>

              <p class="cert-closing">Es cuanto se certifica para fines consiguientes.</p>

              <p class="cert-date">Oruro, {{ formatDateSpanish(certData.fechaEmision) || '__ de ________ de 2025' }}.</p>
            </div>

            <!-- Firmas -->
            <div class="cert-signatures">
              <div class="signature-block">
                <p class="signature-label">Elaborado por</p>
                <p class="signature-name">{{ certData.elaboradoPor || '___________' }}</p>
                <p class="signature-title">{{ certData.cargoElaborador || '___________' }}</p>
                <p class="signature-unit">BATALLÓN DE SEGURIDAD FÍSICA</p>
              </div>

              <div class="signature-block signature-right">
                <p class="signature-label">Vo. Bo.</p>
                <p class="signature-name">{{ certData.comandante || '___________' }}</p>
                <p class="signature-title">{{ certData.cargoComandante || 'COMANDANTE' }}</p>
                <p class="signature-unit">BATALLÓN DE SEGURIDAD FÍSICA</p>
              </div>
            </div>

            <!-- Footer -->
            <div class="cert-footer">
              <p>cc/: Arch.</p>
              <p>file</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="editor-actions">
        <button @click="$emit('close')" class="btn-cancel">
          Cancelar
        </button>
        <button @click="printCertification" class="btn-print">
           Imprimir Certificación
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import BaseButton from '../ui/BaseButton.vue'
import { useToast } from '@/composables/useToast'

const { success, error } = useToast()

const props = defineProps({
  documento: {
    type: Object,
    default: () => ({})
  },
  headers: {
    type: Object,
    required: true
  },
  initialData: {
    type: Object,
    default: null
  },
  certificationId: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['close', 'printed'])

// Logo URL - Laravel sirve archivos de public/ directamente
const logoUrl = '/assets/logo.png'

// Default values
const defaultData = {
  numero: '000/2025',
  textoIntroductorio: 'EL SUSCRITO ENCARGADO DE LA SECCION ARCHIVO KARDEX DEL BATALLÓN DE SEGURIDAD FISICA DE ORURO, EN USO DE SUS ESPECÍFICAS ATRIBUCIONES QUE LE CONFIERE LA LEY Y EN ATENCIÓN A SOLICITUD ESCRITA DEL INTERESADO, CON HOJA DE TRAMITE ____ EMANADA POR SU AUTORIDAD:',
  nombrePersonal: 'SR. SGTO. 2DO. NOMBRE APELLIDO',
  ci: '0000000',
  ciLugar: 'ORURO',
  fechaIngreso: 'Según hoja de filiación 1 de abril de 1996.',
  letra: 'Según Memorándum Cite: 055/2024 En fecha 09 de enero del 2024, con tiempo de permanencia de 27 años y 9 mes 8 días.',
  ultimoDestino: '"Y.P.F.B. ESTACION DE SERVICIO ABEL ASCACINUZ", según Memorándum cite N° 413/2023, de fecha 10 de abril de 2023.',
  fechaEmision: new Date().toISOString().split('T')[0],
  elaboradoPor: 'Sgto. My. Alberto Choque Sánchez',
  cargoElaborador: 'ENCARGADO DE LA SUB SECCIÓN ARCHIVO - KARDEX',
  comandante: 'Cnl. DESP. Grover Miranda Noya',
  cargoComandante: 'COMANDANTE'
}

// Datos editables de la certificación
const certData = ref({ ...defaultData })

// Datos originales para detectar cambios
const originalData = ref(null)

function formatDateSpanish(dateString) {
  if (!dateString) return ''
  
  const date = new Date(dateString)
  const months = [
    'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
  ]
  
  return `${date.getDate()} de ${months[date.getMonth()]} de ${date.getFullYear()}`
}

/**
 * Detectar si hubo cambios en los datos
 */
function hasChanges() {
  if (!originalData.value) return true // Nueva certificación
  
  const current = {
    numero: certData.value.numero,
    textoIntroductorio: certData.value.textoIntroductorio,
    nombrePersonal: certData.value.nombrePersonal,
    ci: certData.value.ci,
    ciLugar: certData.value.ciLugar,
    fechaIngreso: certData.value.fechaIngreso,
    letra: certData.value.letra,
    ultimoDestino: certData.value.ultimoDestino,
    fechaEmision: certData.value.fechaEmision,
    elaboradoPor: certData.value.elaboradoPor,
    cargoElaborador: certData.value.cargoElaborador,
    comandante: certData.value.comandante,
    cargoComandante: certData.value.cargoComandante
  }
  
  return JSON.stringify(current) !== JSON.stringify(originalData.value)
}

/**
 * Guardar o actualizar certificación en la base de datos
 */
async function saveCertificate() {
  try {
    // Si estamos editando y no hay cambios, no guardar
    if (props.certificationId && !hasChanges()) {
      console.log('[CERT] No hay cambios, omitiendo guardado')
      return true
    }
    
    const isUpdate = !!props.certificationId
    const url = isUpdate 
      ? `/api/certificaciones/${props.certificationId}` 
      : '/api/certificaciones'
    const method = isUpdate ? 'PUT' : 'POST'
    
    const payload = {
      numero_certificacion: certData.value.numero,
      texto_introduccion: certData.value.textoIntroductorio,
      nombre_personal: certData.value.nombrePersonal,
      ci: certData.value.ci,
      lugar_expedicion: certData.value.ciLugar,
      fecha_ingreso: certData.value.fechaIngreso,
      designacion: certData.value.letra,
      ultimo_destino: certData.value.ultimoDestino,
      fecha_emision: certData.value.fechaEmision,
      elaborado_por: certData.value.elaboradoPor,
      cargo_elaborador: certData.value.cargoElaborador,
      nombre_comandante: certData.value.comandante,
      cargo_comandante: certData.value.cargoComandante
    }
    
    // Solo agregardocumento_id si es nueva certificación
    if (!isUpdate) {
      payload.documento_id = props.documento.id
    }

    const response = await fetch(url, {
      method,
      headers: {
        ...props.headers,
        'Content-Type': 'application/json'
      },
      credentials: 'include',
      body: JSON.stringify(payload)
    })

    const data = await response.json()
    
    if (data.ok) {
      const message = isUpdate 
        ? 'Certificación actualizada exitosamente' 
        : 'Certificación guardada exitosamente'
      success(message)
      return true
    } else {
      error('Error al guardar certificación', data.message || 'Error desconocido')
      return false
    }
  } catch (err) {
    console.error('Error al guardar certificación:', err)
    error('Error al guardar certificación', err.message)
    return false
  }
}

async function printCertification() {
  // Guardar primero en la BD
  await saveCertificate()
  
  // Imprimir
  window.print()
  emit('printed')
  emit('close')
}

// Initialize with provided data if available
onMounted(() => {
  if (props.initialData) {
    certData.value = { ...defaultData, ...props.initialData }
    // Guardar datos originales para detectar cambios
    if (props.certificationId) {
      originalData.value = {
        numero: certData.value.numero,
        textoIntroductorio: certData.value.textoIntroductorio,
        nombrePersonal: certData.value.nombrePersonal,
        ci: certData.value.ci,
        ciLugar: certData.value.ciLugar,
        fechaIngreso: certData.value.fechaIngreso,
        letra: certData.value.letra,
        ultimoDestino: certData.value.ultimoDestino,
        fechaEmision: certData.value.fechaEmision,
        elaboradoPor: certData.value.elaboradoPor,
        cargoElaborador: certData.value.cargoElaborador,
        comandante: certData.value.comandante,
        cargoComandante: certData.value.cargoComandante
      }
    }
  }
})
</script>

<style scoped>
.certification-editor-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.8);
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.editor-container {
  background: #f5f5f5;
  border-radius: 12px;
  max-width: 1600px;
  width: 100%;
  max-height: 95vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.editor-header {
  background: linear-gradient(135deg, #556b2f 0%, #6b8e23 100%);
  color: white;
  padding: 1.5rem 2rem;
  border-radius: 12px 12px 0 0;
}

.editor-header h2 {
  margin: 0 0 0.5rem 0;
  font-size: 1.5rem;
}

.editor-header p {
  margin: 0;
  opacity: 0.9;
  font-size: 0.938rem;
}

.editor-body {
  display: flex;
  gap: 1.5rem;
  padding: 1.5rem;
  flex: 1;
  overflow: hidden;
}

/* Form Panel */
.editor-form {
  flex: 0 0 400px;
  overflow-y: auto;
  padding-right: 1rem;
}

.editor-form h3 {
  color: #556b2f;
  margin: 1.5rem 0 1rem 0;
  font-size: 1.1rem;
  border-bottom: 2px solid #556b2f;
  padding-bottom: 0.5rem;
}

.editor-form h3:first-child {
  margin-top: 0;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.375rem;
  font-size: 0.875rem;
}

.form-group input,
.form-group textarea {
  width: 100%;
  padding: 0.625rem;
  border: 2px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.938rem;
  font-family: inherit;
  transition: border-color 0.2s;
}

.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #556b2f;
}

.form-group textarea {
  resize: vertical;
  min-height: 80px;
}

/* Preview Panel */
.preview-panel {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: white;
  border-radius: 8px;
  overflow: hidden;
}

.preview-header {
  background: #374151;
  color: white;
  padding: 1rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.preview-header h3 {
  margin: 0;
  font-size: 1rem;
}

.preview-note {
  font-size: 0.813rem;
  opacity: 0.8;
}

.certification-preview {
  flex: 1;
  overflow-y: auto;
  padding: 2rem;
  background: white;
  font-family: 'Times New Roman', serif;
  color: #000;
  line-height: 1.6;
}

/* Certification Styles */
.cert-header {
  text-align: center;
  margin-bottom: 2rem;
}

.cert-logo {
  margin-bottom: 0.5rem;
  display: flex;
  justify-content: center;
}

.logo-img {
  width: 80px;
  height: auto;
  filter: grayscale(0.3);
}

.cert-institution {
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.3;
  color: #2d3e2b;
}

.inst-name {
  font-size: 1rem;
  font-weight: 700;
}

.cert-title {
  text-align: center;
  margin: 2rem 0;
}

.cert-title h1 {
  font-size: 1.25rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  margin: 0 0 0.5rem 0;
}

.cert-title h2 {
  font-size: 1rem;
  font-weight: 600;
  margin: 0;
}

.cert-intro {
  margin: 1.5rem 0;
  text-align: justify;
  font-size: 0.875rem;
  font-style: italic;
}

.cert-body {
  margin: 1.5rem 0;
}

.cert-certifica {
  font-weight: 700;
  margin: 1rem 0;
}

.cert-paragraph {
  text-align: justify;
  margin: 1rem 0;
  font-size: 0.875rem;
}

.cert-list {
  list-style: disc;
  margin-left: 2rem;
  font-size: 0.875rem;
}

.cert-list li {
  margin-bottom: 0.5rem;
}

.cert-closing {
  margin: 1.5rem 0;
  font-style: italic;
  text-align: center;
}

.cert-date {
  text-align: right;
  margin: 1.5rem 0;
  font-style: italic;
}

.cert-signatures {
  display: flex;
  justify-content: space-between;
  margin: 3rem 0 2rem 0;
  gap: 2rem;
}

.signature-block {
  flex: 1;
  text-align: left;
  font-size: 0.813rem;
}

.signature-right {
  text-align: right;
}

.signature-label {
  font-style: italic;
  margin-bottom: 2rem;
}

.signature-name {
  font-weight: 600;
  margin: 0.25rem 0;
}

.signature-title {
  font-weight: 600;
  margin: 0.25rem 0;
}

.signature-unit {
  margin: 0.25rem 0;
}

.cert-footer {
  font-size: 0.75rem;
  margin-top: 2rem;
}

/* Actions */
.editor-actions {
  padding: 1.5rem 2rem;
  background: white;
  border-top: 2px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  border-radius: 0 0 12px 12px;
}

.btn-cancel,
.btn-print {
  padding: 0.75rem 1.5rem;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.938rem;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.btn-cancel {
  background: #e5e7eb;
  color: #374151;
}

.btn-cancel:hover {
  background: #d1d5db;
}

.btn-print {
  background: #556b2f;
  color: white;
}

.btn-print:hover {
  background: #6b8e23;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(85, 107, 47, 0.3);
}

/* Print Styles */
@media print {
  body * {
    visibility: hidden;
  }
  
  #certification-print-area,
  #certification-print-area * {
    visibility: visible;
  }
  
  #certification-print-area {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    padding: 2cm;
    background: white;
    font-size: 11pt;
  }
  
  @page {
    size: A4 portrait;
    margin: 0;
  }
}
</style>
