<template>
  <div class="search-filters">
    <div class="filter-header" @click="expanded = !expanded">
      <h3 class="filter-title">
        <span class="filter-icon">🔍</span>
        Búsqueda Avanzada
      </h3>
      <button class="toggle-btn" :class="{ 'expanded': expanded }">
        {{ expanded ? '▼' : '▶' }}
      </button>
    </div>
    
    <transition name="slide">
      <div v-show="expanded" class="filter-body">
        <div class="filter-grid">
          <!-- Filtro por Sección -->
          <div class="filter-field">
            <label>Sección</label>
            <select v-model="filters.seccion_id" @change="onSeccionChange">
              <option value="">Todas las secciones</option>
              <option v-for="sec in secciones" :key="sec.id" :value="sec.id">
                {{ sec.nombre }}
              </option>
            </select>
          </div>
          
          <!-- Filtro por Subsección (depende de Sección) -->
          <div class="filter-field">
            <label>Subsección</label>
            <select v-model="filters.subseccion_id" :disabled="!filters.seccion_id">
              <option value="">Todas las subsecciones</option>
              <option v-for="sub in subseccionesFiltradas" :key="sub.id" :value="sub.id">
                {{ sub.nombre }}
              </option>
            </select>
          </div>
          
          <!-- Filtro por Tipo de Documento -->
          <div class="filter-field">
            <label>Tipo de Documento</label>
            <select v-model="filters.tipo_documento_id">
              <option value="">Todos los tipos</option>
              <option v-for="tipo in tipos" :key="tipo.id" :value="tipo.id">
                {{ tipo.nombre }}
              </option>
            </select>
          </div>
          
          <!-- Filtro por Gestión (año) -->
          <div class="filter-field">
            <label>Gestión</label>
            <select v-model="filters.gestion_id">
              <option value="">Todas las gestiones</option>
              <option v-for="gest in gestiones" :key="gest.id" :value="gest.id">
                {{ gest.anio }}
              </option>
            </select>
          </div>
          
          <!-- Búsqueda general (título, descripción) -->
          <div class="filter-field filter-field-wide">
            <label>Búsqueda General</label>
            <input 
              type="text" 
              v-model="filters.q" 
              placeholder="Buscar en título, descripción, número..."
            />
          </div>
          
          <!-- Búsqueda OCR (texto extraído) -->
          <div class="filter-field filter-field-wide">
            <label>Búsqueda en Texto OCR</label>
            <input 
              type="text" 
              v-model="filters.ocr_text" 
              placeholder="Buscar en texto extraído del documento..."
            />
          </div>
          
          <!-- Rango de fechas -->
          <div class="filter-field">
            <label>Fecha Desde</label>
            <input type="date" v-model="filters.fecha_desde" />
          </div>
          
          <div class="filter-field">
            <label>Fecha Hasta</label>
            <input type="date" v-model="filters.fecha_hasta" />
          </div>
          
          <!-- Filtro de confidencialidad (solo para archivist) -->
          <div v-if="!isReader" class="filter-field">
            <label>Confidencialidad</label>
            <select v-model="filters.is_confidential">
              <option value="">Todos</option>
              <option value="0">No confidenciales</option>
              <option value="1">Confidenciales</option>
            </select>
          </div>
        </div>
        
        <div class="filter-actions">
          <button @click="applyFilters" class="btn btn-primary">
            🔎 Aplicar Filtros
          </button>
          <button @click="clearFilters" class="btn btn-secondary">
            ✖ Limpiar
          </button>
          <div class="filter-info">
            <span v-if="activeFiltersCount > 0">
              {{ activeFiltersCount }} filtro{{ activeFiltersCount > 1 ? 's' : '' }} activo{{ activeFiltersCount > 1 ? 's' : '' }}
            </span>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  catalogs: {
    type: Object,
    required: true
  },
  userRole: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['search'])

const expanded = ref(false)
const filters = ref({
  seccion_id: '',
  subseccion_id: '',
  tipo_documento_id: '',
  gestion_id: '',
  q: '',
  ocr_text: '',
  fecha_desde: '',
  fecha_hasta: '',
  is_confidential: ''
})

const isReader = computed(() => props.userRole === 'reader')

const secciones = computed(() => props.catalogs?.secciones || [])
const subsecciones = computed(() => props.catalogs?.subsecciones || [])
const tipos = computed(() => props.catalogs?.tipos_documento || [])
const gestiones = computed(() => props.catalogs?.gestiones || [])

// Filtrar subsecciones según la sección seleccionada
const subseccionesFiltradas = computed(() => {
  if (!filters.value.seccion_id) {
    return subsecciones.value
  }
  return subsecciones.value.filter(sub => sub.seccion_id === parseInt(filters.value.seccion_id))
})

function onSeccionChange() {
  // Si cambia la sección, reiniciar subsección si ya no es válida
  const seccionId = parseInt(filters.value.seccion_id)
  const subseccionId = parseInt(filters.value.subseccion_id)
  
  if (subseccionId) {
    const subseccionValida = subsecciones.value.find(
      sub => sub.id === subseccionId && sub.seccion_id === seccionId
    )
    
    if (!subseccionValida) {
      filters.value.subseccion_id = ''
    }
  }
}

const activeFiltersCount = computed(() => {
  let count = 0
  for (const key in filters.value) {
    if (filters.value[key] !== '') {
      count++
    }
  }
  return count
})

function applyFilters() {
  // Emitir filtros al componente padre
  const cleanFilters = {}
  for (const key in filters.value) {
    if (filters.value[key] !== '') {
      cleanFilters[key] = filters.value[key]
    }
  }
  emit('search', cleanFilters)
}

function clearFilters() {
  filters.value = {
    seccion_id: '',
    subseccion_id: '',
    tipo_documento_id: '',
    gestion_id: '',
    q: '',
    ocr_text: '',
    fecha_desde: '',
    fecha_hasta: '',
    is_confidential: ''
  }
  emit('search', {})
}

// Auto-aplicar cuando se teclea (debounced)
let searchTimeout = null
watch(() => [filters.value.q, filters.value.ocr_text], () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    applyFilters()
  }, 500) // 500ms debounce
})
</script>

<style scoped>
.search-filters {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  margin-bottom: 1.5rem;
  overflow: hidden;
}

.filter-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  background: linear-gradient(135deg, #556b2f 0%, #6b8e23 100%); /* Olive Gradient */
  color: white;
  cursor: pointer;
  user-select: none;
}

.filter-header:hover {
  background: linear-gradient(135deg, #4b5f2a 0%, #556b2f 100%);
}

.filter-title {
  font-size: 1.125rem;
  font-weight: 600;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.filter-icon {
  font-size: 1.25rem;
}

.toggle-btn {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: bold;
  transition: transform 0.3s;
}

.toggle-btn.expanded {
  transform: rotate(0deg);
}

.filter-body {
  padding: 1.5rem;
  background: #f9fafb;
}

.filter-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.25rem;
  margin-bottom: 1.5rem;
}

.filter-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.filter-field-wide {
  grid-column: span 2;
}

label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
}

input[type="text"],
input[type="date"],
select {
  padding: 0.625rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.938rem;
  background: white;
  transition: border-color 0.2s, box-shadow 0.2s;
}

input[type="text"]:focus,
input[type="date"]:focus,
select:focus {
  outline: none;
  border-color: #556b2f; /* Olive border */
  box-shadow: 0 0 0 3px rgba(85, 107, 47, 0.1);
}

select:disabled {
  background: #f3f4f6;
  color: #9ca3af;
  cursor: not-allowed;
}

.filter-actions {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  flex-wrap: wrap;
}

.btn {
  padding: 0.625rem 1.25rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.938rem;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-primary {
  background: linear-gradient(135deg, #556b2f 0%, #6b8e23 100%); /* Olive Gradient */
  color: white;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #4b5f2a 0%, #556b2f 100%);
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(85, 107, 47, 0.3);
}

.btn-secondary {
  background: white;
  color: #6b7280;
  border: 2px solid #e5e7eb;
}

.btn-secondary:hover {
  background: #f9fafb;
  border-color: #d1d5db;
}

.filter-info {
  margin-left: auto;
  font-size: 0.875rem;
  color: #6b7280;
  font-weight: 500;
}

.slide-enter-active,
.slide-leave-active {
  transition: all 0.3s ease;
  max-height: 1000px;
}

.slide-enter-from,
.slide-leave-to {
  max-height: 0;
  opacity: 0;
}

@media (max-width: 768px) {
  .filter-grid {
    grid-template-columns: 1fr;
  }
  
  .filter-field-wide {
    grid-column: span 1;
  }
  
  .filter-actions {
    flex-direction: column;
    align-items: stretch;
  }
  
  .btn {
    justify-content: center;
  }
  
  .filter-info {
    margin-left: 0;
    text-align: center;
  }
}
</style>
