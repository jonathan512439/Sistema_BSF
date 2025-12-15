<script setup>
const props = defineProps({
  image: {
    type: Object,
    required: true
  },
  index: {
    type: Number,
    required: true
  },
  total: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['remove', 'rotate', 'move-up', 'move-down'])

function formatSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}
</script>

<template>
  <div class="image-preview-card">
    <div class="card-header">
      <span class="page-number">Pág. {{ index + 1 }}</span>
      <button @click="$emit('remove')" class="btn-remove" title="Eliminar">
        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
          <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8 2.146 2.854Z"/>
        </svg>
      </button>
    </div>
    
    <div class="image-container" :style="{ transform: `rotate(${image.rotation}deg)` }">
      <img :src="image.preview" :alt="image.name" />
    </div>
    
    <div class="card-info">
      <p class="image-name" :title="image.name">{{ image.name }}</p>
      <p class="image-size">{{ formatSize(image.size) }}</p>
    </div>
    
    <div class="card-actions">
      <button 
        @click="$emit('move-up')" 
        class="btn-action" 
        :disabled="index === 0"
        title="Mover arriba"
      >
        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
          <path fill-rule="evenodd" d="M8 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L7.5 2.707V14.5a.5.5 0 0 0 .5.5z"/>
        </svg>
      </button>
      
      <button 
        @click="$emit('rotate')" 
        class="btn-action"
        title="Rotar 90°"
      >
        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
          <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
          <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
        </svg>
      </button>
      
      <button 
        @click="$emit('move-down')" 
        class="btn-action"
        :disabled="index === total - 1"
        title="Mover abajo"
      >
        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
          <path fill-rule="evenodd" d="M8 1a.5.5 0 0 1 .5.5v11.793l3.146-3.147a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 .708-.708L7.5 13.293V1.5A.5.5 0 0 1 8 1z"/>
        </svg>
      </button>
    </div>
  </div>
</template>

<style scoped>
.image-preview-card {
  background: white;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  transition: all 0.2s;
}

.image-preview-card:hover {
  border-color: #cbd5e1;
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0.75rem;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.page-number {
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
}

.btn-remove {
  padding: 0.25rem;
  background: transparent;
  border: none;
  color: #ef4444;
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-remove:hover {
  background: #fee2e2;
}

.image-container {
  aspect-ratio: 1;
  overflow: hidden;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.3s;
}

.image-container img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.card-info {
  padding: 0.75rem;
  border-top: 1px solid #e2e8f0;
}

.image-name {
  margin: 0 0 0.25rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.image-size {
  margin: 0;
  font-size: 0.75rem;
  color: #64748b;
}

.card-actions {
  display: flex;
  gap: 0.25rem;
  padding: 0.5rem;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
}

.btn-action {
  flex: 1;
  padding: 0.5rem;
  background: white;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-action:hover:not(:disabled) {
  background: #f1f5f9;
  border-color: #94a3b8;
}

.btn-action:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
</style>
