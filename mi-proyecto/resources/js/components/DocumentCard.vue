<template>
  <div class="card">
    <div class="row" style="justify-content: space-between">
      <div>
        <div class="title">{{ doc.titulo || '(sin título)' }}</div>
        <div class="muted">
          #{{ doc.id }}
          · Estado: <b>{{ estadoActual }}</b>
          · Confianza OCR: {{ doc.ocr_confidence ?? '—' }}
        </div>
      </div>

      <div class="row">
        <button @click="view(doc.id)" class="btn">Ver</button>

        <!-- Procesar OCR real (usa el servicio Python) -->
        <button
          v-if="can('doc.validate')"
          @click="emitOcr"
          class="btn"
        >
          Procesar OCR
        </button>

        <button
          v-if="mostrarValidar"
          @click="emitValidate"
          class="btn"
        >
          Validar
        </button>

        <button
          v-if="can('doc.seal') && estadoActual === 'validado'"
          @click="emitSeal"
          class="btn olive"
        >
          Sellar custodia
        </button>

        <button
          v-if="can('doc.delete')"
          @click="emitDelete"
          class="btn danger"
        >
          Eliminar
        </button>

        <button @click="toggle" class="btn">
          {{ open ? 'Ocultar detalles' : 'Ver detalles' }}
        </button>
      </div>
    </div>

    <transition name="fade">
      <div v-if="open" class="details">
        <!-- Custodia digital -->
        <div class="section">
          <h4 class="section-title">Custodia digital</h4>
          <div class="grid2">
            <div class="tiny">
              <b>Estado actual:</b>
              <span class="badge estado">{{ estadoActual }}</span>
            </div>
            <div class="tiny">
              <b>Hash de custodia:</b>
              <span v-if="custodiaHash" class="mono">
                {{ custodiaHash.slice(0, 20) }}…
              </span>
              <span v-else class="muted">(aún no sellado)</span>
            </div>
            <div class="tiny">
              <b>Validado por (id):</b>
              <span>{{ det.documento?.validado_por ?? '—' }}</span>
            </div>
            <div class="tiny">
              <b>Fecha de validación:</b>
              <span>{{ det.documento?.validado_en ?? '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Metadatos archivísticos -->
        <div class="section">
          <h4 class="section-title">Metadatos archivísticos</h4>
          <div class="grid2">
            <div class="tiny">
              <b>Tipo de documento:</b>
              <span>{{ tipoDocumentoNombre }}</span>
            </div>
            <div class="tiny">
              <b>Sección / Subsección:</b>
              <span>
                {{ seccionNombre }}
                <span v-if="subseccionNombre && subseccionNombre !== '—'">
                  / {{ subseccionNombre }}
                </span>
              </span>
            </div>
            <div class="tiny">
              <b>Gestión:</b>
              <span>{{ gestionTexto }}</span>
            </div>
            <div class="tiny">
              <b>Fecha del documento:</b>
              <span>{{ det.documento?.fecha_documento ?? doc.fecha_documento ?? '—' }}</span>
            </div>
            <div class="tiny" style="grid-column: 1 / -1">
              <b>Descripción:</b>
              <span>{{ det.documento?.descripcion ?? doc.descripcion ?? '(sin descripción)' }}</span>
            </div>
          </div>
        </div>

        <!-- OCR -->
        <div class="section">
          <h4 class="section-title">Reconocimiento de texto (OCR)</h4>

          <!-- Tabla de campos OCR vs validados -->
          <div class="ocr-table-wrapper">
            <div v-if="ocrCamposMerge.length" class="ocr-table">
              <div class="ocr-table-header tiny">
                <div>Campo</div>
                <div>Valor OCR</div>
                <div>Conf.</div>
                <div>Valor validado</div>
              </div>
              <div
                v-for="row in ocrCamposMerge"
                :key="row.campo"
                class="ocr-table-row tiny"
              >
                <div class="campo">{{ row.campo }}</div>
                <div class="valor-ocr">
                  <span v-if="row.ocr">{{ row.ocr }}</span>
                  <span v-else class="muted">—</span>
                </div>
                <div class="conf">
                  <span v-if="row.ocr_conf !== null && row.ocr_conf !== undefined">
                    {{ row.ocr_conf.toFixed(1) }}%
                  </span>
                  <span v-else class="muted">—</span>
                </div>
                <div class="valor-final">
                  <span v-if="row.final">{{ row.final }}</span>
                  <span v-else class="muted">—</span>
                </div>
              </div>
            </div>
            <div v-else class="tiny muted">
              No hay campos OCR ni validados registrados todavía para este documento.
            </div>
          </div>

          <!-- Toggle para texto completo OCR -->
          <div v-if="ocrFullText && ocrFullText.trim().length" class="tiny" style="margin-top:.4rem">
            <button type="button" class="btn" @click="mostrarTextoCompleto = !mostrarTextoCompleto">
              {{ mostrarTextoCompleto ? 'Ocultar texto completo OCR' : 'Ver texto completo OCR' }}
            </button>
            <div v-if="mostrarTextoCompleto" class="ocr-fulltext-wrapper">
              <div class="tiny muted" style="margin-bottom: .25rem; margin-top:.25rem">
                Texto reconocido por OCR (solo lectura):
              </div>
              <div class="ocr-fulltext-box mono">
                {{ ocrFullText }}
              </div>
            </div>
          </div>
        </div>

        <!-- Archivo y almacén -->
        <div class="section grid2">
          <div>
            <h4 class="section-title">Archivo digital</h4>
            <div class="tiny">
              <b>Versión:</b>
              <span>{{ det.archivo?.version ?? '—' }}</span>
            </div>
            <div class="tiny">
              <b>Ruta relativa:</b>
              <span class="mono">{{ det.archivo?.ruta_relativa ?? '—' }}</span>
            </div>
            <div class="tiny">
              <b>SHA256 del archivo:</b>
              <span v-if="det.archivo?.sha256" class="mono">
                {{ det.archivo.sha256.slice(0, 20) }}…
              </span>
              <span v-else class="muted">—</span>
            </div>
            <div class="tiny">
              <b>Tamaño (bytes):</b>
              <span>{{ det.archivo?.bytes ?? '—' }}</span>
            </div>
          </div>

          <div>
            <h4 class="section-title">Almacén lógico</h4>
            <div class="tiny">
              <b>Nombre:</b>
              <span>{{ det.almacen?.nombre || '—' }}</span>
            </div>
            <div class="tiny">
              <b>Tipo:</b>
              <span>{{ det.almacen?.tipo || '—' }}</span>
            </div>
            <div class="tiny">
              <b>Ruta base:</b>
              <span class="mono">{{ det.almacen?.base_path || '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Ubicación y accesos -->
        <div class="section grid2">
          <div>
            <h4 class="section-title">Ubicación física</h4>

            <!-- Ubicación actual -->
            <div v-if="det.ubicacion" class="tiny">
              <div>
                <b>Actual:</b>
                {{ det.ubicacion.codigo }} — {{ det.ubicacion.descripcion }}
              </div>
              <div v-if="det.ubicacion.codigo_fisico">
                <b>Código físico:</b>
                {{ det.ubicacion.codigo_fisico }}
              </div>
              <div>
                <b>Estado físico:</b>
                {{ det.ubicacion.estado_fisico || '—' }}
              </div>
              <div>
                <b>Asignado desde:</b>
                {{ det.ubicacion.desde || '—' }}
              </div>
              <div v-if="det.ubicacion.hasta">
                <b>Hasta:</b>
                {{ det.ubicacion.hasta }}
              </div>
            </div>
            <div v-else class="tiny muted">
              Sin ubicación física registrada.
            </div>

            <!-- Historial resumido -->
            <div
              v-if="(det.ubicacion_historial || []).length"
              class="tiny"
              style="margin-top: .4rem"
            >
              <b>Historial de movimientos:</b>
              <div
                v-for="u in det.ubicacion_historial"
                :key="u.id"
              >
                • {{ u.desde }} → {{ u.hasta || 'vigente' }} ·
                {{ u.codigo }} — {{ u.descripcion }}
                <span v-if="u.motivo" class="muted"> ({{ u.motivo }})</span>
              </div>
            </div>

            <!-- Formulario de movimiento (solo roles con permiso) -->
            <div v-if="can('doc.move')" class="ubicacion-form">
              <div class="tiny" style="margin-top: .4rem">
                <b>Mover ubicación física:</b>
              </div>
              <div class="ubicacion-grid">
                <div>
                  <label class="label">Nueva ubicación</label>
                  <select v-model="nuevaUbicacionId" class="input">
                    <option :value="null">— Seleccionar ubicación —</option>
                    <option
                      v-for="u in (props.catalogs.ubicaciones || [])"
                      :key="u.id"
                      :value="u.id"
                    >
                      {{ u.codigo }} — {{ u.descripcion }}
                    </option>
                  </select>
                </div>
                <div>
                  <label class="label">Código físico</label>
                  <input
                    v-model="nuevaUbicacionCodigoFisico"
                    class="input"
                    placeholder="Caja, legajo, etc. (opcional)"
                  />
                </div>
                <div>
                  <label class="label">Estado físico</label>
                  <input
                    v-model="nuevaUbicacionEstadoFisico"
                    class="input"
                    placeholder="Ej: buen estado, deteriorado..."
                  />
                </div>
                <div>
                  <label class="label">Motivo del movimiento</label>
                  <input
                    v-model="nuevaUbicacionMotivo"
                    class="input"
                    placeholder="Motivo del movimiento"
                  />
                </div>
              </div>
              <div style="margin-top: .35rem">
                <button
                  class="btn"
                  :disabled="ubicacionGuardando"
                  @click="guardarUbicacion"
                >
                  {{ ubicacionGuardando ? 'Guardando…' : 'Actualizar ubicación' }}
                </button>
              </div>
            </div>
          </div>

          <div>
            <h4 class="section-title">Accesos recientes</h4>
            <div v-if="(det.accesos || []).length" class="tiny">
              <div v-for="a in det.accesos" :key="a.id">
                • {{ etiquetaAccion(a.accion) }} — {{ a.created_at }}
                <span class="muted">
                  (usuario id: {{ a.user_id ?? '—' }}
                  <span v-if="a.motivo">
                    · motivo: {{ a.motivo }}
                  </span>
                  )
                </span>
              </div>
            </div>
            <div v-else class="tiny muted">
              Sin accesos registrados.
            </div>
          </div>
        </div>

        <!-- Impresiones y ledger externo -->
        <div class="section grid2">
          <div>
            <h4 class="section-title">Impresiones recientes</h4>
            <div v-if="(det.impresiones || []).length" class="tiny">
              <div v-for="p in det.impresiones" :key="p.id" style="margin-bottom: 4px">
                • {{ p.created_at }} — usuario id: {{ p.user_id ?? '—' }}<br />
                <span class="mono">
                  hash copia: {{ (p.hash_copia || '').slice(0, 20) }}…
                </span>
              </div>
            </div>
            <div v-else class="tiny muted">
              No hay impresiones registradas.
            </div>
          </div>

          <div>
            <h4 class="section-title">Ledger externo (bsf_audit)</h4>
            <div v-if="ledgerLoading" class="tiny muted">
              Cargando eventos de auditoría…
            </div>
            <div v-else-if="ledgerError" class="tiny error">
              No se pudo cargar el ledger externo.
            </div>
            <div v-else-if="(ledger || []).length" class="tiny">
              <div v-for="e in ledger" :key="e.id" style="margin-bottom: 4px">
                • {{ e.created_at }} — <b>{{ e.evento }}</b><br />
                <span class="muted">
                  actor id: {{ e.actor_id ?? '—' }}
                  · objeto: {{ e.objeto_tipo }} #{{ e.objeto_id }}
                </span>
              </div>
            </div>
            <div v-else class="tiny muted">
              Sin eventos registrados en el ledger externo para este documento.
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  doc: { type: Object, required: true },
  headers: { type: Object, required: true },
  can: { type: Function, required: true },
  catalogs: {
    type: Object,
    required: false,
    default: () => ({
      tipos_documento: [],
      secciones: [],
      subsecciones: [],
      gestiones: [],
      ubicaciones: [],
    }),
  },
})

const emit = defineEmits(['view', 'validate', 'seal', 'delete', 'ocr'])

const open = ref(false)
const det = ref({})
const ledger = ref([])
const ledgerLoading = ref(false)
const ledgerError = ref(false)

const mostrarTextoCompleto = ref(false)

function view (id) {
  emit('view', id)
}
function emitValidate () {
  emit('validate', props.doc.id)
}
function emitSeal () {
  emit('seal', props.doc.id)
}
function emitDelete () {
  emit('delete', props.doc.id)
}
function emitOcr () {
  emit('ocr', props.doc.id)
}

function etiquetaAccion (accion) {
  if (accion === 'view') return 'Vista'
  if (accion === 'download') return 'Descarga'
  if (accion === 'print') return 'Impresión'
  return accion
}

// Estado actual tomando en cuenta detalle si ya se cargó
const estadoActual = computed(() => {
  return det.value?.documento?.estado || props.doc.estado
})

// Mostrar Validar mientras el documento NO esté sellado/custodiado
const mostrarValidar = computed(() => {
  if (!props.can('doc.validate')) return false
  const e = estadoActual.value
  if (!e) return false
  const lowered = String(e).toLowerCase()
  if (lowered.includes('custodi') || lowered.includes('sellad')) return false
  return true
})

const custodiaHash = computed(() => {
  return (
    det.value?.watermark?.custodia_hash ||
    det.value?.documento?.custodia_hash ||
    props.doc.custodia_hash ||
    ''
  )
})

const tipoDocumentoNombre = computed(() => {
  const id =
    det.value?.documento?.tipo_documento_id ?? props.doc.tipo_documento_id
  if (!id) return '—'
  const arr = props.catalogs?.tipos_documento || []
  const found = arr.find(t => t.id === Number(id))
  return found?.nombre || `ID ${id}`
})

const seccionNombre = computed(() => {
  const id = det.value?.documento?.seccion_id ?? props.doc.seccion_id
  if (!id) return '—'
  const arr = props.catalogs?.secciones || []
  const found = arr.find(s => s.id === Number(id))
  return found?.nombre || `ID ${id}`
})

const subseccionNombre = computed(() => {
  const id = det.value?.documento?.subseccion_id ?? props.doc.subseccion_id
  if (!id) return '—'
  const arr = props.catalogs?.subsecciones || []
  const found = arr.find(ss => ss.id === Number(id))
  return found?.nombre || `ID ${id}`
})

const gestionTexto = computed(() => {
  const id = det.value?.documento?.gestion_id ?? props.doc.gestion_id
  if (!id) return '—'
  const arr = props.catalogs?.gestiones || []
  const found = arr.find(g => g.id === Number(id))
  return found?.anio || `ID ${id}`
})

// Campos OCR + validados combinados (tolerante a nombres distintos)
const ocrCamposMerge = computed(() => {
  const map = new Map()

  // Aceptar distintos nombres que pueda devolver tu API
  const camposOcr = det.value?.campos_ocr ||
                    det.value?.ocr_campos ||
                    []

  const camposVal = det.value?.campos_validados ||
                    det.value?.documento_campos ||
                    det.value?.campos ||
                    []

  // Primero los OCR puros
  camposOcr.forEach(c => {
    const key = c.campo
    map.set(key, {
      campo: key,
      ocr: c.valor ?? '',
      ocr_conf: c.confidence ?? null,
      final: null,
    })
  })

  // Luego documentos_campos (puede traer valor_ocr y/o valor_final)
  camposVal.forEach(c => {
    const key = c.campo
    const existing = map.get(key) || {
      campo: key,
      ocr: null,
      ocr_conf: null,
      final: null,
    }

    if (!existing.ocr && c.valor_ocr) {
      existing.ocr = c.valor_ocr
    }
    if (existing.ocr_conf === null && c.confidence !== null && c.confidence !== undefined) {
      existing.ocr_conf = c.confidence
    }

    if (c.valor_final) {
      existing.final = c.valor_final
    }

    map.set(key, existing)
  })

  return Array.from(map.values()).sort((a, b) => a.campo.localeCompare(b.campo))
})

// Texto completo OCR – acepta varios nombres posibles
const ocrFullText = computed(() => {
  return (
    det.value?.ocr_full_text ||
    det.value?.full_text ||
    det.value?.ocr_text ||
    ''
  )
})

const nuevaUbicacionId = ref(null)
const nuevaUbicacionMotivo = ref('')
const nuevaUbicacionCodigoFisico = ref('')
const nuevaUbicacionEstadoFisico = ref('')
const ubicacionGuardando = ref(false)

async function loadDetails () {
  // 1) Detalles del documento
  const r = await fetch(`/api/documentos/${props.doc.id}`, {
    headers: props.headers,
    credentials: 'include',
  })
  if (!r.ok) throw new Error('HTTP ' + r.status)
  det.value = await r.json()

  // 2) Ledger externo
  ledgerLoading.value = true
  ledgerError.value = false
  try {
    const rl = await fetch(`/api/audit/ledger/${props.doc.id}`, {
      headers: props.headers,
      credentials: 'include',
    })
    if (!rl.ok) throw new Error('HTTP ' + rl.status)
    ledger.value = await rl.json()
  } catch (e) {
    ledgerError.value = true
    ledger.value = []
  } finally {
    ledgerLoading.value = false
  }
}

async function toggle () {
  open.value = !open.value
  if (open.value && !det.value.archivo) {
    try {
      await loadDetails()
    } catch (e) {
      // opcional: mostrar error en la UI
    }
  }
}

async function guardarUbicacion () {
  if (!nuevaUbicacionId.value) {
    alert('Debe seleccionar una ubicación física.')
    return
  }

  ubicacionGuardando.value = true
  try {
    const payload = {
      ubicacion_id: nuevaUbicacionId.value,
      motivo: nuevaUbicacionMotivo.value || 'Movimiento de ubicación desde la UI de demo.',
      codigo_fisico: nuevaUbicacionCodigoFisico.value || null,
      estado_fisico: nuevaUbicacionEstadoFisico.value || null,
    }

    const r = await fetch(`/api/documentos/${props.doc.id}/ubicacion`, {
      method: 'POST',
      headers: {
        ...props.headers,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
      credentials: 'include',
    })
    if (!r.ok) {
      const j = await r.json().catch(() => ({}))
      alert('No se pudo actualizar la ubicación física: ' + (j.message || r.status))
      return
    }

    await loadDetails()

    nuevaUbicacionMotivo.value = ''
    nuevaUbicacionCodigoFisico.value = ''
    nuevaUbicacionEstadoFisico.value = ''
  } catch (e) {
    alert('Error inesperado al actualizar ubicación: ' + (e?.message || e))
  } finally {
    ubicacionGuardando.value = false
  }
}
</script>

<style>
.card {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 14px;
  background: #fff;
  margin-bottom: 0.75rem;
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
.btn.danger {
  background: #b91c1c;
  color: #fff;
  border-color: #991b1b;
}
.btn.danger:hover {
  background: #991b1b;
}
.title {
  font-weight: 700;
  color: #111827;
}
.details {
  margin-top: 0.75rem;
  padding: 0.75rem;
  background: #f8faf7;
  border: 1px dashed #cdd6b3;
  border-radius: 8px;
}
.grid2 {
  display: grid;
  gap: 0.5rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.section {
  margin-bottom: 0.75rem;
}
.section-title {
  margin: 0 0 0.25rem 0;
  font-size: 0.9rem;
  font-weight: 600;
  color: #4b5563;
}
.tiny {
  font-size: 0.86rem;
  color: #374151;
  line-height: 1.35;
}
.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,
    'Liberation Mono', monospace;
}
.muted {
  color: #6b7280;
}
.badge {
  display: inline-block;
  padding: 0.05rem 0.45rem;
  border-radius: 999px;
  font-size: 0.75rem;
  background: #e5e7eb;
}
.badge.estado {
  background: #cdd6b3;
  color: #1f2937;
}
.error {
  color: #b91c1c;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
.ubicacion-form {
  margin-top: 0.5rem;
  padding-top: 0.4rem;
  border-top: 1px dashed #d1d5db;
}
.ubicacion-grid {
  display: grid;
  gap: 0.4rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin-top: 0.25rem;
}
.label {
  display: block;
  font-size: 0.75rem;
  color: #4b5563;
  margin-bottom: 0.1rem;
}

/* OCR UI */
.ocr-table-wrapper {
  margin-top: 0.25rem;
}
.ocr-table-header,
.ocr-table-row {
  display: grid;
  grid-template-columns: 0.9fr 2fr 0.8fr 1.8fr;
  gap: 0.4rem;
  align-items: flex-start;
}
.ocr-table-header {
  font-weight: 600;
  border-bottom: 1px solid #e5e7eb;
  padding-bottom: 0.2rem;
  margin-bottom: 0.2rem;
}
.ocr-table-row {
  padding: 0.15rem 0;
  border-bottom: 1px dashed #e5e7eb;
}
.ocr-table-row:last-child {
  border-bottom: none;
}
.ocr-fulltext-wrapper {
  margin-top: 0.25rem;
}
.ocr-fulltext-box {
  max-height: 220px;
  overflow: auto;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 0.4rem 0.5rem;
  background: #ffffff;
  white-space: pre-wrap;
  font-size: 0.8rem;
}

@media (max-width: 800px) {
  .ubicacion-grid {
    grid-template-columns: 1fr;
  }
  .ocr-table-header,
  .ocr-table-row {
    grid-template-columns: 1fr;
  }
}
</style>
