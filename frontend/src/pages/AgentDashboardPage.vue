<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../services/api';

const route = useRoute();
const router = useRouter();
const dashboard = ref(null);
const loading = ref(false);

const successMessage = computed(() => {
  if (route.query.notice === 'report-submitted') {
    return 'Laporan gangguan berhasil dikirim.';
  }

  return '';
});

function dismissNotice() {
  router.replace({ name: 'agent-dashboard' });
}

function formatDateTime(value) {
  if (!value) return '-';

  return new Date(value).toLocaleString('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short'
  });
}

onMounted(async () => {
  loading.value = true;

  try {
    const { data } = await api.get('/agent/dashboard');
    dashboard.value = data;
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <section class="grid">
    <div class="page-title-wrap" style="grid-column: 1 / -1; display:flex; justify-content:space-between; align-items:flex-end;">
      <div>
        <h2 class="page-title">Dashboard Agent</h2>
        <p class="page-desc">Buat laporan gangguan baru dan lihat status pengiriman laporan terakhir.</p>
      </div>
    </div>
    
    <div v-if="successMessage" style="grid-column: 1 / -1; padding: 12px 16px; border-radius:12px; background: #e8f7ec; border:1px solid #bfe3c6; display: flex; justify-content: space-between; gap: 12px; align-items: center;">
      <span style="color: #146c2e; font-weight: 700;">{{ successMessage }}</span>
      <button type="button" class="btn-primary" style="padding: 8px 12px;" @click="dismissNotice">Tutup</button>
    </div>

    <div style="grid-column:1/-1;" class="grid-cols-2">
      <article class="card" style="display: flex; justify-content: space-between; align-items: center; gap: 12px;">
        <div>
          <h3 style="margin-bottom: 6px;">Total Laporan Terkirim</h3>
          <p class="page-desc">Jumlah laporan yang sudah Anda submit.</p>
        </div>
        <div style="min-width: 84px; padding: 10px 16px; border-radius: 14px; background: #e8f7ec; color: #146c2e; font-size: 1.8rem; font-weight: 800; text-align: center;">
          {{ loading ? '...' : (dashboard?.total_reports ?? 0) }}
        </div>
      </article>

      <article class="card" style="display:flex; flex-direction:column; justify-content:center;">
        <h3 style="margin-bottom: 8px;">Status Terakhir</h3>
        <p v-if="loading" class="page-desc">Memuat data laporan...</p>
        <p v-else style="font-weight:700; color:var(--primary);">
          {{ dashboard?.latest_status ? dashboard.latest_status.replace(/_/g, ' ').toUpperCase() : 'Belum ada laporan' }}
        </p>
      </article>
    </div>

    <article class="card" style="grid-column: 1 / -1;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px;">
        <h3 style="margin:0;">Riwayat Laporan Terakhir</h3>
        <RouterLink class="btn-primary" to="/gangguan/create" style="display:inline-block; font-size:0.8rem; padding:6px 12px;">+ Laporan Baru</RouterLink>
      </div>
      <div v-if="loading" style="color: var(--muted);">Memuat riwayat laporan...</div>
      <div v-else-if="dashboard?.latest_reports?.length" class="grid" style="gap: 10px;">
        <RouterLink
          v-for="report in dashboard.latest_reports"
          :key="report.id"
          :to="{ name: 'agent-laporan-detail', params: { id: report.id } }"
          style="text-decoration: none; color: inherit; display: block;"
        >
          <article
            class="card"
            style="padding: 14px; cursor: pointer; transition: box-shadow 0.18s, transform 0.18s; border: 1.5px solid var(--border);"
            onmouseover="this.style.boxShadow='0 4px 18px rgba(0,0,0,0.10)'; this.style.transform='translateY(-2px)';"
            onmouseout="this.style.boxShadow=''; this.style.transform='';"
          >
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
              <div style="flex: 1; min-width: 0;">
                <p style="margin: 0 0 4px; font-weight: 700;">{{ report.ticket_number }}</p>
                <p style="margin: 0 0 4px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ report.judul }}</p>
                <p style="margin: 0; font-size: 0.8rem; color: var(--muted);">Dibuat: {{ formatDateTime(report.created_at) }}</p>
              </div>
              <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
                <span style="padding: 5px 12px; border-radius: 999px; background: #f3f4f6; font-weight: 700; font-size: 0.82rem;">
                  {{ report.status.replace(/_/g, ' ') }}
                </span>
                <span style="padding: 5px 12px; border-radius: 999px; background: var(--primary, #2563eb); color: #fff; font-weight: 700; font-size: 0.82rem;">
                  Lihat →
                </span>
              </div>
            </div>
          </article>
        </RouterLink>
      </div>
      <p v-else style="color: var(--muted);">Belum ada riwayat laporan.</p>
    </article>
  </section>
</template>