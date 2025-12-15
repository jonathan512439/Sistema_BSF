<script setup>
import { ref, onMounted, computed } from 'vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  headers: {
    type: Object,
    required: true,
  },
})

const { success, error } = useToast()

// State
const ubicaciones = ref([])
const stats = ref({})
const loading = ref(false)
const showModal = ref(false)
const editingId = ref(null)

// Form
const form = ref({
 nombre: '',
  codigo: '',
  tipo: 'estante',
  ubicacion_padre_id: null,
  descripcion: '',
  capacidad_max: null,
})

const tiposUbicacion = [
  { value: 'deposito', label: 'Depósito' },
  { value: 'almacen', label: 'Almacén' },
  { value: 'estante', label: 'Estante' },
  { value: 'caja', label: 'Caja' },
  { value: 'carpeta', label: 'Carpeta' },
]

// Computed
const isEditing = computed(() => editingId.value !== null)
const modalTitle = computed(() => isEditing.value ? 'Editar Ubicación' : 'Nueva Ubicación')

const ubicacionesDisponibles = computed(() => {
  // No mostrar la ubicación actual en la lista de padres (para evitar ciclos)
  return ubicaciones.value.filter(u => u.id !== editingId.value)
})

// Methods
async function fetchUbicaciones() {
  loading.value = true
  
  try {
    const response = await fetch('/api/ubicaciones?activas=1', {
      headers: props.headers,
    })
    
    if (!response.ok) throw new Error('Error al cargar ubicaciones')
    
    const data = await response.json()
    ubicaciones.value = data.ubicaciones || []
  } catch (err) {
    error('Error', err.message)
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const response = await fetch('/api/ubicaciones/stats', {
      headers: props.headers,
    })
    
    if (!response.ok) throw new Error('Error al cargar estadísticas')
    
    stats.value = await response.json()
  } catch (err) {
    console.error('Error loading stats:', err)
  }
}

function openModal(ubicacion = null) {
  if (ubicacion) {
    editingId.value = ubicacion.id
    form.value = {
      nombre: ubicacion.nombre,
      codigo: ubicacion.codigo,
      tipo: ubicacion.tipo,
      ubicacion_padre_id: ubicacion.ubicacion_padre_id,
      descripcion: ubicacion.descripcion || '',
      capacidad_max: ubicacion.capacidad_max,
    }
  } else {
    resetForm()
  }
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  resetForm()
}

function resetForm() {
  editingId.value = null
  form.value = {
    nombre: '',
    codigo: '',
    tipo: 'estante',
    ubicacion_padre_id: null,
    descripcion: '',
    capacidad_max: null,
  }
}

async function saveUbicacion() {
  const url = isEditing.value
    ? `/api/ubicaciones/${editingId.value}`
    : '/api/ubicaciones'
  
  const method = isEditing.value ? 'PUT' : 'POST'
  
  try {
    const response = await fetch(url, {
      method,
      headers: {
        ...props.headers,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(form.value),
    })
    
    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.error || data.message || 'Error al guardar')
    }
    
    const data = await response.json()
    
    success(
      isEditing.value ? 'Ubicación actualizada' : 'Ubicación creada',
      data.message
    )
    
    closeModal()
    await fetchUbicaciones()
    await fetchStats()
  } catch (err) {
    error('Error', err.message)
  }
}

async function deleteUbicacion(id, nombre) {
  if (!confirm(`¿Desactivar la ubicación "${nombre}"?`)) return
  
  try {
    const response = await fetch(`/api/ubicaciones/${id}`, {
      method: 'DELETE',
      headers: props.headers,
    })
    
    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.error || 'Error al desactivar')
    }
    
    success('Ubicación desactivada', 'La ubicación ha sido desactivada exitosamente')
    
    await fetchUbicaciones()
    await fetchStats()
  } catch (err) {
    error('Error', err.message)
  }
}

function getTipoLabel(tipo) {
  const item = tiposUbicacion.find(t => t.value === tipo)
  return item ? item.label : tipo
}

function getPadreNombre(ubicacionPadreId) {
  if (!ubicacionPadreId) return '-'
  const padre = ubicaciones.value.find(u => u.id === ubicacionPadreId)
  return padre ? padre.nombre : '-'
}

function getTipoIcon(tipo) {
  const icons = {
    deposito: '🏢',
    almacen: '📦',
    estante: '📚',
    caja: '📁',
    carpeta: '📄',
  }
  return icons[tipo] || '📍'
}

function getOcupacionClass(porcentaje) {
  if (porcentaje >= 90) return 'ocupacion-critica'
  if (porcentaje >= 70) return 'ocupacion-alta'
  if (porcentaje >= 50) return 'ocupacion-media'
  return 'ocupacion-baja'
}

// Lifecycle
onMounted(() => {
  fetchUbicaciones()
  fetchStats()
})
</script>

<template>
  <div class="ubicaciones-view">
    <div class="header">
      <div>
        <h2 class="title">Ubicaciones Físicas</h2>
        <p class="subtitle">Gestión de almacenes, estantes y ubicaciones de documentos</p>
      </div>
      
      <button @click="openModal()" class="btn btn-primary">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
        </svg>
        Nueva Ubicación
      </button>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon blue">
          <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
            <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h11A1.5 1.5 0 0 1 15 2.5v11a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 1 13.5v-11zM2.5 2a.5.5 0 0 0-.5.5v11a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 .5-.5v-11a.5.5 0 0 0-.5-.5h-11z"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.total_ubicaciones || 0 }}</div>
          <div class="stat-label">Total Ubicaciones</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon green">
          <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
            <path d="M10.854 8.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 0 1 .708-.708L7.5 10.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.activas || 0 }}</div>
          <div class="stat-label">Activas</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon yellow">
          <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.inactivas || 0 }}</div>
          <div class="stat-label">Inactivas</div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="table-container">
      <div v-if="loading" class="loading">
        Cargando ubicaciones...
      </div>

      <div v-else-if="ubicaciones.length === 0" class="empty-state">
        <svg width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
          <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h11A1.5 1.5 0 0 1 15 2.5v11a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 1 13.5v-11z"/>
        </svg>
        <p>No hay ubicaciones físicas registradas</p>
        <button @click="openModal()" class="btn btn-primary">
          Crear Primera Ubicación
        </button>
      </div>

      <table v-else class="table">
        <thead>
          <tr>
            <th>Tipo</th>
            <th>Nombre</th>
            <th>Código</th>
            <th>Ubicación Padre</th>
            <th>Capacidad</th>
            <th>Ocupación</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="ubicacion in ubicaciones" :key="ubicacion.id">
            <td>
              <span class="tipo-badge">
                {{ getTipoIcon(ubicacion.tipo) }}
                {{ getTipoLabel(ubicacion.tipo) }}
              </span>
            </td>
            <td><strong>{{ ubicacion.nombre }}</strong></td>
            <td><code class="codigo">{{ ubicacion.codigo }}</code></td>
            <td>{{ getPadreNombre(ubicacion.ubicacion_padre_id) }}</td>
            <td>
              <span v-if="ubicacion.capacidad_max">
                {{ ubicacion.capacidad_max }} docs
              </span>
              <span v-else class="text-muted">Sin límite</span>
            </td>
            <td>
              <div v-if="ubicacion.ocupacion" class="ocupacion">
                <div class="ocupacion-bar">
                  <div 
                    class="ocupacion-fill"
                    :class="getOcupacionClass(ubicacion.ocupacion.porcentaje)"
                    :style="{ width: ubicacion.ocupacion.porcentaje + '%' }"
                  ></div>
                </div>
                <span class="ocupacion-text">
                  {{ ubicacion.ocupacion.actual }}/{{ ubicacion.ocupacion.maxima }}
                  ({{ ubicacion.ocupacion.porcentaje }}%)
                </span>
              </div>
              <span v-else class="text-muted">-</span>
            </td>
            <td>
              <div class="btn-group">
                <button 
                  @click="openModal(ubicacion)" 
                  class="btn-icon" 
                  title="Editar"
                >
                  <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                  </svg>
                </button>
                <button 
                  @click="deleteUbicacion(ubicacion.id, ubicacion.nombre)" 
                  class="btn-icon btn-danger" 
                  title="Desactivar"
                >
                  <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                    <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-overlay" @click="closeModal">
      <div class="modal" @click.stop>
        <div class="modal-header">
          <h3>{{ modalTitle }}</h3>
          <button @click="closeModal" class="btn-close">&times;</button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label for="nombre">Nombre *</label>
            <input 
              id="nombre"
              v-model="form.nombre" 
              type="text" 
              required
              placeholder="Ej: Estante Principal A"
              class="form-control"
            />
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="codigo">Código *</label>
              <input 
                id="codigo"
                v-model="form.codigo" 
                type="text" 
                required
                placeholder="Ej: EST-A1"
                class="form-control"
              />
            </div>

            <div class="form-group">
              <label for="tipo">Tipo *</label>
              <select 
                id="tipo"
                v-model="form.tipo" 
                class="form-control"
              >
                <option 
                  v-for="tipo in tiposUbicacion" 
                  :key="tipo.value" 
                  :value="tipo.value"
                >
                  {{ tipo.label }}
                </option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="ubicacion_padre_id">Ubicación Padre</label>
              <select 
                id="ubicacion_padre_id"
                v-model="form.ubicacion_padre_id" 
                class="form-control"
              >
                <option :value="null">Sin padre (raíz)</option>
                <option 
                  v-for="ubicacion in ubicacionesDisponibles" 
                  :key="ubicacion.id" 
                  :value="ubicacion.id"
                >
                  {{ ubicacion.nombre }} ({{ ubicacion.codigo }})
                </option>
              </select>
            </div>

            <div class="form-group">
              <label for="capacidad_max">Capacidad Máxima</label>
              <input 
                id="capacidad_max"
                v-model.number="form.capacidad_max" 
                type="number" 
                min="0"
                placeholder="0 = sin límite"
                class="form-control"
              />
            </div>
          </div>

          <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea 
              id="descripcion"
              v-model="form.descripcion" 
              rows="3"
              placeholder="Descripción opcional..."
              class="form-control"
            ></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button @click="closeModal" class="btn btn-secondary">
            Cancelar
          </button>
          <button @click="saveUbicacion" class="btn btn-primary">
            {{ isEditing ? 'Actualizar' : 'Crear' }} Ubicación
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ubicaciones-view {
  max-width: 1400px;
  margin: 0 auto;
  padding: 1.5rem;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.title {
  margin: 0 0 0.25rem;
  font-size: 1.5rem;
  font-weight: 600;
  color: #111827;
}

.subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: #6B7280;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.stat-card {
  background: #fff;
  border: 1px solid #E5E7EB;
  border-radius: 8px;
  padding: 1rem;
  display: flex;
  gap: 1rem;
  align-items: center;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-icon.blue { background: #DBEAFE; color: #1E40AF; }
.stat-icon.green { background: #D1FAE5; color: #047857; }
.stat-icon.yellow { background: #FEF3C7; color: #B45309; }

.stat-value {
  font-size: 1.875rem;
  font-weight: 700;
  color: #111827;
  line-height: 1;
}

.stat-label {
  font-size: 0.875rem;
  color: #6B7280;
  margin-top: 0.25rem;
}

.table-container {
  background: #fff;
  border: 1px solid #E5E7EB;
  border-radius: 8px;
  overflow: hidden;
}

.table {
  width: 100%;
  border-collapse: collapse;
}

.table th {
  background: #F9FAFB;
  padding: 0.75rem 1rem;
  text-align: left;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  color: #6B7280;
  border-bottom: 1px solid #E5E7EB;
}

.table td {
  padding: 1rem;
  border-bottom: 1px solid #E5E7EB;
  font-size: 0.875rem;
  color: #374151;
}

.table tbody tr:hover {
  background: #F9FAFB;
}

.tipo-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.75rem;
  background: #F3F4F6;
  border-radius: 6px;
  font-size: 0.8125rem;
  font-weight: 500;
}

.codigo {
  background: #FEF3C7;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  color: #92400E;
  font-family: 'Courier New', monospace;
}

.ocupacion {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.ocupacion-bar {
  flex: 1;
  height: 8px;
  background: #E5E7EB;
  border-radius: 4px;
  overflow: hidden;
}

.ocupacion-fill {
  height: 100%;
  transition: width 0.3s;
}

.ocupacion-fill.ocupacion-baja { background: #10B981; }
.ocupacion-fill.ocupacion-media { background: #F59E0B; }
.ocupacion-fill.ocupacion-alta { background: #F97316; }
.ocupacion-fill.ocupacion-critica { background: #EF4444; }

.ocupacion-text {
  font-size: 0.75rem;
  color: #6B7280;
  white-space: nowrap;
}

.text-muted {
  color: #9CA3AF;
}

.btn-group {
  display: flex;
  gap: 0.5rem;
}

.btn-icon {
  padding: 0.5rem;
  border: 1px solid #D1D5DB;
  border-radius: 6px;
  background: #fff;
  color: #374151;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-icon:hover {
  background: #F9FAFB;
  border-color: #9CA3AF;
}

.btn-icon.btn-danger {
  color: #DC2626;
  border-color: #FCA5A5;
}

.btn-icon.btn-danger:hover {
  background: #FEE2E2;
  border-color: #DC2626;
}

.loading,
.empty-state {
  padding: 3rem;
  text-align: center;
  color: #6B7280;
}

.empty-state svg {
  opacity: 0.5;
  margin-bottom: 1rem;
}

.empty-state p {
  margin: 0 0 1rem;
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-primary {
  background: #2563EB;
  color: #fff;
}

.btn-primary:hover {
  background: #1D4ED8;
}

.btn-secondary {
  background: #F3F4F6;
  color: #374151;
}

.btn-secondary:hover {
  background: #E5E7EB;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.modal {
  background: #fff;
  border-radius: 12px;
  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-header {
  padding: 1.5rem;
  border-bottom: 1px solid #E5E7EB;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
}

.btn-close {
  background: none;
  border: none;
  font-size: 2rem;
  line-height: 1;
  color: #9CA3AF;
  cursor: pointer;
  padding: 0;
  width: 32px;
  height: 32px;
}

.btn-close:hover {
  color: #374151;
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid #E5E7EB;
  display: flex;
  gap: 0.75rem;
  justify-content: flex-end;
}

.form-group {
  margin-bottom: 1.25rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
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
}
</style>
