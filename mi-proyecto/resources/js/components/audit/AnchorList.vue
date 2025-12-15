<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  headers: {
    type: Object,
    required: true,
  },
})

const { success, error } = useToast()

// State
const anchors = ref([])
const loading = ref(false)
const pagination = ref({
  total: 0,
  currentPage: 1,
  perPage: 20,
  lastPage: 1,
})

const stats = ref({
  total_anclas: 0,
  firmadas: 0,
  publicadas: 0,
  pendientes_firma: 0,
  auto_enabled: false,
  block_size: 1000,
  last_anchor_id: 0,
  next_range: null,
})

// Filters
const filters = ref({
  firmadas: false,
  pendientes_firma: false,
  publicadas: false,
})

// Computed
const hasAnchors = computed(() => anchors.value.length > 0)
const hasPendingSignatures = computed(() => stats.value.pendientes_firma > 0)
const canCreateAnchor = computed(() => stats.value.next_range !== null)

// Methods
async function fetchAnchors(page = 1) {
  loading.value = true
  
  try {
    const params = new URLSearchParams({
      page: page.toString(),
      per_page: pagination.value.perPage.toString(),
    })
    
    if (filters.value.firmadas) params.append('firmadas', '1')
    if (filters.value.pendientes_firma) params.append('pendientes_firma', '1')
    if (filters.value.publicadas) params.append('publicadas', '1')
    
    const response = await fetch(`/api/anchors?${params}`, {
      headers: props.headers,
    })
    
    if (!response.ok) throw new Error('Error al cargar anclas')
    
    const data = await response.json()
    anchors.value = data.anclas || []
    
    if (data.pagination) {
      pagination.value = {
        total: data.pagination.total,
        currentPage: data.pagination.current_page,
        perPage: data.pagination.per_page,
        lastPage: data.pagination.last_page,
      }
    }
  } catch (err) {
    error('Error', err.message)
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const response = await fetch('/api/anchors/stats', {
      headers: props.headers,
    })
    
    if (!response.ok) throw new Error('Error al cargar estadísticas')
    
    stats.value = await response.json()
  } catch (err) {
    console.error('Error loading stats:', err)
  }
}

async function createAnchor() {
  if (!confirm('¿Crear un nuevo ancla blockchain?')) return
  
  loading.value = true
  
  try {
    const response = await fetch('/api/anchors/create', {
      method: 'POST',
      headers: {
        ...props.headers,
        'Content-Type': 'application/json',
      },
    })
    
    if (!response.ok) throw new Error('Error al crear ancla')
    
    const data = await response.json()
    success('Ancla creada', `Ancla #${data.ancla_id} creada exitosamente`)
    
    await fetchAnchors()
    await fetchStats()
  } catch (err) {
    error('Error', err.message)
  } finally {
    loading.value = false
  }
}

function handleFilterChange() {
  fetchAnchors(1)
}

function handlePageChange(page) {
  fetchAnchors(page)
}

function formatDate(dateString) {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return date.toLocaleString('es-BO', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatHash(hash) {
  if (!hash) return '-'
  return hash.substring(0, 16) + '...'
}

// Lifecycle
onMounted(() => {
  fetchAnchors()
  fetchStats()
})
</script>

<template>
  <div class="anchor-list">
    <!-- Header con estadísticas -->
    <div class="header">
      <div>
        <h2 class="title">Anclas Blockchain</h2>
        <p class="subtitle">Sistema de integridad del ledger de auditoría</p>
      </div>
      
      <button 
        v-if="canCreateAnchor"
        @click="createAnchor"
        :disabled="loading"
        class="btn btn-primary"
      >
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
        </svg>
        Crear Ancla Manual
      </button>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon blue">
          <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
            <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.total_anclas }}</div>
          <div class="stat-label">Total Anclas</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon green">
          <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
            <path d="M10.854 8.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 0 1 .708-.708L7.5 10.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.firmadas }}</div>
          <div class="stat-label">Firmadas</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon yellow">
          <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
            <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.pendientes_firma }}</div>
          <div class="stat-label">Pendientes Firma</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon purple">
          <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
            <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zm8 0A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm-8 8A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm8 0A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3z"/>
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.block_size }}</div>
          <div class="stat-label">Tamaño Bloque</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters">
      <label class="filter-option">
        <input 
          type="checkbox" 
          v-model="filters.firmadas"
          @change="handleFilterChange"
        />
        <span>Solo firmadas</span>
      </label>

      <label class="filter-option">
        <input 
          type="checkbox" 
          v-model="filters.pendientes_firma"
          @change="handleFilterChange"
        />
        <span>Pendientes de firma</span>
      </label>

      <label class="filter-option">
        <input 
          type="checkbox" 
          v-model="filters.publicadas"
          @change="handleFilterChange"
        />
        <span>Publicadas en blockchain</span>
      </label>
    </div>

    <!-- Table -->
    <div class="table-container">
      <div v-if="loading" class="loading">
        Cargando anclas...
      </div>

      <div v-else-if="!hasAnchors" class="empty-state">
        <svg width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
        </svg>
        <p>No hay anclas blockchain registradas</p>
        <button @click="createAnchor" class="btn btn-primary">
          Crear Primera Ancla
        </button>
      </div>

      <table v-else class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Rango</th>
            <th>Registros</th>
            <th>Hash Raíz</th>
            <th>Estado</th>
            <th>Creada</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="anchor in anchors" :key="anchor.id">
            <td><strong>#{{ anchor.id }}</strong></td>
            <td>
              <code class="range">{{ anchor.desde_id }} - {{ anchor.hasta_id }}</code>
            </td>
            <td>{{ anchor.hasta_id - anchor.desde_id + 1 }} registros</td>
            <td>
              <code class="hash" :title="anchor.hash_raiz">
                {{ formatHash(anchor.hash_raiz) }}
              </code>
            </td>
            <td>
              <div class="status">
                <span v-if="anchor.firmado_por" class="badge badge-success">
                  ✓ Firmada
                </span>
                <span v-else class="badge badge-warning">
                  Pendiente Firma
                </span>
                
                <span v-if="anchor.publicado_en" class="badge badge-info">
                  🌐 Publicada
                </span>
              </div>
            </td>
            <td>{{ formatDate(anchor.created_at) }}</td>
            <td>
              <router-link 
                :to="`/anchors/${anchor.id}`"
                class="btn-link"
              >
                Ver Detalles
              </router-link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.lastPage > 1" class="pagination">
      <button 
        @click="handlePageChange(pagination.currentPage - 1)"
        :disabled="pagination.currentPage === 1 || loading"
        class="btn btn-sm"
      >
        Anterior
      </button>

      <span class="pagination-info">
        Página {{ pagination.currentPage }} de {{ pagination.lastPage }}
      </span>

      <button 
        @click="handlePageChange(pagination.currentPage + 1)"
        :disabled="pagination.currentPage === pagination.lastPage || loading"
        class="btn btn-sm"
      >
        Siguiente
      </button>
    </div>
  </div>
</template>

<style scoped>
.anchor-list {
  max-width: 1400px;
  margin: 0 auto;
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

.stat-icon.blue{ background: #DBEAFE; color: #1E40AF; }
.stat-icon.green { background: #D1FAE5; color: #047857; }
.stat-icon.yellow { background: #FEF3C7; color: #B45309; }
.stat-icon.purple { background: #EDE9FE; color: #6D28D9; }

.stat-content {
  flex: 1;
}

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

.filters {
  display: flex;
  gap: 1.5rem;
  margin-bottom: 1rem;
  padding: 1rem;
  background: #F9FAFB;
  border-radius: 8px;
}

.filter-option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 0.875rem;
  color: #374151;
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

.range {
  background: #F3F4F6;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.8125rem;
  color: #1F2937;
}

.hash {
  background: #FEF3C7;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  color: #92400E;
  font-family: 'Courier New', monospace;
}

.status {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.25rem 0.625rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
}

.badge-success {
  background: #D1FAE5;
  color: #065F46;
}

.badge-warning {
  background: #FEF3C7;
  color: #92400E;
}

.badge-info {
  background: #DBEAFE;
  color: #1E40AF;
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
  font-size: 1rem;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  margin-top: 1.5rem;
}

.pagination-info {
  font-size: 0.875rem;
  color: #6B7280;
}

.btn {
  padding: 0.5rem 1rem;
  border: 1px solid #D1D5DB;
  border-radius: 6px;
  background: #fff;
  color: #374151;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.btn:hover:not(:disabled) {
  background: #F9FAFB;
  border-color: #9CA3AF;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: #2563EB;
  border-color: #2563EB;
  color: #fff;
}

.btn-primary:hover:not(:disabled) {
  background: #1D4ED8;
  border-color: #1D4ED8;
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
}

.btn-link {
  color: #2563EB;
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
}

.btn-link:hover {
  text-decoration: underline;
}
</style>
