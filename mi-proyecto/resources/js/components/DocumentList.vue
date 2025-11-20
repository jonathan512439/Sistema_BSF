<script setup>
import { ref, onMounted, computed } from 'vue'
import DocumentViewer from './DocumentViewer.vue'
import DocumentCard from './DocumentCard.vue'

const props = defineProps({
  headers: { type: Object, required: true },
  rbac: { type: Object, required: true },
  catalogs: { type: Object, required: true },
})

const docs = ref([])
const viewerId = ref(null)
const viewerMotivoId = ref(null)

// Modal de motivo antes de abrir visor
const showMotivoModal = ref(false)
const motivoSeleccionadoId = ref(null)
const pendingViewerId = ref(null)

// Modal de subida de documento
const showUploadModal = ref(false)
const uploadOcrLoading = ref(false)
const uploadOcrMessage = ref('')
const uploadOcrOk = ref(false)

// Campos del formulario
const titulo = ref('')
const fref = ref(null)
const fechaDocumento = ref('')
const descripcion = ref('')

// Metadatos seleccionados
const tipoDocumentoId = ref(null)
const seccionId = ref(null)
const subseccionId = ref(null)
const gestionId = ref(null)
const filtroTexto = ref('')
const errorMsg = ref('')

const currentUserId = computed(
  () => Number(localStorage.getItem('demo_user_id') || 1),
)

// Fallback de permisos por usuario (si rbac.ok === false)
function fallbackPerms (uid) {
  if (uid === 1) return ['doc.view', 'doc.upload', 'doc.validate', 'doc.seal', 'doc.delete', 'doc.move'] // admin
  if (uid === 2) return ['doc.view', 'doc.upload', 'doc.validate', 'doc.seal', 'doc.delete', 'doc.move'] // custodio
  if (uid === 3) return ['doc.view'] // lector
  return ['doc.view']
}

function permsOf (uid) {
  if (props.rbac?.ok) {
    const rids = (props.rbac.user_roles || [])
      .filter(x => x.user_id === uid)
      .map(x => x.role_id)
    const perms = (props.rbac.role_permissions || [])
      .filter(x => rids.includes(x.role_id))
      .map(x => x.perm_slug)
    return Array.from(new Set(perms))
  }
  return fallbackPerms(uid)
}

const can = perm => permsOf(currentUserId.value).includes(perm)

// Sub-secciones filtradas por sección
const subseccionesFiltradas = computed(() => {
  const todas = props.catalogs?.subsecciones || []
  const sid = seccionId.value
  if (!sid) return todas
  return todas.filter(s => s.seccion_id === Number(sid))
})

// Gestiones ordenadas
const gestiones = computed(() => props.catalogs?.gestiones || [])

// Obtener lista de documentos (con filtro texto opcional)
async function fetchDocs () {
  errorMsg.value = ''
  try {
    const params = new URLSearchParams()
    if (filtroTexto.value && filtroTexto.value.trim() !== '') {
      params.append('q', filtroTexto.value.trim())
    }
    const url = '/api/documentos' + (params.toString() ? ('?' + params.toString()) : '')

    const r = await fetch(url, {
      credentials: 'include',
      headers: props.headers,
    })
    if (!r.ok) throw new Error('HTTP ' + r.status)
    const arr = await r.json()
    docs.value = Array.isArray(arr) ? arr : []
  } catch (e) {
    errorMsg.value = 'Error al listar documentos: ' + e.message
  }
}

function aplicarFiltro () {
  fetchDocs()
}

function limpiarFiltro () {
  filtroTexto.value = ''
  fetchDocs()
}

onMounted(fetchDocs)

// --- Modal de subida ---

function openUploadModal () {
  showUploadModal.value = true
  uploadOcrLoading.value = false
  uploadOcrMessage.value = ''
  uploadOcrOk.value = false
}

function closeUploadModal () {
  showUploadModal.value = false
  // Limpiar campos
  if (fref.value) fref.value.value = null
  titulo.value = ''
  tipoDocumentoId.value = null
  seccionId.value = null
  subseccionId.value = null
  gestionId.value = null
  fechaDocumento.value = ''
  descripcion.value = ''
  uploadOcrLoading.value = false
  uploadOcrMessage.value = ''
  uploadOcrOk.value = false
}

async function procesarOcrUpload () {
  if (!fref.value?.files?.length) {
    alert('Selecciona primero un archivo PDF.')
    return
  }
  uploadOcrLoading.value = true
  uploadOcrMessage.value = ''
  uploadOcrOk.value = false

  try {
    const fd = new FormData()
    fd.append('file', fref.value.files[0])

    // Endpoint pensado para previsualización OCR de metadatos
    const r = await fetch('/api/documentos/ocr-preview', {
      method: 'POST',
      body: fd,
      credentials: 'include',
      headers: props.headers,
    })

    const data = await r.json().catch(() => ({}))

    if (!r.ok || !data || data.ok === false) {
      uploadOcrMessage.value =
        'El OCR no pudo extraer datos suficientes del documento. Complete los campos manualmente.'
      return
    }

    const fields = data.fields || {}

    // Intentar precargar campos estándar
    if (fields.titulo) titulo.value = fields.titulo
    if (fields.fecha_documento) fechaDocumento.value = fields.fecha_documento
    if (fields.descripcion) descripcion.value = fields.descripcion

    // Si viene "gestion" como año suelto y existe en catálogo, buscar su id
    if (fields.gestion) {
      const g = (props.catalogs.gestiones || []).find(
        x => String(x.anio) === String(fields.gestion),
      )
      if (g) gestionId.value = g.id
    }

    if (!fields.titulo && !fields.fecha_documento && !fields.gestion && !fields.descripcion) {
      uploadOcrMessage.value =
        'El OCR no pudo extraer datos suficientes del documento. Complete los campos manualmente.'
    } else {
      uploadOcrMessage.value = 'Los campos fueron sugeridos por OCR. Revise y ajuste antes de subir.'
      uploadOcrOk.value = true
    }
  } catch (e) {
    uploadOcrMessage.value =
      'Ocurrió un error al intentar procesar OCR para sugerir metadatos. Complete los campos manualmente.'
  } finally {
    uploadOcrLoading.value = false
  }
}

// Subida real del documento (usa tu endpoint actual)
async function upload () {
  if (!fref.value?.files?.length) {
    alert('Selecciona un archivo PDF.')
    return
  }

  const fd = new FormData()
  fd.append('file', fref.value.files[0])
  if (titulo.value) fd.append('titulo', titulo.value)
  if (tipoDocumentoId.value) fd.append('tipo_documento_id', tipoDocumentoId.value)
  if (seccionId.value) fd.append('seccion_id', seccionId.value)
  if (subseccionId.value) fd.append('subseccion_id', subseccionId.value)
  if (gestionId.value) fd.append('gestion_id', gestionId.value)
  if (fechaDocumento.value) fd.append('fecha_documento', fechaDocumento.value)
  if (descripcion.value) fd.append('descripcion', descripcion.value)

  const r = await fetch('/api/documentos/upload', {
    method: 'POST',
    body: fd,
    credentials: 'include',
    headers: props.headers,
  })
  const j = await r.json().catch(() => ({}))
  if (!r.ok) {
    alert('Error al subir: ' + (j.message || r.status))
    return
  }

  // ✅ Confirmación visible + cerrar modal
  alert('Documento subido correctamente.')
  closeUploadModal()
  await fetchDocs()
}

// --- Flujo de visor con motivo obligatorio ---

function view (id) {
  pendingViewerId.value = id
  motivoSeleccionadoId.value = null
  showMotivoModal.value = true
}

function cancelarMotivo () {
  showMotivoModal.value = false
  pendingViewerId.value = null
  motivoSeleccionadoId.value = null
}

function confirmarMotivo () {
  if (!motivoSeleccionadoId.value) {
    alert('Debe seleccionar un motivo de acceso para ver el documento.')
    return
  }
  viewerId.value = pendingViewerId.value
  viewerMotivoId.value = motivoSeleccionadoId.value
  showMotivoModal.value = false
}

// Validación, sellado y eliminación

async function validar (id) {
  const payload = {
    titulo:
      prompt('Título del documento', 'Documento de prueba') ||
      'Documento de prueba',
    oficial:
      prompt('Nombre del oficial', 'Pérez, Juan') || 'Pérez, Juan',
    fecha:
      prompt(
        'Fecha del documento (YYYY-MM-DD)',
        '2025-01-15',
      ) || '2025-01-15',
    gestion: prompt('Gestión (YYYY)', '2025') || '2025',
  }
  const r = await fetch(`/api/documentos/${id}/validar`, {
    method: 'POST',
    headers: { ...props.headers, 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
    credentials: 'include',
  })
  const j = await r.json().catch(() => ({}))
  if (!r.ok) {
    alert('No se pudo validar el documento: ' + (j.message || r.status))
    return
  }
  await fetchDocs()
}

async function sellar (id) {
  const r = await fetch(`/api/documentos/${id}/sellar`, {
    method: 'POST',
    credentials: 'include',
    headers: props.headers,
  })
  const j = await r.json().catch(() => ({}))
  if (!r.ok) {
    alert('No se pudo sellar el documento: ' + (j.message || r.status))
    return
  }
  await fetchDocs()
}

async function eliminar (id) {
  if (
    !confirm(
      '¿Seguro que desea eliminar este documento? Esta acción quedará registrada en la auditoría.',
    )
  ) {
    return
  }
  const r = await fetch(`/api/documentos/${id}`, {
    method: 'DELETE',
    credentials: 'include',
    headers: props.headers,
  })
  const j = await r.json().catch(() => ({}))
  if (!r.ok) {
    alert('No se pudo eliminar el documento: ' + (j.message || r.status))
    return
  }
  await fetchDocs()
}

// Reprocesar OCR real manualmente
async function ocr (id) {
  const r = await fetch(`/api/documentos/${id}/ocr`, {
    method: 'POST',
    credentials: 'include',
    headers: props.headers,
  })
  const j = await r.json().catch(() => ({}))
  if (!r.ok) {
    alert('No se pudo procesar OCR: ' + (j.message || r.status))
    return
  }

  const conf = j.confidence_media ?? j.confidence ?? '—'
  alert('OCR procesado correctamente. Confianza media: ' + conf)
  await fetchDocs()
}
</script>

<template>
  <div>
    <h2 class="title">Gestión de documentos y custodia</h2>

    <!-- Buscador por texto libre -->
    <div class="card form-card" style="margin-bottom: .75rem">
      <div class="row" style="justify-content: space-between; align-items: center">
        <div style="flex: 1; min-width: 220px; margin-right: .5rem">
          <label style="display:block;font-size:.8rem;color:#4b5563;margin-bottom:2px">
            Buscar en documentos (título, descripción, campos y texto OCR)
          </label>
          <input
            v-model="filtroTexto"
            type="text"
            class="input"
            placeholder="Ej: nombre del oficial, parte del texto, año, etc."
          />
        </div>
        <div class="row">
          <button type="button" class="btn" @click="aplicarFiltro">
            Buscar
          </button>
          <button type="button" class="btn" @click="limpiarFiltro">
            Limpiar
          </button>
        </div>
      </div>
    </div>

    <!-- CTA subir nuevo documento -->
    <div
      v-if="can('doc.upload')"
      class="card form-card"
      style="margin-bottom: .75rem; display:flex; justify-content:space-between; align-items:center;"
    >
      <div>
        <div style="font-weight:600; color:#111827; font-size:.95rem">
          Subir nuevo documento
        </div>
        <div style="font-size:.85rem; color:#6b7280">
          El sistema intentará extraer metadatos con OCR y luego podrás validar y sellar la custodia.
        </div>
      </div>
      <button type="button" class="btn olive" @click="openUploadModal">
        Subir nuevo documento
      </button>
    </div>

    <div v-if="errorMsg" class="error">
      {{ errorMsg }}
    </div>

    <!-- Lista de documentos -->
    <div>
      <DocumentCard
        v-for="d in docs"
        :key="d.id"
        :doc="d"
        :headers="props.headers"
        :can="can"
        :catalogs="props.catalogs"
        @view="view"
        @validate="validar"
        @seal="sellar"
        @delete="eliminar"
        @ocr="ocr"
      />
      <div v-if="!docs.length" class="muted" style="margin-top: 0.5rem">
        No hay documentos registrados todavía.
      </div>
    </div>

    <!-- Modal de selección de motivo -->
    <transition name="fade">
      <div v-if="showMotivoModal" class="modal-backdrop">
        <div class="modal">
          <h3 class="modal-title">Seleccionar motivo de acceso</h3>
          <p class="modal-text">
            Antes de visualizar el documento, debe registrar el motivo de acceso
            para efectos de auditoría.
          </p>

          <div class="modal-field">
            <label>Motivo</label>
            <select v-model="motivoSeleccionadoId" class="input">
              <option :value="null">— Seleccionar motivo —</option>
              <option
                v-for="m in (props.catalogs.motivos_acceso || [])"
                :key="m.id"
                :value="m.id"
              >
                {{ m.descripcion }}
              </option>
            </select>
          </div>

          <div class="modal-actions">
            <button type="button" class="btn" @click="cancelarMotivo">
              Cancelar
            </button>
            <button type="button" class="btn olive" @click="confirmarMotivo">
              Continuar
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Modal de subida de documento -->
    <transition name="fade">
      <div v-if="showUploadModal" class="modal-backdrop">
        <div class="modal modal-large">
          <h3 class="modal-title">Nuevo documento</h3>
          <p class="modal-text">
            1) Seleccione el PDF · 2) Opcional: intente prellenar campos con OCR · 3) Revise y confirme.
          </p>

          <!-- Paso 1: archivo -->
          <div class="modal-section">
            <label class="field-label">Archivo PDF</label>
            <div class="row">
              <input type="file" accept="application/pdf" ref="fref" class="btn" />
              <button
                type="button"
                class="btn"
                @click="procesarOcrUpload"
                :disabled="uploadOcrLoading"
              >
                {{ uploadOcrLoading ? 'Procesando OCR…' : 'Procesar OCR para sugerir campos' }}
              </button>
            </div>
            <div v-if="uploadOcrMessage" class="tiny" :style="{color: uploadOcrOk ? '#166534' : '#b91c1c', marginTop: '0.3rem'}">
              {{ uploadOcrMessage }}
            </div>
          </div>

          <!-- Paso 2: metadatos -->
          <div class="modal-section">
            <label class="field-label">Metadatos del documento</label>
            <div class="grid-meta">
              <div class="field">
                <label>Título</label>
                <input
                  v-model="titulo"
                  placeholder="Título del documento"
                  class="input"
                />
              </div>

              <div class="field">
                <label>Tipo de documento</label>
                <select v-model="tipoDocumentoId" class="input">
                  <option :value="null">— Sin especificar —</option>
                  <option
                    v-for="t in (props.catalogs.tipos_documento || [])"
                    :key="t.id"
                    :value="t.id"
                  >
                    {{ t.nombre }}
                  </option>
                </select>
              </div>

              <div class="field">
                <label>Sección</label>
                <select v-model="seccionId" class="input">
                  <option :value="null">— Sin especificar —</option>
                  <option
                    v-for="s in (props.catalogs.secciones || [])"
                    :key="s.id"
                    :value="s.id"
                  >
                    {{ s.nombre }}
                  </option>
                </select>
              </div>

              <div class="field">
                <label>Subsección</label>
                <select v-model="subseccionId" class="input">
                  <option :value="null">— Sin especificar —</option>
                  <option
                    v-for="ss in subseccionesFiltradas"
                    :key="ss.id"
                    :value="ss.id"
                  >
                    {{ ss.nombre }}
                  </option>
                </select>
              </div>

              <div class="field">
                <label>Gestión</label>
                <select v-model="gestionId" class="input">
                  <option :value="null">— Sin especificar —</option>
                  <option v-for="g in gestiones" :key="g.id" :value="g.id">
                    {{ g.anio }}
                  </option>
                </select>
              </div>

              <div class="field">
                <label>Fecha del documento</label>
                <input v-model="fechaDocumento" type="date" class="input" />
              </div>

              <div class="field" style="grid-column: 1/-1">
                <label>Descripción (resumen)</label>
                <input
                  v-model="descripcion"
                  type="text"
                  class="input"
                  placeholder="Resumen o nota breve"
                />
              </div>
            </div>
          </div>

          <div class="modal-actions">
            <button type="button" class="btn" @click="closeUploadModal">
              Cancelar
            </button>
            <button type="button" class="btn olive" @click="upload">
              Subir documento
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Visor de documento (pantalla completa) -->
    <DocumentViewer
      v-if="viewerId"
      :documento-id="viewerId"
      :headers="props.headers"
      :motivos="props.catalogs.motivos_acceso || []"
      :initial-motivo-id="viewerMotivoId"
      @close="viewerId = null"
    />
  </div>
</template>

<style>
.title {
  color: #556b2f;
  font-weight: 700;
  margin: 0 0 0.5rem 0;
}
.row {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  flex-wrap: wrap;
}
.btn {
  padding: 0.45rem 0.7rem;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  background: #f9fafb;
  cursor: pointer;
}
.btn.olive {
  background: #556b2f;
  color: #fff;
  border-color: #4b5f2a;
}
.input {
  padding: 0.4rem 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.45rem;
  background: #ffffff;
  width: 100%;
  font-size: 0.9rem;
}
.form-card {
  margin-bottom: 0.75rem;
}
.grid-meta {
  display: grid;
  gap: 0.5rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin-top: 0.5rem;
}
.field label,
.field-label {
  display: block;
  font-size: 0.8rem;
  color: #4b5563;
  margin-bottom: 0.15rem;
}
.error {
  margin-top: 0.5rem;
  padding: 0.5rem;
  color: #b91c1c;
  border: 1px solid #fecaca;
  background: #fee2e2;
  border-radius: 6px;
}
.muted {
  color: #6b7280;
}
.tiny {
  font-size: 0.82rem;
}

/* Modales */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.35);
  display: grid;
  place-items: center;
  z-index: 60;
}
.modal {
  width: min(420px, 90vw);
  background: #ffffff;
  border-radius: 12px;
  padding: 1rem 1.2rem;
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.25);
}
.modal-large {
  width: min(900px, 95vw);
}
.modal-title {
  margin: 0 0 0.25rem;
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
}
.modal-text {
  margin: 0 0 0.75rem;
  font-size: 0.85rem;
  color: #4b5563;
}
.modal-field {
  margin-bottom: 0.75rem;
}
.modal-field label {
  display: block;
  font-size: 0.8rem;
  color: #4b5563;
  margin-bottom: 0.15rem;
}
.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}
.modal-section {
  margin-bottom: 0.9rem;
}

/* Animación fade */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media (max-width: 900px) {
  .grid-meta {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 600px) {
  .grid-meta {
    grid-template-columns: 1fr;
  }
}
</style>
