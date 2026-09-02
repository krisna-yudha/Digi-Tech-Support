<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';

const auth = useAuthStore();
const route = useRoute();

const items    = ref([]);
const loading  = ref(false);
const error    = ref('');
const page     = ref(1);
const lastPage = ref(1);
const total    = ref(0);

const sortBy       = ref('created_at');
const sortDir      = ref('desc');
const perPage      = ref(10);
const search       = ref('');
const filterStatus = ref('');
const filterKategori = ref('');
const filterPeriod = ref('');
const filterStartDate = ref('');
const filterEndDate = ref('');
const filterJenis = ref('');

const kategoriOptions = ref([
  'SYSCCA', 'SYSCCAE', 'ICRM+', 'HARDWARE', 'BOTIKA',
  'ICONNPAY', 'SIP', 'INTERNET', 'LOCAL NETWORK', 'MICROSIP'
]);

const displayedItems = computed(() => {
  let list = [...(items.value || [])];

  // 1. Filter Kategori
  if (filterKategori.value) {
    const k = filterKategori.value.toLowerCase().trim();
    list = list.filter(item => {
      const kat = String(item.kategori || '').toLowerCase().trim();
      const cub = String(item.cubicle_name || '').toLowerCase().trim();
      const jud = String(item.judul || '').toLowerCase().trim();
      return kat === k || kat.includes(k) || cub === k || jud.includes(k);
    });
  }

  // 2. Filter Search
  if (search.value) {
    const q = search.value.toLowerCase().trim();
    list = list.filter(item => {
      const jud = String(item.judul || '').toLowerCase();
      const kat = String(item.kategori || '').toLowerCase();
      const tNum = String(item.ticket_number || '').toLowerCase();
      const sip = String(item.id_task_sip || '').toLowerCase();
      const agent = String(item.creator?.name || '').toLowerCase();
      const ts = String(item.assignee?.name || '').toLowerCase();
      const peny = String(item.penyebab_permasalahan || '').toLowerCase();
      const solv = String(item.penyelesaian_masalah || '').toLowerCase();
      const impact = String(item.impact || '').toLowerCase();
      return jud.includes(q) || kat.includes(q) || tNum.includes(q) || sip.includes(q) || agent.includes(q) || ts.includes(q) || peny.includes(q) || solv.includes(q) || impact.includes(q);
    });
  }

  // 3. Filter Status
  if (filterStatus.value) {
    list = list.filter(item => String(item.status || '').toLowerCase() === filterStatus.value.toLowerCase());
  }

  // 4. Filter Jenis Gangguan
  if (filterJenis.value) {
    list = list.filter(item => String(item.jenis_gangguan || '').toLowerCase() === filterJenis.value.toLowerCase());
  }

  // 5. Client-Side Sorting
  if (sortBy.value) {
    list.sort((a, b) => {
      let valA = a[sortBy.value];
      let valB = b[sortBy.value];
      if (sortBy.value === 'created_at' || sortBy.value === 'start_time' || sortBy.value === 'end_time') {
        valA = valA ? new Date(valA).getTime() : 0;
        valB = valB ? new Date(valB).getTime() : 0;
      } else if (sortBy.value === 'durasi') {
        valA = Number(valA || 0);
        valB = Number(valB || 0);
      } else {
        valA = String(valA || '').toLowerCase();
        valB = String(valB || '').toLowerCase();
      }
      if (valA < valB) return sortDir.value === 'asc' ? -1 : 1;
      if (valA > valB) return sortDir.value === 'asc' ? 1 : -1;
      return 0;
    });
  }

  return list;
});

const showFilters = ref(window.innerWidth > 768);

function getRowStyle(status) {
  const s = String(status || '').toLowerCase();
  if (s === 'open')        return 'border-left: 4px solid #ef4444;';
  if (s === 'in_progress') return 'border-left: 4px solid #f59e0b;';
  if (s === 'closed')      return 'border-left: 4px solid #10b981;';
  return 'border-left: 4px solid #cbd5e1;';
}

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

async function fetchGangguan(p = 1) {
  loading.value = true;
  error.value   = '';
  try {
    const { data } = await api.get('/gangguan', {
      params: {
        page: p,
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
        per_page: perPage.value,
        search: search.value || undefined,
        status: filterStatus.value || undefined,
        kategori: filterKategori.value || undefined,
        period: filterPeriod.value || undefined,
        jenis_gangguan: filterJenis.value || undefined,
        start_date: filterPeriod.value === 'custom' ? (filterStartDate.value || undefined) : undefined,
        end_date: filterPeriod.value === 'custom' ? (filterEndDate.value || undefined) : undefined,
      }
    });
    items.value    = data.data || [];
    page.value     = data.current_page ?? 1;
    lastPage.value = data.last_page    ?? 1;
    total.value    = data.total        ?? 0;
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal memuat data gangguan.';
  } finally {
    loading.value = false;
  }
}

function applySort(col) {
  if (sortBy.value === col) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortBy.value = col;
    sortDir.value = 'desc';
  }
  fetchGangguan(1);
}
function sortIcon(col) {
  if (sortBy.value !== col) return '↕';
  return sortDir.value === 'asc' ? '↑' : '↓';
}
function pageNumbers() {
  const range = [], delta = 2;
  for (let i = Math.max(1, page.value - delta); i <= Math.min(lastPage.value, page.value + delta); i++) range.push(i);
  return range;
}

const deleteConfirmId = ref(null);
const deleteConfirmTitle = ref('');

function promptDelete(item) {
  deleteConfirmId.value = item.id;
  deleteConfirmTitle.value = item.judul || item.ticket_number;
}

async function confirmDelete() {
  const id = deleteConfirmId.value;
  if (!id) return;
  try {
    await api.delete(`/gangguan/${id}`);
    deleteConfirmId.value = null;
    fetchGangguan(page.value);
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal menghapus gangguan.');
  }
}

function cancelDelete() {
  deleteConfirmId.value = null;
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

// ───── EXPORT BERITA ACARA PER GANGGUAN ─────
const printableItems = ref([]); // array of items for print
const printAllMode   = ref(false);
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

function openExportPreview(item) {
  printAllMode.value   = false;
  printableItems.value = [item];
  
  baForm.value.nomorSurat = item.nomor_surat || '';
  baForm.value.namaPerangkat = item.kategori || '';
  baForm.value.kode = item.kode || '';
  baForm.value.kubikal = item.cubicle_name || item.kategori || '';
  baForm.value.idTaskSip = item.id_task_sip || '';
  
  const found = cubicleList.value.find(c => c.nama === baForm.value.kubikal);
  if (found && (found.ext || found.ip)) {
    baForm.value.extIp = [found.ext, found.ip].filter(Boolean).join(' / ');
  } else {
    baForm.value.extIp = '';
  }
  
  showPrintPreview.value = true;
}

function exportPdfAll() {
  if (!items.value || !items.value.length) return;
  printAllMode.value   = true;
  printableItems.value = items.value;
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
  const printEl = document.querySelector('.printable-berita-acara-wrapper');
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
          .printable-berita-acara-page { page-break-after: always; }
          .printable-berita-acara-page:last-child { page-break-after: auto; }
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
        /* Info row */
        .ba-info-row { display: flex; justify-content: flex-end; align-items: center; font-size: 7.5pt; padding: 3px 6px; margin-bottom: 6px; }
        .printable-berita-acara-wrapper { width: 100%; }
        .printable-berita-acara-page { margin-bottom: 20px; }
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
          <td colspan="4"><b>Nomor Surat:</b> ${!printAllMode.value ? baForm.value.nomorSurat : (d.ticket_number || '-')}</td>
          <td colspan="4"><b>Kubikal:</b> ${!printAllMode.value ? baForm.value.kubikal : (d.kategori || '-')}</td>
          <td colspan="3"><b>Periode:</b> ${getYear(d.created_at)}</td>
          <td colspan="2"><b>Bulan:</b> ${getMonthName(d.created_at)}</td>
        </tr>
        <tr>
          <td colspan="4"><b>Nama Perangkat:</b> ${!printAllMode.value ? baForm.value.namaPerangkat : (d.kategori || '-')}</td>
          <td colspan="9"><b>Kode:</b> ${!printAllMode.value ? baForm.value.kode : '-'}</td>
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
          <td>${!printAllMode.value ? baForm.value.extIp : '-'}</td>
          <td>${!printAllMode.value ? baForm.value.idTaskSip : (d.ticket_number || '-')}</td>
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

function exportExcelAll() {
  if (!items.value || !items.value.length) return;
  
  const s = baSettings.value;
  const filename = `Berita_Acara_Semua_Gangguan_${new Date().toISOString().slice(0,10)}.xls`;
  
  let blocks = '';
  items.value.forEach((d) => {
    blocks += `
      <table>
        <tr>
          <td colspan="2"><b>Halaman:</b> 1</td>
          <td colspan="4"><b>Departemen:</b> ${s.ba_departemen}</td>
          <td colspan="7" style="font-weight:bold; font-size:13px; text-align:center; background-color:#f1f5f9;">${s.ba_brand_name}<br/>${s.ba_title}</td>
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
        <tr style="background-color:#1d4ed8; color:#ffffff; font-weight:bold; text-align:center;">
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
        <tr style="background-color:#2563eb; color:#ffffff; font-weight:bold;">
          <td colspan="10" style="text-align:right;">TOTAL DOWN TIME :</td>
          <td colspan="3">${calculateDurasi(d.start_time, d.end_time)}</td>
        </tr>
        <tr><td colspan="13"></td></tr>
        <tr>
          <td colspan="7"></td>
          <td colspan="6" style="text-align:center;">${s.ba_location}, ${formatDateFull(new Date())}<br/><br/>
            <b>${s.ba_koord_title}</b> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>TEAM SUPPORT</b><br/><br/><br/>
            ${s.ba_koord_title}: [ ${s.ba_koord_name} ] &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ${s.ba_ts_title}: [ ${d.assignee?.name || 'Petugas TS'} ]
          </td>
        </tr>
      </table>
      <br/><br/><hr/><br/><br/>
    `;
  });

  const excelContent = `
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
      <meta charset="utf-8">
      <style>
        table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px; width: 100%; margin-bottom: 30px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; vertical-align: top; }
      </style>
    </head>
    <body>
      ${blocks}
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
  if (route.query.kategori) {
    filterKategori.value = String(route.query.kategori);
  }
  if (route.query.search) {
    search.value = String(route.query.search);
  }
  if (route.query.status) {
    filterStatus.value = String(route.query.status);
  }
  fetchBaSettings();
  fetchGangguan();
  fetchCubicles();
});

watch(() => route.query, (q) => {
  if (q.kategori !== undefined) filterKategori.value = q.kategori || '';
  if (q.search !== undefined) search.value = q.search || '';
  if (q.status !== undefined) filterStatus.value = q.status || '';
  fetchGangguan(1);
});
</script>

<template>
  <section class="grid" style="gap:16px;">

    <!-- Header -->
    <div class="page-title-wrap" style="grid-column:1/-1; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:12px;">
      <div>
        <h2 class="page-title">Daftar Gangguan</h2>
        <p class="page-desc">Semua tiket gangguan yang masuk. Total: <strong>{{ total }}</strong> tiket</p>
        <div style="display:flex; align-items:center; gap:16px; margin-top:8px; font-size:0.75rem; font-weight:600; color:#64748b;">
          <span style="display:flex; align-items:center; gap:6px;"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#ef4444;"></span> Open</span>
          <span style="display:flex; align-items:center; gap:6px;"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#f59e0b;"></span> In Progress</span>
          <span style="display:flex; align-items:center; gap:6px;"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#10b981;"></span> Closed</span>
        </div>
      </div>


    </div>

    <!-- Toolbar -->
    <div class="filter-toolbar">
      <div class="search-box">
        <input v-model="search" @keyup.enter="fetchGangguan(1)" type="text" placeholder="Cari tiket, judul, agent, kategori..." />
        <span class="search-icon">🔍</span>
        <button @click="fetchGangguan(1)" class="search-btn">Cari</button>
      </div>
      
      <button @click="showFilters = !showFilters" class="mobile-filter-btn">
        <svg v-if="!showFilters" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
        <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        Filter
      </button>

      <div v-show="showFilters" class="filter-controls">
        <select v-model="filterStatus" @change="fetchGangguan(1)" class="filter-select">
          <option value="">Semua Status</option>
          <option value="open">Open</option>
          <option value="in_progress">In Progress</option>
          <option value="closed">Closed</option>
        </select>
        <select v-model="filterKategori" @change="fetchGangguan(1)" class="filter-select">
          <option value="">Semua Kategori</option>
          <option v-for="kat in kategoriOptions" :key="kat" :value="kat">{{ kat }}</option>
        </select>
        <select v-model="filterJenis" @change="fetchGangguan(1)" class="filter-select">
          <option value="">Semua Jenis</option>
          <option value="Personal">Personal</option>
          <option value="Massal">Massal</option>
        </select>
        <select v-model="filterPeriod" @change="fetchGangguan(1)" class="filter-select">
          <option value="">Semua Waktu</option>
          <option value="today">Hari Ini</option>
          <option value="this_week">Minggu Ini</option>
          <option value="this_month">Bulan Ini</option>
          <option value="custom">Custom</option>
        </select>
        <select v-model="perPage" @change="fetchGangguan(1)" class="filter-select">
          <option :value="5">5 / halaman</option>
          <option :value="10">10 / halaman</option>
          <option :value="25">25 / halaman</option>
          <option :value="50">50 / halaman</option>
          <option :value="100">100 / halaman</option>
        </select>
      </div>

      <div v-show="showFilters" v-if="filterPeriod === 'custom'" class="custom-date-filter">
        <input type="date" v-model="filterStartDate" @change="fetchGangguan(1)" class="filter-input-date" />
        <span style="color:#64748b;">-</span>
        <input type="date" v-model="filterEndDate" @change="fetchGangguan(1)" class="filter-input-date" />
      </div>

      <button v-show="showFilters" v-if="search || filterStatus || filterKategori || filterPeriod || filterJenis" @click="search='';filterStatus='';filterKategori='';filterPeriod='';filterStartDate='';filterEndDate='';filterJenis='';fetchGangguan(1)" class="reset-btn">✕ Reset</button>
    </div>

    <!-- Error -->
    <div v-if="error" class="alert alert-danger" style="grid-column:1/-1;">{{ error }}</div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="card" style="grid-column:1/-1;padding:32px;text-align:center;color:var(--muted);">
      Memuat data...
    </div>

    <!-- Tabel Format Excel (Premium) -->
    <div v-else-if="displayedItems.length" class="card" style="grid-column:1/-1;padding:0;overflow:hidden;border:1px solid var(--border);box-shadow:0 4px 12px rgba(0,0,0,0.03);margin-bottom:24px;">

      <div class="table-responsive elegant-scroll" style="overflow-x: auto; width: 100%;">
        <table style="width: 100%; border-collapse: collapse; white-space: nowrap; font-size: 0.85rem;">
          <thead>
            <tr style="background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
              <th class="th-base" style="text-align:center;">No</th>
              <th class="th-sort" @click="applySort('created_at')">Hari/Tanggal <span class="sort-icon">{{ sortIcon('created_at') }}</span></th>
              <th class="th-base">Nama Agent</th>
              <th class="th-sort" @click="applySort('start_time')">Start Downtime <span class="sort-icon">{{ sortIcon('start_time') }}</span></th>
              <th class="th-sort" @click="applySort('end_time')">End Downtime <span class="sort-icon">{{ sortIcon('end_time') }}</span></th>
              <th class="th-sort" @click="applySort('durasi')">Durasi <span class="sort-icon">{{ sortIcon('durasi') }}</span></th>
              <th class="th-base" style="text-align:center;">Ext / IP</th>
              <th class="th-sort" @click="applySort('kategori')">Kubikal <span class="sort-icon">{{ sortIcon('kategori') }}</span></th>
              <th class="th-base">Subject Kendala</th>
              <th class="th-base">Penyebab Permasalahan</th>
              <th class="th-base">Penyelesaian Masalah</th>
              <th class="th-base">Impact</th>
              <th class="th-base" style="text-align:center;">Jml Agent Terdampak</th>
              <th class="th-base">Petugas TS (Shift)</th>
              <th class="th-base">Analisa</th>
              <th class="th-sticky">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in displayedItems" :key="item.id" class="table-row-hover"
              :style="`border-bottom: 1px solid #f1f5f9; ${getRowStyle(item.status)}`">
              <td class="td-center muted">{{ (page - 1) * perPage + index + 1 }}</td>
              <td class="td-base bold">{{ formatDateOnly(item.created_at) }}</td>
              <td class="td-base">{{ item.jenis_gangguan === 'Massal' ? 'ALL AGENT' : (item.creator?.name || '-') }}</td>
              <td class="td-center">{{ formatTimeOnly(item.start_time) }}</td>
              <td class="td-center">{{ formatTimeOnly(item.end_time) }}</td>
              <td class="td-center bold">{{ calculateDurasi(item.start_time, item.end_time) }}</td>
              <td class="td-center muted">-</td>
              <td class="td-center">{{ item.kategori || '-' }}</td>
              <td class="td-ellipsis" :title="item.judul">
                <div style="display:flex; flex-direction:column; gap:4px;">
                  <span>{{ item.judul || '-' }}</span>
                  <span v-if="item.jenis_gangguan === 'Massal'" style="background: #fff1f2; color: #e11d48; border: 1px solid #ffe4e6; padding: 3px 8px; border-radius: 6px; width: fit-content; font-size: 0.65rem; font-weight: 700; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 4px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    KENDALA MASSAL
                  </span>
                </div>
              </td>
              <td class="td-ellipsis" :title="item.penyebab_permasalahan">{{ item.penyebab_permasalahan || '-' }}</td>
              <td class="td-ellipsis" :title="item.penyelesaian_masalah">{{ item.penyelesaian_masalah || '-' }}</td>
              <td class="td-ellipsis" :title="item.impact">{{ item.impact || '-' }}</td>
              <td class="td-center bold" style="color:var(--primary);">{{ item.jumlah_agent_terdampak || 1 }} Agent</td>
              <td class="td-base bold">{{ item.assignee?.name || '-' }}</td>
              <td class="td-ellipsis wide" :title="item.analisa">{{ item.analisa || '-' }}</td>
              <td class="sticky-aksi">
                <div style="display:flex; justify-content:center; align-items:center; gap:5px;">
                  <RouterLink :to="`/gangguan/${item.id}`" class="btn-lihat">Lihat</RouterLink>
                  <button v-if="auth.hasRole('Admin') || auth.hasRole('TS')" @click="promptDelete(item)" class="btn-hapus">Hapus</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="pagination-wrap">
        <span class="pagination-info">
          Menampilkan {{ displayedItems.length }} data{{ total ? ' (Total ' + total + ')' : '' }}
        </span>
        <div class="pagination-buttons">
          <button class="pg-btn" :disabled="page <= 1" @click="fetchGangguan(1)">«</button>
          <button class="pg-btn" :disabled="page <= 1" @click="fetchGangguan(page - 1)">‹</button>
          <button v-for="n in pageNumbers()" :key="n" class="pg-btn" :class="{'pg-active': n === page}" @click="fetchGangguan(n)">{{ n }}</button>
          <button class="pg-btn" :disabled="page >= lastPage" @click="fetchGangguan(page + 1)">›</button>
          <button class="pg-btn" :disabled="page >= lastPage" @click="fetchGangguan(lastPage)">»</button>
        </div>
      </div>
    </div>

    <div v-else class="card" style="grid-column:1/-1;text-align:center;padding:48px;color:var(--muted);">
      <div style="font-size:2.5rem;margin-bottom:8px;">📭</div>
      <p>Tidak ada data yang ditemukan.</p>
      <button v-if="search || filterStatus || filterKategori || filterPeriod || filterJenis" @click="search='';filterStatus='';filterKategori='';filterPeriod='';filterStartDate='';filterEndDate='';filterJenis='';fetchGangguan(1)"
        style="margin-top:12px;padding:8px 20px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;font-size:0.85rem;">Reset Filter</button>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div v-if="deleteConfirmId" class="modal-overlay">
      <div class="modal-content">
        <h3 style="margin-top:0; color:var(--text-main); font-size: 1.2rem;">Konfirmasi Hapus</h3>
        <p style="color:var(--muted); font-size:0.9rem; line-height:1.5;">Apakah Anda yakin ingin menghapus tiket <strong>{{ deleteConfirmTitle }}</strong>? Tindakan ini tidak dapat dibatalkan.</p>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:24px;">
          <button @click="cancelDelete" style="padding:10px 16px; border-radius:8px; border:1px solid #cbd5e1; background:#f8fafc; color:#334155; cursor:pointer; font-weight:600; font-size: 0.85rem;">Batal</button>
          <button @click="confirmDelete" style="padding:10px 16px; border-radius:8px; border:none; background:#ef4444; color:#fff; cursor:pointer; font-weight:600; font-size: 0.85rem;">Ya, Hapus</button>
        </div>
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
          <div v-if="!printAllMode" style="background: #fff; padding: 16px; border-radius: 8px; margin-bottom: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
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
          
          <div class="printable-berita-acara-wrapper">
            <div v-for="(printableItem, pIdx) in printableItems" :key="printableItem.id || pIdx" class="printable-berita-acara-page">
              <!-- KOP Table - MATCHING DETAIL PAGE -->
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
                    <td style="border: 1px solid #000; padding: 4px; font-weight: bold;">{{ formatDateOnly(printableItem.created_at) }}</td>
                    <td style="border: 1px solid #000; padding: 4px; font-weight: bold;">{{ baSettings.ba_departemen }}</td>
                    <td style="border: 1px solid #000; padding: 4px; font-weight: bold;">{{ baSettings.ba_title }}</td>
                  </tr>
                </tbody>
              </table>

              <div style="font-size: 8pt; font-weight: bold; margin-bottom: 12px; padding: 0 4px; line-height: 1.6;">
                <div style="display: flex;">
                  <div style="width: 110px;">Nomor Surat</div>
                  <div>: {{ !printAllMode ? (baForm.nomorSurat || '-') : (printableItem.ticket_number || '-') }}</div>
                </div>
                <div style="display: flex;">
                  <div style="width: 110px;">Nama Perangkat</div>
                  <div>: {{ !printAllMode ? (baForm.namaPerangkat || '-') : (printableItem.kategori || '-') }}</div>
                </div>
                <div style="display: flex; justify-content: space-between;">
                  <div style="display: flex;">
                    <div style="width: 110px;">Kode</div>
                    <div style="width: 150px;">: {{ !printAllMode ? (baForm.kode || '-') : '-' }}</div>
                    <div>Kubikal : &nbsp; {{ !printAllMode ? (baForm.kubikal || '-') : (printableItem.kategori || '-') }}</div>
                  </div>
                  <div style="display: flex; gap: 20px;">
                    <div>Periode : {{ getYear(printableItem.created_at) }}</div>
                    <div>Bulan : {{ getMonthName(printableItem.created_at) }}</div>
                  </div>
                </div>
              </div>

              <!-- Table Utama Kronologi -->
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
                    <td style="text-align: center;">1</td>
                    <td>{{ formatDateFull(printableItem.created_at) }}</td>
                    <td style="text-align: center;">{{ formatTimeOnly(printableItem.start_time || printableItem.created_at) }}</td>
                    <td style="text-align: center;">{{ formatTimeOnly(printableItem.end_time || printableItem.resolved_at) }}</td>
                    <td>{{ printableItem.jenis_gangguan === 'Massal' ? 'ALL AGENT' : (printableItem.creator?.name || '-') }}</td>
                    <td style="text-align: center;">{{ !printAllMode ? (baForm.extIp || '-') : '-' }}</td>
                    <td>{{ !printAllMode ? (baForm.idTaskSip || '-') : (printableItem.ticket_number || '-') }}</td>
                    <td>{{ printableItem.penyebab_permasalahan || printableItem.deskripsi || '-' }}</td>
                    <td>{{ printableItem.penyelesaian_masalah || '-' }}</td>
                    <td>{{ printableItem.impact || '-' }}</td>
                    <td style="text-align: center;">{{ calculateDurasi(printableItem.start_time, printableItem.end_time) }}</td>
                    <td>{{ printableItem.assignee?.name || '-' }}</td>
                    <td>{{ printableItem.analisa || '-' }}</td>
                  </tr>
                  <tr class="ba-total-row">
                    <td colspan="7" style="border:none !important; background:transparent !important;"></td>
                    <td colspan="3" class="ba-total-cell" style="text-align:right; background-color:#5b9bd5 !important; color:#000 !important; font-weight:bold; border:1px solid #000 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact;">TOTAL DOWN TIME :</td>
                    <td class="ba-total-cell" style="text-align:center; background-color:#5b9bd5 !important; color:#000 !important; font-weight:bold; border:1px solid #000 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact;">{{ calculateDurasi(printableItem.start_time, printableItem.end_time) }}</td>
                    <td colspan="2" style="border:none !important; background:transparent !important;"></td>
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
          <button v-if="!printAllMode" @click="exportExcelSingle(printableItems[0])" style="padding: 8px 16px; background: #16a34a; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">📊 Export Excel</button>
          <button @click="executePrint" style="padding: 8px 16px; background: #1d4ed8; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">🖨️ Cetak PDF</button>
        </div>
      </div>
    </div>

  </section>
</template>

<style scoped>
.modal-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;
}
.modal-content {
  background: #fff; padding: 24px; border-radius: 12px; width: 90%; max-width: 400px;
  box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
}
.filter-toolbar { grid-column: 1 / -1; display: flex; flex-direction: column; gap: 10px; }
.search-box { position: relative; width: 100%; }
.search-box input {
  width: 100%; padding: 10px 80px 10px 38px; border: 1.5px solid #e2e8f0; border-radius: 10px;
  font-size: 0.88rem; outline: none; background: #fff; box-sizing: border-box; margin: 0; height: 42px;
}
.search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.9rem; }
.search-btn {
  position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
  background: #2563eb; color: #fff; border: none; border-radius: 7px; padding: 5px 14px; font-size: 0.78rem; cursor: pointer; font-weight: 600;
}
.filter-controls { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; width: 100%; }
.filter-select {
  padding: 8px 10px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.83rem; background: #fff; cursor: pointer; color: #334155; width: 100%; margin: 0; height: 36px;
}
.custom-date-filter { display: flex; gap: 8px; align-items: center; width: 100%; }
.filter-input-date { flex: 1; padding: 7px 10px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.83rem; outline: none; margin: 0; width: 100%; }
.reset-btn { padding: 7px 14px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem; background: #fff; cursor: pointer; color: #64748b; font-weight: 600; }
.mobile-filter-btn {
  display: flex; gap: 6px; align-items: center; justify-content: center; padding: 8px 16px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; font-weight: 600; background: #fff; cursor: pointer; color: #334155; width: 100%;
}

@media (min-width: 769px) {
  .filter-toolbar { flex-direction: row; flex-wrap: wrap; align-items: center; }
  .search-box { min-width: 220px; flex: 1; }
  .filter-controls { display: flex; flex-wrap: wrap; width: auto; flex: 2 !important; gap: 8px; }
  .filter-select { width: auto; min-width: 120px; height: auto; }
  .custom-date-filter { width: auto; }
  .reset-btn { align-self: center; margin-left: auto; }
  .mobile-filter-btn { display: none; width: auto; }
}

.th-base { padding: 12px 16px; font-weight: 600; color: #475569; text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.05em; }
.th-sort { padding: 12px 16px; font-weight: 600; color: #475569; text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.05em; cursor: pointer; user-select: none; white-space: nowrap; }
.th-sort:hover { background: #f1f5f9; }
.th-sticky { position: sticky; right: 0; background-color: #f8fafc; padding: 12px 16px; text-align: center; font-weight: 600; color: #475569; text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.05em; z-index: 2; box-shadow: -4px 0 12px rgba(0,0,0,0.04); }
.sort-icon { font-size: 0.8rem; opacity: 0.5; margin-left: 3px; }

.td-base { padding: 12px 16px; color: #334155; }
.td-center { padding: 12px 16px; text-align: center; color: #475569; }
.td-ellipsis { padding: 12px 16px; color: #475569; min-width: 200px; max-width: 400px; white-space: normal; overflow-wrap: break-word; line-height: 1.5; }
.td-ellipsis.wide { max-width: 500px; }
.bold { font-weight: 600 !important; color: #334155 !important; }
.muted { color: #94a3b8 !important; }

table { border: 1px solid #cbd5e1; }
.th-base, .th-sort, .th-sticky { background-color: #f1f5f9; border-bottom: 2px solid #cbd5e1 !important; border-right: 1px solid #e2e8f0; }
.th-sticky { border-right: none; }

.td-base, .td-center, .td-ellipsis, .sticky-aksi { border-bottom: 1px solid #e2e8f0 !important; border-right: 1px solid #f1f5f9; }
.sticky-aksi { border-right: none; }

.table-row-hover { background: #ffffff; transition: background 0.15s; }
.table-row-hover:nth-child(even) { background: #fcfcfc; }
.table-row-hover:hover { background: #f1f5f9 !important; }
.table-row-hover .sticky-aksi { background: #ffffff; transition: background 0.15s; }
.table-row-hover:nth-child(even) .sticky-aksi { background: #fcfcfc; }
.table-row-hover:hover .sticky-aksi { background: #f1f5f9 !important; z-index: 10; }

.sticky-aksi { position: sticky; right: 0; padding: 10px 16px; text-align: center; z-index: 1; box-shadow: -4px 0 12px rgba(0,0,0,0.05); }

.btn-lihat {
  display: inline-block; padding: 4px 10px; background: #eff6ff; color: #2563eb; font-weight: 600; font-size: 0.75rem; border-radius: 999px; text-decoration: none; transition: background 0.15s;
}
.btn-lihat:hover { background: #dbeafe; }

.btn-export {
  padding: 4px 10px; border: 1px solid #cbd5e1; background: #f8fafc; color: #334155; font-weight: 600; font-size: 0.72rem; border-radius: 999px; cursor: pointer; transition: background 0.15s;
}
.btn-export:hover { background: #e2e8f0; }

.btn-hapus {
  display: inline-block; padding: 4px 10px; border: none; cursor: pointer; background: #fef2f2; color: #dc2626; font-weight: 600; font-size: 0.75rem; border-radius: 999px; text-decoration: none; transition: background 0.15s;
}
.btn-hapus:hover { background: #fee2e2; }

.elegant-scroll::-webkit-scrollbar { height: 7px; }
.elegant-scroll::-webkit-scrollbar-track { background: #f8fafc; }
.elegant-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.elegant-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

.pagination-wrap { display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; border-top: 1px solid #f1f5f9; background: #fff; flex-wrap: wrap; gap: 12px; }
.pagination-info { font-size: 0.8rem; color: #64748b; }
.pagination-buttons { display: flex; gap: 4px; align-items: center; }

.pg-btn { padding: 5px 10px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; color: #334155; font-size: 0.82rem; cursor: pointer; transition: all 0.15s; min-width: 32px; }
.pg-btn:hover:not(:disabled) { background: #eff6ff; border-color: #93c5fd; color: #2563eb; }
.pg-btn:disabled { opacity: 0.4; cursor: default; }
.pg-active { background: #2563eb !important; color: #fff !important; border-color: #2563eb !important; }

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
