<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../services/api';
import { useAuthStore } from '../stores/auth';
import { useBmkgTime } from '../composables/useBmkgTime';
import { useAgentBreakTimer } from '../composables/useAgentBreakTimer';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const bmkgTime = useBmkgTime();
const breakTimer = useAgentBreakTimer();

const dashboard = ref(null);
const loading = ref(false);

// State untuk input break durasi kustom
const customMinutesInput = ref(20);
const showCustomInput = ref(false);

function handleStartCustomBreak() {
  const mins = parseInt(customMinutesInput.value, 10);
  if (isNaN(mins) || mins <= 0) return;
  breakTimer.startBreak(mins, `Break Kustom (${mins}m)`, 'custom');
  showCustomInput.value = false;
}

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
        <div class="agent-badge-pill" :class="{ 'pill-on-break': breakTimer.isRunning.value }">
          <span class="pulse-indicator" :class="{ 'pulse-amber': breakTimer.isRunning.value }"></span>
          <span>{{ breakTimer.isRunning.value ? `Sedang Break (${breakTimer.formattedRemaining.value})` : 'Agent On Duty' }}</span>
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

          <!-- Bottom Control Bar (Sinkronkan Ulang, Title Mode, & Toggle Detik) -->
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

            <!-- Mode Format Judul Tab: Waktu Saja vs + Nama App -->
            <div class="bmkg-title-mode-group">
              <span class="mode-group-label">Judul Tab:</span>
              <div class="mode-segmented-pill">
                <button
                  type="button"
                  class="btn-mode-segment"
                  :class="{ active: bmkgTime.titleMode.value === 'time_only' }"
                  @click="bmkgTime.setTitleMode('time_only')"
                  title="Menampilkan waktu saja (contoh: 20:33:46) agar tab browser bersih dan terlihat jelas"
                >
                  Waktu Saja
                </button>
                <button
                  type="button"
                  class="btn-mode-segment"
                  :class="{ active: bmkgTime.titleMode.value === 'time_with_app' }"
                  @click="bmkgTime.setTitleMode('time_with_app')"
                  title="Menampilkan waktu beserta nama aplikasi (contoh: 20:33:46 - TS Monitoring)"
                >
                  + Nama App
                </button>
              </div>
            </div>

            <!-- Toggle Detik pada Tab Title Browser -->
            <label class="bmkg-tab-toggle" title="Pilih apakah detik ditampilkan pada judul tab browser (Time.is)">
              <input
                type="checkbox"
                :checked="bmkgTime.showSecondsInTitle.value"
                @change="bmkgTime.toggleShowSecondsInTitle()"
              />
              <span class="toggle-label-text">Tampilkan detik di tab</span>
            </label>
          </div>
        </div>
      </div>

      <!-- ── Section Stopwatch & Timer Break Agent (Terintegrasi) ── -->
      <div class="agent-break-section">
        <div class="break-section-header">
          <div class="break-header-left">
            <div class="break-icon-bubble" :class="{ 'pulse-active': breakTimer.isRunning.value }">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
              </svg>
            </div>
            <div>
              <div class="break-title-row">
                <h4 class="break-section-title">Stopwatch & Timer Break Agent</h4>
                <span
                  class="break-status-badge"
                  :class="'badge-status-' + breakTimer.urgencyStatus.value"
                >
                  <span v-if="breakTimer.isRunning.value" class="pulse-dot-inner"></span>
                  {{ breakTimer.isRunning.value ? (breakTimer.isPaused.value ? 'Dijeda' : (breakTimer.mode.value === 'stopwatch' ? 'Stopwatch Aktif' : 'Sedang Break')) : 'Siap / On Duty' }}
                </span>
              </div>
              <p class="break-section-desc">
                Atur durasi istirahat agent. Waktu sisa otomatis tampil realtime pada tab browser.
              </p>
            </div>
          </div>

          <div class="break-header-right">
            <!-- Toggle Suara Alarm -->
            <button
              type="button"
              class="btn-break-sound-toggle"
              :class="{ 'sound-active': breakTimer.soundEnabled.value }"
              @click="breakTimer.toggleSound()"
              :title="breakTimer.soundEnabled.value ? 'Suara alarm aktif saat waktu break habis' : 'Suara alarm dimatikan'"
            >
              <svg v-if="breakTimer.soundEnabled.value" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
              </svg>
              <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                <path d="M18.63 13A17.89 17.89 0 0 1 18 8"></path>
                <path d="M6.26 6.26A5.86 5.86 0 0 0 6 8c0 7-3 9-3 9h14"></path>
                <path d="M18 8a6 6 0 0 0-9.33-5"></path>
                <line x1="1" y1="1" x2="23" y2="23"></line>
              </svg>
              <span>{{ breakTimer.soundEnabled.value ? 'Alarm Aktif' : 'Alarm Hening' }}</span>
            </button>
          </div>
        </div>

        <!-- JIKA SEDANG AKTIF BREAK -->
        <div v-if="breakTimer.isRunning.value" class="break-active-panel" :class="'panel-urgency-' + breakTimer.urgencyStatus.value">
          <div class="active-break-row">
            <div class="active-break-time-block">
              <span class="active-break-label">
                {{ breakTimer.mode.value === 'stopwatch' ? 'DURASI STOPWATCH BERJALAN' : (breakTimer.isOvertime.value ? '⚠️ WAKTU ISTIRAHAT HABIS (OVERTIME)' : 'SISA WAKTU ISTIRAHAT') }}
              </span>
              <div class="active-break-digits-wrap">
                <span class="active-break-digits" :class="{ 'digits-overtime': breakTimer.isOvertime.value }">
                  {{ breakTimer.isOvertime.value ? breakTimer.overtimeFormatted.value : breakTimer.formattedRemaining.value }}
                </span>
                <span class="active-break-type-pill">{{ breakTimer.breakLabel.value }}</span>
              </div>
            </div>

            <!-- Tombol Kontrol Saat Aktif -->
            <div class="active-break-actions">
              <button
                v-if="!breakTimer.isPaused.value"
                type="button"
                class="btn-break-ctrl btn-break-pause"
                @click="breakTimer.pauseBreak()"
                title="Jeda timer break sementara"
              >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="6" y="4" width="4" height="16"></rect>
                  <rect x="14" y="4" width="4" height="16"></rect>
                </svg>
                <span>Jeda</span>
              </button>
              <button
                v-else
                type="button"
                class="btn-break-ctrl btn-break-resume"
                @click="breakTimer.resumeBreak()"
                title="Lanjutkan timer break"
              >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                  <polygon points="5 3 19 12 5 21 5 3"></polygon>
                </svg>
                <span>Lanjutkan</span>
              </button>

              <button
                type="button"
                class="btn-break-ctrl btn-break-finish"
                @click="breakTimer.finishBreak()"
                title="Selesaikan break dan kembali On Duty"
              >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span>Selesai Break (On Duty)</span>
              </button>

              <button
                type="button"
                class="btn-break-ctrl btn-break-cancel"
                @click="breakTimer.cancelBreak()"
                title="Batalkan sesi break ini"
              >
                &times; Batal
              </button>
            </div>
          </div>

          <!-- Progress Bar (Countdown Mode) -->
          <div v-if="breakTimer.mode.value === 'countdown'" class="break-progress-wrap">
            <div class="break-progress-bar">
              <div
                class="break-progress-fill"
                :style="{ width: breakTimer.progressPercent.value + '%' }"
                :class="'fill-' + breakTimer.urgencyStatus.value"
              ></div>
            </div>
            <div class="break-progress-legend">
              <span>Waktu Berjalan: {{ breakTimer.formattedElapsed.value }}</span>
              <span class="legend-percent">{{ breakTimer.progressPercent.value }}%</span>
              <span>Target: {{ breakTimer.targetMinutes.value }} menit</span>
            </div>
          </div>

          <!-- Tab Title Helper Note -->
          <div class="break-tab-hint">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="16" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <span>Sisa break tampil otomatis di judul tab browser (<strong>{{ breakTimer.titleSnippet.value }}</strong>). Anda dapat memantau waktu istirahat sambil membuka tab lain (Facebook, YouTube, dll).</span>
          </div>
        </div>

        <!-- JIKA IDLE (TAMPILKAN PILIHAN PRESET & STOPWATCH) -->
        <div v-else class="break-presets-panel">
          <div class="break-presets-row">
            <span class="preset-group-label">Pilih Durasi:</span>

            <button
              type="button"
              class="btn-preset-pill"
              @click="breakTimer.startBreak(15, 'Coffee / Toilet Break (15m)', '15m')"
            >
              <span class="preset-pill-icon">☕</span>
              <span class="preset-pill-title">15 Menit</span>
              <span class="preset-pill-sub">Short Break</span>
            </button>

            <button
              type="button"
              class="btn-preset-pill btn-preset-highlight"
              @click="breakTimer.startBreak(30, 'Istirahat Makan / Ishoma (30m)', '30m')"
            >
              <span class="preset-pill-icon">🍱</span>
              <span class="preset-pill-title">30 Menit</span>
              <span class="preset-pill-sub">Ishoma / Makan</span>
            </button>

            <button
              type="button"
              class="btn-preset-pill"
              @click="breakTimer.startBreak(45, 'Istirahat Panjang (45m)', '45m')"
            >
              <span class="preset-pill-icon">🍽️</span>
              <span class="preset-pill-title">45 Menit</span>
              <span class="preset-pill-sub">Salat & Makan</span>
            </button>

            <button
              type="button"
              class="btn-preset-pill"
              @click="breakTimer.startBreak(60, 'Full Break 1 Jam (60m)', '60m')"
            >
              <span class="preset-pill-icon">⏳</span>
              <span class="preset-pill-title">60 Menit</span>
              <span class="preset-pill-sub">Full Break</span>
            </button>

            <button
              type="button"
              class="btn-preset-pill btn-preset-stopwatch"
              @click="breakTimer.startStopwatch('Stopwatch Break Bebas')"
              title="Hitung maju dari 00:00 tanpa batas waktu"
            >
              <span class="preset-pill-icon">⏱️</span>
              <span class="preset-pill-title">Stopwatch</span>
              <span class="preset-pill-sub">Hitung Maju</span>
            </button>

            <button
              type="button"
              class="btn-preset-pill btn-preset-custom-toggle"
              :class="{ active: showCustomInput }"
              @click="showCustomInput = !showCustomInput"
            >
              <span class="preset-pill-icon">⚙️</span>
              <span class="preset-pill-title">Kustom</span>
              <span class="preset-pill-sub">Ketik Menit</span>
            </button>
          </div>

          <!-- Form Input Kustom Menit jika dibuka -->
          <div v-if="showCustomInput" class="custom-break-row">
            <label class="custom-break-label">Tentukan Menit Istirahat:</label>
            <input
              type="number"
              v-model.number="customMinutesInput"
              min="1"
              max="240"
              class="custom-minutes-input"
              placeholder="1-240"
            />
            <span class="custom-input-unit">Menit</span>
            <button
              type="button"
              class="btn-start-custom"
              @click="handleStartCustomBreak"
            >
              Mulai Break {{ customMinutesInput }} Menit
            </button>
            <button
              type="button"
              class="btn-cancel-custom"
              @click="showCustomInput = false"
            >
              Batal
            </button>
          </div>

          <!-- Catatan Riwayat Break Terakhir (Jika ada) -->
          <div v-if="breakTimer.lastBreakRecord.value" class="last-break-history-row">
            <span class="last-break-icon">📋</span>
            <span class="last-break-text">
              Break Terakhir: <strong>{{ breakTimer.lastBreakRecord.value.label }}</strong> &bull; Pukul {{ breakTimer.lastBreakRecord.value.startTime }} - {{ breakTimer.lastBreakRecord.value.endTime }} (Durasi: {{ breakTimer.lastBreakRecord.value.actualDurationFormatted }})
              <span v-if="breakTimer.lastBreakRecord.value.wasOvertime" class="tag-overtime-record">Overtime</span>
            </span>
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
  grid-template-columns: 1.22fr 1fr;
  gap: 24px;
  padding: 22px 24px;
  align-items: center;
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
  gap: 10px;
  margin: 6px 0 6px;
}

.bmkg-digits {
  font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  font-variant-numeric: tabular-nums;
  font-size: 4.15rem;
  font-weight: 900;
  letter-spacing: -0.04em;
  color: #0f172a;
  line-height: 1;
}

.bmkg-tz-badge {
  font-size: 1.1rem;
  font-weight: 800;
  color: #2563eb;
  letter-spacing: 0.03em;
  background: #eff6ff;
  border: 1px solid #dbeafe;
  padding: 3px 8px;
  border-radius: 6px;
  align-self: center;
}

.bmkg-date-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #475569;
  font-size: 0.92rem;
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

@media (max-width: 1024px) {
  .bmkg-card-inner {
    grid-template-columns: 1.15fr 1fr;
    gap: 20px;
    padding: 20px;
  }
  .bmkg-digits {
    font-size: 3.4rem;
  }
}

@media (max-width: 860px) {
  .bmkg-card-inner {
    grid-template-columns: 1fr;
    gap: 16px;
    padding: 18px;
  }
  .bmkg-digits {
    font-size: 3rem;
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
    font-size: 2.35rem;
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

/* ─── Mode Title Tab Segmented Control ───────────────────────── */
.bmkg-title-mode-group {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.75rem;
}

.mode-group-label {
  color: #64748b;
  font-weight: 600;
}

.mode-segmented-pill {
  display: inline-flex;
  align-items: center;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  padding: 2px;
  gap: 2px;
}

.btn-mode-segment {
  padding: 3px 9px;
  border-radius: 999px;
  border: none;
  background: transparent;
  color: #64748b;
  font-size: 0.72rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
  line-height: 1.2;
}

.btn-mode-segment:hover {
  color: #0f172a;
}

.btn-mode-segment.active {
  background: #2563eb;
  color: #ffffff;
  box-shadow: 0 1px 3px rgba(37, 99, 235, 0.3);
}

/* ─── Greeting Pill When On Break ────────────────────────────── */
.pill-on-break {
  background: #fff7ed !important;
  border-color: #ffedd5 !important;
  color: #c2410c !important;
}

.pulse-amber {
  background: #ea580c !important;
  box-shadow: 0 0 0 0 rgba(234, 88, 12, 0.7) !important;
  animation: amberPulse 1.6s infinite !important;
}

@keyframes amberPulse {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(234, 88, 12, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(234, 88, 12, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(234, 88, 12, 0); }
}

/* ─── Break Section (Stopwatch & Countdown) ───────────────────── */
.agent-break-section {
  border-top: 1px solid #f1f5f9;
  background: #fafbfc;
  padding: 18px 24px 22px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.break-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.break-header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.break-icon-bubble {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: #eff6ff;
  border: 1px solid #dbeafe;
  color: #2563eb;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.2s ease;
}

.break-icon-bubble.pulse-active {
  background: #fff7ed;
  border-color: #fed7aa;
  color: #ea580c;
  animation: bubblePulse 2s infinite;
}

@keyframes bubblePulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}

.break-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.break-section-title {
  margin: 0;
  font-size: 0.98rem;
  font-weight: 800;
  color: #0f172a;
}

.break-section-desc {
  margin: 2px 0 0;
  font-size: 0.76rem;
  color: #64748b;
}

.break-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.68rem;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 999px;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.pulse-dot-inner {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  animation: dotBlink 1s infinite;
}

@keyframes dotBlink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}

.badge-status-idle {
  background: #f1f5f9;
  color: #475569;
}

.badge-status-normal,
.badge-status-stopwatch {
  background: #dbeafe;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
}

.badge-status-warning {
  background: #fef3c7;
  color: #b45309;
  border: 1px solid #fde68a;
}

.badge-status-overtime {
  background: #fee2e2;
  color: #dc2626;
  border: 1px solid #fecaca;
  animation: overtimeBlink 1.5s infinite;
}

@keyframes overtimeBlink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}

.btn-break-sound-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #64748b;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-break-sound-toggle:hover {
  background: #f8fafc;
  color: #0f172a;
}

.btn-break-sound-toggle.sound-active {
  background: #eff6ff;
  border-color: #bfdbfe;
  color: #2563eb;
}

/* ─── Active Break Panel ─────────────────────────────────────── */
.break-active-panel {
  background: #ffffff;
  border-radius: 14px;
  border: 1.5px solid #bfdbfe;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.06);
}

.panel-urgency-warning {
  border-color: #fcd34d;
  background: #fffdf5;
  box-shadow: 0 4px 14px rgba(217, 119, 6, 0.08);
}

.panel-urgency-overtime {
  border-color: #fca5a5;
  background: #fffafa;
  box-shadow: 0 4px 14px rgba(220, 38, 38, 0.1);
}

.active-break-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.active-break-time-block {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.active-break-label {
  font-size: 0.7rem;
  font-weight: 800;
  color: #64748b;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.active-break-digits-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
}

.active-break-digits {
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  font-variant-numeric: tabular-nums;
  font-size: 2.85rem;
  font-weight: 900;
  color: #1e3a8a;
  line-height: 1;
  letter-spacing: -0.03em;
}

.digits-overtime {
  color: #dc2626 !important;
}

.active-break-type-pill {
  font-size: 0.78rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 999px;
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #dbeafe;
}

.active-break-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-break-ctrl {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 9px;
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
  border: none;
}

.btn-break-pause {
  background: #fef3c7;
  color: #b45309;
  border: 1px solid #fde68a;
}

.btn-break-pause:hover {
  background: #fde68a;
  color: #92400e;
}

.btn-break-resume {
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
}

.btn-break-resume:hover {
  background: #dbeafe;
}

.btn-break-finish {
  background: #10b981;
  color: #ffffff;
  box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25);
}

.btn-break-finish:hover {
  background: #059669;
}

.btn-break-cancel {
  background: #f1f5f9;
  color: #64748b;
  border: 1px solid #e2e8f0;
}

.btn-break-cancel:hover {
  background: #fee2e2;
  color: #dc2626;
  border-color: #fecaca;
}

/* Progress bar */
.break-progress-wrap {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.break-progress-bar {
  width: 100%;
  height: 8px;
  border-radius: 999px;
  background: #e2e8f0;
  overflow: hidden;
}

.break-progress-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.5s ease;
}

.fill-normal,
.fill-stopwatch {
  background: linear-gradient(90deg, #3b82f6, #2563eb);
}

.fill-warning {
  background: linear-gradient(90deg, #f59e0b, #d97706);
}

.fill-overtime {
  background: linear-gradient(90deg, #ef4444, #dc2626);
}

.break-progress-legend {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.72rem;
  color: #64748b;
  font-weight: 600;
}

.legend-percent {
  font-weight: 800;
  color: #0f172a;
}

.break-tab-hint {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.74rem;
  color: #475569;
  background: #f8fafc;
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid #f1f5f9;
}

.break-tab-hint svg {
  color: #2563eb;
  flex-shrink: 0;
}

/* ─── Idle Presets Panel ─────────────────────────────────────── */
.break-presets-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.break-presets-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.preset-group-label {
  font-size: 0.76rem;
  font-weight: 700;
  color: #475569;
  margin-right: 4px;
}

.btn-preset-pill {
  display: inline-flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 8px 14px;
  border-radius: 10px;
  background: #ffffff;
  border: 1.5px solid #e2e8f0;
  cursor: pointer;
  transition: all 0.15s ease;
  text-align: left;
}

.btn-preset-pill:hover {
  border-color: #93c5fd;
  background: #f8fafc;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.btn-preset-highlight {
  border-color: #bfdbfe;
  background: #eff6ff;
}

.btn-preset-highlight:hover {
  border-color: #3b82f6;
  background: #dbeafe;
}

.btn-preset-stopwatch {
  border-color: #d1fae5;
  background: #ecfdf5;
}

.btn-preset-stopwatch:hover {
  border-color: #10b981;
  background: #d1fae5;
}

.btn-preset-custom-toggle.active {
  border-color: #2563eb;
  background: #eff6ff;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
}

.preset-pill-icon {
  font-size: 1rem;
  line-height: 1;
}

.preset-pill-title {
  font-size: 0.82rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.2;
}

.preset-pill-sub {
  font-size: 0.68rem;
  color: #64748b;
  font-weight: 600;
}

/* Custom Minutes Input Row */
.custom-break-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 10px;
  background: #ffffff;
  border: 1px solid #bfdbfe;
  animation: fadeIn 0.2s ease;
  flex-wrap: wrap;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}

.custom-break-label {
  font-size: 0.8rem;
  font-weight: 700;
  color: #334155;
}

.custom-minutes-input {
  width: 75px;
  padding: 6px 10px;
  border-radius: 8px;
  border: 1.5px solid #cbd5e1;
  font-size: 0.88rem;
  font-weight: 700;
  color: #0f172a;
  outline: none;
  box-sizing: border-box;
}

.custom-minutes-input:focus {
  border-color: #2563eb;
}

.custom-input-unit {
  font-size: 0.8rem;
  color: #64748b;
  font-weight: 600;
}

.btn-start-custom {
  padding: 7px 14px;
  border-radius: 8px;
  background: #2563eb;
  color: #ffffff;
  border: none;
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s ease;
}

.btn-start-custom:hover {
  background: #1d4ed8;
}

.btn-cancel-custom {
  padding: 7px 12px;
  border-radius: 8px;
  background: #f1f5f9;
  color: #64748b;
  border: 1px solid #e2e8f0;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-cancel-custom:hover {
  background: #e2e8f0;
  color: #0f172a;
}

/* Last break history row */
.last-break-history-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.74rem;
  color: #64748b;
  padding-top: 4px;
}

.last-break-icon {
  font-size: 0.85rem;
}

.tag-overtime-record {
  margin-left: 6px;
  font-size: 0.65rem;
  font-weight: 800;
  background: #fee2e2;
  color: #dc2626;
  padding: 1px 6px;
  border-radius: 4px;
  text-transform: uppercase;
}

@media (max-width: 640px) {
  .agent-break-section {
    padding: 14px 16px;
  }
  .active-break-digits {
    font-size: 2.2rem;
  }
  .active-break-actions {
    width: 100%;
    justify-content: stretch;
  }
  .active-break-actions .btn-break-ctrl {
    flex: 1;
    justify-content: center;
  }
  .break-presets-row {
    flex-direction: column;
    align-items: stretch;
  }
  .btn-preset-pill {
    width: 100%;
  }
}
</style>