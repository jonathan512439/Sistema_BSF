<script setup>
import { ref, onMounted, computed } from 'vue'
import RbacPanel from './RbacPanel.vue'
import DocumentList from './DocumentList.vue'

const users = ref([])
const rbac = ref({ ok: false, users: [], user_roles: [], role_permissions: [] })
const catalogs = ref({
  tipos_documento: [],
  secciones: [],
  subsecciones: [],
  gestiones: [],
  ubicaciones: [],
  almacenes: [],
  motivos_acceso: [],
})
const userId = ref(Number(localStorage.getItem('demo_user_id') || 1))

const headers = computed(() => ({
  'X-Demo-User': String(userId.value),
  Accept: 'application/json',
}))

function persistUser () {
  localStorage.setItem('demo_user_id', String(userId.value))
}

async function reloadAll () {
  try {
    const r1 = await fetch('/api/rbac/users', {
      headers: headers.value,
      credentials: 'include',
    })
    const r2 = await fetch('/api/catalogs', {
      headers: headers.value,
      credentials: 'include',
    })

    rbac.value = await r1.json().catch(() => ({
      ok: false,
      users: [],
      user_roles: [],
      role_permissions: [],
    }))
    catalogs.value = await r2.json().catch(() => ({
      tipos_documento: [],
      secciones: [],
      subsecciones: [],
      gestiones: [],
      ubicaciones: [],
      almacenes: [],
      motivos_acceso: [],
    }))

    users.value = rbac.value.users || []
    if (!users.value.length) {
      // Fallback visible de usuarios demo
      users.value = [
        { id: 1, name: 'Admin BSF (demo)', email: 'admin@demo.local' },
        { id: 2, name: 'Custodio (demo)', email: 'custodio@demo.local' },
        { id: 3, name: 'Lector (demo)', email: 'lector@demo.local' },
      ]
    }
    if (!users.value.find(u => u.id === userId.value) && users.value.length) {
      userId.value = users.value[0].id
      persistUser()
    }
  } catch (e) {
    // en caso de fallo total, usamos modo demo
    rbac.value = { ok: false, users: [], user_roles: [], role_permissions: [] }
  }
}

onMounted(reloadAll)
</script>

<template>
  <div class="container grid" style="gap: 1rem">
    <header class="row header-bar">
      <div>
        <h1 class="title">BSF — Demostración (Documentos)</h1>
        <p class="subtitle">
          Subir, reconocer texto (OCR), validar y sellar custodia de documentos.
        </p>
      </div>

      <div class="header-controls">
        <div class="row">
          <label class="header-label">Usuario:</label>
          <select v-model.number="userId" @change="persistUser" class="btn">
            <option v-for="u in users" :key="u.id" :value="u.id">
              {{ u.name }} (id: {{ u.id }})
            </option>
          </select>
          <button class="btn olive" @click="reloadAll">Actualizar</button>
        </div>
      </div>
    </header>

    <section
      v-if="!rbac.ok"
      class="card"
      style="border-color: #b45309; background: #fffbeb"
    >
      <b style="color: #92400e">Aviso RBAC:</b>
      No se encontraron roles/permisos desde la API. Usando
      <b>modo demo</b> (permisos ficticios por usuario). Importa el seed
      <i>bsf_seed_demo.sql</i> para datos reales.
    </section>

    <section class="card">
      <h2 style="margin: 0 0 0.25rem 0">
        Roles y permisos
        <span class="badge">
          {{ rbac.ok ? 'Desde BD' : 'Demo' }}
        </span>
      </h2>
      <RbacPanel :rbac="rbac" />
    </section>

    <section class="card">
      <h2 style="margin: 0 0 0.25rem 0">Gestión de documentos</h2>
      <DocumentList
        :headers="headers"
        :rbac="rbac"
        :catalogs="catalogs"
      />
    </section>
  </div>
</template>

<style>
.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1rem;
}
.header-bar {
  justify-content: space-between;
  align-items: flex-start;
}
.header-controls {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  align-items: flex-end;
}
.header-label {
  font-size: 0.8rem;
  color: #4b5563;
}
.title {
  color: #1f2933;
  font-weight: 700;
  margin: 0;
}
.subtitle {
  margin: 0.15rem 0 0;
  font-size: 0.9rem;
  color: #6b7280;
}
.row {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  flex-wrap: wrap;
}
.card {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 14px;
  background: #fff;
}
.btn {
  padding: 0.45rem 0.7rem;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  background: #f9fafb;
  cursor: pointer;
  font-size: 0.85rem;
}
.btn.olive {
  background: #556b2f;
  color: #fff;
  border-color: #4b5f2a;
}
.badge {
  display: inline-block;
  padding: 0.05rem 0.45rem;
  border-radius: 999px;
  font-size: 0.75rem;
  background: #e5e7eb;
}

@media (max-width: 800px) {
  .header-bar {
    flex-direction: column;
    gap: 0.75rem;
  }
  .header-controls {
    align-items: flex-start;
  }
}
</style>
