<script setup>
import { ref, onMounted } from 'vue'

const props = defineProps({
  headers: {
    type: Object,
    required: true
  }
})

const stats = ref({
  total_documentos: 0,
  validados: 0,
  sellados: 0,
  recientes_7_dias: 0
})

const loading = ref(true)

onMounted(async () => {
  await loadStats()
})

async function loadStats() {
  try {
    const response = await fetch('/api/categories/stats', {
      headers: props.headers
    })
    
    const data = await response.json()
    
    if (data.ok) {
      stats.value = data.stats
    }
  } catch (error) {
    console.error('Error loading stats:', error)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
        </svg>
      </div>
      <div class="stat-content">
        <p class="stat-number">{{ loading ? '...' : stats.total_documentos }}</p>
        <p class="stat-label">Total Documentos</p>
      </div>
    </div>
    
    <div class="stat-card">
      <div class="stat-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
          <polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
      </div>
      <div class="stat-content">
        <p class="stat-number">{{ loading ? '...' : stats.validados }}</p>
        <p class="stat-label">Validados</p>
      </div>
    </div>
    
    <div class="stat-card">
      <div class="stat-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
      </div>
      <div class="stat-content">
        <p class="stat-number">{{ loading ? '...' : stats.sellados }}</p>
        <p class="stat-label">Sellados</p>
      </div>
    </div>
    
    <div class="stat-card">
      <div class="stat-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <polyline points="12 6 12 12 16 14"/>
        </svg>
      </div>
      <div class="stat-content">
        <p class="stat-number">{{ loading ? '...' : stats.recientes_7_dias }}</p>
        <p class="stat-label">Últimos 7 días</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: all 0.2s;
}

.stat-card:hover {
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  width: 56px;
  height: 56px;
  background: linear-gradient(135deg, #556b2f 0%, #6b8e23 100%);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.75rem;
  font-weight: 700;
  color: white;
  flex-shrink: 0;
}

.stat-icon svg {
  color: white;
  stroke: white;
}

.stat-content {
  flex: 1;
  min-width: 0;
}

.stat-number {
  font-size: 1.75rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0;
  line-height: 1;
}

.stat-label {
  font-size: 0.813rem;
  color: #6b7280;
  margin: 0.25rem 0 0;
}

@media (max-width: 1024px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
