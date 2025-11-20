<template>
  <div>
    <div class="row" style="gap:1.5rem; flex-wrap:wrap">
      <div v-for="u in rbac.users" :key="u.id" class="rbac-card">
        <div class="name">{{ u.name }}</div>
        <div class="muted">{{ u.email }}</div>
        <div class="muted" style="margin-top:.25rem">
          <b>Roles:</b>
          <span v-for="r in rolesOf(u.id)" :key="r.role_id" class="chip">{{ r.role_name }}</span>
        </div>
        <div class="muted" style="margin-top:.25rem">
          <b>Permisos:</b>
          <span v-for="p in permsOf(u.id)" :key="p" class="chip">{{ p }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({ rbac: { type: Object, required: true } })

const rolesOf = (uid) => (props.rbac.user_roles || []).filter(x => x.user_id === uid)
const permsOf = (uid) => {
  const rids = rolesOf(uid).map(x => x.role_id)
  const perms = (props.rbac.role_permissions || []).filter(x => rids.includes(x.role_id)).map(x => x.perm_slug)
  return Array.from(new Set(perms))
}
</script>

<style>
.rbac-card{
  border:1px solid #e5e7eb; border-radius:12px; padding:.75rem; width:300px; background:#fff
}
.name{ font-weight:700; color:#556B2F }
.chip{ display:inline-block; background:#cdd6b3; color:#1f2937; padding:.1rem .5rem; border-radius:12px; margin:.1rem }
.row{ display:flex; gap:.75rem; align-items:center; flex-wrap:wrap }
.muted{ color:#6b7280 }
</style>
