<script setup>
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();

const loading = ref(false);
const error   = ref('');
const msg     = ref('');

const stats = ref({
  total_handled: 0,
  total_closed: 0,
  total_active: 0,
  avg_duration_minutes: 0,
  gangguan_list: [],
});

// State User Signature
const userSigFile     = ref(null);
const userSigPreview  = ref(null);
const userSigUploading= ref(false);

// State Template BA Settings for Print
const baSettings = ref({
  ba_brand_name:    'PLN Icon Plus',
  ba_departemen:    'Divisi Perencanaan Ops Ritel',
  ba_title:         'BERITA ACARA KRONOLOGIS GANGGUAN APLIKASI/JARINGAN',
  ba_location:      'SEMARANG',
  ba_koord_name:    'AHMAD ZAENAL ARIFIN',
  ba_koord_title:   'KOORDINATOR',
  ba_ts_title:      'TECHNICAL SUPPORT',
  ba_show_evidence: 'true',
  ba_logo_url:      null,
  ba_koord_signature_url: null,
});

const printableItems = ref([]);

function formatDateOnly(v) {
  if (!v) return '-';
  return new Date(v).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
function formatDateFull(v) {
  if (!v) return '-';
  return new Date(v).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
}
function formatTimeOnly(v) {
  if (!v) return '-';
  return new Date(v).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).replace(/\./g, ':');
}
function getMonthName(dateStr) {
  if (!dateStr) return 'AGUSTUS';
  const months = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'];
  return months[new Date(dateStr).getMonth()] || 'AGUSTUS';
}
function getYear(dateStr) {
  if (!dateStr) return '2026';
  return new Date(dateStr).getFullYear();
}
function calculateDurasi(start, end) {
  if (!start || !end) return '-';
  const diffMs = new Date(end) - new Date(start);
  if (diffMs < 0) return '-';
  const diffSecs = Math.floor(diffMs / 1000);
  const h = Math.floor(diffSecs / 3600);
  const m = Math.floor((diffSecs % 3600) / 60);
  const s = diffSecs % 60;
  return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
}

async function fetchMyStats() {
  loading.value = true;
  error.value   = '';
  try {
    const { data } = await api.get('/ts/my-stats');
    stats.value = data;
    // Hanya update signature_url jika ada, JANGAN overwrite seluruh auth.user
    // karena bisa merusak format roles di auth store
    if (data.user?.signature_url !== undefined && auth.user) {
      auth.user.signature_url = data.user.signature_url;
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal memuat statistik penanganan gangguan TS.';
  }
  loading.value = false;
}

async function fetchBaSettings() {
  try {
    const { data } = await api.get('/settings');
    if (data) {
      baSettings.value = { ...baSettings.value, ...data };
    }
  } catch (err) {}
}

// ───── Helper Paste Image Clipboard ─────
function extractImageFromPaste(e) {
  const items = e.clipboardData?.items;
  if (!items) return null;
  for (let i = 0; i < items.length; i++) {
    if (items[i].type.indexOf('image') !== -1) {
      const blob = items[i].getAsFile();
      if (blob) {
        return new File([blob], `pasted_ts_signature_${Date.now()}.png`, { type: blob.type });
      }
    }
  }
  return null;
}

function setUserSigFile(file) {
  if (!file) return;
  userSigFile.value = file;
  userSigPreview.value = URL.createObjectURL(file);
}

function clearUserSigDraft() {
  userSigFile.value = null;
  userSigPreview.value = null;
}

function onUserSigSelected(e) {
  const file = e.target.files[0];
  if (file) setUserSigFile(file);
}

function onUserSigPaste(e) {
  const file = extractImageFromPaste(e);
  if (file) {
    e.preventDefault();
    setUserSigFile(file);
    msg.value = 'Gambar screenshot tanda tangan digital Anda berhasil disalin!';
  }
}

async function uploadUserSig() {
  if (!userSigFile.value) return;
  userSigUploading.value = true;
  msg.value   = '';
  error.value = '';

  const fd = new FormData();
  fd.append('signature', userSigFile.value);

  try {
    const { data } = await api.post('/users/upload-signature', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    if (auth.user) {
      auth.user.signature_url = data.signature_url;
    }
    msg.value = data.message || 'Tanda tangan digital Anda berhasil diunggah.';
    clearUserSigDraft();
    fetchMyStats();
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal mengunggah tanda tangan digital.';
  } finally {
    userSigUploading.value = false;
  }
}

async function deleteUploadedUserSig() {
  if (!confirm('Apakah Anda yakin ingin menghapus tanda tangan digital Anda?')) return;
  try {
    const { data } = await api.delete('/users/signature');
    if (auth.user) {
      auth.user.signature_url = null;
    }
    clearUserSigDraft();
    msg.value = data.message || 'Tanda tangan digital berhasil dihapus.';
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal menghapus tanda tangan digital.';
  }
}

// ───── Export Berita Acara (Single Ticket) ─────
const showPrintPreview = ref(false);

function exportPdfSingle(item) {
  printableItems.value = [item];
  showPrintPreview.value = true;
}

function executePrint() {
  window.print();
}

function exportExcelSingle(item) {
  if (!item) return;
  const d = item;
  const s = baSettings.value;
  const filename = `Berita_Acara_Gangguan_${d.ticket_number || 'Tiket'}.xls`;

  const excelContent = `
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
      <meta charset="utf-8">
      <style>
        table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px; width: 100%; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; vertical-align: top; }
        .header-title { font-weight: bold; font-size: 14px; text-align: center; background-color: #f1f5f9; }
        .th-blue { background-color: #1d4ed8; color: #ffffff; font-weight: bold; text-align: center; }
        .bg-total { background-color: #2563eb; color: #ffffff; font-weight: bold; }
      </style>
    </head>
    <body>
      <table>
        <tr>
          <td colspan="2"><b>Halaman:</b> 1</td>
          <td colspan="4"><b>Departemen:</b> ${s.ba_departemen}</td>
          <td colspan="7" class="header-title">${s.ba_brand_name}<br/>${s.ba_title}</td>
        </tr>
        <tr>
          <td colspan="4"><b>Nomor Surat:</b> ${d.ticket_number || '-'}</td>
          <td colspan="4"><b>Kubikal:</b> ${d.kategori || '-'}</td>
          <td colspan="3"><b>Periode:</b> ${getYear(d.created_at)}</td>
          <td colspan="2"><b>Bulan:</b> ${getMonthName(d.created_at)}</td>
        </tr>
        <tr>
          <td colspan="4"><b>Nama Perangkat:</b> ${d.kategori || '-'}</td>
          <td colspan="9"><b>Kode:</b> -</td>
        </tr>
        <tr><td colspan="13"></td></tr>
        <tr class="th-blue">
          <th>No</th>
          <th>Hari/Tanggal</th>
          <th>Start Downtime (h:mm:ss)</th>
          <th>End Downtime (h:mm:ss)</th>
          <th>Nama Agent</th>
          <th>Ext / IP</th>
          <th>ID Task SIP</th>
          <th>Penyebab Permasalahan</th>
          <th>Penyelesaian Masalah</th>
          <th>Impact</th>
          <th>Durasi Downtime</th>
          <th>Petugas TS (Shift...)</th>
          <th>Analisa</th>
        </tr>
        <tr>
          <td style="text-align:center;">1</td>
          <td>${formatDateFull(d.created_at)}</td>
          <td>${formatTimeOnly(d.start_time || d.created_at)}</td>
          <td>${formatTimeOnly(d.end_time || d.resolved_at)}</td>
          <td>${d.jenis_gangguan === 'Massal' ? 'ALL AGENT' : (d.creator?.name || '-')}</td>
          <td>-</td>
          <td>${d.ticket_number || '-'}</td>
          <td>${d.penyebab_permasalahan || d.deskripsi || '-'}</td>
          <td>${d.penyelesaian_masalah || '-'}</td>
          <td>${d.impact || '-'}</td>
          <td>${calculateDurasi(d.start_time, d.end_time)}</td>
          <td>${d.assignee?.name || '-'}</td>
          <td>${d.analisa || '-'}</td>
        </tr>
        <tr class="bg-total">
          <td colspan="10" style="text-align:right;">TOTAL DOWN TIME :</td>
          <td colspan="3">${calculateDurasi(d.start_time, d.end_time)}</td>
        </tr>
        <tr><td colspan="13"></td></tr>
        <tr>
          <td colspan="7"></td>
          <td colspan="6" style="text-align:center;">${s.ba_location}, ${formatDateFull(new Date())}<br/><br/>
            <b>${s.ba_koord_title}</b> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>TEAM SUPPORT</b><br/><br/><br/><br/>
            ${s.ba_koord_title}: [ ${s.ba_koord_name} ] &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ${s.ba_ts_title}: [ ${d.assignee?.name || 'Petugas TS'} ]
          </td>
        </tr>
      </table>
    </body>
    </html>
  `;

  const blob = new Blob([excelContent], { type: 'application/vnd.ms-excel;charset=utf-8' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

onMounted(() => {
  fetchMyStats();
  fetchBaSettings();
});
</script>

<template>
  <div style="display: flex; flex-direction: column; gap: 24px; width: 100%;">
    
    <!-- Header Title Banner -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
      <div>
        <h2 class="page-title" style="margin: 0;">Digi TS - Kinerja & Tanda Tangan Digital</h2>
        <p style="margin: 4px 0 0; color: var(--muted); font-size: 0.88rem;">
          Kelola tanda tangan digital Anda dan pantau seluruh riwayat Berita Acara & penanganan gangguan Anda.
        </p>
      </div>

      <div style="display: flex; align-items: center; gap: 12px; background: #ffffff; padding: 8px 16px; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
        <div style="width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; font-weight: 800; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
          {{ auth.user?.name ? auth.user.name.charAt(0).toUpperCase() : 'T' }}
        </div>
        <div>
          <div style="font-weight: 700; font-size: 0.9rem; color: #1e293b;">{{ auth.user?.name }}</div>
          <div style="font-size: 0.75rem; color: #64748b;">Technical Support (TS)</div>
        </div>
      </div>
    </div>

    <!-- Notification Alert -->
    <div v-if="msg" style="padding: 12px 16px; background: #d1fae5; color: #065f46; border-radius: 8px; font-size: 0.88rem; font-weight: 600;">
      ✅ {{ msg }}
    </div>
    <div v-if="error" style="padding: 12px 16px; background: #fee2e2; color: #991b1b; border-radius: 8px; font-size: 0.88rem; font-weight: 600;">
      ❌ {{ error }}
    </div>

    <!-- 1. SECTION UPLOAD TANDA TANGAN DIGITAL TS -->
    <div class="card" style="padding: 24px; border-radius: 12px; background: #ffffff; border: 1.5px solid #93c5fd; box-shadow: 0 4px 16px rgba(37,99,235,0.06);">
      <div style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
          <h3 style="margin: 0 0 4px; font-size: 1.1rem; font-weight: 700; color: #1e3a8a;">
            ✍️ Tanda Tangan Digital Saya
          </h3>
          <p style="margin: 0; font-size: 0.84rem; color: #475569;">
            Tanda tangan ini otomatis terpasang di kolom <strong>TECHNICAL SUPPORT</strong> pada dokumen PDF & Excel Berita Acara yang Anda tangani.
          </p>
        </div>

        <span v-if="auth.user?.signature_url" style="font-size: 0.78rem; font-weight: 700; background: #d1fae5; color: #065f46; padding: 5px 12px; border-radius: 999px; display: inline-flex; align-items: center; gap: 6px;">
          <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span> Tanda Tangan Aktif
        </span>
        <span v-else style="font-size: 0.78rem; font-weight: 700; background: #fee2e2; color: #991b1b; padding: 5px 12px; border-radius: 999px;">
          ⚠️ Belum Di-upload
        </span>
      </div>

      <div 
        tabindex="0"
        @paste="onUserSigPaste"
        class="paste-dropzone"
        style="display: flex; align-items: center; gap: 24px; flex-wrap: wrap; background: #f8fafc; padding: 20px; border-radius: 12px; border: 2px dashed #93c5fd; outline: none; cursor: pointer;"
      >
        <!-- Box Preview Signature -->
        <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
          <div style="width: 180px; height: 90px; border: 1.5px dashed #cbd5e1; border-radius: 10px; background: #ffffff; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <img v-if="userSigPreview" :src="userSigPreview" style="max-width: 100%; max-height: 100%; object-fit: contain; border: 2px solid #2563eb;" title="Preview Tanda Tangan Baru" />
            <img v-else-if="auth.user?.signature_url" :src="auth.user.signature_url" style="max-width: 100%; max-height: 100%; object-fit: contain;" />
            <span v-else style="font-size: 0.78rem; color: #94a3b8; text-align: center; padding: 6px; line-height: 1.3;">Belum Ada Tanda Tangan</span>
          </div>

          <button v-if="auth.user?.signature_url && !userSigPreview" @click.stop="deleteUploadedUserSig" class="btn-delete-link">
            🗑️ Hapus Tanda Tangan Saya
          </button>
        </div>

        <!-- Form Upload & Instruction -->
        <div style="flex: 1; min-width: 260px; display: flex; flex-direction: column; gap: 12px;">
          <div style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 700; color: #1d4ed8;">
            <span>📋 Klik di sini lalu tekan <kbd style="background: #e2e8f0; padding: 2px 7px; border-radius: 4px; font-family: monospace;">Ctrl + V</kbd> untuk Copas Screenshot Tanda Tangan</span>
          </div>

          <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="file" accept="image/*" @change="onUserSigSelected" class="setting-input" style="padding: 7px 12px; font-size: 0.83rem; flex: 1; min-width: 220px;" />
            <button @click="uploadUserSig" :disabled="!userSigFile || userSigUploading" class="btn-primary" style="white-space: nowrap; padding: 9px 20px; font-size: 0.85rem; font-weight: 700;">
              {{ userSigUploading ? 'Mengunggah...' : 'Upload Tanda Tangan Saya' }}
            </button>
          </div>

          <!-- Indicator File Terdeteksi -->
          <div v-if="userSigFile" style="display: flex; align-items: center; justify-content: space-between; background: #e0f2fe; padding: 8px 14px; border-radius: 8px; border: 1px solid #bae6fd;">
            <span style="font-size: 0.8rem; color: #0369a1; font-weight: 700;">
              ✓ Gambar terdeteksi: {{ userSigFile.name }} (Siap di-upload)
            </span>
            <button @click="clearUserSigDraft" style="background: none; border: none; color: #ef4444; font-weight: 700; cursor: pointer; font-size: 0.8rem;" title="Batal pilihan gambar ini">
              ✕ Batal / Hapus Draft
            </button>
          </div>
        </div>

      </div>
    </div>

    <!-- 2. KARTU STATISTIK KINERJA TS (4 METRIC CARDS) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
      
      <!-- Card 1: Total Ditangani -->
      <div class="card" style="padding: 20px; border-radius: 12px; background: linear-gradient(135deg, #ffffff, #f8fafc); border: 1px solid #e2e8f0; border-left: 5px solid #2563eb; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
        <div style="font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">
          Total Gangguan Ditangani
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: #1e293b;">
          {{ loading ? '...' : stats.total_handled }}
        </div>
        <div style="font-size: 0.75rem; color: #64748b; margin-top: 4px;">
          Tiket gangguan yang ditugaskan ke Anda
        </div>
      </div>

      <!-- Card 2: Berita Acara Diselesaikan -->
      <div class="card" style="padding: 20px; border-radius: 12px; background: linear-gradient(135deg, #ffffff, #f0fdf4); border: 1px solid #bbf7d0; border-left: 5px solid #16a34a; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
        <div style="font-size: 0.78rem; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">
          Berita Acara Diselesaikan
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: #15803d;">
          {{ loading ? '...' : stats.total_closed }}
        </div>
        <div style="font-size: 0.75rem; color: #166534; margin-top: 4px;">
          Gangguan berstatus CLOSED (Terbit BA)
        </div>
      </div>

      <!-- Card 3: Gangguan Aktif -->
      <div class="card" style="padding: 20px; border-radius: 12px; background: linear-gradient(135deg, #ffffff, #fffbe6); border: 1px solid #fef08a; border-left: 5px solid #d97706; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
        <div style="font-size: 0.78rem; font-weight: 700; color: #854d0e; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">
          Gangguan Aktif Handled
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: #b45309;">
          {{ loading ? '...' : stats.total_active }}
        </div>
        <div style="font-size: 0.75rem; color: #854d0e; margin-top: 4px;">
          Gangguan Open / In Progress
        </div>
      </div>

      <!-- Card 4: Rata-Rata Durasi -->
      <div class="card" style="padding: 20px; border-radius: 12px; background: linear-gradient(135deg, #ffffff, #eff6ff); border: 1px solid #bfdbfe; border-left: 5px solid #0284c7; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
        <div style="font-size: 0.78rem; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">
          Rata-Rata Waktu Penanganan
        </div>
        <div style="font-size: 1.8rem; font-weight: 800; color: #0369a1;">
          {{ loading ? '...' : stats.avg_duration_minutes }} <span style="font-size: 1rem; font-weight: 600;">menit</span>
        </div>
        <div style="font-size: 0.75rem; color: #0369a1; margin-top: 4px;">
          Mean Time To Resolve (MTTR)
        </div>
      </div>

    </div>

    <!-- 3. TABEL RIWAYAT GANGGUAN & BERITA ACARA PENANGANAN TS -->
    <div class="card" style="padding: 0; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; background: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
      <div style="padding: 18px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; background: #f8fafc;">
        <div>
          <h3 style="margin: 0; font-size: 1rem; font-weight: 700; color: #0f172a;">
            📋 Riwayat Penanganan Gangguan & Berita Acara Anda
          </h3>
          <p style="margin: 2px 0 0; font-size: 0.82rem; color: #64748b;">
            Daftar gangguan yang ditugaskan kepada Anda. Klik tombol 📄 PDF atau 📊 Excel untuk langsung mengunduh Berita Acara.
          </p>
        </div>
        <button @click="fetchMyStats" style="padding: 6px 14px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; cursor: pointer; font-size: 0.8rem; font-weight: 600; color: #334155;">
          🔄 Refresh Data
        </button>
      </div>

      <div v-if="loading" style="padding: 40px; text-align: center; color: #94a3b8;">
        Memuat data penanganan gangguan...
      </div>

      <div v-else-if="stats.gangguan_list?.length" class="table-responsive elegant-scroll" style="overflow-x: auto; width: 100%;">
        <table style="width: 100%; border-collapse: collapse; white-space: nowrap; font-size: 0.85rem;">
          <thead>
            <tr style="background-color: #f8fafc; border-bottom: 2px solid #cbd5e1;">
              <th style="padding: 12px 16px; text-align: center; font-weight: 600; color: #475569; font-size: 0.72rem; text-transform: uppercase;">No</th>
              <th style="padding: 12px 16px; font-weight: 600; color: #475569; font-size: 0.72rem; text-transform: uppercase;">Nomor Tiket</th>
              <th style="padding: 12px 16px; font-weight: 600; color: #475569; font-size: 0.72rem; text-transform: uppercase;">Tanggal</th>
              <th style="padding: 12px 16px; font-weight: 600; color: #475569; font-size: 0.72rem; text-transform: uppercase;">Kubikal</th>
              <th style="padding: 12px 16px; font-weight: 600; color: #475569; font-size: 0.72rem; text-transform: uppercase;">Subject Kendala</th>
              <th style="padding: 12px 16px; font-weight: 600; color: #475569; font-size: 0.72rem; text-transform: uppercase; text-align: center;">Status</th>
              <th style="padding: 12px 16px; font-weight: 600; color: #475569; font-size: 0.72rem; text-transform: uppercase; text-align: center;">Durasi</th>
              <th style="padding: 12px 16px; font-weight: 600; color: #475569; font-size: 0.72rem; text-transform: uppercase; text-align: center;">Aksi Export</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in stats.gangguan_list" :key="item.id" style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 12px 16px; text-align: center; color: #94a3b8;">{{ idx + 1 }}</td>
              <td style="padding: 12px 16px; font-weight: 700; color: #2563eb;">{{ item.ticket_number }}</td>
              <td style="padding: 12px 16px; color: #334155;">{{ formatDateOnly(item.created_at) }}</td>
              <td style="padding: 12px 16px; color: #334155;">{{ item.kategori || '-' }}</td>
              <td style="padding: 12px 16px; color: #334155; max-width: 300px; overflow: hidden; text-overflow: ellipsis;" :title="item.judul">
                {{ item.judul || '-' }}
              </td>
              <td style="padding: 12px 16px; text-align: center;">
                <span v-if="item.status === 'closed'" style="background: #d1fae5; color: #065f46; padding: 4px 10px; border-radius: 999px; font-weight: 700; font-size: 0.72rem;">CLOSED</span>
                <span v-else-if="item.status === 'in_progress'" style="background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 999px; font-weight: 700; font-size: 0.72rem;">IN PROGRESS</span>
                <span v-else style="background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 999px; font-weight: 700; font-size: 0.72rem;">OPEN</span>
              </td>
              <td style="padding: 12px 16px; text-align: center; font-weight: 700; color: #334155;">
                {{ calculateDurasi(item.start_time, item.end_time) }}
              </td>
              <td style="padding: 12px 16px; text-align: center;">
                <div style="display: flex; gap: 6px; justify-content: center;">
                  <RouterLink :to="`/gangguan/${item.id}`" class="btn-lihat">Lihat</RouterLink>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else style="padding: 40px; text-align: center; color: #94a3b8;">
        Belum ada gangguan yang ditugaskan kepada Anda.
      </div>
    </div>

    <!-- ── PREVIEW & PRINTABLE BERITA ACARA TEMPLATE ── -->
    <!-- Modal Preview (Visible on Screen when showPrintPreview is true) -->
    <div v-if="showPrintPreview" class="print-preview-overlay">
      <div class="print-preview-modal">
        <div class="print-preview-header">
          <h3 style="margin: 0; font-size: 1.2rem; color: #1e293b;">Preview Berita Acara</h3>
          <button @click="showPrintPreview = false" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b;">&times;</button>
        </div>
        
        <div class="print-preview-body">
          <div class="printable-berita-acara-wrapper">
            <div v-for="(printableItem, pIdx) in printableItems" :key="printableItem.id || pIdx" class="printable-berita-acara-page">
              
              <!-- KOP Table - 4 Kolom -->
              <table class="ba-kop-table">
                <tbody>
                  <tr>
                    <td class="ba-kop-left">
                      <table style="width:100%;border-collapse:collapse;font-size:7.5pt;">
                        <tbody>
                          <tr><td style="border-right:1px solid #000;padding:2px 6px;white-space:nowrap;"><strong>Halaman</strong></td><td style="padding:2px 6px;">: 1</td></tr>
                          <tr><td style="border-right:1px solid #000;padding:2px 6px;white-space:nowrap;"><strong>Tanggal Berlaku</strong></td><td style="padding:2px 6px;">: {{ formatDateOnly(printableItem.created_at) }}</td></tr>
                          <tr><td style="border-right:1px solid #000;padding:2px 6px;white-space:nowrap;"><strong>Departemen</strong></td><td style="padding:2px 6px;">:</td></tr>
                        </tbody>
                      </table>
                    </td>
                    <td class="ba-kop-dept">{{ baSettings.ba_departemen }}</td>
                    <td class="ba-kop-title">
                      <div style="font-size:9pt;font-weight:800;">{{ baSettings.ba_brand_name }}</div>
                      <div style="font-size:8.5pt;font-weight:900;margin-top:3px;">{{ baSettings.ba_title }}</div>
                    </td>
                    <td class="ba-kop-logo">
                      <img v-if="baSettings.ba_logo_url" :src="baSettings.ba_logo_url" style="max-height:52px;max-width:110px;object-fit:contain;" />
                      <div v-else style="display:inline-flex;align-items:center;gap:3px;border:1.5px solid #003;padding:3px 6px;border-radius:3px;">
                        <span style="color:#d97706;font-weight:900;font-size:13pt;">⚡ PLN</span>
                        <span style="color:#0284c7;font-weight:800;font-size:9pt;">Icon Plus</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>

              <!-- Sub Header -->
              <table class="ba-info-table">
                <tbody>
                  <tr>
                    <td style="width:33%;"><strong>Nomor Surat</strong> : {{ printableItem.ticket_number }}</td>
                    <td style="width:33%;"><strong>Nama Perangkat</strong> : {{ printableItem.kategori || '-' }}</td>
                    <td style="width:34%;"><strong>Kode</strong> : -</td>
                  </tr>
                </tbody>
              </table>
              <!-- Nomor / Periode / Bulan Row -->
              <div class="ba-info-row">
                <span style="margin-right:30px;"><strong>Nomor :</strong> -</span>
                <span style="margin-right:30px;"><strong>Periode : {{ getYear(printableItem.created_at) }}</strong></span>
                <span><strong>Bulan : {{ getMonthName(printableItem.created_at) }}</strong></span>
              </div>

              <!-- Table Utama Kronologi -->
              <table class="ba-main-table">
                <thead>
                  <tr>
                    <th style="width:3%;">No</th>
                    <th style="width:8%;">Hari/Tanggal</th>
                    <th style="width:7%;">Start Downtime<br/><span style="font-weight:normal;font-size:7pt;">(h:mm:ss)</span></th>
                    <th style="width:7%;">End Downtime<br/><span style="font-weight:normal;font-size:7pt;">(h:mm:ss)</span></th>
                    <th style="width:9%;">Nama Agent</th>
                    <th style="width:5%;">Ext / IP</th>
                    <th style="width:8%;">ID Task SIP</th>
                    <th style="width:13%;">Penyebab Permasalahan</th>
                    <th style="width:13%;">Penyelesaian Masalah</th>
                    <th style="width:8%;">Impact</th>
                    <th style="width:7%;">Durasi Downtime</th>
                    <th style="width:7%;">Petugas TS Shift ...</th>
                    <th style="width:5%;">Analisa</th>
                  </tr>
                </thead>
                <tbody>
                  <tr class="ba-data-row">
                    <td style="text-align: center;">1</td>
                    <td>{{ formatDateFull(printableItem.created_at) }}</td>
                    <td style="text-align: center;">{{ formatTimeOnly(printableItem.start_time || printableItem.created_at) }}</td>
                    <td style="text-align: center;">{{ formatTimeOnly(printableItem.end_time || printableItem.resolved_at) }}</td>
                    <td>{{ printableItem.jenis_gangguan === 'Massal' ? 'ALL AGENT' : (printableItem.creator?.name || '-') }}</td>
                    <td style="text-align: center;">-</td>
                    <td>{{ printableItem.ticket_number }}</td>
                    <td>{{ printableItem.penyebab_permasalahan || printableItem.deskripsi || '-' }}</td>
                    <td>{{ printableItem.penyelesaian_masalah || '-' }}</td>
                    <td>{{ printableItem.impact || '-' }}</td>
                    <td style="text-align: center;">{{ calculateDurasi(printableItem.start_time, printableItem.end_time) }}</td>
                    <td>{{ printableItem.assignee?.name || '-' }}</td>
                    <td>{{ printableItem.analisa || '-' }}</td>
                  </tr>
                  <tr class="ba-total-row">
                    <td colspan="10" style="text-align:right;font-weight:bold;">TOTAL DOWN TIME :</td>
                    <td colspan="3" style="font-weight:bold;text-align:center;">{{ calculateDurasi(printableItem.start_time, printableItem.end_time) }}</td>
                  </tr>
                </tbody>
              </table>

              <!-- Signature Box -->
              <div class="ba-signature-box">
                <table style="width:100%;border:none;margin-bottom:8px;">
                  <tbody>
                    <tr>
                      <td style="width:50%;border:none;"></td>
                      <td style="width:25%;text-align:center;border:none;font-size:8pt;font-weight:600;">{{ baSettings.ba_location }}, {{ formatDateFull(new Date()) }}</td>
                      <td style="width:25%;border:none;"></td>
                    </tr>
                  </tbody>
                </table>
                <div style="display:flex;justify-content:flex-end;gap:80px;text-align:center;">
                  <!-- Koordinator -->
                  <div style="min-width:180px;display:flex;flex-direction:column;align-items:center;">
                    <div style="font-size:8pt;font-weight:700;color:#c2410c;margin-bottom:6px;">KOORD OPS</div>
                    <div style="height:60px;display:flex;align-items:center;justify-content:center;margin-bottom:6px;">
                      <img v-if="baSettings.ba_koord_signature_url" :src="baSettings.ba_koord_signature_url" style="max-height:55px;max-width:150px;object-fit:contain;" />
                    </div>
                    <div style="font-size:7.5pt;font-weight:800;margin-bottom:2px;">KOORDINATOR</div>
                    <div style="font-size:7.5pt;">[ {{ baSettings.ba_koord_name }} ]</div>
                  </div>
                  <!-- Technical Support -->
                  <div style="min-width:180px;display:flex;flex-direction:column;align-items:center;">
                    <div style="font-size:8pt;font-weight:700;color:#c2410c;margin-bottom:6px;">TEAM SUPPORT</div>
                    <div style="height:60px;display:flex;align-items:center;justify-content:center;margin-bottom:6px;">
                      <img v-if="printableItem.assignee?.signature_url" :src="printableItem.assignee.signature_url" style="max-height:55px;max-width:150px;object-fit:contain;" />
                    </div>
                    <div style="font-size:7.5pt;font-weight:800;margin-bottom:2px;">TECHNICAL SUPPORT</div>
                    <div style="font-size:7.5pt;">[ {{ printableItem.assignee?.name || '' }} ]</div>
                  </div>
                </div>
              </div>



            </div>
          </div>
        </div>

        <div class="print-preview-footer">
          <button @click="showPrintPreview = false" style="padding: 8px 16px; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer; font-weight: 600;">Batal</button>
          <button @click="executePrint" style="padding: 8px 16px; background: #1d4ed8; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">🖨️ Cetak PDF</button>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
.setting-input {
  width: 100%;
  padding: 9px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.88rem;
  outline: none;
  background: #ffffff;
  box-sizing: border-box;
}
.setting-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}
.paste-dropzone:focus {
  border-color: #2563eb !important;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
  background: #eff6ff !important;
}
.btn-delete-link {
  background: none;
  border: none;
  color: #dc2626;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  padding: 2px 6px;
  border-radius: 4px;
}
.btn-delete-link:hover {
  background: #fee2e2;
  text-decoration: underline;
}

.btn-lihat {
  display: inline-block; padding: 4px 10px; background: #eff6ff; color: #2563eb; font-weight: 600; font-size: 0.75rem; border-radius: 999px; text-decoration: none;
}
.btn-lihat:hover { background: #dbeafe; }

.btn-pdf-action {
  padding: 4px 10px; border: 1px solid #93c5fd; background: #eff6ff; color: #1d4ed8; font-weight: 700; font-size: 0.72rem; border-radius: 999px; cursor: pointer;
}
.btn-pdf-action:hover { background: #dbeafe; }

.btn-excel-action {
  padding: 4px 10px; border: 1px solid #86efac; background: #f0fdf4; color: #15803d; font-weight: 700; font-size: 0.72rem; border-radius: 999px; cursor: pointer;
}
.btn-excel-action:hover { background: #dcfce7; }

/* Preview Modal CSS */
.print-preview-overlay {
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px);
  z-index: 9999; display: flex; align-items: center; justify-content: center;
}
.print-preview-modal {
  background: #fff; width: 95%; max-width: 1000px; border-radius: 12px;
  box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); display: flex; flex-direction: column;
  max-height: 90vh;
}
.print-preview-header {
  padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex;
  justify-content: space-between; align-items: center;
}
.print-preview-body {
  padding: 20px; background: #e2e8f0; overflow-y: auto; flex: 1;
}
.print-preview-footer {
  padding: 16px 20px; border-top: 1px solid #e2e8f0; display: flex;
  justify-content: flex-end; gap: 12px; background: #fff; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;
}
.printable-berita-acara-wrapper {
  background: #fff; padding: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin: 0 auto;
  color: #000; font-family: Arial, sans-serif; font-size: 8pt; width: 297mm; max-width: 100%; box-sizing: border-box; overflow-x: auto;
}
.ba-kop-table { width: 100%; border-collapse: collapse; margin-bottom: 0; border: 1px solid #000; }
.ba-kop-table td { border: 1px solid #000; padding: 4px 6px; vertical-align: middle; }
.ba-kop-left  { width: 16%; padding: 0 !important; vertical-align: top !important; }
.ba-kop-dept  { width: 20%; text-align: center; font-weight: 700; font-size: 8.5pt; }
.ba-kop-title { width: 46%; text-align: center; font-weight: 800; }
.ba-kop-logo  { width: 18%; text-align: center; }
.ba-info-table { width: 100%; border-collapse: collapse; margin-bottom: 2px; font-size: 7.5pt; border: 1px solid #000; border-top: none; }
.ba-info-table td { border: 1px solid #000; padding: 3px 6px; }
.ba-info-row { display: flex; justify-content: flex-end; align-items: center; font-size: 7.5pt; padding: 3px 6px; margin-bottom: 6px; }
.ba-main-table { width: 100%; border-collapse: collapse; font-size: 7.5pt; margin-bottom: 12px; }
.ba-main-table th { background-color: #1d4ed8 !important; color: #ffffff !important; border: 1px solid #000 !important; padding: 6px 4px; font-weight: bold; text-align: center; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.ba-main-table td { border: 1px solid #000 !important; padding: 6px 4px; vertical-align: top; }
.ba-data-row td { min-height: 60px; height: 60px; }
.ba-total-row td { background-color: #2563eb !important; color: #ffffff !important; font-weight: bold; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.ba-signature-box { margin-top: 14px; margin-bottom: 16px; page-break-inside: avoid; }
.ba-evidence-container { margin-top: 14px; border: 1px solid #000; page-break-inside: avoid; }
.ba-evidence-title { background-color: #1d4ed8 !important; color: #ffffff !important; font-weight: bold; text-align: center; padding: 4px; font-size: 8.5pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.ba-evidence-grid { display: flex; flex-wrap: wrap; gap: 8px; padding: 8px; justify-content: center; }
.ba-evidence-item img { max-height: 220px; max-width: 100%; object-fit: contain; border: 1px solid #ccc; }
.ba-evidence-empty { padding: 30px; text-align: center; color: #64748b; font-style: italic; }
</style>

<style>
/* UNSCOPED PRINT STYLES - Fixes the issue where other elements weren't hidden */
@media print {
  body * { visibility: hidden !important; }
  .printable-berita-acara-wrapper, .printable-berita-acara-wrapper * { visibility: visible !important; }
  .printable-berita-acara-wrapper {
    display: block !important; position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 0; box-shadow: none;
    color: #000; font-family: Arial, sans-serif; font-size: 8pt; background: #fff;
  }
  .printable-berita-acara-page { page-break-after: always; margin-bottom: 20px; }
  .printable-berita-acara-page:last-child { page-break-after: auto; }
  @page { size: A4 landscape; margin: 8mm; }
}
</style>
