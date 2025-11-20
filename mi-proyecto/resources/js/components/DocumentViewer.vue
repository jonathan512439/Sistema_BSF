<template>
  <div class="overlay" :class="modeClass">
    <div class="viewer-shell">
      <div class="bar">
        <div class="title">
          Documento #{{ documentoId }}
        </div>

        <div class="actions">
          <!-- Motivo seleccionado (solo lectura en el visor) -->
          <div class="motivo-chip" v-if="motivoDescripcion">
            <span class="motivo-label">Motivo:</span>
            <span class="motivo-text">{{ motivoDescripcion }}</span>
          </div>

          <a
            v-if="streamBase"
            class="btn"
            :href="downloadUrl"
            target="_blank"
            rel="noopener"
            @click.prevent="handleDownload"
          >
            Descargar
          </a>
          <a
            v-if="streamBase"
            class="btn"
            :href="printUrl"
            target="_blank"
            rel="noopener"
            @click.prevent="handlePrint"
          >
            Imprimir
          </a>
          <button @click="$emit('close')" class="btn olive">Cerrar</button>
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
  </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue'

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

    streamBase.value = `/api/stream/${props.documentoId}`
    const baseView = meta?.archivo?.stream_url || `${streamBase.value}/view`
    pdfUrl.value = motivoId.value
      ? `${baseView}?motivo_id=${motivoId.value}`
      : `${baseView}`

    watermarkHash.value = meta?.watermark?.custodia_hash || ''
  } catch (e) {
    error.value = 'No se pudo cargar el documento: ' + (e?.message || e)
  } finally {
    loading.value = false
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
  window.open(downloadUrl.value, '_blank', 'noopener')
}

function handlePrint () {
  if (!ensureMotivo()) return
  window.open(printUrl.value, '_blank', 'noopener')
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
      pdfUrl.value = motivoId.value
        ? `${baseView}?motivo_id=${motivoId.value}`
        : baseView
    }
  },
)
</script>

<style>
.overlay {
  position: fixed;
  inset: 0;
  z-index: 70;
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
}

.overlay-sidepanel .viewer-shell {
  width: min(900px, 70vw);
  box-shadow: -16px 0 35px rgba(15, 23, 42, 0.35);
}

.bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 16px;
  border-bottom: 1px solid #e5e7eb;
  gap: 0.75rem;
  flex-wrap: wrap;
}
.title {
  font-weight: 700;
  color: #111827;
}
.actions {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}
.btn {
  padding: 6px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #f9fafb;
  cursor: pointer;
  font-size: 0.85rem;
}
.btn.olive {
  background: #556b2f;
  color: #fff;
  border-color: #4b5f2a;
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

.input {
  padding: 4px 6px;
  border-radius: 6px;
  border: 1px solid #d1d5db;
  background: #fff;
  font-size: 0.85rem;
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
