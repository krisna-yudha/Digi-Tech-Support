import { ref, computed } from 'vue';
import api from '../services/api';

// Shared singleton state agar sinkronisasi dan jam realtime konsisten di semua halaman/komponen
const currentTime = ref(new Date());
const isSyncing = ref(false);
const isSynced = ref(false);
const lastSyncTime = ref(null);
const source = ref('bmkg');
const sourceName = ref('Server Waktu BMKG RI');
const bmkgUrl = ref('https://jam.bmkg.go.id/JamServerFD.html');
const timezone = ref('WIB');
const driftMs = ref(0); // Selisih milidetik antara Jam BMKG dan Jam PC/Device lokal
const latencyMs = ref(0);
const notice = ref('');

// Pilihan apakah detik ikut ditampilkan di tab browser title (default: false agar seperti referensi Time.is 19:14)
const showSecondsInTitle = ref(localStorage.getItem('bmkg_title_seconds') === 'true');

// Mode tampilan judul tab browser: 'time_only' (Waktu Saja) atau 'time_with_app' (Waktu + TS Monitoring)
const titleMode = ref(localStorage.getItem('bmkg_title_mode') || 'time_only');

let tickTimer = null;
let autoSyncTimer = null;

// Hari dan bulan berbahasa Indonesia sesuai format BMKG
const namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const namaBulan = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

/**
 * Format durasi selisih waktu
 */
function formatDriftDuration(seconds) {
  const absSec = Math.abs(seconds);
  if (absSec < 60) {
    return `${absSec} detik`;
  }
  const mins = Math.floor(absSec / 60);
  const remSec = absSec % 60;
  return remSec > 0 ? `${mins} menit ${remSec} detik` : `${mins} menit`;
}

/**
 * Hook dan sinkronisasi tanda waktu resmi dari BMKG
 */
async function syncWithBmkg() {
  if (isSyncing.value) return;
  isSyncing.value = true;

  const clientRequestStart = Date.now();

  try {
    // 1. Panggil endpoint backend yang menghubungkan ke https://jam.bmkg.go.id/JamServer.php
    const { data } = await api.get('/bmkg-time');

    const clientRequestEnd = Date.now();
    const roundTripLatency = clientRequestEnd - clientRequestStart;
    latencyMs.value = roundTripLatency;

    if (data && data.timestamp_ms) {
      // Estimasi waktu BMKG saat respon diterima di browser (kompensasi separuh latency jaringan)
      const estimatedBmkgTimeAtClientEnd = data.timestamp_ms + Math.round(roundTripLatency / 2);

      // driftMs = (Jam BMKG Sebenarnya) - (Jam PC Klien saat ini)
      // Jika positif (+): Jam PC Klien tertinggal / lebih lambat dari BMKG
      // Jika negatif (-): Jam PC Klien lebih cepat / mendahului BMKG
      driftMs.value = estimatedBmkgTimeAtClientEnd - clientRequestEnd;

      source.value = data.source || 'bmkg';
      sourceName.value = data.source_name || (data.source === 'bmkg' ? 'Server Waktu BMKG RI' : 'Server Sistem (Cadangan)');
      bmkgUrl.value = data.url || 'https://jam.bmkg.go.id/JamServerFD.html';
      timezone.value = data.timezone || 'WIB';
      notice.value = data.notice || '';
      isSynced.value = true;
      lastSyncTime.value = new Date();

      // Perbarui currentTime segera dengan delta baru
      currentTime.value = new Date(Date.now() + driftMs.value);
    }
  } catch (err) {
    console.warn('Gagal sinkronisasi dengan endpoint /api/bmkg-time, mencoba fallback proxy:', err);
    // Coba fallback langsung via proxy jika tersedia
    try {
      const res = await fetch('/bmkg-proxy/JamServer.php?t=' + Date.now());
      if (res.ok) {
        const text = await res.text();
        const m = text.match(/new Date\((\d+),\s*(\d+)(?:-1)?,\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)\)/i);
        if (m) {
          const clientEnd = Date.now();
          const bmkgDate = new Date(
            parseInt(m[1]),
            parseInt(m[2]) - 1,
            parseInt(m[3]),
            parseInt(m[4]),
            parseInt(m[5]),
            parseInt(m[6])
          );
          driftMs.value = bmkgDate.getTime() - clientEnd;
          source.value = 'bmkg';
          sourceName.value = 'Server Waktu BMKG RI';
          isSynced.value = true;
          lastSyncTime.value = new Date();
          currentTime.value = new Date(Date.now() + driftMs.value);
        }
      }
    } catch (fallbackErr) {
      console.error('Fallback BMKG juga gagal, menggunakan waktu lokal:', fallbackErr);
    }
  } finally {
    isSyncing.value = false;
  }
}

/**
 * Jalankan clock tick setiap detik dan jadwalkan sinkronisasi otomatis berkala (setiap 5 menit)
 */
function startBmkgClock() {
  if (!tickTimer) {
    // Tick setiap detik
    tickTimer = setInterval(() => {
      currentTime.value = new Date(Date.now() + driftMs.value);
    }, 1000);
  }

  if (!autoSyncTimer) {
    // Sinkronkan ulang otomatis setiap 5 menit agar akurasi tetap terjaga
    autoSyncTimer = setInterval(() => {
      syncWithBmkg();
    }, 5 * 60 * 1000);
  }

  // Sinkronisasi pertama kali jika belum pernah
  if (!isSynced.value) {
    syncWithBmkg();
  }
}

/**
 * Format string waktu dan tanggal
 */
const hours = computed(() => String(currentTime.value.getHours()).padStart(2, '0'));
const minutes = computed(() => String(currentTime.value.getMinutes()).padStart(2, '0'));
const seconds = computed(() => String(currentTime.value.getSeconds()).padStart(2, '0'));

// Format jam:menit:detik (contoh: 19:14:05)
const timeString = computed(() => `${hours.value}:${minutes.value}:${seconds.value}`);

// Format jam:menit (contoh: 19:14 — persis seperti referensi tab Time.is)
const shortTimeString = computed(() => `${hours.value}:${minutes.value}`);

// Format tanggal lengkap (contoh: Jumat, 23 Oktober 2026)
const dayName = computed(() => namaHari[currentTime.value.getDay()]);
const dayNum = computed(() => currentTime.value.getDate());
const monthName = computed(() => namaBulan[currentTime.value.getMonth()]);
const yearNum = computed(() => currentTime.value.getFullYear());

const dateString = computed(() => `${dayName.value}, ${dayNum.value} ${monthName.value} ${yearNum.value}`);

// Selisih dalam detik (dibulatkan)
const driftSeconds = computed(() => Math.round(driftMs.value / 1000));

// Status kesesuaian jam device vs BMKG
const driftStatus = computed(() => {
  const s = driftSeconds.value;
  if (Math.abs(s) <= 2) return 'synchronized';
  if (s > 2) return 'pc_slow'; // Jam PC Klien tertinggal
  return 'pc_fast'; // Jam PC Klien mendahului
});

// Penjelasan diagnosis ketidaksesuaian jam
const driftDiagnosis = computed(() => {
  const s = driftSeconds.value;
  if (Math.abs(s) <= 2) {
    return {
      type: 'success',
      badge: 'SINKRON',
      badgeClass: 'badge-drift-ok',
      title: 'Jam Perangkat Sesuai & Akurat',
      desc: `Waktu PC Anda selaras dengan Tanda Waktu Resmi BMKG (selisih ${Math.abs(s)} dtk). Laporan gangguan tercatat dengan presisi tinggi.`,
      recommendation: 'Tidak ada tindakan diperlukan. Jam perangkat Anda siap untuk operasional.'
    };
  }
  if (s > 2) {
    return {
      type: 'warning',
      badge: 'TIDAK SESUAI',
      badgeClass: 'badge-drift-warning',
      title: `Jam PC Tertinggal ${formatDriftDuration(s)}`,
      desc: `Waktu pada PC/perangkat Anda lebih lambat ${s} detik dibandingkan Jam Resmi BMKG RI.`,
      recommendation: 'Sistem aplikasi telah mengoreksi waktu operasional Anda secara otomatis sesuai jam BMKG.'
    };
  }
  return {
    type: 'warning',
    badge: 'TIDAK SESUAI',
    badgeClass: 'badge-drift-warning',
    title: `Jam PC Lebih Cepat ${formatDriftDuration(Math.abs(s))}`,
    desc: `Waktu pada PC/perangkat Anda mendahului ${Math.abs(s)} detik dari Jam Resmi BMKG RI.`,
    recommendation: 'Sistem aplikasi telah mengoreksi waktu operasional Anda secara otomatis sesuai jam BMKG.'
  };
});

function toggleShowSecondsInTitle() {
  showSecondsInTitle.value = !showSecondsInTitle.value;
  localStorage.setItem('bmkg_title_seconds', String(showSecondsInTitle.value));
}

function setTitleMode(newMode) {
  titleMode.value = newMode;
  localStorage.setItem('bmkg_title_mode', newMode);
}

export function useBmkgTime() {
  return {
    // Realtime values
    currentTime,
    hours,
    minutes,
    seconds,
    timeString,
    shortTimeString,
    dayName,
    dayNum,
    monthName,
    yearNum,
    dateString,
    timezone,

    // Status sinkronisasi & sumber BMKG
    isSyncing,
    isSynced,
    lastSyncTime,
    source,
    sourceName,
    bmkgUrl,
    latencyMs,
    notice,

    // Pemeriksaan ketidaksesuaian jam PC/Device
    driftMs,
    driftSeconds,
    driftStatus,
    driftDiagnosis,
    formatDriftDuration,

    // Fitur Tab Title
    showSecondsInTitle,
    toggleShowSecondsInTitle,
    titleMode,
    setTitleMode,

    // Action handlers
    syncWithBmkg,
    startBmkgClock
  };
}
