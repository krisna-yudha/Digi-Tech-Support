<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../services/api';
import { useAuthStore } from '../stores/auth';
import { useBmkgTime } from '../composables/useBmkgTime';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const bmkgTime = useBmkgTime();
const dashboard = ref(null);
const loading = ref(false);

const lastSyncFormatted = computed(() => {
  if (!bmkgTime.lastSyncTime.value) return 'Baru saja';
  return bmkgTime.lastSyncTime.value.toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  }) + ' WIB';
});

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
        <p class="agent-subtitle">Pantau status terkini dan riwayat pelaporan gangguan pelanggan.</p>
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

    <!-- ── Kartu Jam Realtime BMKG & Pemeriksaan Ketidaksesuaian Jam PC ── -->
    <div class="agent-bmkg-card" style="grid-column: 1 / -1;">
      <div class="bmkg-card-inner">
        <!-- Kolom Kiri: Realtime Digital Clock & Tanggal -->
        <div class="bmkg-clock-left">
          <div class="bmkg-badge-row">
            <span class="bmkg-tag">
              <span class="bmkg-pulse-dot"></span>
              STANDAR WAKTU OPERASIONAL
            </span>
            <span class="bmkg-source-tag">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="2" y1="12" x2="22" y2="12"></line>
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
              </svg>
              <span>{{ bmkgTime.sourceName.value }}</span>
            </span>
          </div>

          <!-- Digital Clock Display (Ticking Realtime) -->
          <div class="bmkg-time-display">
            <span class="bmkg-digits">{{ bmkgTime.timeString.value }}</span>
            <span class="bmkg-tz-badge">{{ bmkgTime.timezone.value }}</span>
          </div>

          <!-- Hari, Tanggal Lengkap & Link Sumber BMKG -->
          <div class="bmkg-date-meta">
            <div class="bmkg-date-item">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
              <span class="bmkg-date-text">{{ bmkgTime.dateString.value }}</span>
            </div>

            <span class="meta-separator">&bull;</span>

            <a
              :href="bmkgTime.bmkgUrl.value"
              target="_blank"
              rel="noopener noreferrer"
              class="bmkg-web-link"
              title="Buka Referensi Tanda Waktu Indonesia BMKG (JamServerFD.html)"
            >
              <span>jam.bmkg.go.id</span>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
              </svg>
            </a>
          </div>
        </div>

        <!-- Kolom Kanan: Pengecekan Ketidaksesuaian Jam PC / Device -->
        <div class="bmkg-drift-right">
          <div
            class="drift-status-box"
            :class="'drift-box-' + bmkgTime.driftDiagnosis.value.type"
          >
            <div class="drift-box-header">
              <div class="drift-header-title">
                <!-- Icon Hijau Jika Sinkron -->
                <svg v-if="bmkgTime.driftStatus.value === 'synchronized'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <!-- Icon Kuning Jika Ada Selisih/Ketidaksesuaian -->
                <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                  <line x1="12" y1="9" x2="12" y2="13"></line>
                  <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <h4 class="drift-title">{{ bmkgTime.driftDiagnosis.value.title }}</h4>
              </div>

              <span class="drift-status-pill" :class="bmkgTime.driftDiagnosis.value.badgeClass">
                {{ bmkgTime.driftDiagnosis.value.badge }}
              </span>
            </div>

            <p class="drift-desc">
              {{ bmkgTime.driftDiagnosis.value.desc }}
            </p>

            <div class="drift-meta-row">
              <span class="drift-meta-item">
                <strong>Selisih PC vs BMKG:</strong>
                <span class="drift-diff-number" :class="{ 'drift-diff-warn': Math.abs(bmkgTime.driftSeconds.value) > 2 }">
                  {{ bmkgTime.driftSeconds.value > 0 ? `+${bmkgTime.driftSeconds.value} dtk (PC lebih lambat)` : (bmkgTime.driftSeconds.value < 0 ? `${bmkgTime.driftSeconds.value} dtk (PC lebih cepat)` : '0 dtk (Pas)') }}
                </span>
              </span>
              <span class="meta-separator">&bull;</span>
              <span class="drift-meta-item">
                Latency: {{ bmkgTime.latencyMs.value }} ms
              </span>
            </div>
          </div>

          <!-- Bottom Control Bar (Sinkronkan Ulang & Toggle Tab Title) -->
          <div class="bmkg-controls-bar">
            <button
              type="button"
              class="btn-sync-bmkg"
              @click="bmkgTime.syncWithBmkg()"
              :disabled="bmkgTime.isSyncing.value"
              title="Sinkronkan ulang waktu dengan Server BMKG"
            >
              <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.2"
                stroke-linecap="round"
                stroke-linejoin="round"
                :class="{ 'animate-spin': bmkgTime.isSyncing.value }"
              >
                <polyline points="23 4 23 10 17 10"></polyline>
                <polyline points="1 20 1 14 7 14"></polyline>
                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
              </svg>
              <span>{{ bmkgTime.isSyncing.value ? 'Sinkronisasi...' : 'Sinkronkan Ulang' }}</span>
            </button>

            <!-- Toggle Detik pada Tab Title Browser -->
            <label class="bmkg-tab-toggle" title="Pilih apakah detik ditampilkan pada judul tab browser (Time.is)">
              <input
                type="checkbox"
                :checked="bmkgTime.showSecondsInTitle.value"
                @change="bmkgTime.toggleShowSecondsInTitle()"
              />
              <span class="toggle-label-text">Tampilkan detik di tab browser</span>
            </label>
          </div>
        </div>
      </div>
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

/* ─── Realtime BMKG Clock & Discrepancy Checker Card Styles ──────────────── */
.agent-bmkg-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
  overflow: hidden;
  position: relative;
  transition: all 0.2s ease;
}

.agent-bmkg-card:hover {
  border-color: #cbd5e1;
  box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.08);
}

.bmkg-card-inner {
  display: grid;
  grid-template-columns: 1fr 1.15fr;
  gap: 24px;
  padding: 22px 24px;
}

/* Kolom Kiri: Clock & Date */
.bmkg-clock-left {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 8px;
}

.bmkg-badge-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.bmkg-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.68rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 3px 8px;
  border-radius: 999px;
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #dbeafe;
}

.bmkg-pulse-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
  animation: bmkgPulse 1.8s infinite;
}

@keyframes bmkgPulse {
  0% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
  }
  70% {
    transform: scale(1);
    box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
  }
  100% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
  }
}

.bmkg-source-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.72rem;
  font-weight: 600;
  color: #64748b;
}

.bmkg-time-display {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin: 4px 0 2px;
}

.bmkg-digits {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  font-size: 2.75rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: #0f172a;
  line-height: 1;
}

.bmkg-tz-badge {
  font-size: 0.95rem;
  font-weight: 800;
  color: #2563eb;
  letter-spacing: 0.02em;
}

.bmkg-date-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #475569;
  font-size: 0.85rem;
  flex-wrap: wrap;
}

.bmkg-date-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
  color: #334155;
}

.bmkg-date-item svg {
  color: #2563eb;
}

.meta-separator {
  color: #cbd5e1;
}

.bmkg-web-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: #2563eb;
  font-size: 0.8rem;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.15s ease;
}

.bmkg-web-link:hover {
  color: #1d4ed8;
  text-decoration: underline;
}

/* Kolom Kanan: Pengecekan Ketidaksesuaian Jam */
.bmkg-drift-right {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 12px;
}

.drift-status-box {
  padding: 14px 16px;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  transition: all 0.2s ease;
}

.drift-box-success {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
}

.drift-box-warning {
  background: #fffbeb;
  border: 1px solid #fde68a;
}

.drift-box-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.drift-header-title {
  display: flex;
  align-items: center;
  gap: 7px;
}

.drift-title {
  margin: 0;
  font-size: 0.88rem;
  font-weight: 700;
  color: #0f172a;
}

.drift-status-pill {
  font-size: 0.68rem;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 999px;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.badge-drift-ok {
  background: #dcfce7;
  color: #15803d;
  border: 1px solid #86efac;
}

.badge-drift-warning {
  background: #fef3c7;
  color: #b45309;
  border: 1px solid #fcd34d;
}

.drift-desc {
  margin: 0;
  font-size: 0.78rem;
  line-height: 1.45;
  color: #475569;
}

.drift-meta-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.72rem;
  color: #64748b;
  margin-top: 2px;
}

.drift-diff-number {
  color: #059669;
  font-weight: 700;
}

.drift-diff-warn {
  color: #d97706 !important;
  font-weight: 800;
}

/* Bottom Controls */
.bmkg-controls-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 4px;
  flex-wrap: wrap;
}

.btn-sync-bmkg {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  color: #334155;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-sync-bmkg:hover:not(:disabled) {
  background: #e2e8f0;
  color: #0f172a;
  border-color: #cbd5e1;
}

.btn-sync-bmkg:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.bmkg-tab-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.75rem;
  color: #64748b;
  cursor: pointer;
  user-select: none;
}

.bmkg-tab-toggle input[type="checkbox"] {
  cursor: pointer;
  accent-color: #2563eb;
}

.toggle-label-text {
  font-weight: 600;
}

@media (max-width: 860px) {
  .bmkg-card-inner {
    grid-template-columns: 1fr;
    gap: 16px;
    padding: 16px;
  }
  .bmkg-digits {
    font-size: 2.2rem;
  }
}

@media (max-width: 640px) {
  .agent-header-row {
    flex-direction: column;
    align-items: stretch;
  }
  .btn-new-report-header {
    justify-content: center;
  }
  .bmkg-digits {
    font-size: 2rem;
  }
  .bmkg-controls-bar {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
  .btn-sync-bmkg {
    width: 100%;
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