<!-- resources/js/components/Dashboard.vue -->
<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import DocumentList from './documents/DocumentList.vue'
import UserManagement from './admin/UserManagement.vue'
import DocumentUploadForm from './documents/DocumentUploadForm.vue'
import BaseModal from './ui/BaseModal.vue'
import ToastContainer from './ui/ToastContainer.vue'
import StatsGrid from './dashboard/StatsGrid.vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  user: {
    type: Object,
    required: true,
  },
  headers: {
    type: Object,
    required: true,
  },
  catalogs: {
    type: Object,
    required: true,
  },
})

const router = useRouter()
const userRole = computed(() => props.user?.role || null)

// Upload Modal State
const showUploadModal = ref(false)
const documentListRef = ref(null)

const { success } = useToast()

function handleShowUpload() {
  showUploadModal.value = true
}

function handleCloseUpload() {
  showUploadModal.value = false
}

function handleUploadSuccess(data) {
  success('Documento subido correctamente', `Documento #${data.id || data.documento?.id} creado exitamente`)
  
  // Refrescar la lista de documentos
  if (documentListRef.value) {
    documentListRef.value.refreshFromStart()
  }
  
  showUploadModal.value = false
}

// Navegación jerárquica
function navigateToSecciones() {
  router.push('/secciones')
}

function navigateToTipos() {
  router.push('/tipos')
}
</script>

<template>
  <div>
    <!-- Sistema global de notificaciones -->
    <ToastContainer />

    <!-- SUPERADMIN - Solo gestión de usuarios -->
    <section
      v-if="userRole === 'superadmin'"
      class="card"
    >
      <UserManagement 
        :headers="headers"
      />
    </section>

    <!-- ARCHIVISTA - Dashboard Jerárquico BSF -->
    <section
      v-else-if="userRole === 'archivist'"
      class="dashboard-section"
    >
      <div class="dashboard-header">
        <h1>Panel de Control BSF</h1>
        <p class="header-subtitle">
          Sistema de Archivo y Gestión Documental - Batallón de Seguridad Física
        </p>
      </div>

      <!-- Estadísticas Resumidas -->
      <StatsGrid :headers="headers" />

      <!-- Explorar Documentos -->
      <div class="explorer-section">
        <h2 class="section-title">Explorar Documentos</h2>
        <p class="section-subtitle">
          Navega por la estructura jerárquica del archivo documental
        </p>

        <div class="explorer-grid">
          <!-- Card Por Secciones -->
          <div class="explorer-card" @click="navigateToSecciones">
            <div class="card-header">
              <div class="card-icon">📂</div>
              <div>
                <h3>Por Sección</h3>
                <p class="card-subtitle">9 secciones institucionales</p>
              </div>
            </div>
            <div class="card-body">
              <p class="card-description">
                Explora documentos organizados por áreas funcionales del batallón
              </p>
              <div class="card-footer">
                <span class="card-action">
                  Ver secciones
                  <span class="arrow">→</span>
                </span>
              </div>
            </div>
          </div>

          <!-- Card Por Tipos -->
          <div class="explorer-card" @click="navigateToTipos">
            <div class="card-header">
              <div class="card-icon">📄</div>
              <div>
                <h3>Por Tipo de Documento</h3>
                <p class="card-subtitle">35+ tipos documentales</p>
              </div>
            </div>
            <div class="card-body">
              <p class="card-description">
                Busca por clase documental (resoluciones, informes, actas, etc.)
              </p>
              <div class="card-footer">
                <span class="card-action">
                  Ver tipos
                  <span class="arrow">→</span>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Acciones Rápidas -->
      <div class="quick-actions">
        <button @click="handleShowUpload" class="btn-primary">
          <span class="btn-icon">➕</span>
          Subir Documento
        </button>
      </div>
    </section>

    <!-- LECTOR - Dashboard Jerárquico BSF (solo lectura) -->
    <section v-else-if="userRole === 'reader'" class="dashboard-section">
      <div class="dashboard-header">
        <h1>Panel del Lector</h1>
        <p class="header-subtitle">
          Acceso a documentos no confidenciales del BSF
        </p>
      </div>

      <!-- Estadísticas Resumidas -->
      <StatsGrid :headers="headers" />

      <!-- Explorar Documentos -->
      <div class="explorer-section">
        <h2 class="section-title">Explorar Documentos</h2>
        <p class="section-subtitle">
          Navega por la estructura jerárquica del archivo documental
        </p>

        <div class="explorer-grid">
          <!-- Card Por Secciones -->
          <div class="explorer-card" @click="navigateToSecciones">
            <div class="card-header">
              <div class="card-icon">📂</div>
              <div>
                <h3>Por Sección</h3>
                <p class="card-subtitle">9 secciones institucionales</p>
              </div>
            </div>
            <div class="card-body">
              <p class="card-description">
                Explora documentos organizados por áreas funcionales del batallón
              </p>
              <div class="card-footer">
                <span class="card-action">
                  Ver secciones
                  <span class="arrow">→</span>
                </span>
              </div>
            </div>
          </div>

          <!-- Card Por Tipos -->
          <div class="explorer-card" @click="navigateToTipos">
            <div class="card-header">
              <div class="card-icon">📄</div>
              <div>
                <h3>Por Tipo de Documento</h3>
                <p class="card-subtitle">35+ tipos documentales</p>
              </div>
            </div>
            <div class="card-body">
              <p class="card-description">
                Busca por clase documental (resoluciones, informes, actas, etc.)
              </p>
              <div class="card-footer">
                <span class="card-action">
                  Ver tipos
                  <span class="arrow">→</span>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Cualquier caso inesperado -->
    <section v-else class="card">
      <h2 class="section-title">Rol no reconocido</h2>
      <p>
        No se pudo determinar un rol válido para el usuario (<code>{{ userRole }}</code>).
        Contacta al administrador del sistema para revisar la configuración de tu cuenta.
      </p>
    </section>

    <!-- Modal de Subida de Documentos -->
    <BaseModal
      :open="showUploadModal"
      title=""
      size="large"
      @close="handleCloseUpload"
    >
      <DocumentUploadForm
        :catalogs="catalogs"
        :headers="headers"
        @close="handleCloseUpload"
        @success="handleUploadSuccess"
      />
    </BaseModal>
  </div>
</template>

<style scoped>
/* Dashboard Header */
.dashboard-section {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

.dashboard-header {
  margin-bottom: 2rem;
  text-align: center;
}

.dashboard-header h1 {
  font-size: 2.5rem;
  font-weight: 800;
  color: #1f2937;
  margin: 0 0 0.5rem;
  letter-spacing: -0.02em;
}

.header-subtitle {
  font-size: 1.125rem;
  color: #6b7280;
  margin: 0;
}

/* Explorer Section */
.explorer-section {
  margin-top: 3rem;
}

.section-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 0.5rem;
}

.section-subtitle {
  font-size: 1rem;
  color: #6b7280;
  margin: 0 0 1.5rem;
}

/* Explorer Grid */
.explorer-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
}

.explorer-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-left: 6px solid #556b2f;
  border-radius: 16px;
  padding: 2rem;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.explorer-card:hover {
  border-left-width: 8px;
  box-shadow: 
    0 10px 25px -5px rgba(85, 107, 47, 0.1),
    0 10px 10px -5px rgba(85, 107, 47, 0.04);
  transform: translateY(-4px);
}

.explorer-card .card-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.explorer-card .card-icon {
  width: 64px;
  height: 64px;
  background: linear-gradient(135deg, #556b2f 0%, #6b8e23 100%);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  box-shadow: 0 4px 6px rgba(85, 107, 47, 0.2);
  flex-shrink: 0;
}

.explorer-card .card-header h3 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 0.25rem;
}

.explorer-card .card-subtitle {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0;
}

.explorer-card .card-body {
  padding-left: 80px;
}

.card-description {
  color: #6b7280;
  font-size: 0.938rem;
  margin: 0 0 1.5rem;
  line-height: 1.6;
}

.card-footer {
  display: flex;
  justify-content: flex-end;
}

.card-action {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  color: #556b2f;
  font-size: 0.938rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  transition: gap 0.3s;
}

.explorer-card:hover .card-action {
  gap: 0.75rem;
}

.arrow {
  font-size: 1.25rem;
  transition: transform 0.3s;
}

.explorer-card:hover .arrow {
  transform: translateX(4px);
}

/* Quick Actions */
.quick-actions {
  margin-top: 2rem;
  display: flex;
  justify-content: center;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem 2rem;
  background: linear-gradient(135deg, #556b2f 0%, #6b8e23 100%);
  color: white;
  border: none;
  border-radius: 12px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  box-shadow: 0 4px 6px rgba(85, 107, 47, 0.2);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 24px rgba(85, 107, 47, 0.3);
}

.btn-icon {
  font-size: 1.25rem;
}

/* Responsive */
@media (max-width: 1024px) {
  .explorer-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .dashboard-section {
    padding: 1rem;
  }
  
  .dashboard-header h1 {
    font-size: 1.75rem;
  }
  
  .header-subtitle {
    font-size: 1rem;
  }
  
  .explorer-card {
    padding: 1.5rem;
  }
  
  .explorer-card .card-body {
    padding-left: 0;
    margin-top: 1rem;
  }
}
</style>
