<script setup>
import { onMounted, ref } from 'vue';
import api from '../services/api';

const items = ref([]);
const loading = ref(false);

async function fetchBackups() {
  loading.value = true;
  try {
    const { data } = await api.get('/backups');
    items.value = data.data || [];
  } finally {
    loading.value = false;
  }
}

async function triggerBackup() {
  await api.post('/backups/trigger');
  await fetchBackups();
}

onMounted(fetchBackups);
</script>

<template>
  <section class="card grid">
    <h2 class="page-title">Backup</h2>
    <p style="color: var(--muted);">Daftar backup dan aksi download/restore.</p>

    <div style="display: flex; gap: 10px;">
      <button class="btn-primary" @click="triggerBackup">Trigger Backup</button>
    </div>

    <p v-if="loading">Memuat daftar backup...</p>
    <div v-if="!loading && items.length" class="grid">
      <article v-for="item in items" :key="item.id" class="card" style="padding: 12px;">
        <h4>{{ item.filename }}</h4>
        <p style="color: var(--muted);">Status: {{ item.status }} | Tanggal: {{ item.backup_date }}</p>
      </article>
    </div>
  </section>
</template>
