<script setup>
import { ref } from 'vue'
import AnchorList from './AnchorList.vue'
import AnchorVerification from './AnchorVerification.vue'

const props = defineProps({
  headers: {
    type: Object,
    required: true,
  },
})

// State
const activeTab = ref('list')

// Methods
function setTab(tab) {
  activeTab.value = tab
}
</script>

<template>
  <div class="anchor-dashboard">
    <div class="dashboard-header">
      <div>
        <h1 class="main-title">Sistema de Anclaje Blockchain</h1>
        <p class="main-subtitle">
          Gestión e integridad del ledger de auditoría mediante anclas blockchain
        </p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <button 
        @click="setTab('list')"
        :class="['tab', { active: activeTab === 'list' }]"
      >
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
        </svg>
        Lista de Anclas
      </button>

      <button 
        @click="setTab('verify')"
        :class="['tab', { active: activeTab === 'verify' }]"
      >
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M10.854 6.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
          <path d="M8 1a2.5 2.5 0 0 1 2.5 2.5V4h-5v-.5A2.5 2.5 0 0 1 8 1zm3.5 3v-.5a3.5 3.5 0 1 0-7 0V4H1v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V4h-3.5z"/>
        </svg>
        Verificación de Integridad
      </button>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
      <AnchorList 
        v-if="activeTab === 'list'"
        :headers="headers"
      />

      <AnchorVerification 
        v-if="activeTab === 'verify'"
        :headers="headers"
      />
    </div>
  </div>
</template>

<style scoped>
.anchor-dashboard {
  padding: 1.5rem;
}

.dashboard-header {
  margin-bottom: 2rem;
}

.main-title {
  margin: 0 0 0.5rem;
  font-size: 1.875rem;
  font-weight: 700;
  color: #111827;
}

.main-subtitle {
  margin: 0;
  font-size: 1rem;
  color: #6B7280;
}

.tabs {
  display: flex;
  gap: 0.5rem;
  border-bottom: 2px solid #E5E7EB;
  margin-bottom: 2rem;
}

.tab {
  padding: 0.75rem 1.25rem;
  border: none;
  background: transparent;
  color: #6B7280;
  font-size: 0.9375rem;
  font-weight: 500;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: all 0.15s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.tab:hover {
  color: #374151;
  background: #F9FAFB;
}

.tab.active {
  color: #2563EB;
  border-bottom-color: #2563EB;
  background: transparent;
}

.tab-content {
  animation: fadeIn 0.2s;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
