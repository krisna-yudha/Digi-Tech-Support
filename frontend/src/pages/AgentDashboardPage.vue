<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../services/api';
import { useAuthStore } from '../stores/auth';
import OperationalTemplatesSection from '../components/OperationalTemplatesSection.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const dashboard = ref(null);
const loading = ref(false);

const successMessage = computed(() => {
  if (route.query.notice === 'report-submitted') {
    return 'Laporan gangguan berhasil dikirim.';
  }
  if (route.query.notice === 'joined-mass-incident') {
    return 'Berhasil mencatat Anda sebagai agent terdampak pada Gangguan Massal.';
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
  <section class="grid agent-dashboard-container">
    <!-- Header Title & Agent Greeting -->
    <div class="agent-header-row" style="grid-column: 1 / -1;">
      <div class="agent-greeting-wrap">
        <div class="agent-badge-pill">
          <span class="pulse-indicator"></span>
          <span>Agent On Duty</span>
        </div>
        <h2 class="agent-title">Halo, {{ auth.user?.name || 'Agent' }}</h2>
        <p class="agent-subtitle">Kelola template operasional dan pantau riwayat pelaporan gangguan pelanggan.</p>
      </div>

      <RouterLink to="/gangguan/create" class="btn-primary btn-new-report-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="16"></line>
          <line x1="8" y1="12" x2="16" y2="12"></line>
        </svg>
        <span>Lapor Gangguan</span>
      </RouterLink>
    </div>
    
    <!-- Success Banner -->
    <div v-if="successMessage" class="agent-notice-banner" style="grid-column: 1 / -1;">
      <div class="notice-text">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
          <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        <span>{{ successMessage }}</span>
      </div>
      <button type="button" class="btn-dismiss-notice" @click="dismissNotice">Tutup</button>
    </div>

    <!-- Stat Widgets (Native Card Style) -->
    <div class="agent-stat-grid" style="grid-column: 1 / -1;">
      <!-- Total Laporan -->
      <article class="stat-widget-card stat-card-reports">
        <div class="widget-icon-wrap icon-emerald">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
        </div>
        <div class="widget-content">
          <span class="widget-label">Total Laporan Terkirim</span>
          <div class="widget-value-row">
            <span class="widget-number">{{ loading ? '...' : (dashboard?.total_reports ?? 0) }}</span>
            <span class="widget-sub">laporan tersubmit</span>
          </div>
        </div>
      </article>

      <!-- Status Terakhir -->
      <article class="stat-widget-card stat-card-status">
        <div class="widget-icon-wrap icon-blue">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
          </svg>
        </div>
        <div class="widget-content">
          <span class="widget-label">Status Terakhir</span>
          <div class="widget-value-row">
            <span v-if="loading" class="widget-status-pill pill-loading">Memuat...</span>
            <span v-else-if="dashboard?.latest_status" class="widget-status-pill pill-active">
              <span class="status-pulse-dot"></span>
              {{ dashboard.latest_status.replace(/_/g, ' ') }}
            </span>
            <span v-else class="widget-status-pill pill-empty">
              Belum ada laporan
            </span>
          </div>
        </div>
      </article>
    </div>

    <!-- Operational Templates Section (Upload DOCX/PDF/TXT, Direct Edit, Copy, Download) -->
    <OperationalTemplatesSection />

    <!-- Riwayat Laporan Terakhir -->
    <article class="card history-section-card" style="grid-column: 1 / -1;">
      <div class="history-header-row">
        <div>
          <h3 class="history-title">Riwayat Laporan Terakhir</h3>
          <p class="history-caption">Daftar laporan gangguan terbaru yang Anda inputkan ke sistem.</p>
        </div>
        <RouterLink class="btn-outline-action" to="/gangguan/create">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          <span>Laporan Baru</span>
        </RouterLink>
      </div>

      <div v-if="loading" class="history-loading-notice">
        Memuat riwayat laporan gangguan...
      </div>

      <div v-else-if="dashboard?.latest_reports?.length" class="history-list-grid">
        <RouterLink
          v-for="report in dashboard.latest_reports"
          :key="report.id"
          :to="{ name: 'agent-laporan-detail', params: { id: report.id } }"
          class="history-item-link"
        >
          <div class="history-card">
            <div class="history-main-info">
              <div class="history-title-row">
                <span class="history-item-id">
                  {{ (report.id_task_sip && report.id_task_sip !== '-') ? report.id_task_sip : (report.judul || report.kategori || 'Gangguan') }}
                </span>
                <span v-if="report.jenis_gangguan === 'Massal'" class="badge-massal">
                  MASSAL
                </span>
              </div>
              <p class="history-item-title">{{ report.judul || report.kategori || '-' }}</p>
              <p class="history-item-meta">Dibuat: {{ formatDateTime(report.created_at) }}</p>
            </div>

            <div class="history-side-actions">
              <span class="history-status-tag">
                {{ report.status ? report.status.replace(/_/g, ' ') : 'Open' }}
              </span>
              <span class="btn-history-view">
                <span>Detail</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
              </span>
            </div>
          </div>
        </RouterLink>
      </div>

      <div v-else class="history-empty-state">
        <p>Belum ada riwayat laporan gangguan.</p>
      </div>
    </article>
  </section>
</template>

<style scoped>
.agent-dashboard-container {
  gap: 16px;
}

.agent-header-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 16px;
  flex-wrap: wrap;
}

.agent-greeting-wrap {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.agent-badge-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 10px;
  background: #ecfdf5;
  color: #047857;
  border: 1px solid #a7f3d0;
  border-radius: 999px;
  font-size: 0.74rem;
  font-weight: 700;
  width: fit-content;
}

.pulse-indicator {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
}

.agent-title {
  margin: 2px 0 0;
  font-size: 1.45rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.02em;
}

.agent-subtitle {
  margin: 0;
  font-size: 0.88rem;
  color: #64748b;
}

.btn-new-report-header {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 16px;
  font-size: 0.86rem;
  font-weight: 700;
  border-radius: 10px;
  text-decoration: none;
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
}

.agent-notice-banner {
  padding: 12px 16px;
  border-radius: 12px;
  background: #ecfdf5;
  border: 1px solid #bbf7d0;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
}

.notice-text {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #047857;
  font-weight: 700;
  font-size: 0.88rem;
}

.btn-dismiss-notice {
  background: #ffffff;
  border: 1px solid #a7f3d0;
  color: #047857;
  padding: 6px 12px;
  font-size: 0.78rem;
  font-weight: 700;
  border-radius: 6px;
  cursor: pointer;
}

/* Stat Widgets */
.agent-stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 14px;
}

.stat-widget-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px 20px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.stat-widget-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
}

.widget-icon-wrap {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.icon-emerald {
  background: #ecfdf5;
  color: #059669;
}

.icon-blue {
  background: #eff6ff;
  color: #2563eb;
}

.widget-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.widget-label {
  font-size: 0.74rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
}

.widget-value-row {
  display: flex;
  align-items: baseline;
  gap: 6px;
  flex-wrap: wrap;
}

.widget-number {
  font-size: 1.75rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.1;
}

.widget-sub {
  font-size: 0.8rem;
  color: #94a3b8;
  font-weight: 500;
}

.widget-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.84rem;
  font-weight: 700;
  text-transform: capitalize;
}

.pill-active {
  background: #eff6ff;
  color: #1d4ed8;
}

.status-pulse-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #2563eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.25);
  display: inline-block;
}

.pill-empty {
  background: #f1f5f9;
  color: #64748b;
}

.pill-loading {
  background: #f8fafc;
  color: #94a3b8;
}

/* Riwayat Section */
.history-section-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  padding: 20px;
}

.history-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
}

.history-title {
  margin: 0 0 2px;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
}

.history-caption {
  margin: 0;
  font-size: 0.82rem;
  color: #64748b;
}

.btn-outline-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  color: #334155;
  font-size: 0.78rem;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.15s ease;
}

.btn-outline-action:hover {
  border-color: #2563eb;
  color: #2563eb;
  background: #eff6ff;
}

.history-list-grid {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.history-item-link {
  text-decoration: none;
  color: inherit;
  display: block;
}

.history-card {
  padding: 14px 16px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}

.history-card:hover {
  transform: translateY(-2px);
  border-color: #cbd5e1;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
}

.history-card:active {
  transform: scale(0.99);
}

.history-main-info {
  flex: 1;
  min-width: 0;
}

.history-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.history-item-id {
  font-weight: 700;
  font-size: 0.92rem;
  color: #0f172a;
}

.badge-massal {
  background: #fee2e2;
  color: #991b1b;
  font-size: 0.68rem;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 4px;
}

.history-item-title {
  margin: 0 0 4px;
  font-size: 0.86rem;
  color: #334155;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.history-item-meta {
  margin: 0;
  font-size: 0.76rem;
  color: #94a3b8;
}

.history-side-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}

.history-status-tag {
  padding: 4px 10px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #475569;
  font-weight: 700;
  font-size: 0.78rem;
  text-transform: capitalize;
}

.btn-history-view {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 10px;
  border-radius: 8px;
  background: #eff6ff;
  color: #2563eb;
  font-weight: 700;
  font-size: 0.78rem;
}

.history-loading-notice,
.history-empty-state {
  padding: 24px;
  text-align: center;
  color: #64748b;
  font-size: 0.88rem;
}

@media (max-width: 640px) {
  .agent-header-row {
    flex-direction: column;
    align-items: stretch;
  }
  .btn-new-report-header {
    justify-content: center;
  }
  .agent-stat-grid {
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }
  .stat-widget-card {
    padding: 12px 14px;
    gap: 10px;
  }
  .widget-icon-wrap {
    width: 38px;
    height: 38px;
    border-radius: 10px;
  }
  .widget-icon-wrap svg {
    width: 18px;
    height: 18px;
  }
  .widget-number {
    font-size: 1.35rem;
  }
  .widget-status-pill {
    font-size: 0.72rem;
    padding: 3px 8px;
  }
  .history-card {
    padding: 12px;
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }
  .history-side-actions {
    width: 100%;
    justify-content: space-between;
    border-top: 1px dashed #f1f5f9;
    padding-top: 8px;
  }
}
</style>