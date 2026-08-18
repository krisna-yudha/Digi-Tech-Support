<script setup>
import { onMounted, ref } from 'vue';
import api from '../services/api';

const summary = ref(null);
const loading = ref(false);

const startDate = ref('');
const endDate = ref('');

async function fetchSummary() {
  loading.value = true;
  try {
    const params = {};
    if (startDate.value) params.start_date = startDate.value;
    if (endDate.value) params.end_date = endDate.value;

    const { data } = await api.get('/summary', { params });
    summary.value = data;
  } finally {
    loading.value = false;
  }
}

onMounted(fetchSummary);
</script>

<template>
  <section class="grid" style="gap:16px;">

    <!-- Header -->
    <div class="page-title-wrap" style="grid-column:1/-1; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap: wrap; gap: 16px;">
      <div>
        <h2 class="page-title">Dashboard Admin</h2>
        <p class="page-desc">Ringkasan status gangguan dan navigasi cepat.</p>
      </div>

      <!-- Date Filter -->
      <div style="display:flex; gap:8px; align-items:center; background: #fff; padding: 12px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div>
          <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 4px;">Dari Tanggal</label>
          <input type="date" v-model="startDate" class="form-input" style="padding: 6px 10px; font-size: 0.85rem; border: 1px solid #ccc; border-radius: 6px;" />
        </div>
        <div>
          <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 4px;">Sampai Tanggal</label>
          <input type="date" v-model="endDate" class="form-input" style="padding: 6px 10px; font-size: 0.85rem; border: 1px solid #ccc; border-radius: 6px;" />
        </div>
        <button @click="fetchSummary" class="btn-primary" style="align-self: flex-end; padding: 8px 16px; font-size: 0.85rem; height: 35px; border: none; border-radius: 6px; cursor: pointer;">
          Filter
        </button>
      </div>
    </div>

    <!-- Status Stats -->
    <div style="grid-column:1/-1;" class="grid-cols-4">
      <template v-if="loading">
        <div v-for="i in 4" :key="i" class="stat-card" style="animation:pulse 1.2s infinite alternate;background:#ededea;">
          <div class="stat-value" style="color:transparent;background:#ddd;border-radius:6px;width:40px;">0</div>
          <div class="stat-label">&nbsp;</div>
        </div>
      </template>

      <template v-else-if="summary">
      <div class="stat-card">
        <div class="stat-value" style="color:var(--primary);">{{ summary.total ?? 0 }}</div>
        <div class="stat-label">Total Tiket</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:var(--success);">{{ summary.open ?? 0 }}</div>
        <div class="stat-label">Open</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:var(--warn);">{{ summary.in_progress ?? 0 }}</div>
        <div class="stat-label">In Progress</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:var(--info);">{{ summary.closed ?? 0 }}</div>
        <div class="stat-label">Closed</div>
      </div>
      </template>
    </div>

    <!-- Informative Data -->
    <div style="grid-column:1/-1; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px;" v-if="summary && !loading">
      
      <!-- By Kategori -->
      <div class="card" style="background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px; border-bottom: 1px solid #eee; padding-bottom: 8px;">Tiket Berdasarkan Kategori</h3>
        <div v-if="Object.keys(summary.by_kategori || {}).length === 0" style="color: var(--text-muted); font-size: 0.9rem;">
          Tidak ada data.
        </div>
        <div v-else style="display:flex; flex-direction:column; gap:12px;">
          <div v-for="(count, category) in summary.by_kategori" :key="category" style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.95rem; font-weight: 500; color: var(--text);">{{ category || 'Tanpa Kategori' }}</span>
            <span style="background: var(--primary-lt); color: var(--primary-dk); padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 0.85rem;">{{ count }}</span>
          </div>
        </div>
      </div>

      <!-- By Priority -->
      <div class="card" style="background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px; border-bottom: 1px solid #eee; padding-bottom: 8px;">Tiket Berdasarkan Prioritas</h3>
        <div v-if="Object.keys(summary.by_priority || {}).length === 0" style="color: var(--text-muted); font-size: 0.9rem;">
          Tidak ada data.
        </div>
        <div v-else style="display:flex; flex-direction:column; gap:12px;">
          <div v-for="(count, priority) in summary.by_priority" :key="priority" style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.95rem; font-weight: 500; color: var(--text);">{{ priority || 'Tanpa Prioritas' }}</span>
            <span style="background: var(--info-lt); color: var(--info-dk); padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 0.85rem;">{{ count }}</span>
          </div>
        </div>
      </div>

    </div>

  </section>
</template>
