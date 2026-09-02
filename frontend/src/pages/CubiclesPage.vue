<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../services/api';

const fileInput  = ref(null);
const uploading  = ref(false);
const previewing = ref(false);
const progress   = ref(0);
const toast      = ref(null);
const cubicles   = ref([]);
const loading    = ref(false);
let toastTimer   = null;

// ─── Preview Modal State ───────────────────────────────
const showPreviewModal = ref(false);
const previewData      = ref([]);
const previewTotal     = ref(0);
const previewExisting  = ref(0);
const previewSearch    = ref('');
const previewPage      = ref(1);
const PREVIEW_PER_PAGE = 10;

const filteredPreview = computed(() => {
  const q = previewSearch.value.toLowerCase();
  if (!q) return previewData.value;
  return previewData.value.filter(r =>
    r.nama?.toLowerCase().includes(q) ||
    r.rochet?.toLowerCase().includes(q) ||
    r.ext?.toLowerCase().includes(q) ||
    r.ip?.toLowerCase().includes(q)
  );
});
const previewLastPage = computed(() => Math.ceil(filteredPreview.value.length / PREVIEW_PER_PAGE) || 1);
const pagedPreview    = computed(() => {
  const start = (previewPage.value - 1) * PREVIEW_PER_PAGE;
  return filteredPreview.value.slice(start, start + PREVIEW_PER_PAGE);
});
// ──────────────────────────────────────────────────────

function showToast(type, text) {
  if (toastTimer) clearTimeout(toastTimer);
  toast.value = { type, text };
  toastTimer = setTimeout(() => { toast.value = null; }, 4000);
}

const search    = ref('');
const page      = ref(1);
const perPage   = ref(10);
const sortField = ref('nama');
const sortDir   = ref('asc');

function sortData(field) {
  if (sortField.value === field) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  else { sortField.value = field; sortDir.value = 'asc'; }
  page.value = 1;
}
function sortIcon(field) {
  if (sortField.value !== field) return '↕';
  return sortDir.value === 'asc' ? '↑' : '↓';
}

const filtered = computed(() => {
  const q = search.value.toLowerCase();
  let list = cubicles.value.filter(c =>
    !q || c.nama?.toLowerCase().includes(q) ||
    c.rochet?.toLowerCase().includes(q) ||
    c.ext?.toLowerCase().includes(q) ||
    c.ip?.toLowerCase().includes(q)
  );
  list = [...list].sort((a, b) => {
    const va = String(a[sortField.value] || '').toLowerCase();
    const vb = String(b[sortField.value] || '').toLowerCase();
    return sortDir.value === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
  });
  return list;
});
const lastPage = computed(() => Math.ceil(filtered.value.length / perPage.value) || 1);
const paged = computed(() => {
  const start = (page.value - 1) * perPage.value;
  return filtered.value.slice(start, start + perPage.value);
});
function pageNumbers() {
  const range = [], delta = 2;
  for (let i = Math.max(1, page.value - delta); i <= Math.min(lastPage.value, page.value + delta); i++) range.push(i);
  return range;
}

async function fetchCubicles() {
  loading.value = true;
  try {
    const { data } = await api.get('/cubicles');
    cubicles.value = Array.isArray(data) ? data : (data.data || []);
  } catch (error) {
    console.error('Failed to load cubicles:', error);
    showToast('error', 'Gagal memuat data cubicle.');
  } finally {
    loading.value = false;
  }
}

onMounted(() => { fetchCubicles(); });

function triggerFileInput() {
  fileInput.value.click();
}

// Step 1: user picks file → call /preview endpoint
async function onFileChange(event) {
  const file = event.target.files[0];
  if (!file) return;
  event.target.value = null;

  previewing.value = true;
  try {
    const fd = new FormData();
    fd.append('file', file);
    const { data } = await api.post('/cubicles/import/preview', fd);
    previewData.value     = data.data;
    previewTotal.value    = data.total;
    previewExisting.value = data.existing_count;
    previewPage.value     = 1;
    previewSearch.value   = '';
    showPreviewModal.value = true;
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal membaca CSV.';
    showToast('error', msg);
  } finally {
    previewing.value = false;
  }
}

// Step 2: user confirms → send JSON per batch (50 per request)
const BATCH_SIZE = 50;

async function confirmImport() {
  uploading.value = true;
  progress.value  = 0;
  showPreviewModal.value = false;

  const all     = previewData.value;
  const total   = all.length;
  const batches = [];
  for (let i = 0; i < total; i += BATCH_SIZE) {
    batches.push(all.slice(i, i + BATCH_SIZE));
  }

  let done   = 0;
  let failed = 0;
  const errors = [];

  for (let bIdx = 0; bIdx < batches.length; bIdx++) {
    try {
      await api.post('/cubicles/import', { cubicles: batches[bIdx] });
      done += batches[bIdx].length;
    } catch (err) {
      failed += batches[bIdx].length;
      errors.push(err.response?.data?.message || `Batch ${bIdx + 1} gagal.`);
    }
    progress.value = Math.round(((bIdx + 1) / batches.length) * 100);
    await new Promise(r => setTimeout(r, 200));
  }

  uploading.value = false;

  if (failed === 0) {
    showToast('success', `Berhasil mengimport ${done} dari ${total} data cubicle.`);
  } else if (done > 0) {
    showToast('error', `${done} berhasil, ${failed} gagal. ${errors[0] || ''}`);
  } else {
    showToast('error', `Semua data gagal diimport. ${errors[0] || ''}`);
  }

  setTimeout(() => { progress.value = 0; }, 1000);
  fetchCubicles();
}
</script>

<template>
  <Transition name="toast">
    <div v-if="toast" :style="{
      position: 'fixed',
      top: '24px',
      right: '24px',
      zIndex: 9999,
      display: 'flex',
      alignItems: 'center',
      gap: '12px',
      padding: '14px 20px',
      borderRadius: '12px',
      boxShadow: '0 8px 32px rgba(0,0,0,0.15)',
      background: toast.type === 'success' ? '#16a34a' : '#dc2626',
      color: '#fff',
      fontWeight: 500,
      fontSize: '0.92rem',
      maxWidth: '380px',
      lineHeight: 1.4,
    }">
      <svg v-if="toast.type === 'success'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M20 6L9 17l-5-5"/></svg>
      <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span>{{ toast.text }}</span>
    </div>
  </Transition>

  <!-- ═══════════════════════════════════════════
       PREVIEW MODAL
  ═══════════════════════════════════════════ -->
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="showPreviewModal" class="modal-overlay" @click.self="showPreviewModal = false">
        <div class="modal-box">
          <!-- Header -->
          <div style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid #e2e8f0;">
            <div>
              <h3 style="margin:0; font-size:1.1rem; font-weight:800; color:#0f172a;">📋 Preview Data Import Cubicle</h3>
              <p style="margin:4px 0 0; font-size:0.85rem; color:#64748b;">
                {{ previewTotal }} data ditemukan
                <span v-if="previewExisting > 0" style="margin-left:6px; color:#f59e0b; font-weight:700;">· {{ previewExisting }} sudah ada (akan ditimpa)</span>
              </p>
            </div>
            <button @click="showPreviewModal = false" style="background:none; border:none; cursor:pointer; font-size:1.4rem; color:#94a3b8; line-height:1;">&times;</button>
          </div>

          <!-- Search -->
          <div style="padding:14px 24px; border-bottom:1px solid #f1f5f9;">
            <input v-model="previewSearch" @input="previewPage=1" type="text" placeholder="Cari nama cubicle, rochet, ext, ip..."
              style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:0.85rem; outline:none; box-sizing:border-box;">
          </div>

          <!-- Table -->
          <div style="overflow-x:auto; max-height:380px; overflow-y:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.88rem;">
              <thead style="position:sticky; top:0; background:#f8fafc; z-index:1;">
                <tr>
                  <th class="pth">#</th>
                  <th class="pth">Nama Cubicle</th>
                  <th class="pth">Rochet</th>
                  <th class="pth">Extension</th>
                  <th class="pth">IP</th>
                  <th class="pth">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="filteredPreview.length === 0">
                  <td colspan="6" style="padding:20px; text-align:center; color:#94a3b8;">Tidak ada data.</td>
                </tr>
                <tr v-for="(row, i) in pagedPreview" :key="i"
                    :style="{ borderBottom: '1px solid #f1f5f9', background: row.exists ? '#fffbeb' : 'white' }">
                  <td style="padding:8px 12px; color:#94a3b8;">{{ (previewPage-1)*PREVIEW_PER_PAGE + i + 1 }}</td>
                  <td style="padding:8px 12px; font-weight:600; color:#0f172a;">{{ row.nama }}</td>
                  <td style="padding:8px 12px; color:#64748b;">{{ row.rochet || '-' }}</td>
                  <td style="padding:8px 12px; color:#64748b;">{{ row.ext || '-' }}</td>
                  <td style="padding:8px 12px;">
                    <code style="background:#f1f5f9; padding:2px 7px; border-radius:5px;">{{ row.ip || '-' }}</code>
                  </td>
                  <td style="padding:8px 12px;">
                    <span v-if="row.exists" style="background:#fef3c7; color:#92400e; border-radius:99px; padding:2px 10px; font-size:0.78rem; font-weight:700;">Timpa</span>
                    <span v-else style="background:#d1fae5; color:#065f46; border-radius:99px; padding:2px 10px; font-size:0.78rem; font-weight:700;">Baru</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="previewLastPage > 1" style="display:flex; justify-content:center; gap:4px; padding:10px 24px; border-top:1px solid #f1f5f9;">
            <button class="pg-btn" :disabled="previewPage<=1" @click="previewPage--">‹</button>
            <span style="font-size:0.82rem; color:#64748b; line-height:2;">{{ previewPage }} / {{ previewLastPage }}</span>
            <button class="pg-btn" :disabled="previewPage>=previewLastPage" @click="previewPage++">›</button>
          </div>

          <!-- Footer Actions -->
          <div style="display:flex; gap:10px; justify-content:flex-end; padding:16px 24px; border-top:1px solid #e2e8f0;">
            <button @click="showPreviewModal = false" style="background:#f1f5f9; border:none; border-radius:8px; padding:9px 20px; font-weight:600; color:#475569; cursor:pointer; font-size:0.9rem;">Batal</button>
            <button @click="confirmImport" :disabled="uploading"
              style="background:linear-gradient(135deg,#2563eb,#1d4ed8); border:none; border-radius:8px; padding:9px 22px; font-weight:700; color:white; cursor:pointer; font-size:0.9rem; display:flex; align-items:center; gap:8px; box-shadow:0 4px 6px -1px rgba(37,99,235,0.4);">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              Konfirmasi Import ({{ previewTotal }} data)
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <section class="section-card card">
    <div class="section-header-wrap">
      <div class="desktop-sub-header">
        <h2 class="page-title" style="margin:0 0 4px; font-size:1.35rem;">Cubicles Management</h2>
        <p style="color: var(--muted); margin:0; font-size:0.85rem;">Kelola data ekstensi dan IP per cubicle.</p>
      </div>
      <div class="sub-header-action">
        <input type="file" ref="fileInput" @change="onFileChange" accept=".csv,.txt" style="display: none;" />
        <button class="btn-primary" @click="triggerFileInput" :disabled="uploading || previewing" style="width:100%; display:flex; align-items:center; justify-content:center; gap:8px;">
          <svg v-if="!uploading && !previewing" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          {{ uploading ? 'Mengimport...' : previewing ? 'Membaca CSV...' : 'Import CSV Cubicle' }}
        </button>
      </div>
    </div>

    <!-- Progress Bar -->
    <div v-if="uploading" style="margin-bottom: 16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
        <span style="font-size:0.85rem;color:var(--primary);font-weight:500;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
          Mengimport data cubicle ke database...
        </span>
        <span style="font-size:0.82rem;color:var(--muted);">{{ Math.round(progress) }}%</span>
      </div>
      <div style="background:var(--border,#e2e8f0);border-radius:99px;height:7px;overflow:hidden;">
        <div :style="{
          width: progress + '%',
          height: '100%',
          background: 'linear-gradient(90deg, var(--primary, #2563eb), #60a5fa)',
          borderRadius: '99px',
          transition: 'width 0.2s ease',
        }"></div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="user-toolbar-wrap">
      <div class="user-search-box">
        <input v-model="search" @input="page=1" type="text" placeholder="Cari nama cubicle, rochet, ext, ip..." class="user-search-input">
        <span class="user-search-icon">🔍</span>
      </div>
      <div class="user-toolbar-sub">
        <span class="user-count-badge">Total: <strong>{{ filtered.length }}</strong> cubicle</span>
        <select v-model="perPage" @change="page=1" class="user-perpage-select">
          <option :value="10">10 / hal</option>
          <option :value="25">25 / hal</option>
          <option :value="50">50 / hal</option>
        </select>
      </div>
    </div>

    <!-- Desktop Table View (>= 769px) -->
    <div class="desktop-cubicle-table table-scroll-container elegant-scroll">
      <table style="width: 100%; border-collapse: collapse; white-space: nowrap;">
        <thead>
        <tr style="background:#f8fafc;">
          <th class="uth" style="cursor:pointer;user-select:none;" @click="sortData('nama')">Nama Cubicle <span style="opacity:.5;font-size:.8rem;">{{ sortIcon('nama') }}</span></th>
          <th class="uth" style="cursor:pointer;user-select:none;" @click="sortData('rochet')">Rochet <span style="opacity:.5;font-size:.8rem;">{{ sortIcon('rochet') }}</span></th>
          <th class="uth" style="cursor:pointer;user-select:none;" @click="sortData('ext')">Extension <span style="opacity:.5;font-size:.8rem;">{{ sortIcon('ext') }}</span></th>
          <th class="uth" style="cursor:pointer;user-select:none;" @click="sortData('ip')">IP <span style="opacity:.5;font-size:.8rem;">{{ sortIcon('ip') }}</span></th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading">
          <td colspan="4" style="padding: 24px; text-align: center; color: var(--muted);">Memuat data cubicle...</td>
        </tr>
        <tr v-else-if="paged.length === 0">
          <td colspan="4" style="padding: 24px; text-align: center; color: var(--muted);">Tidak ada cubicle yang ditemukan.</td>
        </tr>
        <tr v-for="c in paged" :key="c.id" v-else style="border-bottom: 1px solid var(--border);">
          <td style="padding: 10px 12px; font-weight: 600; color: #1e293b;">{{ c.nama }}</td>
          <td style="padding: 10px 12px; color: var(--muted);">{{ c.rochet || '-' }}</td>
          <td style="padding: 10px 12px; color: var(--muted);">{{ c.ext || '-' }}</td>
          <td style="padding: 10px 12px;">
            <code style="background: var(--bg-secondary, #f4f6fb); padding: 3px 8px; border-radius: 5px; font-size: 0.85em; color: #2563eb; font-weight: 600;">{{ c.ip || '-' }}</code>
          </td>
        </tr>
      </tbody>
    </table>
    </div>

    <!-- Mobile Native Cubicle Cards (< 769px) -->
    <div class="mobile-cubicle-list">
      <div v-if="loading" style="padding: 32px 16px; text-align: center; color: var(--muted);">
        Memuat data cubicle...
      </div>
      <div v-else-if="paged.length === 0" style="padding: 32px 16px; text-align: center; color: var(--muted);">
        Tidak ada cubicle yang ditemukan.
      </div>
      <div
        v-for="c in paged"
        :key="'mob-c-' + c.id"
        class="mob-cubicle-card"
        v-else
      >
        <div class="mob-cubicle-header">
          <div style="display:flex; align-items:center; gap:8px;">
            <div class="mob-cubicle-icon">🖥️</div>
            <h4 class="mob-cubicle-name">{{ c.nama }}</h4>
          </div>
          <span v-if="c.rochet" class="mob-pill-rochet">{{ c.rochet }}</span>
        </div>
        
        <div class="mob-cubicle-info-grid">
          <div class="mob-info-col">
            <span class="mob-info-lbl">Extension</span>
            <span class="mob-info-val">{{ c.ext || '-' }}</span>
          </div>
          <div class="mob-info-col">
            <span class="mob-info-lbl">IP Address</span>
            <code class="mob-info-ip">{{ c.ip || '-' }}</code>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination (Desktop & Mobile-Native Dual Mode) -->
    <div v-if="lastPage > 1" class="user-pagination-wrap">
      <!-- Desktop Pagination (>= 769px) -->
      <div class="desktop-pagination-inner">
        <span class="pagination-info-text">
          Menampilkan <strong>{{ (page-1)*perPage+1 }}–{{ Math.min(page*perPage, filtered.length) }}</strong> dari <strong>{{ filtered.length }}</strong> cubicle
        </span>
        <div class="pagination-btn-group">
          <button class="pg-btn" :disabled="page<=1" @click="page=1" title="Halaman Pertama">«</button>
          <button class="pg-btn" :disabled="page<=1" @click="page--" title="Halaman Sebelumnya">‹</button>
          <button v-for="n in pageNumbers()" :key="n" class="pg-btn" :class="{'pg-active':n===page}" @click="page=n">{{ n }}</button>
          <button class="pg-btn" :disabled="page>=lastPage" @click="page++" title="Halaman Selanjutnya">›</button>
          <button class="pg-btn" :disabled="page>=lastPage" @click="page=lastPage" title="Halaman Terakhir">»</button>
        </div>
      </div>

      <!-- Mobile Thumb-Friendly Pagination (< 769px) -->
      <div class="mobile-pagination-inner">
        <button class="mob-pg-action-btn" :disabled="page<=1" @click="page--">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
          <span>Prev</span>
        </button>

        <div class="mob-pg-indicator">
          <span class="mob-pg-current">Halaman <strong>{{ page }}</strong> / {{ lastPage }}</span>
          <span class="mob-pg-total">({{ filtered.length }} cubicle)</span>
        </div>

        <button class="mob-pg-action-btn" :disabled="page>=lastPage" @click="page++">
          <span>Next</span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.uth {
  text-align: left;
  border-bottom: 1px solid var(--border);
  padding: 10px 12px;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #475569;
  font-weight: 600;
}
.uth:hover { background: #f8fafc; }
.pth {
  text-align: left;
  padding: 10px 12px;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #475569;
  font-weight: 700;
  border-bottom: 1px solid #e2e8f0;
}

/* ─── Toolbar ─── */
.user-toolbar-wrap {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-bottom: 14px;
  flex-wrap: wrap;
}
.user-search-box {
  position: relative;
  flex: 1;
  min-width: 220px;
}
.user-search-input {
  width: 100%;
  padding: 9px 12px 9px 36px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.88rem;
  outline: none;
  background: #fff;
  box-sizing: border-box;
  margin: 0;
}
.user-search-input:focus {
  border-color: #2563eb;
}
.user-search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  font-size: 0.85rem;
}
.user-toolbar-sub {
  display: flex;
  align-items: center;
  gap: 10px;
}
.user-count-badge {
  font-size: 0.82rem;
  color: #64748b;
  white-space: nowrap;
}
.user-perpage-select {
  padding: 8px 12px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.84rem;
  background: #fff;
  cursor: pointer;
  color: #334155;
  margin: 0;
  width: auto;
}

/* ─── Mobile Cards List ─── */
.mobile-cubicle-list {
  display: none;
}

@media (max-width: 768px) {
  .desktop-cubicle-table {
    display: none !important;
  }
  .mobile-cubicle-list {
    display: flex !important;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 12px;
  }
  .mob-cubicle-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .mob-cubicle-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .mob-cubicle-icon {
    font-size: 1rem;
  }
  .mob-cubicle-name {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
  }
  .mob-pill-rochet {
    font-size: 0.72rem;
    font-weight: 700;
    color: #2563eb;
    background: #eff6ff;
    padding: 2px 8px;
    border-radius: 6px;
    border: 1px solid #dbeafe;
  }
  .mob-cubicle-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    background: #f8fafc;
    padding: 8px 12px;
    border-radius: 8px;
  }
  .mob-info-col {
    display: flex;
    flex-direction: column;
  }
  .mob-info-lbl {
    font-size: 0.68rem;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
  }
  .mob-info-val {
    font-size: 0.84rem;
    font-weight: 700;
    color: #1e293b;
  }
  .mob-info-ip {
    font-size: 0.78rem;
    font-weight: 600;
    color: #2563eb;
  }

  .user-toolbar-wrap {
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
  }
  .user-search-box {
    width: 100%;
  }
  .user-toolbar-sub {
    justify-content: space-between;
    width: 100%;
  }
}
.desktop-pagination-inner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}
.mobile-pagination-inner {
  display: none;
}
.user-pagination-wrap {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 4px 4px;
  border-top: 1px solid #f1f5f9;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 8px;
}
.pagination-info-text {
  font-size: 0.82rem;
  color: #64748b;
}
.pagination-btn-group {
  display: flex;
  gap: 5px;
  align-items: center;
}
.pg-btn {
  min-width: 36px;
  height: 36px;
  padding: 0 10px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  color: #334155;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  user-select: none;
}
.pg-btn:hover:not(:disabled) { background: #eff6ff; border-color: #93c5fd; color: #2563eb; }
.pg-btn:disabled { opacity: 0.4; cursor: default; }
.pg-active { background: #2563eb !important; color: #fff !important; border-color: #2563eb !important; box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3); }

@media (max-width: 768px) {
  .section-card {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
  }
  .desktop-sub-header {
    display: none !important;
  }
  .sub-header-action {
    width: 100%;
  }
  .section-header-wrap {
    margin-bottom: 12px;
  }
  .desktop-pagination-inner {
    display: none !important;
  }
  .mobile-pagination-inner {
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 8px;
  }
  .user-pagination-wrap {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 14px !important;
    padding: 10px 14px !important;
    margin-top: 14px !important;
    margin-bottom: 24px !important;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05) !important;
  }
  .mob-pg-action-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 8px 16px;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 700;
    color: #1e293b;
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
  }
  .mob-pg-action-btn:active:not(:disabled) {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
    transform: scale(0.95);
  }
  .mob-pg-action-btn:disabled {
    opacity: 0.35;
    cursor: default;
    background: #f1f5f9;
  }
  .mob-pg-indicator {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1px;
  }
  .mob-pg-current {
    font-size: 0.82rem;
    font-weight: 600;
    color: #0f172a;
  }
  .mob-pg-current strong {
    color: #2563eb;
    font-size: 0.95rem;
  }
  .mob-pg-total {
    font-size: 0.72rem;
    color: #64748b;
  }
}
.toast-enter-active { animation: slideIn 0.3s ease; }
.toast-leave-active { animation: slideIn 0.25s ease reverse; }
@keyframes slideIn {
  from { opacity: 0; transform: translateX(40px); }
  to   { opacity: 1; transform: translateX(0); }
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}
/* Modal */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.45);
  display: flex; align-items: center; justify-content: center;
  z-index: 9000; padding: 20px; box-sizing: border-box;
}
.modal-box {
  background: white; border-radius: 16px;
  box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
  width: 100%; max-width: 820px;
  display: flex; flex-direction: column;
  max-height: 90vh; overflow: hidden;
}
.modal-fade-enter-active { animation: modalIn 0.25s ease; }
.modal-fade-leave-active { animation: modalIn 0.2s ease reverse; }
@keyframes modalIn {
  from { opacity: 0; transform: scale(0.95) translateY(-20px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}

.section-header-wrap {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 16px;
}

.table-scroll-container {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  margin-bottom: 8px;
  border-radius: 8px;
}

@media (max-width: 640px) {
  .section-header-wrap {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
