<script setup>
import { onMounted, ref } from 'vue';
import api from '../services/api';

const items   = ref([]);
const loading = ref(false);
const error   = ref('');
const page    = ref(1);
const lastPage = ref(1);

function statusClass(s) {
  if (s === 'open')        return 'badge badge-open';
  if (s === 'in_progress') return 'badge badge-progress';
  if (s === 'closed')      return 'badge badge-closed';
  return 'badge badge-default';
}

function priorityLabel(p) {
  return { low: 'Rendah', medium: 'Sedang', high: 'Tinggi' }[p] || p;
}

function fmt(v) {
  if (!v) return '-';
  return new Date(v).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit', hour12: false,
  });
}

async function fetchGangguan(p = 1) {
  loading.value = true;
  error.value   = '';
  try {
    const { data } = await api.get('/gangguan', { params: { page: p } });
    items.value    = data.data || [];
    page.value     = data.current_page ?? 1;
    lastPage.value = data.last_page    ?? 1;
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal memuat data gangguan.';
  } finally {
    loading.value = false;
  }
}

onMounted(() => fetchGangguan());
</script>

<template>
  <section class="grid" style="gap:16px;">

    <!-- Header -->
    <div class="page-title-wrap" style="grid-column:1/-1; display:flex; justify-content:space-between; align-items:flex-end;">
      <div>
        <h2 class="page-title">Daftar Gangguan</h2>
        <p class="page-desc">Semua tiket gangguan yang masuk.</p>
      </div>
    </div>

    <!-- Error -->
    <div v-if="error" class="alert alert-danger" style="grid-column:1/-1;">{{ error }}</div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="card" style="grid-column:1/-1;padding:32px;text-align:center;color:var(--muted);">
      Memuat data...
    </div>

    <!-- Tabel -->
    <div v-else-if="items.length" class="card" style="grid-column:1/-1;padding:0;overflow:hidden;">
      <div class="table-responsive">
        <table>
          <thead>
          <tr>
            <th>Tiket</th>
            <th>Judul</th>
            <th>Status</th>
            <th>Prioritas</th>
            <th>Dibuat</th>
            <th style="text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td style="font-weight:700;font-size:0.82rem;color:var(--primary);white-space:nowrap;">{{ item.ticket_number }}</td>
            <td style="max-width:260px;">
              <p style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ item.judul }}</p>
              <p style="font-size:0.76rem;color:var(--muted);">{{ item.creator?.name ?? '-' }}</p>
            </td>
            <td><span :class="statusClass(item.status)">{{ item.status?.replace(/_/g,' ') }}</span></td>
            <td style="font-size:0.82rem;">{{ priorityLabel(item.priority) }}</td>
            <td style="font-size:0.78rem;color:var(--muted);white-space:nowrap;">{{ fmt(item.created_at) }}</td>
            <td style="text-align:center;">
              <RouterLink
                :to="`/gangguan/${item.id}`"
                style="display:inline-block;padding:5px 12px;border-radius:6px;background:var(--primary-lt);color:var(--primary-dk);font-weight:700;font-size:0.78rem;"
              >Buka</RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
      </div>

      <!-- Pagination -->
      <div v-if="lastPage > 1" style="display:flex;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid var(--border);">
        <button class="btn-ghost" :disabled="page <= 1" @click="fetchGangguan(page - 1)" style="padding:6px 12px;font-size:0.8rem;">← Prev</button>
        <span style="padding:6px 12px;font-size:0.8rem;color:var(--muted);">{{ page }} / {{ lastPage }}</span>
        <button class="btn-ghost" :disabled="page >= lastPage" @click="fetchGangguan(page + 1)" style="padding:6px 12px;font-size:0.8rem;">Next →</button>
      </div>
    </div>

    <div v-else class="card" style="grid-column:1/-1;text-align:center;padding:40px;color:var(--muted);">
      Belum ada data gangguan.
    </div>

  </section>
</template>
