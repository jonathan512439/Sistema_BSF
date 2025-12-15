<template>
  <div class="user-management-view">
    <UserManagement :headers="headers" />
  </div>
</template>

<script setup>
import UserManagement from '../admin/UserManagement.vue'

defineProps({
  headers: {
    type: Object,
    required: true
  }
})
</script>

<style scoped>
.user-management-view {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

@media (max-width: 768px) {
  .user-management-view {
    padding: 1rem;
  }
}
</style>
