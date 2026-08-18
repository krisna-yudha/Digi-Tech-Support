<script setup>
import { onMounted, ref } from 'vue';
import api from '../services/api';

const summary = ref(null);
const loading = ref(false);

async function fetchSummary() {
  loading.value = true;
  try {
    const { data } = await api.get('/summary');
    summary.value = data;
  } finally {
    loading.value = false;
  }
}

onMounted(fetchSummary);
</script>

<template>
  <section class="grid grid-2">
    <article class="card">
      <h2 class="page-title">Summary</h2>
      <p v-if="loading">Memuat summary...</p>
      <div v-else-if="summary" class="grid">
        <p>Total tiket: <strong>{{ summary.total }}</strong></p>
        <p>Open: <strong>{{ summary.open }}</strong></p>
        <p>In Progress: <strong>{{ summary.in_progress }}</strong></p>
        <p>Closed: <strong>{{ summary.closed }}</strong></p>
      </div>
      <p v-else style="color: var(--muted);">Belum ada data summary.</p>
    </article>
    <article class="card">
      <h3 style="margin-bottom: 8px;">Cache Note</h3>
      <p>Data summary dapat di-cache setiap 5 menit sesuai planning jobs.</p>
    </article>
  </section>
</template>
