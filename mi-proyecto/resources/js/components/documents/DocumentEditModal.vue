<script setup>
import { ref, watch } from 'vue'
import { useToast } from '@/composables/useToast'
import BaseModal from '../ui/BaseModal.vue'

const props = defineProps({
  documento: {
    type: Object,
    required: true,
  },
  catalogs: {
    type: Object,
    required: true,
  },
  headers: {
    type: Object,
    required: true,
  },
  open: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['close', 'success'])

const { success, error: showError } = useToast()

// Form state
const form = ref({
  titulo: '',
  numero_documento: '',
  tipo_documento_id: null,
  seccion_id: null,
  subseccion_id: null,
  gestion_id: null,
  is_confidential: false,
  descripcion: '',
})

const saving = ref(false)

// Watch for documento changes to populate form
watch(() => props.documento, (newDoc) => {
  if (newDoc) {
    form.value = {
      titulo: newDoc.titulo || '',
      numero_documento: newDoc.numero_documento || '',
      tipo_documento_id: newDoc.tipo_documento_id || null,
      seccion_id: newDoc.seccion_id || null,
      subseccion_id: newDoc.subseccion_id || null,
      gestion_id: newDoc.gestion_id || null,
      is_confidential: newDoc.is_confidential || false,
      descripcion: newDoc.descripcion || '',
    }
  }
}, { immediate: true })

async function handleSave() {
  saving.value = true
  
  try {
    const response = await fetch(`/api/documentos/${props.documento.id}`, {
      method: 'PUT',
      headers: {
        ...props.headers,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(form.value),
    })
    
    const data = await response.json()
    
    if (!response.ok || !data.ok) {
      throw new Error(data.message || data.error || 'Error al guardar cambios')
    }
    
    success('Documento actualizado', 'Los cambios se guardaron correctamente')
    emit('success', data.documento)
    emit('close')
    
  } catch (err) {
    showError('Error al guardar', err.message)
  } finally {
    saving.value = false
  }
}

function handleClose() {
  emit('close')
}
</script>

<template>
  <BaseModal
    :open="open"
    :title="`Editar Documento #${documento?.id}`"
    size="large"
    @close="handleClose"
  >
    <form @submit.prevent="handleSave">
      <!-- Título -->
      <div class="form-group">
        <label for="titulo" class="form-label">
          Título <span class="required">*</span>
        </label>
        <input
          id="titulo"
          v-model="form.titulo"
          type="text"
          class="form-control"
          placeholder="Título del documento"
          required
        />
      </div>

      <!-- Código / Número -->
      <div class="form-group">
        <label for="numero_documento" class="form-label">
          Código / Número
        </label>
        <input
          id="numero_documento"
          v-model="form.numero_documento"
          type="text"
          class="form-control"
          placeholder="Ej: RES-2024-001"
        />
      </div>

      <!-- Row: Tipo y Gestión -->
      <div class="form-row">
        <div class="form-group">
          <label for="tipo_documento_id" class="form-label">
            Tipo de Documento <span class="required">*</span>
          </label>
          <select
            id="tipo_documento_id"
            v-model="form.tipo_documento_id"
            class="form-control"
            required
          >
            <option :value="null">Seleccionar tipo...</option>
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
          <label for="gestion_id" class="form-label">
            Gestión (Año) <span class="required">*</span>
          </label>
          <select
            id="gestion_id"
            v-model="form.gestion_id"
            class="form-control"
            required
          >
            <option :value="null">Seleccionar gestión...</option>
            <option
              v-for="gestion in catalogs.gestiones"
              :key="gestion.id"
              :value="gestion.id"
            >
              {{ gestion.anio }}
            </option>
          </select>
        </div>
      </div>

      <!-- Row: Sección y Subsección -->
      <div class="form-row">
        <div class="form-group">
          <label for="seccion_id" class="form-label">
            Sección <span class="required">*</span>
          </label>
          <select
            id="seccion_id"
            v-model="form.seccion_id"
            class="form-control"
            required
          >
            <option :value="null">Seleccionar sección...</option>
            <option
              v-for="seccion in catalogs.secciones"
              :key="seccion.id"
              :value="seccion.id"
            >
              {{ seccion.nombre }}
            </option>
          </select>
        </div>

        <div class="form-group">
          <label for="subseccion_id" class="form-label">
            Subsección
          </label>
          <select
            id="subseccion_id"
            v-model="form.subseccion_id"
            class="form-control"
          >
            <option :value="null">Sin subsección</option>
            <option
              v-for="subseccion in catalogs.subsecciones"
              :key="subseccion.id"
              :value="subseccion.id"
            >
              {{ subseccion.nombre }}
            </option>
          </select>
        </div>
      </div>

      <!-- Descripción -->
      <div class="form-group">
        <label for="descripcion" class="form-label">
          Descripción
        </label>
        <textarea
          id="descripcion"
          v-model="form.descripcion"
          class="form-control"
          rows="4"
          placeholder="Descripción opcional del documento..."
        ></textarea>
      </div>

      <!-- Confidencialidad -->
      <div class="form-group">
        <label class="checkbox-label">
          <input
            v-model="form.is_confidential"
            type="checkbox"
            class="form-checkbox"
          />
          <span>Documento confidencial</span>
        </label>
        <p class="help-text">
          Los documentos confidenciales solo son visibles para archivistas
        </p>
      </div>

      <!-- Important warnings box -->
      <div class="warning-box">
        <div class="warning-header">
          <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
            <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
          </svg>
          <strong>Notas Importantes</strong>
        </div>
        <ul class="warning-list">
          <li>
            <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
              <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
              <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533L8.93 6.588zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
            </svg>
            <span>Los cambios quedan <strong>registrados en el historial</strong> de auditoría del documento</span>
          </li>
          <li>
            <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
              <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
            </svg>
            <span><strong>No se puede editar</strong> documentos sellados o en custodia</span>
          </li>
          <li>
            <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
              <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"/>
              <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3zM3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.1z"/>
            </svg>
            <span>Los cambios son <strong>irreversibles</strong> (sin función de deshacer)</span>
          </li>
        </ul>
      </div>

      <!-- Footer con botones -->
      <div class="modal-footer">
        <button
          type="button"
          @click="handleClose"
          class="btn btn-secondary"
          :disabled="saving"
        >
          Cancelar
        </button>
        <button
          type="submit"
          class="btn btn-primary"
          :disabled="saving"
        >
          <svg v-if="saving" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="spin">
            <path d="M8 0c-4.418 0-8 3.582-8 8s3.582 8 8 8 8-3.582 8-8-3.582-8-8-8zm0 14c-3.309 0-6-2.691-6-6s2.691-6 6-6 6 2.691 6 6-2.691 6-6 6z" opacity="0.3"/>
            <path d="M8 2c3.309 0 6 2.691 6 6h2c0-4.418-3.582-8-8-8v2z"/>
          </svg>
          <svg v-else width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
            <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425a.247.247 0 0 1 .02-.022Z"/>
          </svg>
          {{ saving ? 'Guardando...' : 'Guardar Cambios' }}
        </button>
      </div>
    </form>
  </BaseModal>
</template>

<style scoped>
.form-group {
  margin-bottom: 1.25rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-label {
  display: block;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
}

.required {
  color: #DC2626;
}

.form-control {
  width: 100%;
  padding: 0.75rem;
  font-size: 0.9375rem;
  border: 2px solid #E5E7EB;
  border-radius: 6px;
  transition: all 0.2s;
  font-family: inherit;
}

.form-control:focus {
  outline: none;
  border-color: #2563EB;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

textarea.form-control {
  resize: vertical;
  min-height: 100px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 0.9375rem;
  color: #374151;
}

.form-checkbox {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.help-text {
  margin: 0.5rem 0 0;
  font-size: 0.8125rem;
  color: #6B7280;
}

.info-box {
  display: flex;
  gap: 0.75rem;
  padding: 1rem;
  background: #EFF6FF;
  border: 1px solid #BFDBFE;
  border-radius: 6px;
  font-size: 0.875rem;
  color: #1E40AF;
  margin-bottom: 1.5rem;
}

.info-box svg {
  flex-shrink: 0;
  margin-top: 0.125rem;
}

.info-box strong {
  font-weight: 600;
}

.warning-box {
  padding: 1rem;
  background: #FEF3C7;
  border: 2px solid #F59E0B;
  border-radius: 8px;
  margin-bottom: 1.5rem;
}

.warning-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #92400E;
  margin-bottom: 0.75rem;
  font-size: 0.9375rem;
}

.warning-header svg {
  flex-shrink: 0;
}

.warning-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.warning-list li {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  color: #78350F;
  font-size: 0.875rem;
  line-height: 1.5;
}

.warning-list li svg {
  flex-shrink: 0;
  margin-top: 0.125rem;
  opacity: 0.7;
}

.warning-list li strong {
  font-weight: 600;
  color: #92400E;
}

.modal-footer {
  display: flex;
  gap: 0.75rem;
  justify-content: flex-end;
  padding-top: 1.5rem;
  border-top: 1px solid #E5E7EB;
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
  background: #F3F4F6;
  color: #374151;
}

.btn-secondary:hover:not(:disabled) {
  background: #E5E7EB;
}

.btn-primary {
  background: #2563EB;
  color: #fff;
}

.btn-primary:hover:not(:disabled) {
  background: #1D4ED8;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
