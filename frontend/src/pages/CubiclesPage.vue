<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../services/api';

const fileInput  = ref(null);
const uploading  = ref(false);
const progress   = ref(0);
const toast      = ref(null);
const cubicles   = ref([]);
const loading    = ref(false);
let toastTimer   = null;

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

async function onFileChange(event) {
  const file = event.target.files[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('file', file);

  await submitImport(formData);
  
  event.target.value = null;
}

async function submitImport(formData) {
  uploading.value = true;
  progress.value  = 0;
  toast.value     = null;

  const interval = setInterval(() => {
    if (progress.value < 85) progress.value += Math.random() * 12;
  }, 180);

  try {
    const { data } = await api.post('/cubicles/import', formData);
    clearInterval(interval);
    progress.value = 100;
    setTimeout(() => {
      uploading.value = false;
      progress.value  = 0;
      showToast('success', data.message);
      fetchCubicles();
    }, 400);
  } catch (error) {
    clearInterval(interval);
    uploading.value = false;
    progress.value  = 0;

    let msg = 'Gagal mengimport data.';
    if (error.response?.data?.errors) {
      msg = Object.values(error.response.data.errors).flat().join(', ');
    } else if (error.response?.data?.message) {
      msg = error.response.data.message;
    } else if (error.message) {
      msg = error.message;
    }
    
    showToast('error', msg);
  }
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

  <section class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
      <div>
        <h2 class="page-title">Cubicles Management</h2>
        <p style="color: var(--muted);">Kelola data ekstensi dan IP per cubicle.</p>
      </div>
      <div>
        <input type="file" ref="fileInput" @change="onFileChange" accept=".csv,.txt" style="display: none;" />
        <button class="btn-primary" @click="triggerFileInput" :disabled="uploading">
          <span style="display:flex;align-items:center;gap:8px;">
            <svg v-if="!uploading" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            {{ uploading ? 'Memproses...' : 'Import CSV Cubicle' }}
          </span>
        </button>
      </div>
    </div>

    <!-- Progress Bar -->
    <div v-if="uploading" style="margin-bottom: 16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
        <span style="font-size:0.85rem;color:var(--primary);font-weight:500;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
          Mengunggah dan memproses data cubicle...
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

    <div class="table-responsive">
      <!-- Toolbar -->
      <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px;">
        <div style="position:relative;flex:1;min-width:200px;">
          <input v-model="search" @input="page=1" type="text" placeholder="Cari nama cubicle, ext, ip..."
            style="width:100%;padding:8px 12px 8px 34px;border:1px solid #e2e8f0;border-radius:8px;font-size:0.85rem;outline:none;background:#fff;box-sizing:border-box;">
          <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;">🔍</span>
        </div>
        <select v-model="perPage" @change="page=1"
          style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:0.85rem;background:#fff;cursor:pointer;color:#334155;">
          <option :value="10">10 / halaman</option>
          <option :value="25">25 / halaman</option>
          <option :value="50">50 / halaman</option>
        </select>
        <span style="font-size:0.8rem;color:#64748b;">Total: <strong>{{ filtered.length }}</strong> cubicle</span>
      </div>

      <table style="width: 100%; border-collapse: collapse;">
        <thead>
        <tr style="background:#f8fafc;">
          <th class="uth" style="cursor:pointer;user-select:none;" @click="sortData('nama')">Nama Cubicle <span style="opacity:.5;font-size:.8rem;">{{ sortIcon('nama') }}</span></th>
          <th class="uth" style="cursor:pointer;user-select:none;" @click="sortData('ext')">Extension <span style="opacity:.5;font-size:.8rem;">{{ sortIcon('ext') }}</span></th>
          <th class="uth" style="cursor:pointer;user-select:none;" @click="sortData('ip')">IP <span style="opacity:.5;font-size:.8rem;">{{ sortIcon('ip') }}</span></th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading">
          <td colspan="3" style="padding: 24px; text-align: center; color: var(--muted);">Memuat data cubicle...</td>
        </tr>
        <tr v-else-if="paged.length === 0">
          <td colspan="3" style="padding: 24px; text-align: center; color: var(--muted);">Tidak ada cubicle yang ditemukan.</td>
        </tr>
        <tr v-for="c in paged" :key="c.id" v-else style="border-bottom: 1px solid var(--border);">
          <td style="padding: 8px 10px; font-weight: 500;">{{ c.nama }}</td>
          <td style="padding: 8px 10px; color: var(--muted);">{{ c.ext || '-' }}</td>
          <td style="padding: 8px 10px;">
            <code style="background: var(--bg-secondary, #f4f6fb); padding: 2px 7px; border-radius: 5px; font-size: 0.85em;">{{ c.ip || '-' }}</code>
          </td>
        </tr>
      </tbody>
    </table>

      <!-- Pagination -->
      <div v-if="lastPage > 1" style="display:flex;justify-content:space-between;align-items:center;padding:12px 4px;flex-wrap:wrap;gap:8px;">
        <span style="font-size:0.8rem;color:#64748b;">
          Menampilkan {{ (page-1)*perPage+1 }}–{{ Math.min(page*perPage, filtered.length) }} dari {{ filtered.length }}
        </span>
        <div style="display:flex;gap:4px;">
          <button class="pg-btn" :disabled="page<=1" @click="page=1">«</button>
          <button class="pg-btn" :disabled="page<=1" @click="page--">‹</button>
          <button v-for="n in pageNumbers()" :key="n" class="pg-btn" :class="{'pg-active':n===page}" @click="page=n">{{ n }}</button>
          <button class="pg-btn" :disabled="page>=lastPage" @click="page++">›</button>
          <button class="pg-btn" :disabled="page>=lastPage" @click="page=lastPage">»</button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.uth {
  text-align: left;
  border-bottom: 1px solid var(--border);
  padding: 10px;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #475569;
  font-weight: 600;
}
.uth:hover { background: #f8fafc; }
.pg-btn {
  padding: 5px 10px; border: 1px solid #e2e8f0; border-radius: 6px;
  background: #fff; color: #334155; font-size: 0.82rem; cursor: pointer; transition: all 0.15s; min-width: 32px;
}
.pg-btn:hover:not(:disabled) { background: #eff6ff; border-color: #93c5fd; color: #2563eb; }
.pg-btn:disabled { opacity: 0.4; cursor: default; }
.pg-active { background: #2563eb !important; color: #fff !important; border-color: #2563eb !important; }
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
</style>
