<template>
  <div class="doc-card" @click="$emit('open-viewer', documento)">
    <!-- Thumbnail - Always show PDF icon -->
    <div class="doc-thumbnail-container">
      <div class="doc-thumbnail-placeholder">
        <span class="placeholder-icon">📄</span>
      </div>
      
      <!-- Badge de estado -->
      <div class="doc-badges">
        <span :class="['badge-estado', `estado-${documento.estado}`]">
          {{ documento.estado }}
        </span>
        <span v-if="documento.is_confidential" class="badge-confidential">
          🔒 Confidencial
        </span>
      </div>
    </div>
    
    <!-- Contenido de la card -->
    <div class="doc-content">
      <h3 class="doc-title">
        {{ documento.titulo || documento.numero_documento || `Doc #${documento.id}` }}
      </h3>
      
      <p class="doc-description">
        {{ truncateText(documento.descripcion, 80) }}
      </p>
      
      <div class="doc-meta">
        <div class="meta-row">
          <span class="meta-icon">📋</span>
          <span class="meta-text">{{ tipoNombre }}</span>
        </div>
        <div class="meta-row">
          <span class="meta-icon">📁</span>
          <span class="meta-text">{{ seccionNombre }}</span>
        </div>
        <div v-if="documento.fecha_documento" class="meta-row">
          <span class="meta-icon">📅</span>
          <span class="meta-text">{{ formatDate(documento.fecha_documento) }}</span>
        </div>
        <div v-if="documento.ocr_confidence" class="meta-row">
          <span class="meta-icon">🔍</span>
          <span class="meta-text">OCR: {{ documento.ocr_confidence }}%</span>
        </div>
      </div>
    </div>
    
    <!-- Footer con acciones -->
    <div class="doc-footer">
      <button @click.stop="$emit('show-details', documento)" class="btn-details">
        Ver Detalles →
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  documento: {
    type: Object,
    required: true
  },
  catalogs: {
    type: Object,
    default: () => ({})
  }
})

defineEmits(['show-details', 'open-viewer'])

const tipoNombre = computed(() => {
  if (!props.documento.tipo_documento_id) return 'Sin tipo'
  const tipo = props.catalogs.tipos_documento?.find(t => t.id === props.documento.tipo_documento_id)
  return tipo?.nombre || 'Desconocido'
})

const seccionNombre = computed(() => {
  if (!props.documento.seccion_id) return 'Sin sección'
  const seccion = props.catalogs.secciones?.find(s => s.id === props.documento.seccion_id)
  return seccion?.nombre || 'Desconocida'
})

function truncateText(text, maxLength) {
  if (!text) return 'Sin descripción'
  if (text.length <= maxLength) return text
  return text.substring(0, maxLength) + '...'
}

function formatDate(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString('es-ES', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric' 
  })
}
</script>

<style scoped>
.doc-card {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
  transition: all 0.3s ease;
  cursor: pointer;
  display: flex;
  flex-direction: row;
  height: 180px;
  width: 100%;
}

.doc-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

.doc-thumbnail-container {
  position: relative;
  width: 140px;
  min-width: 140px;
  height: 100%;
  background: linear-gradient(135deg, #556b2f 0%, #6b8e23 100%);
  overflow: hidden;
}

.doc-thumbnail-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #556b2f 0%, #6b8e23 100%);
}

.placeholder-icon {
  font-size: 3rem;
  opacity: 0.7;
  filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
}

.doc-badges {
  position: absolute;
  top: 8px;
  left: 8px;
  right: auto;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  align-items: flex-start;
}

.badge-estado {
  background: rgba(255, 255, 255, 0.95);
  color: #374151;
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: capitalize;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.estado-capturado {
  background: #dbeafe;
  color: #1e40af;
}

.estado-procesado_ocr {
  background: #fef3c7;
  color: #92400e;
}

.estado-validado {
  background: #d1fae5;
  color: #065f46;
}

.estado-custodio,
.estado-sellado {
  background: #dcfce7;
  color: #166534;
  border: 2px solid #16a34a;
}

.badge-confidential {
  background: rgba(254, 202, 202, 0.95);
  color: #991b1b;
  padding: 0.2rem 0.5rem;
  border-radius: 10px;
  font-size: 0.65rem;
  font-weight: 600;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.doc-content {
  padding: 1.25rem;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-width: 0;
}

.doc-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.doc-description {
  font-size: 0.875rem;
  color: #6b7280;
  line-height: 1.5;
  margin: 0;
  flex: 1;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
}

.doc-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  padding-top: 0.5rem;
  border-top: 1px solid #e5e7eb;
  margin-top: auto;
}

.meta-row {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.813rem;
}

.meta-icon {
  font-size: 1rem;
  opacity: 0.8;
}

.meta-text {
  color: #4b5563;
  font-weight: 500;
}

.doc-footer {
  padding: 1rem;
  background: #f9fafb;
  border-top: none;
  border-left: 1px solid #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 130px;
}

.btn-details {
  padding: 0.625rem 1.25rem;
  background: white;
  color: #556b2f;
  border: 2px solid #556b2f;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.btn-details:hover {
  background: #556b2f;
  color: white;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(85, 107, 47, 0.3);
}

@media (max-width: 768px) {
  .doc-card {
    flex-direction: column;
    height: auto;
  }
  
  .doc-thumbnail-container {
    width: 100%;
    height: 120px;
  }
  
  .doc-footer {
    border-left: none;
    border-top: 1px solid #e5e7eb;
    min-width: auto;
  }
}
</style>
