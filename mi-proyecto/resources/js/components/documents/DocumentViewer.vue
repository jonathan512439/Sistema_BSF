<template>
  <div class="overlay" :class="modeClass">
    <div class="viewer-shell">
      <div class="bar">
        <div class="bar-left">
          <div class="title">
            Documento #{{ documentoId }}
          </div>
          
          <!-- Motivo seleccionado -->
          <div class="motivo-chip" v-if="motivoDescripcion">
            <span class="motivo-label">Motivo:</span>
            <span class="motivo-text">{{ motivoDescripcion }}</span>
          </div>
          
          <!-- Usuario actual -->
          <div class="user-chip" v-if="wm.user">
            <span class="user-label">Usuario:</span>
            <span class="user-text">{{ wm.user }} ({{ wm.email }})</span>
          </div>
        </div>

        <div class="actions">
          <!-- Botón Descargar - solo si el motivo lo permite -->
          <BaseButton
            v-if="streamBase && permissions.canDownload"
            size="sm"
            @click="handleDownload"
          >
            Descargar
          </BaseButton>

          <!-- Botón Imprimir - solo si el motivo lo permite -->
          <BaseButton
            v-if="streamBase && permissions.canPrint"
            size="sm"
            @click="handlePrint"
          >
            Imprimir
          </BaseButton>

          <!-- Legal Hold -->
          <BaseButton
            v-if="legalHold"
            size="sm"
            style="background-color: #ef4444; color: white; border-color: #dc2626;"
            @click="toggleHold"
            title="Click para ver detalles o levantar bloqueo"
          >
            BLOQUEO LEGAL
          </BaseButton>
          <BaseButton
            v-else
            size="sm"
            @click="toggleHold"
            title="Activar Retención Legal"
          >
            Retención Legal
          </BaseButton>

          <PrimaryButton
            size="sm"
            @click="$emit('close')"
          >
            Cerrar
          </PrimaryButton>
        </div>
      </div>

      <div v-if="error" class="error">{{ error }}</div>

      <div class="wm-overlay" v-if="!error">
        {{ wm.user }} · {{ wm.email }} · {{ wm.ip }} ·
        {{ wm.ts }} · {{ watermarkHash || '(sin hash de custodia)' }}
      </div>

      <iframe v-if="pdfUrl && !error" :src="pdfUrl" class="iframe"></iframe>
      <div v-else-if="loading" class="loading">Cargando…</div>
    </div>

    <!-- Confirmation Modal -->
    <div v-if="showCertConfirmation" class="cert-confirmation-modal">
      <div class="cert-confirm-box">
        <h3>Copia para Informe</h3>
        <p>¿Desea generar una Certificación Oficial para adjuntar?</p>
        <div class="cert-confirm-actions">
          <button @click="handleCertConfirm(false)" class="btn-no">No, solo {{ certAction }}</button>
          <button @click="handleCertConfirm(true)" class="btn-yes">Sí, generar certificación</button>
        </div>
      </div>
    </div>

    <!-- Certification Editor -->
    <CertificationEditor
      v-if="showCertEditor"
      :documento="currentDocumento"
      :headers="headers"
      @close="closeCertEditor"
      @printed="handleCertPrinted"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import BaseButton from '../ui/BaseButton.vue'
import PrimaryButton from '../ui/PrimaryButton.vue'
import CertificationEditor from './CertificationEditor.vue'

const props = defineProps({
  documentoId: { type: Number, required: true },
  headers: { type: Object, required: true },
  motivos: { type: Array, required: false, default: () => [] },
  // 'fullscreen' | 'sidepanel'
  mode: { type: String, required: false, default: 'fullscreen' },
  // motivo preseleccionado desde el modal
  initialMotivoId: { type: [Number, null], required: false, default: null },
})

const loading = ref(false)
const error = ref('')
const pdfUrl = ref('')
const streamBase = ref('')
const watermarkHash = ref('')
const wm = ref({ user: '', email: '', ip: '', ts: '' })
const legalHold = ref(null)
const currentDocumento = ref(null)
const showCertConfirmation = ref(false)
const showCertEditor = ref(false)
const certAction = ref('') 
const pendingAction = ref(null)

const motivoId = ref(props.initialMotivoId)

// estilos según modo
const modeClass = computed(() =>
  props.mode === 'sidepanel' ? 'overlay-sidepanel' : 'overlay-fullscreen',
)

const downloadUrl = computed(() => {
  if (!streamBase.value) return '#'
  const base = `${streamBase.value}/download`
  return motivoId.value ? `${base}?motivo_id=${motivoId.value}` : base
})

const printUrl = computed(() => {
  if (!streamBase.value) return '#'
  const base = `${streamBase.value}/print`
  return motivoId.value ? `${base}?motivo_id=${motivoId.value}` : base
})

const motivoDescripcion = computed(() => {
  if (!motivoId.value) return ''
  const m = (props.motivos || []).find(x => x.id === Number(motivoId.value))
  return m ? m.descripcion : `ID ${motivoId.value}`
})

// Objeto del motivo actual
const currentMotivo = computed(() => {
  if (!motivoId.value) return null
  return (props.motivos || []).find(x => x.id === Number(motivoId.value))
})

// Permisos basados en el motivo seleccionado
const permissions = computed(() => {
  if (!currentMotivo.value) {
    return { canView: false, canPrint: false, canDownload: false }
  }
  return {
    canView: currentMotivo.value.can_view ?? true,
    canPrint: currentMotivo.value.can_print ?? false,
    canDownload: currentMotivo.value.can_download ?? false
  }
})

async function load () {
  loading.value = true
  error.value = ''
  try {
    const rw = await fetch('/api/wm-context', {
      credentials: 'include',
      headers: props.headers,
    })
    if (!rw.ok) throw new Error(await rw.text())
    wm.value = await rw.json()

    const r = await fetch(`/api/documentos/${props.documentoId}`, {
      credentials: 'include',
      headers: props.headers,
    })
    if (!r.ok) throw new Error('HTTP ' + r.status)
    const meta = await r.json()

    // Cargar estado de Legal Hold
    legalHold.value = meta.legal_hold || null
    // Guardar documento completo para el editor
    currentDocumento.value = meta.documento

    streamBase.value = `/api/stream/${props.documentoId}`
    const baseView = meta?.archivo?.stream_url || `${streamBase.value}/view`
    const viewUrl = motivoId.value
      ? `${baseView}?motivo_id=${motivoId.value}`
      : `${baseView}`
    
    // Agregar #toolbar=0 para ocultar la barra de herramientas del PDF
    pdfUrl.value = `${viewUrl}#toolbar=0`

    watermarkHash.value = meta?.watermark?.custodia_hash || ''
  } catch (e) {
    error.value = 'No se pudo cargar el documento: ' + (e?.message || e)
  } finally {
    loading.value = false
  }
}

async function toggleHold() {
  if (legalHold.value) {
    // Levantar hold
    if (!confirm(`El documento tiene una Retención Legal activa por:\n"${legalHold.value.motivo}"\n\n¿Desea LEVANTAR este bloqueo? Esta acción quedará auditada.`)) {
      return
    }
    
    try {
      const r = await fetch(`/api/documentos/${props.documentoId}/hold`, {
        method: 'DELETE',
        headers: props.headers,
      })
      if (!r.ok) throw new Error(await r.text())
      alert('Retención Legal levantada correctamente.')
      load() // Recargar para actualizar estado
    } catch (e) {
      alert('Error al levantar bloqueo: ' + e.message)
    }
  } else {
    // Activar hold
    const motivo = prompt('ACTIVAR RETENCIÓN LEGAL\n\nIngrese el motivo legal o administrativo para bloquear la eliminación de este documento:')
    if (!motivo) return

    try {
      const r = await fetch(`/api/documentos/${props.documentoId}/hold`, {
        method: 'POST',
        headers: {
          ...props.headers,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ motivo })
      })
      if (!r.ok) throw new Error(await r.text())
      alert('Retención Legal activada correctamente.')
      load() // Recargar para actualizar estado
    } catch (e) {
      alert('Error al activar bloqueo: ' + e.message)
    }
  }
}

function ensureMotivo () {
  if (!motivoId.value) {
    alert(
      'No se recibió un motivo de acceso. Cierre el visor y vuelva a abrir seleccionando un motivo.',
    )
    return false
  }
  return true
}

function handleDownload () {
  if (!ensureMotivo()) return
  // Mostrar modal de confirmación
  certAction.value = 'descargar'
  pendingAction.value = () => window.open(downloadUrl.value, '_blank', 'noopener')
  showCertConfirmation.value = true
}

function handlePrint () {
  if (!ensureMotivo()) return
  // Mostrar modal de confirmación
  certAction.value = 'imprimir'
  pendingAction.value = () => window.open(printUrl.value, '_blank', 'noopener')
  showCertConfirmation.value = true
}

function handleCertConfirm(generateCert) {
  showCertConfirmation.value = false
  
  if (generateCert) {
    // Abrir editor de certificación
    showCertEditor.value = true
  } else {
    // Ejecutar acción original (descargar o imprimir)
    if (pendingAction.value) {
      pendingAction.value()
      pendingAction.value = null
    }
  }
}

function closeCertEditor() {
  showCertEditor.value = false
  pendingAction.value = null
}

function handleCertPrinted() {
  // Después de imprimir la certificación, ejecutar la acción original si existe
  if (pendingAction.value) {
    pendingAction.value()
  }
  closeCertEditor()
}

onMounted(load)

watch(
  () => props.documentoId,
  () => {
    motivoId.value = props.initialMotivoId
    load()
  },
)

watch(
  () => props.initialMotivoId,
  newVal => {
    motivoId.value = newVal
    if (streamBase.value) {
      const baseView = `${streamBase.value}/view`
      const viewUrl = motivoId.value
        ? `${baseView}?motivo_id=${motivoId.value}`
        : baseView
      // Agregar #toolbar=0 para ocultar la barra de herramientas del PDF
      pdfUrl.value = `${viewUrl}#toolbar=0`
    }
  },
)
</script>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
}

/* Modo pantalla completa */
.overlay-fullscreen {
  background: #ffffff;
  align-items: stretch;
  justify-content: stretch;
}

/* Modo panel lateral (drawer derecho) */
.overlay-sidepanel {
  background: rgba(15, 23, 42, 0.35);
  align-items: stretch;
  justify-content: flex-end;
}

.viewer-shell {
  background: #ffffff;
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  position: relative;
}

.overlay-sidepanel .viewer-shell {
  width: min(900px, 70vw);
  box-shadow: -16px 0 35px rgba(15, 23, 42, 0.35);
}

.bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid #e5e7eb;
  gap: 1rem;
  background: #f9fafb;
  min-height: 64px;
  flex-wrap: wrap;
}

.bar-left {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex: 1;
  min-width: 0;
  flex-wrap: wrap;
}

.title {
  font-weight: 700;
  color: #111827;
  font-size: 1rem;
  white-space: nowrap;
}

.actions {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}

/* Motivo seleccionado */
.motivo-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 999px;
  background: #ecfdf3;
  border: 1px solid #bbf7d0;
  font-size: 0.78rem;
}
.motivo-label {
  font-weight: 600;
  color: #166534;
}
.motivo-text {
  color: #065f46;
}

/* Usuario actual */
.user-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 999px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  font-size: 0.78rem;
}
.user-label {
  font-weight: 600;
  color: #1e40af;
}
.user-text {
  color: #1e3a8a;
}

.iframe {
  width: 100%;
  height: calc(100vh - 56px);
  border: 0;
}

.overlay-sidepanel .iframe {
  height: calc(100vh - 56px);
}

.loading {
  padding: 16px;
}

.error {
  margin: 12px;
  padding: 8px;
  color: #b91c1c;
  border: 1px solid #fecaca;
  background: #fee2e2;
  border-radius: 6px;
}

.wm-overlay {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  font-size: 18px;
  opacity: 0.18;
  transform: rotate(-24deg);
  pointer-events: none;
  z-index: 9999;
  text-align: center;
  white-space: pre-wrap;
}

/* Certification Confirmation Modal */
.cert-confirmation-modal {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  z-index: 1500;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.cert-confirm-box {
  background: white;
  border-radius: 12px;
  padding: 2rem;
  max-width: 500px;
  width: 100%;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
  animation: slideIn 0.2s ease-out;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

.cert-confirm-box h3 {
  margin: 0 0 1rem 0;
  color: #1f2937;
  font-size: 1.5rem;
}

.cert-confirm-box p {
  margin: 0 0 1.5rem 0;
  color: #6b7280;
  font-size: 1rem;
}

.cert-confirm-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
}

.btn-no,
.btn-yes {
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.938rem;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.btn-no {
  background: #e5e7eb;
  color: #374151;
}

.btn-no:hover {
  background: #d1d5db;
}

.btn-yes {
  background: #556b2f;
  color: white;
}

.btn-yes:hover {
  background: #6b8e23;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(85, 107, 47, 0.3);
}

/* Animación simple si lo usas con v-if/v-show externo */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media (max-width: 768px) {
  .overlay-sidepanel .viewer-shell {
    width: 100%;
  }
}
</style>
