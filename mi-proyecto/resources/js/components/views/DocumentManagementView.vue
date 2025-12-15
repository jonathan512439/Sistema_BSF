<template>
  <div class="document-management-view">
    <div class="view-header">
      <div>
        <h1 class="view-title">Gestión de Documentos</h1>
        <p class="view-subtitle">Lista completa de documentos del sistema</p>
      </div>
      <button v-if="can('doc.upload')" @click="showUploadModal = true" class="btn-upload">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
          <path d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2z"/>
        </svg>
        Subir Documento
      </button>
    </div>

    <DocumentList
      ref="documentListRef"
      :headers="headers"
      :catalogs="catalogs"
      :role="user?.role || 'reader'"
      :can="can"
      @show-upload="showUploadModal = true"
    />

    <!-- Modal de Upload -->
    <BaseModal
      :open="showUploadModal"
      title="Subir Nuevo Documento"
      size="large"
      @close="showUploadModal = false"
    >
      <DocumentUploadForm
        :headers="headers"
        :catalogs="catalogs"
        @success="handleUploadSuccess"
        @close="showUploadModal = false"
      />
    </BaseModal>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import DocumentList from '../documents/DocumentList.vue'
import BaseModal from '../ui/BaseModal.vue'
import DocumentUploadForm from '../documents/DocumentUploadForm.vue'

defineProps({
  user: {
    type: Object,
    default: () => ({})
  },
  headers: {
    type: Object,
    required: true
  },
  catalogs: {
    type: Object,
    default: () => ({})
  },
  can: {
    type: Function,
    required: true
  }
})

const showUploadModal = ref(false)
const documentListRef = ref(null)

function handleUploadSuccess(data) {
  // Refrescar la lista de documentos
  if (documentListRef.value?.refreshFromStart) {
    documentListRef.value.refreshFromStart()
  }
  
  // Cerrar el modal
  showUploadModal.value = false
  
  // Opcional: mostrar mensaje de éxito
  console.log('Documento subido exitosamente:', data)
}
</script>

<style scoped>
.document-management-view {
  padding: 1.5rem;
  max-width: 1600px;
  margin: 0 auto;
  width: 100%;
}

.view-header {
  margin-bottom: 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.view-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 0.5rem 0;
}

.view-subtitle {
  font-size: 0.938rem;
  color: #6b7280;
  margin: 0;
}

.btn-upload {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 1.25rem;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 0.5rem;
  font-size: 0.938rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.btn-upload:hover {
  background: #2563eb;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-upload:active {
  transform: translateY(0);
}

.btn-upload svg {
  flex-shrink: 0;
}

@media (max-width: 1024px) {
  .document-management-view {
    padding: 1.25rem;
  }
  
  .view-title {
    font-size: 1.5rem;
  }
}

@media (max-width: 768px) {
  .document-management-view {
    padding: 1rem;
  }
  
  .view-title {
    font-size: 1.375rem;
  }
  
  .view-subtitle {
    font-size: 0.875rem;
  }
}

@media (max-width: 480px) {
  .document-management-view {
    padding: 0.75rem;
  }
  
  .view-title {
    font-size: 1.25rem;
  }
}
</style>
