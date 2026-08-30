<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';

const route  = useRoute();
const router = useRouter();
const auth   = useAuthStore();
const tsList = ref([]);
const item   = ref(null);
const loading = ref(false);
const saving  = ref(false);
const savingForm = ref(false);
const successMsg = ref('');
const errorMsg   = ref('');
const form = ref({
  penyebab_permasalahan: '',
  penyelesaian_masalah: '',
  impact: '',
  analisa: '',
  end_time: '',
  assigned_to: '',
  nomor_surat: '',
  kode: '',
  id_task_sip: '',
  kategori: ''
});
const apiBaseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

// ───── helpers ─────
function fmt(value) {
  if (!value) return '-';
  return new Date(value).toLocaleString('id-ID', {
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
    hour12: false,
  }).replace(',', '');
}

function fmtDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric'
  });
}

function fmtDateShort(value) {
  if (!value) return '-';
  const dt = new Date(value);
  const dd = String(dt.getDate()).padStart(2, '0');
  const mm = String(dt.getMonth() + 1).padStart(2, '0');
  const yyyy = dt.getFullYear();
  return `${dd}/${mm}/${yyyy}`;
}

function fmtDateFull(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID', {
    day: '2-digit', month: 'long', year: 'numeric'
  });
}

function fmtTime(value) {
  if (!value) return '-';
  return new Date(value).toLocaleTimeString('id-ID', {
    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
  }).replace(/\./g, ':');
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

function calcDurasi(start, end) {
  if (!start || !end) return '-';
  const diffMs = new Date(end) - new Date(start);
  if (diffMs < 0) return '-';
  const s = Math.floor(diffMs / 1000);
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = s % 60;
  return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`;
}

function statusLabel(s) {
  return String(s || 'unknown').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function statusStyle(s) {
  const n = String(s || '').toLowerCase();
  if (n === 'open')        return 'background:#e8f7ec;color:#146c2e;';
  if (n === 'in_progress') return 'background:#fff1d6;color:#9a5b00;';
  if (n === 'closed')      return 'background:#e7eefc;color:#234a92;';
  return 'background:#f3f4f6;color:#374151;';
}

function priorityLabel(p) {
  return { low: 'Rendah', medium: 'Sedang', high: 'Tinggi' }[p] || p || '-';
}

function priorityStyle(p) {
  if (p === 'high')   return 'background:#fde8e8;color:#991b1b;';
  if (p === 'medium') return 'background:#fff1d6;color:#9a5b00;';
  return 'background:#f3f4f6;color:#374151;';
}

// ───── fetch ─────
async function fetchDetail() {
  loading.value = true;
  try {
    const { data } = await api.get(`/gangguan/${route.params.id}`);
    item.value = data;
    form.value.penyebab_permasalahan = data.penyebab_permasalahan || '';
    form.value.penyelesaian_masalah = data.penyelesaian_masalah || '';
    form.value.impact = data.impact || '';
    form.value.jumlah_agent_terdampak = data.jenis_gangguan === 'Massal' ? (data.jumlah_agent_terdampak || 1) : 1;
    form.value.analisa = data.analisa || '';
    form.value.assigned_to = data.assigned_to || '';
    form.value.nomor_surat = data.nomor_surat || '';
    form.value.kode = data.kode || '';
    form.value.id_task_sip = data.id_task_sip || '';
    form.value.kategori = data.kategori || '';
    if (data.end_time) {
      const d = new Date(data.end_time);
      const tzoffset = d.getTimezoneOffset() * 60000;
      form.value.end_time = (new Date(d - tzoffset)).toISOString().slice(0, 16);
    } else {
      form.value.end_time = '';
    }
  } finally {
    loading.value = false;
  }
}

function setNow() {
  const d = new Date();
  const tzoffset = d.getTimezoneOffset() * 60000;
  form.value.end_time = (new Date(d - tzoffset)).toISOString().slice(0, 16);
}

// ───── update status ─────
async function updateStatus(newStatus) {
  if (saving.value) return;
  saving.value = true;
  successMsg.value = '';
  errorMsg.value   = '';
  try {
    const payload = { status: newStatus };
    if (newStatus === 'closed') {
      Object.assign(payload, form.value);
      if (!auth.hasRole('Admin')) {
        delete payload.assigned_to;
      }
      if (payload.end_time) {
        payload.end_time = new Date(payload.end_time).toISOString();
      }
    }
    const { data } = await api.patch(`/gangguan/${route.params.id}`, payload);
    item.value = data;
    form.value.assigned_to = data.assigned_to || '';
    successMsg.value = `Status berhasil diubah menjadi "${statusLabel(newStatus)}".`;
  } catch (err) {
    errorMsg.value = err.response?.data?.message || 'Gagal mengubah status.';
  } finally {
    saving.value = false;
  }
}

async function updateDetail() {
  if (savingForm.value) return;
  savingForm.value = true;
  successMsg.value = '';
  errorMsg.value   = '';
  try {
    const payload = { ...form.value };
    if (!auth.hasRole('Admin')) {
      delete payload.assigned_to;
    }
    if (payload.end_time) {
      payload.end_time = new Date(payload.end_time).toISOString();
    }
    const { data } = await api.patch(`/gangguan/${route.params.id}`, payload);
    item.value = data;
    form.value.assigned_to = data.assigned_to || '';
    successMsg.value = 'Detail penanganan berhasil disimpan.';
  } catch (err) {
    errorMsg.value = err.response?.data?.message || 'Gagal menyimpan detail.';
  } finally {
    savingForm.value = false;
  }
}

async function deleteEvidence(evidenceId) {
  if (!confirm('Apakah Anda yakin ingin menghapus evidence ini?')) return;
  
  try {
    await api.delete(`/evidence/${evidenceId}`);
    if (item.value && item.value.evidences) {
      item.value.evidences = item.value.evidences.filter(e => e.id !== evidenceId);
    }
    successMsg.value = 'Evidence berhasil dihapus.';
  } catch (err) {
    errorMsg.value = err.response?.data?.message || 'Gagal menghapus evidence.';
  }
}

// ───── Settings Template BA ─────
const baSettings = ref({
  ba_brand_name:    'PLN Icon Plus',
  ba_departemen:    'Divisi Perencanaan Ops Ritel',
  ba_title:         'BERITA ACARA KRONOLOGIS GANGGUAN APLIKASI/JARINGAN',
  ba_location:      'SEMARANG',
  ba_koord_name:    'AHMAD ZAENAL ARIFIN',
  ba_koord_title:   'KOORDINATOR',
  ba_ts_title:      'TECHNICAL SUPPORT',
  ba_show_evidence: 'true',
});

async function fetchBaSettings() {
  try {
    const { data } = await api.get('/settings');
    if (data) {
      baSettings.value = { ...baSettings.value, ...data };
    }
  } catch (err) {}
}

// ───── Export PDF & Excel Berita Acara ─────
const showPrintPreview = ref(false);
const cubicleList = ref([]);
const baForm = ref({
  nomorSurat: '',
  namaPerangkat: '',
  kode: '',
  kubikal: '',
  extIp: '',
  idTaskSip: ''
});

async function fetchCubicles() {
  try {
    const { data } = await api.get('/cubicles');
    cubicleList.value = Array.isArray(data) ? data : (data.data || []);
  } catch (err) {
    console.error('Failed to load cubicles:', err);
  }
}

function onCubicleChange() {
  const selected = cubicleList.value.find(c => c.nama === baForm.value.kubikal);
  if (selected) {
    const ext = selected.ext || '';
    const ip = selected.ip || '';
    if (ext || ip) {
      baForm.value.extIp = [ext, ip].filter(Boolean).join(' / ');
    } else {
      baForm.value.extIp = '';
    }
  }
}

function printBeritaAcara() {
  if (item.value) {
    baForm.value.nomorSurat = item.value.nomor_surat || '';
    baForm.value.namaPerangkat = item.value.kategori || '';
    baForm.value.kode = item.value.kode || '';
    baForm.value.kubikal = item.value.cubicle_name || item.value.kategori || '';
    baForm.value.idTaskSip = item.value.id_task_sip || '';

    const ext = item.value.cubicle_ext || '';
    const ip = item.value.cubicle_ip || '';
    if (ext || ip) {
      baForm.value.extIp = [ext, ip].filter(Boolean).join(' / ');
    } else {
      const found = cubicleList.value.find(c => c.nama === baForm.value.kubikal);
      if (found && (found.ext || found.ip)) {
        baForm.value.extIp = [found.ext, found.ip].filter(Boolean).join(' / ');
      } else {
        baForm.value.extIp = '';
      }
    }
  }
  showPrintPreview.value = true;
}

async function executePrint() {
  // Convert image URL to data URL (bypasses print-time auth issues)
  const toDataUrl = (url) => fetch(url, { credentials: 'include' })
    .then(r => r.blob())
    .then(blob => new Promise(resolve => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.readAsDataURL(blob);
    }))
    .catch(() => url); // fallback to original URL if fetch fails

  // Clone the printable area
  const printEl = document.querySelector('.printable-berita-acara');
  if (!printEl) { window.print(); return; }

  const clone = printEl.cloneNode(true);

  // Replace all img srcs with data URLs in the clone
  const imgs = clone.querySelectorAll('img');
  const origImgs = printEl.querySelectorAll('img');
  for (let i = 0; i < imgs.length; i++) {
    const src = origImgs[i]?.src;
    if (src) {
      const dataUrl = await toDataUrl(src);
      imgs[i].src = dataUrl;
    }
  }

  // Remove file inputs and upload forms from clone
  clone.querySelectorAll('input, button, label').forEach(el => el.remove());

  // Open dedicated print window
  const printWin = window.open('', '_blank', 'width=1200,height=800');
  printWin.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="utf-8">
      <title>Berita Acara</title>
      <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 8pt; color: #000; background: #fff; }
        @page { size: landscape; margin: 5mm; }
        @media print {
          body { margin: 0; }
        }
        /* KOP */
        .ba-kop-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; border: 1px solid #000; background-color: #e2e8f0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .ba-kop-table td { border: 1px solid #000; padding: 4px 6px; vertical-align: middle; }
        /* Info */
        .ba-info-table { width: 100%; border-collapse: collapse; margin-bottom: 2px; font-size: 7.5pt; border: 1px solid #000; border-top: none; }
        .ba-info-table td { border: 1px solid #000; padding: 3px 6px; }
        /* Main table */
        .ba-main-table { width: 100%; border-collapse: collapse; font-size: 7.5pt; margin-bottom: 12px; table-layout: fixed; }
        .ba-main-table th { background-color: #5b9bd5 !important; color: #000 !important; border: 1px solid #000 !important; padding: 6px 4px; font-weight: bold; text-align: center; font-size: 7pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; overflow-wrap: break-word; }
        .ba-main-table td { border: 1px solid #000 !important; padding: 6px 4px; vertical-align: middle; text-align: center; overflow-wrap: break-word; word-break: break-word; }
        .ba-data-row td { min-height: 60px; height: 60px; }
        .ba-signature-box { margin-top: 14px; margin-bottom: 16px; }
        /* Evidence */
        .ba-evidence-container { margin-top: 14px; }
        .ba-evidence-grid { display: block; }
        .ba-evidence-item { border: 1px solid #000; margin-bottom: 14px; page-break-inside: avoid; }
        .ba-evidence-item:last-child { margin-bottom: 0; }
        .ba-evidence-item-header { background-color: #00bcd4 !important; color: #000 !important; font-weight: bold; text-align: center; padding: 4px 6px; font-size: 8pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; border-bottom: 1px solid #000; }
        .ba-evidence-item-body { min-height: 120px; padding: 12px; text-align: center; display: flex; align-items: center; justify-content: center; }
        .ba-evidence-item-body img { max-height: 350px; max-width: 100%; object-fit: contain; }
        .ba-evidence-item-footer { background-color: #00bcd4 !important; height: 8px; -webkit-print-color-adjust: exact; print-color-adjust: exact; border-top: 1px solid #000; }
        /* Total row */
        .ba-total-row td { }
        /* Info row */
        .ba-info-row { display: flex; justify-content: flex-end; align-items: center; font-size: 7.5pt; padding: 3px 6px; margin-bottom: 6px; }
        .printable-berita-acara { width: 100%; }
        .ba-kop-left { width: 16%; padding: 0 !important; vertical-align: top !important; }
        .ba-kop-dept { width: 20%; text-align: center; font-weight: 700; font-size: 8.5pt; }
        .ba-kop-title { width: 46%; text-align: center; font-weight: 800; }
        .ba-kop-logo { width: 18%; text-align: center; }
      </style>
    </head>
    <body>${clone.outerHTML}</body>
    </html>
  `);
  printWin.document.close();
  printWin.focus();
  setTimeout(() => {
    printWin.print();
    printWin.close();
  }, 800);
}

async function exportExcelBeritaAcara() {
  // Convert image URL to data URL (bypasses auth issues)
  const toDataUrl = (url) => fetch(url, { credentials: 'include' })
    .then(r => r.blob())
    .then(blob => new Promise(resolve => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.readAsDataURL(blob);
    }))
    .catch(() => url);

  // Clone the same printable area used by PDF
  const printEl = document.querySelector('.printable-berita-acara');
  if (!printEl) return;

  const clone = printEl.cloneNode(true);

  // Replace all img srcs with data URLs in the clone
  const imgs = clone.querySelectorAll('img');
  const origImgs = printEl.querySelectorAll('img');
  for (let i = 0; i < imgs.length; i++) {
    const src = origImgs[i]?.src;
    if (src) {
      const dataUrl = await toDataUrl(src);
      imgs[i].src = dataUrl;
    }
  }

  // Remove form inputs and upload elements
  clone.querySelectorAll('input, button, label').forEach(el => el.remove());

  // Collect all scoped CSS from the page
  const styleSheets = Array.from(document.styleSheets)
    .map(sheet => {
      try {
        return Array.from(sheet.cssRules).map(r => r.cssText).join('\n');
      } catch { return ''; }
    }).join('\n');

  const d = item.value;
  const filename = `Berita_Acara_${d?.ticket_number || 'Gangguan'}.xls`;

  const excelContent = `
    <html xmlns:o="urn:schemas-microsoft-com:office:office"
          xmlns:x="urn:schemas-microsoft-com:office:excel"
          xmlns="http://www.w3.org/TR/REC-html40">
    <head>
      <meta charset="utf-8">
      <style>
        ${styleSheets}
        body { font-family: Arial, sans-serif; font-size: 8pt; margin: 0; padding: 0; }
        .printable-berita-acara { width: 100%; }
        .print-preview-header, .print-preview-footer, .print-preview-body > div:first-child,
        input, button { display: none !important; }
      </style>
    </head>
    <body>${clone.outerHTML}</body>
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

  const d = item.value;
  const s = baSettings.value;

onMounted(async () => {
  await fetchBaSettings();
  await fetchDetail();
  await fetchCubicles();
  if (auth.hasRole('Admin')) {
    try {
      const { data } = await api.get('/users', { params: { role: 'TS' } });
      tsList.value = Array.isArray(data) ? data : (data.data || []);
    } catch (err) {}
  }
});
</script>

<template>
  <section class="grid" style="gap: 20px;">

    <!-- Header / Action Bar -->
    <div style="grid-column:1/-1; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom: 4px;">
      <div>
        <h2 class="page-title" style="margin:0; font-size:1.6rem; color:#0f172a; font-weight:700;">Detail Gangguan</h2>
        <p class="page-desc" style="margin:4px 0 0; color:#475569; font-size:0.95rem;">Monitoring &amp; penanganan tiket gangguan.</p>
      </div>

      <!-- Action Buttons -->
      <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <button
          type="button"
          @click="router.back()"
          style="display:inline-flex; align-items:center; gap:6px; background:#fff; border:1px solid #cbd5e1; border-radius:8px; padding:8px 16px; cursor:pointer; font-weight:600; font-size:0.85rem; color:#334155; transition:all 0.15s;"
          onmouseover="this.style.background='#f8fafc'"
          onmouseout="this.style.background='#fff'"
        >
          <span style="font-size:1rem; line-height:1;">&larr;</span> Kembali
        </button>

        <button
          type="button"
          @click="printBeritaAcara"
          style="display:inline-flex; align-items:center; gap:8px; background:linear-gradient(135deg, #1d4ed8, #2563eb); border:none; border-radius:8px; padding:8px 18px; cursor:pointer; font-weight:700; font-size:0.85rem; color:#fff; box-shadow:0 4px 12px rgba(37,99,235,0.25); transition:all 0.15s;"
        >
          📋 Buat Berita Acara
        </button>
      </div>
    </div>

    <!-- Loading -->
    <article v-if="loading" class="card" style="grid-column:1/-1;text-align:center;padding:48px;color:var(--muted);">
      <div style="font-size:2rem;margin-bottom:10px;">⏳</div>
      Memuat data tiket...
    </article>

    <template v-else-if="item">

      <!-- HEADER: Nomor Tiket & Status -->
      <article class="card" style="grid-column:1/-1; padding: 16px 20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
          <div>
            <p style="margin:0 0 2px;font-size:0.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;">Nomor Tiket</p>
            <h3 style="margin:0;font-size:1.35rem;letter-spacing:.02em;">{{ item.ticket_number }}</h3>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <span :style="priorityStyle(item.priority)" style="padding:5px 14px;border-radius:999px;font-size:.8rem;font-weight:700;">
              Prioritas: {{ priorityLabel(item.priority) }}
            </span>
            <span :style="statusStyle(item.status)" style="padding:5px 14px;border-radius:999px;font-weight:700;">
              {{ statusLabel(item.status) }}
            </span>
          </div>
        </div>
      </article>

      <!-- ROW 1: Timeline | Dampak | Umum -->
      <div style="grid-column:1/-1; display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:20px; align-items:stretch;">
        
        <!-- Progress Timeline -->
        <article class="card" style="padding:20px; display:flex; flex-direction:column; flex: 1.5;">
          <h3 style="margin:0 0 24px; font-size:1.05rem; color:var(--text-main);">Progress Timeline</h3>
          
          <div style="position: relative; display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
            <div style="position: absolute; top: 14px; left: 16.66%; right: 16.66%; height: 3px; background: #e2e8f0; z-index: 1;">
              <div :style="`height: 100%; background: #3b82f6; transition: width 0.3s; width: ${item.resolved_at ? '100%' : (item.read_at ? '50%' : '0%')};`"></div>
            </div>
            
            <div style="position: relative; z-index: 2; text-align: center; width: 33.33%;">
              <div style="width: 32px; height: 32px; border-radius: 50%; background: #3b82f6; box-shadow: 0 0 0 4px #fff; color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
              </div>
              <div style="display:flex; flex-direction:column; align-items:center; gap:3px;">
                <span style="font-size: 0.75rem; font-weight: 700; color: #334155;">{{ fmtDate(item.created_at) }}</span>
                <span style="font-size: 0.7rem; font-weight: 600; color: #64748b; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">{{ fmtTime(item.created_at) }}</span>
              </div>
            </div>
            
            <div style="position: relative; z-index: 2; text-align: center; width: 33.33%;">
              <div :style="`width: 32px; height: 32px; border-radius: 50%; background: ${item.read_at ? '#3b82f6' : '#e2e8f0'}; box-shadow: 0 0 0 4px #fff; color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;`">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
              </div>
              <div v-if="item.read_at" style="display:flex; flex-direction:column; align-items:center; gap:3px;">
                <span style="font-size: 0.75rem; font-weight: 700; color: #334155;">{{ fmtDate(item.read_at) }}</span>
                <span style="font-size: 0.7rem; font-weight: 600; color: #64748b; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">{{ fmtTime(item.read_at) }}</span>
              </div>
              <span v-else style="font-size: 0.75rem; font-weight: 700; color: #94a3b8;">-</span>
            </div>
            
            <div style="position: relative; z-index: 2; text-align: center; width: 33.33%;">
              <div :style="`width: 32px; height: 32px; border-radius: 50%; background: ${item.resolved_at ? '#10b981' : '#e2e8f0'}; box-shadow: 0 0 0 4px #fff; color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;`">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
              </div>
              <div v-if="item.resolved_at" style="display:flex; flex-direction:column; align-items:center; gap:3px;">
                <span style="font-size: 0.75rem; font-weight: 700; color: #334155;">{{ fmtDate(item.resolved_at) }}</span>
                <span style="font-size: 0.7rem; font-weight: 600; color: #047857; background: #d1fae5; padding: 2px 6px; border-radius: 4px;">{{ fmtTime(item.resolved_at) }}</span>
              </div>
              <span v-else style="font-size: 0.75rem; font-weight: 700; color: #94a3b8;">-</span>
            </div>
          </div>
          
          <div style="background: #f8fafc; padding: 10px; border-radius: 8px; text-align: center; font-weight: 600; font-size: 0.85rem; color: #475569; margin-top: auto;">
            Durasi: {{ item.durasi ? item.durasi + ' menit' : 'Sedang berjalan' }}
          </div>
        </article>

        <!-- Ringkasan Dampak -->
        <article class="card" style="padding:20px; display:flex; flex-direction:column; flex: 1;">
          <h3 style="margin:0 0 16px; font-size:1.05rem; color:var(--text-main);">Ringkasan Dampak</h3>
          <div style="display: flex; align-items: center; justify-content: space-around; flex: 1;">
            <div style="display: flex; flex-direction: column; align-items: flex-start; min-width: 120px;">
              <p style="margin:0 0 4px; font-size:0.7rem; color:#dc2626; font-weight: 700; text-transform: uppercase;">▶ IMPACT</p>
              <p style="margin:0 0 8px; font-weight: 700; font-size: 1.15rem; color: #0f172a;">{{ item.impact || '-' }}</p>
            </div>
            
            <div style="width: 1px; height: 50px; background: #e2e8f0; margin: 0 16px;"></div>
            
            <div style="display: flex; flex-direction: column; align-items: flex-start; min-width: 140px;">
              <p style="margin:0 0 4px; font-size:0.7rem; color:#475569; font-weight: 700; text-transform: uppercase;">JML AGENT TERDAMPAK</p>
              <p style="margin:0 0 8px; font-weight: 700; font-size: 1.15rem; color: #0f172a;">{{ item.jumlah_agent_terdampak || 1 }} Agent</p>
            </div>
          </div>
        </article>

        <!-- Detail Umum -->
        <article class="card" style="padding:20px; display:flex; flex-direction:column; flex: 1;">
          <h3 style="margin:0 0 16px; font-size:1.05rem; color:var(--text-main);">Detail Umum</h3>
          <div style="display: flex; flex-direction: column; gap: 12px; font-size: 0.85rem; flex: 1; justify-content: center;">
            <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px;">
              <span style="color: #64748b; font-weight: 600;">JUDUL</span>
              <span style="font-weight: 700; color: #0f172a; text-align: right; max-width: 65%; word-break: break-word;">{{ item.judul || '-' }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px;">
              <span style="color: #64748b; font-weight: 600;">KATEGORI</span>
              <span style="font-weight: 700; color: #0f172a;">{{ item.kategori || '-' }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: #64748b; font-weight: 600;">DITANGANI OLEH</span>
              <span style="font-weight: 700; color: #0f172a;">{{ item.assignee?.name || '-' }}</span>
            </div>
          </div>
        </article>
      </div>

      <!-- ROW 2: Deskripsi & Penyelesaian -->
      <div style="grid-column:1/-1; display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:20px; align-items:stretch;">
        <article class="card" style="padding:20px; display:flex; flex-direction:column;">
          <h3 style="margin:0 0 12px; font-size:1.05rem; color:var(--text-main);">Penyebab Permasalahan</h3>
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; flex: 1;">
            <p style="margin:0; white-space: pre-wrap; font-size: 0.9rem; line-height: 1.6; color: #334155;">{{ item.deskripsi || item.penyebab_permasalahan || '-' }}</p>
          </div>
        </article>
        
        <article class="card" style="padding:20px; display:flex; flex-direction:column;">
          <h3 style="margin:0 0 12px; font-size:1.05rem; color:var(--text-main);">Penyelesaian Masalah</h3>
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; flex: 1;">
            <p style="margin:0; white-space: pre-wrap; font-size: 0.9rem; line-height: 1.6; color: #334155;">{{ item.penyelesaian_masalah || '-' }}</p>
          </div>
        </article>
      </div>

      <!-- ROW 3: Tindakan TS & Analisa Teknis -->
      <div style="grid-column:1/-1; display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:20px; align-items:stretch;">
        
        <!-- Tindakan TS -->
        <article class="card" style="padding:20px; display:flex; flex-direction:column;">
          <h3 style="margin:0 0 16px;font-size:1.05rem;color:var(--text-main);">🔧 Tindakan TS</h3>

          <div v-if="successMsg" style="background:#e8f7ec;color:#146c2e;padding:10px 14px;border-radius:8px;margin-bottom:12px;font-weight:600;font-size:0.85rem;">
            ✅ {{ successMsg }}
          </div>
          <div v-if="errorMsg" style="background:#fef2f2;color:#dc2626;padding:10px 14px;border-radius:8px;margin-bottom:12px;font-weight:600;font-size:0.85rem;">
            ❌ {{ errorMsg }}
          </div>

          <!-- Form Update Detail Penanganan -->
          <div v-if="item.status === 'in_progress'" style="display:flex;flex-direction:column;gap:12px;margin-bottom:16px;">
            <div v-if="auth.hasRole('Admin')">
              <label style="display:block;margin-bottom:4px;font-size:.85rem;font-weight:600;">Petugas TS (Shift)</label>
              <select v-model="form.assigned_to" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);font-family:inherit;background:#fff;">
                <option value="">-- Pilih Petugas TS --</option>
                <option v-for="ts in tsList" :key="ts.id" :value="ts.id">{{ ts.name }}</option>
              </select>
            </div>
            <div>
              <label style="display:block;margin-bottom:4px;font-size:.85rem;font-weight:600;">Kategori Gangguan (Nama Perangkat)</label>
              <select v-model="form.kategori" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);font-family:inherit;background:#fff;">
                <option value="SYSCCA">SYSCCA</option>
                <option value="SYSCCAE">SYSCCAE</option>
                <option value="ICRM+">ICRM+</option>
                <option value="HARDWARE">HARDWARE</option>
                <option value="BOTIKA">BOTIKA</option>
                <option value="ICONNPAY">ICONNPAY</option>
                <option value="SIP">SIP</option>
                <option value="INTERNET">INTERNET</option>
                <option value="LOCAL NETWORK">LOCAL NETWORK</option>
                <option value="MICROSIP">MICROSIP</option>
              </select>
            </div>
            <div>
              <label style="display:block;margin-bottom:4px;font-size:.85rem;font-weight:600;">Penyebab Permasalahan</label>
              <textarea v-model="form.penyebab_permasalahan" rows="2" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);font-family:inherit;"></textarea>
            </div>
            <div>
              <label style="display:block;margin-bottom:4px;font-size:.85rem;font-weight:600;">Penyelesaian Masalah</label>
              <textarea v-model="form.penyelesaian_masalah" rows="2" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);font-family:inherit;"></textarea>
            </div>
            <div>
              <label style="display:block;margin-bottom:4px;font-size:.85rem;font-weight:600;">Impact</label>
              <textarea v-model="form.impact" rows="2" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);font-family:inherit;"></textarea>
            </div>
            <div>
              <label style="display:block;margin-bottom:4px;font-size:.85rem;font-weight:600;">Jumlah Agent Terdampak</label>
              <input type="number" min="1" v-model="form.jumlah_agent_terdampak" :readonly="item.jenis_gangguan !== 'Massal'"
                :style="item.jenis_gangguan !== 'Massal' ? 'background:#f3f4f6;cursor:not-allowed;' : 'background:#fff;'"
                style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);font-family:inherit;" />
            </div>
            <div>
              <label style="display:block;margin-bottom:4px;font-size:.85rem;font-weight:600;">Nomor Surat</label>
              <input type="text" v-model="form.nomor_surat" placeholder="Ambil dari dokumen luar..." style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);font-family:inherit;" />
            </div>
            <div>
              <label style="display:block;margin-bottom:4px;font-size:.85rem;font-weight:600;">Kode</label>
              <input type="text" v-model="form.kode" placeholder="Kode dari luar aplikasi..." style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);font-family:inherit;" />
            </div>
            <div>
              <label style="display:block;margin-bottom:4px;font-size:.85rem;font-weight:600;">ID Task SIP</label>
              <input type="text" v-model="form.id_task_sip" placeholder="ID Task SIP..." style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);font-family:inherit;" />
            </div>
            <div>
              <label style="display:block;margin-bottom:4px;font-size:.85rem;font-weight:600;">Analisa</label>
              <textarea v-model="form.analisa" rows="2" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);font-family:inherit;"></textarea>
            </div>
            <div>
              <label style="display:block;margin-bottom:4px;font-size:.85rem;font-weight:600;">Waktu Selesai (End Downtime)</label>
              <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <input type="datetime-local" v-model="form.end_time" style="padding:9px 12px;border-radius:8px;border:1px solid var(--border);font-family:inherit;background:#fff;font-size:0.9rem; width: auto; min-width: 200px;">
                <button @click="setNow" type="button" style="padding: 9px 16px; border-radius: 8px; border: 1px solid var(--primary); background: var(--primary-lt, #eff6ff); color: var(--primary-dk, #1e40af); font-weight: 700; font-size:0.85rem; cursor: pointer; white-space: nowrap;">
                  ⏱️ Set Sekarang
                </button>
              </div>
            </div>
            <div>
              <button
                :disabled="savingForm"
                @click="updateDetail"
                style="padding:8px 16px;border-radius:8px;border:none;cursor:pointer;font-weight:700;font-size:.85rem;background:var(--primary);color:#fff;"
                :style="savingForm ? 'opacity:.6;cursor:not-allowed;' : ''"
              >
                {{ savingForm ? 'Menyimpan...' : '💾 Simpan Detail Penanganan' }}
              </button>
            </div>
          </div>

          <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:8px;">
            <button
              v-if="item.status === 'open'"
              :disabled="saving"
              @click="updateStatus('in_progress')"
              style="padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:700;font-size:.85rem;background:#fff1d6;color:#9a5b00;"
            >
              {{ saving ? 'Menyimpan...' : '▶ Mulai Tangani (In Progress)' }}
            </button>
            <button
              v-if="item.status === 'in_progress'"
              :disabled="saving"
              @click="updateStatus('closed')"
              style="padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:700;font-size:.85rem;background:#e8f7ec;color:#146c2e;"
            >
              {{ saving ? 'Menyimpan...' : '✓ Tandai Selesai (Closed)' }}
            </button>
            <button
              v-if="item.status === 'closed'"
              :disabled="saving"
              @click="updateStatus('open')"
              style="padding:10px 16px;border-radius:8px;border:1px solid #cbd5e1;cursor:pointer;font-weight:700;font-size:.85rem;background:#fff;color:#334155;"
            >
              ↩ Buka Kembali
            </button>

            <RouterLink
              :to="`/gangguan/${route.params.id}/upload`"
              style="display:inline-flex;align-items:center;justify-content:center;padding:10px 20px;border-radius:8px;background:var(--primary,#2563eb);color:#fff;font-weight:700;font-size:.85rem;text-decoration:none;"
            >
              📎 Upload Evidence
            </RouterLink>
          </div>
        </article>
        
        <!-- Analisa Teknis -->
        <article class="card" style="padding:20px; display:flex; flex-direction:column;">
          <h3 style="margin:0 0 12px; font-size:1.05rem; color:var(--text-main);">Analisa Teknis</h3>
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; flex: 1;">
            <p style="margin:0; white-space: pre-wrap; font-size: 0.9rem; line-height: 1.6; color: #334155;">{{ item.analisa || '-' }}</p>
          </div>
        </article>
      </div>

      <!-- Evidence / Lampiran -->
      <article class="card no-print" style="grid-column:1/-1;">
        <h3 style="margin:0 0 16px;">📁 Evidence / Lampiran</h3>
        <div v-if="item.evidences?.length" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;">
          <article v-for="evidence in item.evidences" :key="evidence.id" class="card" style="padding:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px;">
              <p style="margin:0;font-size:.78rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ evidence.filename }}</p>
              <div style="display: flex; gap: 8px;">
                <button
                  type="button"
                  @click="deleteEvidence(evidence.id)"
                  style="background:none; border:none; color:#ef4444; cursor:pointer; padding:0; display:flex; align-items:center;"
                  title="Hapus Evidence"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zm2.46-7.12l1.41-1.41L12 12.59l2.12-2.12 1.41 1.41L13.41 14l2.12 2.12-1.41 1.41L12 15.41l-2.12 2.12-1.41-1.41L10.59 14l-2.13-2.12zM15.5 4l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                </button>
                <a
                  :href="`${apiBaseUrl}/evidence/${evidence.id}/view`"
                  :download="evidence.filename"
                  style="white-space:nowrap;color:var(--primary);font-weight:700;font-size:.78rem;flex-shrink:0;"
                >↓ Unduh</a>
              </div>
            </div>
            <a :href="`${apiBaseUrl}/evidence/${evidence.id}/view`" target="_blank" rel="noopener noreferrer" style="display:block;">
              <img
                :src="`${apiBaseUrl}/evidence/${evidence.id}/view`"
                :alt="evidence.filename"
                style="width:100%;height:140px;border-radius:8px;border:1px solid var(--border);object-fit:cover;background:#f3f4f6;display:block;"
              >
            </a>
          </article>
        </div>
        <div v-else style="text-align:center;padding:32px 0;color:var(--muted);">
          <div style="font-size:2rem;margin-bottom:8px;">📎</div>
          Belum ada evidence yang diupload.
        </div>
      </article>

      <!-- ── PREVIEW & PRINTABLE BERITA ACARA TEMPLATE ── -->
      <div v-if="showPrintPreview" class="print-preview-overlay">
        <div class="print-preview-modal">
          <div class="print-preview-header">
            <h3 style="margin: 0; font-size: 1.2rem; color: #1e293b;">Preview Berita Acara</h3>
            <button @click="showPrintPreview = false" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b;">&times;</button>
          </div>
          
          <div class="print-preview-body">
            <!-- Form Input Tambahan -->
            <div style="background: #fff; padding: 16px; border-radius: 8px; margin-bottom: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
              <div>
                <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 4px;">Nomor Surat</label>
                <input v-model="baForm.nomorSurat" type="text" style="width:100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" />
              </div>
              <div>
                <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 4px;">Nama Perangkat</label>
                <select v-model="baForm.namaPerangkat" style="width:100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff;">
                  <option value="SYSCCA">SYSCCA</option>
                  <option value="SYSCCAE">SYSCCAE</option>
                  <option value="ICRM+">ICRM+</option>
                  <option value="HARDWARE">HARDWARE</option>
                  <option value="BOTIKA">BOTIKA</option>
                  <option value="ICONNPAY">ICONNPAY</option>
                  <option value="SIP">SIP</option>
                  <option value="INTERNET">INTERNET</option>
                  <option value="LOCAL NETWORK">LOCAL NETWORK</option>
                  <option value="MICROSIP">MICROSIP</option>
                </select>
              </div>
              <div>
                <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 4px;">Kode</label>
                <input v-model="baForm.kode" type="text" style="width:100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" />
              </div>
              <div>
                <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 4px;">Kubikal</label>
                <select v-model="baForm.kubikal" @change="onCubicleChange" style="width:100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff;">
                  <option value="">-- Pilih Kubikal --</option>
                  <option v-if="baForm.kubikal && !cubicleList.some(c => c.nama === baForm.kubikal)" :value="baForm.kubikal">
                    {{ baForm.kubikal }}
                  </option>
                  <option v-for="c in cubicleList" :key="c.id || c.nama" :value="c.nama">
                    {{ c.nama }}{{ c.ext ? ` (Ext: ${c.ext})` : '' }}
                  </option>
                </select>
              </div>
              <div>
                <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 4px;">Ext / IP</label>
                <input v-model="baForm.extIp" type="text" style="width:100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" />
              </div>
              <div>
                <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 4px;">ID Task SIP</label>
                <input v-model="baForm.idTaskSip" type="text" style="width:100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;" />
              </div>
            </div>

            <div class="printable-berita-acara">
              <div class="printable-berita-acara-page">
                <table class="ba-kop-table" style="text-align: center; font-size: 8.5pt; font-family: Calibri, Arial, sans-serif; background-color: #e2e8f0; border: 1px solid #000; -webkit-print-color-adjust: exact; print-color-adjust: exact; margin-bottom: 8px;">
                  <tbody>
                    <tr>
                      <td style="width: 3%; border: 1px solid #000; padding: 4px; font-weight: bold;">Halaman</td>
                      <td style="width: 4.5%; border: 1px solid #000; padding: 4px; font-weight: bold;">Tanggal Berlaku</td>
                      <td style="width: 34%; border: 1px solid #000; padding: 4px; font-weight: bold;">Departemen:</td>
                      <td style="width: 46.5%; border: 1px solid #000; padding: 4px; font-weight: bold;">{{ baSettings.ba_brand_name }}</td>
                      <td rowspan="2" style="width: 12%; border: 1px solid #000; padding: 4px; background-color: #fff;">
                        <img v-if="baSettings.ba_logo_url" :src="baSettings.ba_logo_url" style="max-height:55px;max-width:100%;object-fit:contain;" />
                        <div v-else style="display:inline-flex;align-items:center;gap:3px;border:1.5px solid #003;padding:3px 6px;border-radius:3px;">
                          <span style="color:#d97706;font-weight:900;font-size:11pt;">⚡ PLN</span>
                          <span style="color:#0284c7;font-weight:800;font-size:7pt;">Icon Plus</span>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td style="border: 1px solid #000; padding: 4px; font-weight: bold;">1</td>
                      <td style="border: 1px solid #000; padding: 4px; font-weight: bold;">{{ fmtDateShort(item.created_at) }}</td>
                      <td style="border: 1px solid #000; padding: 4px; font-weight: bold;">{{ baSettings.ba_departemen }}</td>
                      <td style="border: 1px solid #000; padding: 4px; font-weight: bold;">{{ baSettings.ba_title }}</td>
                    </tr>
                  </tbody>
                </table>

                <div style="font-size: 8pt; font-weight: bold; margin-bottom: 12px; padding: 0 4px; line-height: 1.6;">
                  <div style="display: flex;">
                    <div style="width: 110px;">Nomor Surat</div>
                    <div>: {{ baForm.nomorSurat || '-' }}</div>
                  </div>
                  <div style="display: flex;">
                    <div style="width: 110px;">Nama Perangkat</div>
                    <div>: {{ baForm.namaPerangkat || '-' }}</div>
                  </div>
                  <div style="display: flex; justify-content: space-between;">
                    <div style="display: flex;">
                      <div style="width: 110px;">Kode</div>
                      <div style="width: 150px;">: {{ baForm.kode || '-' }}</div>
                      <div>Kubikal : &nbsp; {{ baForm.kubikal || '-' }}</div>
                    </div>
                    <div style="display: flex; gap: 20px;">
                      <div>Periode : {{ getYear(item.created_at) }}</div>
                      <div>Bulan : {{ getMonthName(item.created_at) }}</div>
                    </div>
                  </div>
                </div>

                <table class="ba-main-table">
                  <thead>
                    <tr>
                      <th style="width:2.5%;">No</th>
                      <th style="width:7%;">HARI/<br/>TANGGAL</th>
                      <th style="width:8%;">START DOWNTIME<br/><span style="font-weight:normal;font-size:6.5pt;">(h:mm:ss)</span></th>
                      <th style="width:8%;">END DOWNTIME<br/><span style="font-weight:normal;font-size:6.5pt;">(h:mm:ss)</span></th>
                      <th style="width:7%;">NAMA AGENT</th>
                      <th style="width:7%;">EXT / IP</th>
                      <th style="width:12%;">ID TASK SIP</th>
                      <th style="width:10%;">PENYEBAB PERMASALAHAN</th>
                      <th style="width:10%;">PENYELESAIAN MASALAH</th>
                      <th style="width:6.5%;">IMPACT</th>
                      <th style="width:8%;">DURASI DOWNTIME</th>
                      <th style="width:7%;">PETUGAS TS</th>
                      <th style="width:7%;">ANALISA</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr class="ba-data-row">
                      <td style="text-align:center;">1</td>
                      <td>{{ fmtDateFull(item.created_at) }}</td>
                      <td style="text-align:center;">{{ fmtTime(item.start_time || item.created_at) }}</td>
                      <td style="text-align:center;">{{ fmtTime(item.end_time || item.resolved_at) }}</td>
                      <td>{{ item.jenis_gangguan === 'Massal' ? 'ALL AGENT' : (item.creator?.name || '-') }}</td>
                      <td style="text-align:center;">{{ baForm.extIp || '-' }}</td>
                      <td>{{ baForm.idTaskSip || '-' }}</td>
                      <td>{{ item.penyebab_permasalahan || item.deskripsi || '-' }}</td>
                      <td>{{ item.penyelesaian_masalah || '-' }}</td>
                      <td>{{ item.impact || '-' }}</td>
                      <td style="text-align:center;">{{ calcDurasi(item.start_time, item.end_time) }}</td>
                      <td>{{ item.assignee?.name || '-' }}</td>
                      <td>{{ item.analisa || '-' }}</td>
                    </tr>
                    <tr class="ba-total-row">
                      <td colspan="7" style="border:none !important; background:transparent !important;"></td>
                      <td colspan="3" class="ba-total-cell" style="text-align:right; background-color:#5b9bd5 !important; color:#000 !important; font-weight:bold; border:1px solid #000 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact;">TOTAL DOWN TIME :</td>
                      <td class="ba-total-cell" style="text-align:center; background-color:#5b9bd5 !important; color:#000 !important; font-weight:bold; border:1px solid #000 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact;">{{ calcDurasi(item.start_time, item.end_time) }}</td>
                      <td colspan="2" style="border:none !important; background:transparent !important;"></td>
                    </tr>
                  </tbody>
                </table>

                <div class="ba-signature-box">
                  <table style="width:100%;border:none;margin-bottom:8px;">
                    <tbody>
                      <tr>
                        <td style="width:50%;border:none;"></td>
                        <td style="width:25%;text-align:center;border:none;font-size:8pt;font-weight:600;">{{ baSettings.ba_location }}, {{ fmtDateFull(new Date()) }}</td>
                        <td style="width:25%;border:none;"></td>
                      </tr>
                    </tbody>
                  </table>
                  <div style="display:flex;justify-content:flex-end;gap:80px;text-align:center;">
                    <div style="min-width:180px;display:flex;flex-direction:column;align-items:center;">
                      <div style="font-size:8pt;font-weight:700;color:#c2410c;margin-bottom:6px;">KOORD OPS</div>
                      <div style="height:60px;display:flex;align-items:center;justify-content:center;margin-bottom:6px;">
                        <img v-if="baSettings.ba_koord_signature_url" :src="baSettings.ba_koord_signature_url" style="max-height:55px;max-width:150px;object-fit:contain;" />
                      </div>
                      <div style="font-size:7.5pt;font-weight:800;margin-bottom:2px;">KOORDINATOR</div>
                      <div style="font-size:7.5pt;">[ {{ baSettings.ba_koord_name }} ]</div>
                    </div>
                    <div style="min-width:180px;display:flex;flex-direction:column;align-items:center;">
                      <div style="font-size:8pt;font-weight:700;color:#c2410c;margin-bottom:6px;">TEAM SUPPORT</div>
                      <div style="height:60px;display:flex;align-items:center;justify-content:center;margin-bottom:6px;">
                        <img v-if="item.assignee?.signature_url" :src="item.assignee.signature_url" style="max-height:55px;max-width:150px;object-fit:contain;" />
                      </div>
                      <div style="font-size:7.5pt;font-weight:800;margin-bottom:2px;">TECHNICAL SUPPORT</div>
                      <div style="font-size:7.5pt;">[ {{ item.assignee?.name || '' }} ]</div>
                    </div>
                  </div>
                </div>

                <div class="ba-evidence-container" v-if="baSettings.ba_show_evidence === 'true' && item.evidences?.length">
                  <div class="ba-evidence-grid">
                    <div v-for="ev in item.evidences" :key="ev.id" class="ba-evidence-item">
                      <div class="ba-evidence-item-header">EVIDENCE</div>
                      <div class="ba-evidence-item-body">
                        <img :src="`${apiBaseUrl}/evidence/${ev.id}/view`" />
                      </div>
                      <div class="ba-evidence-item-footer"></div>
                    </div>
                  </div>
                </div>


              </div>
            </div>
          </div>

          <div class="print-preview-footer">
            <button @click="showPrintPreview = false" style="padding: 8px 16px; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer; font-weight: 600;">Batal</button>
            <button @click="exportExcelBeritaAcara" style="padding: 8px 16px; background: #16a34a; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">📊 Export Excel</button>
            <button @click="executePrint" style="padding: 8px 16px; background: #1d4ed8; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">🖨️ Cetak PDF</button>
          </div>
        </div>
      </div>

    </template>
  </section>
</template>

<style scoped>
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
  padding: 20px; background: #e2e8f0; overflow-y: auto; overflow-x: auto; flex: 1;
}
.print-preview-footer {
  padding: 16px 20px; border-top: 1px solid #e2e8f0; display: flex;
  justify-content: flex-end; gap: 12px; background: #fff; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;
}
.printable-berita-acara {
  background: #fff; padding: 10px 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin: 0 auto;
  color: #000; font-family: Arial, sans-serif; font-size: 8pt; width: 297mm; min-width: 297mm; box-sizing: border-box; overflow-x: hidden;
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
.ba-main-table { width: 100%; border-collapse: collapse; font-size: 7.5pt; margin-bottom: 12px; table-layout: fixed; }
.ba-main-table th { background-color: #5b9bd5 !important; color: #000000 !important; border: 1px solid #000 !important; padding: 6px 4px; font-weight: bold; text-align: center; font-size: 7pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; overflow-wrap: break-word; word-break: normal; }
.ba-main-table td { border: 1px solid #000 !important; padding: 6px 4px; vertical-align: middle; text-align: center; overflow-wrap: break-word; word-break: break-word; }
.ba-data-row td { min-height: 60px; height: 60px; }

.ba-signature-box { margin-top: 14px; margin-bottom: 16px; page-break-inside: avoid; }
.ba-evidence-container { margin-top: 14px; }
.ba-evidence-grid { display: block; padding: 0; }
.ba-evidence-item { border: 1px solid #000; margin-bottom: 14px; page-break-inside: avoid; }
.ba-evidence-item:last-child { margin-bottom: 0; }
.ba-evidence-item-header { background-color: #00bcd4 !important; color: #000 !important; font-weight: bold; text-align: center; padding: 4px 6px; font-size: 8pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; border-bottom: 1px solid #000; }
.ba-evidence-item-body { min-height: 120px; padding: 12px; text-align: center; display: flex; align-items: center; justify-content: center; }
.ba-evidence-item-body img { max-height: 350px; max-width: 100%; object-fit: contain; }
.ba-evidence-item-footer { background-color: #00bcd4 !important; height: 8px; -webkit-print-color-adjust: exact; print-color-adjust: exact; border-top: 1px solid #000; }
.ba-evidence-empty { padding: 30px; text-align: center; color: #64748b; font-style: italic; }
</style>

<style>
/* Print styles are handled by the dedicated print window in executePrint() */
/* These styles apply if user presses Ctrl+P directly */
@media print {
  body > * { display: none !important; }
  .print-preview-overlay { display: none !important; }
}
</style>
