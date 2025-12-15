<script setup>
const props = defineProps({
  title: {
    type: String,
    required: true
  },
  icon: {
    type: String,
    required: true
  },
  count: {
    type: Number,
    default: 0
  },
  subtitle: {
    type: String,
    default: ''
  },
  subsecciones: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['click'])
</script>

<template>
  <div class="category-card" @click="$emit('click')">
    <div class="card-header">
      <div class="card-icon">
        {{ icon }}
      </div>
      <div class="card-title">
        <h3>{{ title }}</h3>
        <p v-if="subtitle" class="card-subtitle">{{ subtitle }}</p>
      </div>
    </div>
    
    <div class="card-body">
      <div class="card-stats">
        <div class="stat-item">
          <span class="stat-value">{{ count }}</span>
          <span class="stat-label">Documentos</span>
        </div>
        <div v-if="subsecciones !== null" class="stat-item">
          <span class="stat-value">{{ subsecciones }}</span>
          <span class="stat-label">Subsecciones</span>
        </div>
      </div>
    </div>
    
    <div class="card-footer">
      <span class="card-action">
        Ver documentos
        <span class="card-arrow">→</span>
      </span>
    </div>
  </div>
</template>

<style scoped>
.category-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-left: 6px solid #556b2f;
  border-radius: 12px;
  padding: 1.5rem;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  position: relative;
  overflow: hidden;
}

.category-card:hover {
  border-left-width: 8px;
  box-shadow: 
    0 4px 6px -1px rgba(85, 107, 47, 0.1),
    0 2px 4px -1px rgba(85, 107, 47, 0.06);
  transform: translateY(-2px);
}

.category-card::after {
  content: '';
  position: absolute;
  top: 0;
  right: 0;
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, transparent 50%, #f0f4e8 50%);
  opacity: 0;
  transition: opacity 0.3s;
}

.category-card:hover::after {
  opacity: 1;
}

.card-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.card-icon {
  width: 56px;
  height: 56px;
  background: linear-gradient(135deg, #556b2f 0%, #6b8e23 100%);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.75rem;
  color: white;
  box-shadow: 0 4px 6px rgba(85, 107, 47, 0.2);
  flex-shrink: 0;
}

.card-title {
  flex: 1;
  min-width: 0;
}

.card-title h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 0.25rem;
  letter-spacing: -0.01em;
}

.card-subtitle {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0;
}

.card-body {
  padding-left: 72px;
  margin-bottom: 1rem;
}

.card-stats {
  display: flex;
  gap: 2rem;
}

.stat-item {
  display: flex;
  flex-direction: column;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #556b2f;
  line-height: 1;
}

.stat-label {
  font-size: 0.75rem;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-top: 0.25rem;
}

.card-footer {
  padding-left: 72px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-action {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  color: #556b2f;
  font-size: 0.875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  transition: gap 0.3s;
}

.category-card:hover .card-action {
  gap: 0.75rem;
}

.card-arrow {
  font-size: 1.25rem;
  transition: transform 0.3s;
}

.category-card:hover .card-arrow {
  transform: translateX(4px);
}

@media (max-width: 640px) {
  .card-body {
    padding-left: 0;
    margin-top: 1rem;
  }
  
  .card-footer {
    padding-left: 0;
  }
}
</style>
