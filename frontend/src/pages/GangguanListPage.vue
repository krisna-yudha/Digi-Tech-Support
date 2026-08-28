<script setup>
import { onMounted, ref } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';

const auth = useAuthStore();

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
const filterPeriod = ref('');
const filterStartDate = ref('');
const filterEndDate = ref('');
const filterJenis = ref('');

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
function formatTimeOnly(v) {
  if (!v) return '-';
  return new Date(v).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).replace(/\./g, ':');
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
  if (sortBy.value === col) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  else { sortBy.value = col; sortDir.value = 'desc'; }
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

onMounted(() => fetchGangguan());
</script>

<template>
  <section class="grid" style="gap:16px;">

    <!-- Header -->
    <div class="page-title-wrap" style="grid-column:1/-1; display:flex; justify-content:space-between; align-items:flex-end;">
      <div>
        <h2 class="page-title">Daftar Gangguan</h2>
        <p class="page-desc">Semua tiket gangguan yang masuk. Total: <strong>{{ total }}</strong> tiket</p>
        <!-- Legend Status -->
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
        <input v-model="search" @keyup.enter="fetchGangguan(1)" type="text" placeholder="Cari tiket, judul, agent..." />
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

      <button v-show="showFilters" v-if="search || filterStatus || filterPeriod || filterJenis" @click="search='';filterStatus='';filterPeriod='';filterStartDate='';filterEndDate='';filterJenis='';fetchGangguan(1)" class="reset-btn">✕ Reset</button>
    </div>
    <!-- Error -->
    <div v-if="error" class="alert alert-danger" style="grid-column:1/-1;">{{ error }}</div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="card" style="grid-column:1/-1;padding:32px;text-align:center;color:var(--muted);">
      Memuat data...
    </div>

    <!-- Tabel Format Excel (Premium) -->
    <div v-else-if="items.length" class="card" style="grid-column:1/-1;padding:0;overflow:hidden;border:1px solid var(--border);box-shadow:0 4px 12px rgba(0,0,0,0.03);margin-bottom:24px;">

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
            <tr v-for="(item, index) in items" :key="item.id" class="table-row-hover"
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
                <div style="display:flex; justify-content:center; gap:6px;">
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
          Menampilkan {{ (page-1)*perPage+1 }}–{{ Math.min(page*perPage, total) }} dari {{ total }} data
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
      <button v-if="search || filterStatus || filterPeriod || filterJenis" @click="search='';filterStatus='';filterPeriod='';filterStartDate='';filterEndDate='';filterJenis='';fetchGangguan(1)"
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
  </section>
</template>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}
.modal-content {
  background: #fff;
  padding: 24px;
  border-radius: 12px;
  width: 90%;
  max-width: 400px;
  box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
}
.filter-toolbar {
  grid-column: 1 / -1;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.search-box {
  position: relative;
  width: 100%;
}
.search-box input {
  width: 100%;
  padding: 10px 80px 10px 38px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.88rem;
  outline: none;
  background: #fff;
  box-sizing: border-box;
  margin: 0;
  height: 42px;
}
.search-icon {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.9rem;
}
.search-btn {
  position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
  background: #2563eb; color: #fff; border: none; border-radius: 7px;
  padding: 5px 14px; font-size: 0.78rem; cursor: pointer; font-weight: 600;
}
.filter-controls {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  width: 100%;
}
.filter-select {
  padding: 8px 10px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.83rem;
  background: #fff;
  cursor: pointer;
  color: #334155;
  width: 100%;
  margin: 0;
  height: 36px;
}
.custom-date-filter {
  display: flex; gap: 8px; align-items: center; width: 100%;
}
.filter-input-date {
  flex: 1; padding: 7px 10px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.83rem; outline: none; margin: 0; width: 100%;
}
.reset-btn {
  padding: 7px 14px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem;
  background: #fff; cursor: pointer; color: #64748b; font-weight: 600;
}
.mobile-filter-btn {
  display: flex; gap: 6px; align-items: center; justify-content: center;
  padding: 8px 16px; border: 1.5px solid #e2e8f0; border-radius: 8px;
  font-size: 0.85rem; font-weight: 600; background: #fff; cursor: pointer; color: #334155;
  width: 100%;
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

/* Sort header */
.th-base {
  padding: 12px 16px; font-weight: 600; color: #475569;
  text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.05em;
}
.th-sort {
  padding: 12px 16px; font-weight: 600; color: #475569;
  text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.05em;
  cursor: pointer; user-select: none; white-space: nowrap;
}
.th-sort:hover { background: #f1f5f9; }
.th-sticky {
  position: sticky; right: 0; background-color: #f8fafc;
  padding: 12px 16px; text-align: center; font-weight: 600; color: #475569;
  text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.05em;
  z-index: 2; box-shadow: -4px 0 12px rgba(0,0,0,0.04);
}
.sort-icon { font-size: 0.8rem; opacity: 0.5; margin-left: 3px; }

/* Body cells */
.td-base    { padding: 12px 16px; color: #334155; }
.td-center  { padding: 12px 16px; text-align: center; color: #475569; }
.td-ellipsis { padding: 12px 16px; color: #475569; min-width: 200px; max-width: 400px; white-space: normal; overflow-wrap: break-word; line-height: 1.5; }
.td-ellipsis.wide { max-width: 500px; }
.bold  { font-weight: 600 !important; color: #334155 !important; }
.muted { color: #94a3b8 !important; }

table { border: 1px solid #cbd5e1; }
.th-base, .th-sort, .th-sticky {
  background-color: #f1f5f9;
  border-bottom: 2px solid #cbd5e1 !important;
  border-right: 1px solid #e2e8f0;
}
.th-sticky { border-right: none; }

.td-base, .td-center, .td-ellipsis, .sticky-aksi {
  border-bottom: 1px solid #e2e8f0 !important;
  border-right: 1px solid #f1f5f9;
}
.sticky-aksi { border-right: none; }

.table-row-hover { background: #ffffff; transition: background 0.15s; }
.table-row-hover:nth-child(even) { background: #fcfcfc; }
.table-row-hover:hover { background: #f1f5f9 !important; }
.table-row-hover .sticky-aksi { background: #ffffff; transition: background 0.15s; }
.table-row-hover:nth-child(even) .sticky-aksi { background: #fcfcfc; }
.table-row-hover:hover .sticky-aksi { background: #f1f5f9 !important; }

.sticky-aksi {
  position: sticky; right: 0; padding: 10px 16px;
  text-align: center; z-index: 1; box-shadow: -4px 0 12px rgba(0,0,0,0.05);
}

.btn-lihat {
  display: inline-block; padding: 5px 14px;
  background: #eff6ff; color: #2563eb; font-weight: 600;
  font-size: 0.75rem; border-radius: 999px; text-decoration: none; transition: background 0.15s;
}
.btn-lihat:hover { background: #dbeafe; }

.btn-hapus {
  display: inline-block; padding: 5px 14px; border: none; cursor: pointer;
  background: #fef2f2; color: #dc2626; font-weight: 600;
  font-size: 0.75rem; border-radius: 999px; text-decoration: none; transition: background 0.15s;
}
.btn-hapus:hover { background: #fee2e2; }

.elegant-scroll::-webkit-scrollbar { height: 7px; }
.elegant-scroll::-webkit-scrollbar-track { background: #f8fafc; }
.elegant-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.elegant-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

/* Pagination */
.pagination-wrap {
  display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; border-top: 1px solid #f1f5f9; background: #fff; flex-wrap: wrap; gap: 12px;
}
.pagination-info { font-size: 0.8rem; color: #64748b; }
.pagination-buttons { display: flex; gap: 4px; align-items: center; }

@media (max-width: 768px) {
  .pagination-wrap {
    flex-direction: column; justify-content: center; text-align: center; padding: 14px 12px;
  }
  .th-base, .th-sort, .th-sticky, .td-base, .td-center, .td-ellipsis, .sticky-aksi {
    padding-left: 8px !important;
    padding-right: 8px !important;
    padding-top: 10px !important;
    padding-bottom: 10px !important;
    font-size: 0.8rem !important;
  }
}

.pg-btn {
  padding: 5px 10px; border: 1px solid #e2e8f0; border-radius: 6px;
  background: #fff; color: #334155; font-size: 0.82rem; cursor: pointer; transition: all 0.15s; min-width: 32px;
}
.pg-btn:hover:not(:disabled) { background: #eff6ff; border-color: #93c5fd; color: #2563eb; }
.pg-btn:disabled { opacity: 0.4; cursor: default; }
.pg-active { background: #2563eb !important; color: #fff !important; border-color: #2563eb !important; }
</style>
