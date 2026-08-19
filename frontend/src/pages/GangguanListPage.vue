<script setup>
import { onMounted, ref } from 'vue';
import api from '../services/api';

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
function formatDurasi(minutes) {
  if (!minutes && minutes !== 0) return '-';
  const h = Math.floor(minutes / 60), m = minutes % 60;
  return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:00`;
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

onMounted(() => fetchGangguan());
</script>

<template>
  <section class="grid" style="gap:16px;">

    <!-- Header -->
    <div class="page-title-wrap" style="grid-column:1/-1; display:flex; justify-content:space-between; align-items:flex-end;">
      <div>
        <h2 class="page-title">Daftar Gangguan</h2>
        <p class="page-desc">Semua tiket gangguan yang masuk. Total: <strong>{{ total }}</strong> tiket</p>
      </div>
    </div>

    <!-- Toolbar -->
    <div style="grid-column:1/-1; display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
      <div style="position:relative; flex:1; min-width:220px;">
        <input v-model="search" @keyup.enter="fetchGangguan(1)" type="text" placeholder="Cari tiket, judul, agent..."
          style="width:100%;padding:8px 12px 8px 36px;border:1px solid #e2e8f0;border-radius:8px;font-size:0.85rem;outline:none;background:#fff;box-sizing:border-box;">
        <span style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94a3b8;">🔍</span>
        <button @click="fetchGangguan(1)" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:#2563eb;color:#fff;border:none;border-radius:6px;padding:3px 10px;font-size:0.75rem;cursor:pointer;">Cari</button>
      </div>
      <select v-model="filterStatus" @change="fetchGangguan(1)"
        style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:0.85rem;background:#fff;cursor:pointer;color:#334155;">
        <option value="">Semua Status</option>
        <option value="open">Open</option>
        <option value="in_progress">In Progress</option>
        <option value="closed">Closed</option>
      </select>
      <select v-model="perPage" @change="fetchGangguan(1)"
        style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:0.85rem;background:#fff;cursor:pointer;color:#334155;">
        <option :value="10">10 / halaman</option>
        <option :value="25">25 / halaman</option>
        <option :value="50">50 / halaman</option>
        <option :value="100">100 / halaman</option>
      </select>
      <button v-if="search || filterStatus" @click="search='';filterStatus='';fetchGangguan(1)"
        style="padding:8px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:0.82rem;background:#fff;cursor:pointer;color:#64748b;">✕ Reset</button>
    </div>
    <!-- Error -->
    <div v-if="error" class="alert alert-danger" style="grid-column:1/-1;">{{ error }}</div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="card" style="grid-column:1/-1;padding:32px;text-align:center;color:var(--muted);">
      Memuat data...
    </div>

    <!-- Tabel Format Excel (Premium) -->
    <div v-else-if="items.length" class="card" style="grid-column:1/-1;padding:0;overflow:hidden;border:1px solid var(--border);box-shadow:0 4px 12px rgba(0,0,0,0.03);">
      
      <!-- Legend Status -->
      <div style="display:flex; justify-content:flex-end; gap:16px; padding:12px 16px; background:#fff; border-bottom:1px solid #e2e8f0; font-size:0.75rem; font-weight:600; color:#64748b;">
        <span style="display:flex; align-items:center; gap:6px;"><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#ef4444;"></span> Open</span>
        <span style="display:flex; align-items:center; gap:6px;"><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#f59e0b;"></span> In Progress</span>
        <span style="display:flex; align-items:center; gap:6px;"><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#10b981;"></span> Closed</span>
      </div>

      <div class="table-responsive elegant-scroll" style="overflow-x: auto; max-width: 100vw;">
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
              <td class="td-base">{{ item.creator?.name || '-' }}</td>
              <td class="td-center">{{ formatTimeOnly(item.start_time) }}</td>
              <td class="td-center">{{ formatTimeOnly(item.end_time) }}</td>
              <td class="td-center bold">{{ formatDurasi(item.durasi) }}</td>
              <td class="td-center muted">-</td>
              <td class="td-center">{{ item.kategori || '-' }}</td>
              <td class="td-ellipsis" :title="item.judul">{{ item.judul || '-' }}</td>
              <td class="td-ellipsis" :title="item.penyebab_permasalahan">{{ item.penyebab_permasalahan || '-' }}</td>
              <td class="td-ellipsis" :title="item.penyelesaian_masalah">{{ item.penyelesaian_masalah || '-' }}</td>
              <td class="td-ellipsis" :title="item.impact">{{ item.impact || '-' }}</td>
              <td class="td-base bold">{{ item.assignee?.name || '-' }}</td>
              <td class="td-ellipsis wide" :title="item.analisa">{{ item.analisa || '-' }}</td>
              <td class="sticky-aksi">
                <RouterLink :to="`/gangguan/${item.id}`" class="btn-lihat">Lihat</RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-top:1px solid #f1f5f9;background:#fff;flex-wrap:wrap;gap:8px;">
        <span style="font-size:0.8rem;color:#64748b;">
          Menampilkan {{ (page-1)*perPage+1 }}–{{ Math.min(page*perPage, total) }} dari {{ total }} data
        </span>
        <div style="display:flex;gap:4px;align-items:center;">
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
      <button v-if="search || filterStatus" @click="search='';filterStatus='';fetchGangguan(1)"
        style="margin-top:12px;padding:8px 20px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;font-size:0.85rem;">Reset Filter</button>
    </div>

  </section>
</template>

<style scoped>
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
.td-ellipsis { padding: 12px 16px; color: #475569; max-width: 220px; overflow: hidden; text-overflow: ellipsis; }
.td-ellipsis.wide { max-width: 280px; }
.bold  { font-weight: 600 !important; color: #334155 !important; }
.muted { color: #94a3b8 !important; }

.table-row-hover { background: #ffffff; transition: background 0.15s; }
.table-row-hover:hover { background: #f8fafc !important; }
.table-row-hover .sticky-aksi { background: #ffffff; }
.table-row-hover:hover .sticky-aksi { background: #f8fafc !important; }

.sticky-aksi {
  position: sticky; right: 0; padding: 10px 16px;
  text-align: center; z-index: 1; box-shadow: -4px 0 12px rgba(0,0,0,0.03);
}

.btn-lihat {
  display: inline-block; padding: 5px 14px;
  background: #eff6ff; color: #2563eb; font-weight: 600;
  font-size: 0.75rem; border-radius: 999px; text-decoration: none; transition: background 0.15s;
}
.btn-lihat:hover { background: #dbeafe; }

.elegant-scroll::-webkit-scrollbar { height: 7px; }
.elegant-scroll::-webkit-scrollbar-track { background: #f8fafc; }
.elegant-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.elegant-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

/* Pagination */
.pg-btn {
  padding: 5px 10px; border: 1px solid #e2e8f0; border-radius: 6px;
  background: #fff; color: #334155; font-size: 0.82rem; cursor: pointer; transition: all 0.15s; min-width: 32px;
}
.pg-btn:hover:not(:disabled) { background: #eff6ff; border-color: #93c5fd; color: #2563eb; }
.pg-btn:disabled { opacity: 0.4; cursor: default; }
.pg-active { background: #2563eb !important; color: #fff !important; border-color: #2563eb !important; }
</style>
