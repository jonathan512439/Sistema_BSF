<script setup>
import { ref } from 'vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  headers: {
    type: Object,
    required: true,
  },
})

const { success, error } = useToast()

// State
const verifying = ref(false)
const results = ref(null)

// Methods
async function verifyChain() {
  if (!confirm('¿Verificar integridad de toda la cadena de anclas?')) return
  
  verifying.value = true
  results.value = null
  
  try {
    const response = await fetch('/api/anchors/verify', {
      method: 'POST',
      headers: props.headers,
    })
    
    if (!response.ok) throw new Error('Error al verificar cadena')
    
    results.value = await response.json()
    
    if (results.value.all_valid) {
      success('Verificación exitosa', 'Toda la cadena de anclas es válida')
    } else {
      error('Problemas detectados', 'Se encontraron anclas con problemas')
    }
  } catch (err) {
    error('Error', err.message)
  } finally {
    verifying.value = false
  }
}

function getStatusClass(valid) {
  return valid ? 'status-valid' : 'status-invalid'
}

function getStatusIcon(valid) {
  return valid ? '✓' : '✗'
}
</script>

<template>
  <div class="verification">
    <h2 class="title">Verificación de Integridad</h2>
    <p class="subtitle">
      Verifica la integridad completa de la cadena de anclas blockchain,
      incluyendo hashes y firmas digitales.
    </p>

    <!-- Action -->
    <div class="action-section">
      <button 
        @click="verifyChain"
        :disabled="verifying"
        class="btn btn-primary btn-large"
      >
        <svg v-if="!verifying" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
          <path d="M10.854 6.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
          <path d="M8 1a2.5 2.5 0 0 1 2.5 2.5V4h-5v-.5A2.5 2.5 0 0 1 8 1zm3.5 3v-.5a3.5 3.5 0 1 0-7 0V4H1v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V4h-3.5z"/>
        </svg>
        <svg v-else width="20" height="20" fill="currentColor" viewBox="0 0 16 16" class="spin">
          <path d="M8 0c-4.418 0-8 3.582-8 8s3.582 8 8 8 8-3.582 8-8-3.582-8-8-8zm0 14c-3.309 0-6-2.691-6-6s2.691-6 6-6 6 2.691 6 6-2.691 6-6 6z" opacity="0.3"/>
          <path d="M8 2c3.309 0 6 2.691 6 6h2c0-4.418-3.582-8-8-8v2z"/>
        </svg>
        {{ verifying ? 'Verificando...' : 'Verificar Cadena Completa' }}
      </button>
    </div>

    <!-- Results -->
    <div v-if="results" class="results">
      <!-- Summary -->
      <div class="summary-card" :class="results.all_valid ? 'valid' : 'invalid'">
        <div class="summary-icon">
          <svg v-if="results.all_valid" width="32" height="32" fill="currentColor" viewBox="0 0 16 16">
            <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
          </svg>
          <svg v-else width="32" height="32" fill="currentColor" viewBox="0 0 16 16">
            <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/>
          </svg>
        </div>
        
        <div class="summary-content">
          <div class="summary-title">
            {{ results.all_valid ? 'Cadena Válida' : 'Problemas Detectados' }}
          </div>
          <div class="summary-subtitle">
            {{ results.total_anclas }} ancla{{ results.total_anclas !== 1 ? 's' : '' }} verificada{{ results.total_anclas !== 1 ? 's' : '' }}
          </div>
        </div>
      </div>

      <!-- Detailed Results -->
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Ancla</th>
              <th>Rango</th>
              <th>Estado</th>
              <th>Firmada</th>
              <th>Publicada</th>
              <th>Problemas</th>
            </tr>
          </thead>
          <tbody>
            <tr 
              v-for="anchor in results.anclas" 
              :key="anchor.ancla_id"
              :class="getStatusClass(anchor.valid)"
            >
              <td><strong>#{{ anchor.ancla_id }}</strong></td>
              <td>
                <code class="range">{{ anchor.rango }}</code>
              </td>
              <td>
                <span class="status-badge" :class="anchor.valid ? 'valid' : 'invalid'">
                  {{ getStatusIcon(anchor.valid) }}
                  {{ anchor.valid ? 'Válida' : 'Inválida' }}
                </span>
              </td>
              <td>
                <span v-if="anchor.firmada" class="badge badge-success">Sí</span>
                <span v-else class="badge badge-gray">No</span>
              </td>
              <td>
                <span v-if="anchor.publicada" class="badge badge-info">Sí</span>
                <span v-else class="badge badge-gray">No</span>
              </td>
              <td>
                <div v-if="anchor.issues.length > 0" class="issues">
                  <span 
                    v-for="(issue, idx) in anchor.issues" 
                    :key="idx"
                    class="issue-badge"
                  >
                    {{ issue }}
                  </span>
                </div>
                <span v-else class="text-muted">-</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Explanation -->
      <div class="info-box">
        <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
          <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533L8.93 6.588zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
        </svg>
        <div>
          <strong>¿Cómo funciona la verificación?</strong>
          <p>
            El sistema recalcula el hash de cada ancla usando los registros del ledger
            y lo compara con el hash almacenado. Si están firmadas, también verifica
            la firma digital RSA-SHA256 con la clave pública.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.verification {
  max-width: 1200px;
  margin: 0 auto;
}

.title {
  margin: 0 0 0.5rem;
  font-size: 1.5rem;
  font-weight: 600;
  color: #111827;
}

.subtitle {
  margin: 0 0 2rem;
  font-size: 0.9375rem;
  color: #6B7280;
  line-height: 1.6;
}

.action-section {
  text-align: center;
  padding: 2rem;
  background: #F9FAFB;
  border-radius: 8px;
  margin-bottom: 2rem;
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 6px;
  font-size: 0.9375rem;
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

.btn-primary:hover:not(:disabled) {
  background: #1D4ED8;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-large {
  padding: 1rem 2rem;
  font-size: 1rem;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.results {
  margin-top: 2rem;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  padding: 1.5rem;
  border-radius: 8px;
  margin-bottom: 1.5rem;
}

.summary-card.valid {
  background: #D1FAE5;
  border: 2px solid #059669;
}

.summary-card.invalid {
  background: #FEE2E2;
  border: 2px solid #DC2626;
}

.summary-icon {
  flex-shrink: 0;
}

.summary-card.valid .summary-icon {
  color: #059669;
}

.summary-card.invalid .summary-icon {
  color: #DC2626;
}

.summary-title {
  font-size: 1.25rem;
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.summary-card.valid .summary-title {
  color: #065F46;
}

.summary-card.invalid .summary-title {
  color: #991B1B;
}

.summary-subtitle {
  font-size: 0.875rem;
  opacity: 0.8;
}

.table-container {
  background: #fff;
  border: 1px solid #E5E7EB;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 1.5rem;
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

.table tbody tr.status-valid {
  background: #F0FDF4;
}

.table tbody tr.status-invalid {
  background: #FEF2F2;
}

.range {
  background: #F3F4F6;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.8125rem;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.8125rem;
  font-weight: 500;
}

.status-badge.valid {
  background: #D1FAE5;
  color: #065F46;
}

.status-badge.invalid {
  background: #FEE2E2;
  color: #991B1B;
}

.badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
}

.badge-success {
  background: #D1FAE5;
  color: #065F46;
}

.badge-info {
  background: #DBEAFE;
  color: #1E40AF;
}

.badge-gray {
  background: #F3F4F6;
  color: #6B7280;
}

.issues {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.issue-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  background: #FEF3C7;
  color: #92400E;
  border-radius: 4px;
  font-size: 0.75rem;
}

.text-muted {
  color: #9CA3AF;
}

.info-box {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  background: #EFF6FF;
  border: 1px solid #BFDBFE;
  border-radius: 8px;
  font-size: 0.875rem;
  color: #1E40AF;
}

.info-box svg {
  flex-shrink: 0;
  margin-top: 0.125rem;
}

.info-box strong {
  display: block;
  margin-bottom: 0.25rem;
}

.info-box p {
  margin: 0;
  line-height: 1.5;
  color: #1E3A8A;
}
</style>
