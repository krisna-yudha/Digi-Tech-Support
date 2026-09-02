<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../services/api';

const fileInput  = ref(null);
const uploading  = ref(false);
const previewing = ref(false);
const progress   = ref(0);
const toast      = ref(null);
const users      = ref([]);
const loadingUsers = ref(false);
let toastTimer   = null;

// ─── Preview Modal State ───────────────────────────────
const showPreviewModal = ref(false);
const previewData      = ref([]);   // parsed rows from backend
const previewTotal     = ref(0);
const previewExisting  = ref(0);
const previewSearch    = ref('');
const previewPage      = ref(1);
const PREVIEW_PER_PAGE = 10;

const filteredPreview = computed(() => {
  const q = previewSearch.value.toLowerCase();
  if (!q) return previewData.value;
  return previewData.value.filter(r =>
    r.name?.toLowerCase().includes(q) ||
    r.email?.toLowerCase().includes(q) ||
    r.jabatan?.toLowerCase().includes(q)
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

const userSearch  = ref('');
const userPage    = ref(1);
const userPerPage = ref(10);
const sortField   = ref('name');
const sortDir     = ref('asc');

function sortUsers(field) {
  if (sortField.value === field) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  else { sortField.value = field; sortDir.value = 'asc'; }
  userPage.value = 1;
}
function sortIcon(field) {
  if (sortField.value !== field) return '↕';
  return sortDir.value === 'asc' ? '↑' : '↓';
}

const filteredUsers = computed(() => {
  const q = userSearch.value.toLowerCase();
  let list = users.value.filter(u =>
    !q || u.name?.toLowerCase().includes(q) ||
    u.email?.toLowerCase().includes(q) ||
    u.jabatan?.toLowerCase().includes(q)
  );
  list = [...list].sort((a, b) => {
    const va = String(a[sortField.value] || '').toLowerCase();
    const vb = String(b[sortField.value] || '').toLowerCase();
    return sortDir.value === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
  });
  return list;
});
const userLastPage = computed(() => Math.ceil(filteredUsers.value.length / userPerPage.value) || 1);
const pagedUsers   = computed(() => {
  const start = (userPage.value - 1) * userPerPage.value;
  return filteredUsers.value.slice(start, start + userPerPage.value);
});
function userPageNumbers() {
  const range = [], delta = 2;
  for (let i = Math.max(1, userPage.value - delta); i <= Math.min(userLastPage.value, userPage.value + delta); i++) range.push(i);
  return range;
}

async function fetchUsers() {
  loadingUsers.value = true;
  try {
    const { data } = await api.get('/users');
    users.value = Array.isArray(data) ? data : (data.data || []);
  } catch (error) {
    console.error('Failed to load users:', error);
    showToast('error', 'Gagal memuat data user.');
  } finally {
    loadingUsers.value = false;
  }
}

onMounted(() => { fetchUsers(); });

function triggerFileInput() {
  fileInput.value.click();
}

// Step 1: user picks a file → call /preview endpoint
async function onFileChange(event) {
  const file = event.target.files[0];
  if (!file) return;
  event.target.value = null; // reset so same file can be picked again

  previewing.value = true;
  try {
    const fd = new FormData();
    fd.append('file', file);
    const { data } = await api.post('/users/import/preview', fd);
    previewData.value    = data.data;
    previewTotal.value   = data.total;
    previewExisting.value= data.existing_count;
    previewPage.value    = 1;
    previewSearch.value  = '';
    showPreviewModal.value = true;
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal membaca CSV.';
    showToast('error', msg);
  } finally {
    previewing.value = false;
  }
}

// Step 2: user confirms in modal → send parsed JSON in BATCHES to avoid server timeout
const BATCH_SIZE = 15;  // kirim 15 user per request agar tidak timeout
const importStatus = ref(null); // { done, total, failed }

async function confirmImport() {
  uploading.value = true;
  progress.value  = 0;
  importStatus.value = null;
  showPreviewModal.value = false;

  const allUsers  = previewData.value;
  const total     = allUsers.length;
  const batches   = [];
  for (let i = 0; i < total; i += BATCH_SIZE) {
    batches.push(allUsers.slice(i, i + BATCH_SIZE));
  }

  let done   = 0;
  let failed = 0;
  const errors = [];

  for (let bIdx = 0; bIdx < batches.length; bIdx++) {
    try {
      await api.post('/users/import', { users: batches[bIdx] });
      done += batches[bIdx].length;
    } catch (err) {
      failed += batches[bIdx].length;
      errors.push(err.response?.data?.message || `Batch ${bIdx + 1} gagal.`);
    }
    // Progress akurat berdasarkan batch yang selesai
    progress.value = Math.round(((bIdx + 1) / batches.length) * 100);
    // Jeda singkat agar server tidak kelelahan
    await new Promise(r => setTimeout(r, 300));
  }

  uploading.value = false;

  if (failed === 0) {
    showToast('success', `Berhasil mengimport ${done} dari ${total} data agent.`);
  } else if (done > 0) {
    showToast('error', `${done} berhasil, ${failed} gagal. ${errors[0] || ''}`);
  } else {
    showToast('error', `Semua data gagal diimport. ${errors[0] || ''}`);
  }

  setTimeout(() => { progress.value = 0; }, 1000);
  fetchUsers();
}

function displayAccount(email) {
  return email?.replace('@ts.internal', '') || email;
}

const deleting = ref(null);

async function deleteUser(user) {
  if (!confirm(`Hapus akun "${user.name}"? Tindakan ini tidak dapat dibatalkan.`)) return;

  deleting.value = user.id;
  toast.value = null;
  try {
    const { data } = await api.delete(`/users/${user.id}`);
    showToast('success', data.message);
    fetchUsers();
  } catch (error) {
    showToast('error', error.response?.data?.message || 'Gagal menghapus akun.');
  } finally {
    deleting.value = null;
  }
}
</script>

<template>
  <!-- Toast Notification -->
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
          <div style="display:flex; justify-content:space-between; align-items:center; padding: 20px 24px; border-bottom: 1px solid #e2e8f0;">
            <div>
              <h3 style="margin:0; font-size:1.1rem; font-weight:800; color:#0f172a;">📋 Preview Data Import</h3>
              <p style="margin:4px 0 0; font-size:0.85rem; color:#64748b;">
                {{ previewTotal }} data ditemukan
                <span v-if="previewExisting > 0" style="margin-left:6px; color:#f59e0b; font-weight:700;">· {{ previewExisting }} sudah terdaftar (akan ditimpa)</span>
              </p>
            </div>
            <button @click="showPreviewModal = false" style="background:none; border:none; cursor:pointer; font-size:1.4rem; color:#94a3b8; line-height:1;">&times;</button>
          </div>

          <!-- Search -->
          <div style="padding: 14px 24px; border-bottom: 1px solid #f1f5f9;">
            <input v-model="previewSearch" @input="previewPage=1" type="text" placeholder="Cari nama, account, jabatan..."
              style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:0.85rem; outline:none; box-sizing:border-box;">
          </div>

          <!-- Table -->
          <div style="overflow-x: auto; max-height: 380px; overflow-y: auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.88rem;">
              <thead style="position:sticky; top:0; background:#f8fafc; z-index:1;">
                <tr>
                  <th class="pth">#</th>
                  <th class="pth">Nama</th>
                  <th class="pth">JK</th>
                  <th class="pth">Jabatan</th>
                  <th class="pth">Account</th>
                  <th class="pth">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="filteredPreview.length === 0">
                  <td colspan="6" style="padding:20px; text-align:center; color:#94a3b8;">Tidak ada data.</td>
                </tr>
                <tr v-for="(row, i) in pagedPreview" :key="i"
                    :style="{ borderBottom: '1px solid #f1f5f9', background: row.exists ? '#fffbeb' : 'white' }">
                  <td style="padding:8px 12px; color:#94a3b8;">{{ (previewPage-1)*10 + i + 1 }}</td>
                  <td style="padding:8px 12px; font-weight:600; color:#0f172a;">{{ row.name }}</td>
                  <td style="padding:8px 12px; color:#64748b;">{{ row.gender || '-' }}</td>
                  <td style="padding:8px 12px; color:#64748b;">{{ row.jabatan || '-' }}</td>
                  <td style="padding:8px 12px;">
                    <code style="background:#f1f5f9; padding:2px 7px; border-radius:5px;">{{ displayAccount(row.email) }}</code>
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
              style="background: linear-gradient(135deg, #2563eb, #1d4ed8); border:none; border-radius:8px; padding:9px 22px; font-weight:700; color:white; cursor:pointer; font-size:0.9rem; display:flex; align-items:center; gap:8px; box-shadow:0 4px 6px -1px rgba(37,99,235,0.4);">
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
        <h2 class="page-title" style="margin:0 0 4px; font-size:1.35rem;">User Management</h2>
        <p style="color: var(--muted); margin:0; font-size:0.85rem;">Kelola user dan role (Admin, TS, Agent).</p>
      </div>
      <div class="sub-header-action">
        <input type="file" ref="fileInput" @change="onFileChange" accept=".csv,.txt" style="display: none;" />
        <button class="btn-primary" @click="triggerFileInput" :disabled="uploading || previewing" style="width:100%; display:flex; align-items:center; justify-content:center; gap:8px;">
          <svg v-if="!uploading && !previewing" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          {{ uploading ? 'Mengimport...' : previewing ? 'Membaca CSV...' : 'Import Data Naker (CSV)' }}
        </button>
      </div>
    </div>

    <!-- Progress Bar -->
    <div v-if="uploading" style="margin-bottom: 16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
        <span style="font-size:0.85rem;color:var(--primary);font-weight:500;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
          Mengimport data ke database...
        </span>
        <span style="font-size:0.82rem;color:var(--muted);">{{ Math.round(progress) }}%</span>
      </div>
      <div style="background:var(--border,#e2e8f0);border-radius:99px;height:7px;overflow:hidden;">
        <div :style="{ width: progress + '%', height: '100%', background: 'linear-gradient(90deg, var(--primary, #2563eb), #60a5fa)', borderRadius: '99px', transition: 'width 0.2s ease' }"></div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="user-toolbar-wrap">
      <div class="user-search-box">
        <input v-model="userSearch" @input="userPage=1" type="text" placeholder="Cari nama, jabatan, akun..." class="user-search-input">
        <span class="user-search-icon">🔍</span>
      </div>
      <div class="user-toolbar-sub">
        <span class="user-count-badge">Total: <strong>{{ filteredUsers.length }}</strong> user</span>
        <select v-model="userPerPage" @change="userPage=1" class="user-perpage-select">
          <option :value="10">10 / hal</option>
          <option :value="25">25 / hal</option>
          <option :value="50">50 / hal</option>
        </select>
      </div>
    </div>

    <!-- Desktop Table View (>= 769px) -->
    <div class="desktop-user-table table-scroll-container elegant-scroll">
      <table style="width: 100%; border-collapse: collapse; white-space: nowrap;">
        <thead>
        <tr style="background:#f8fafc;">
          <th class="uth" style="cursor:pointer;user-select:none;" @click="sortUsers('name')">Nama <span style="opacity:.5;font-size:.8rem;">{{ sortIcon('name') }}</span></th>
          <th class="uth" style="cursor:pointer;user-select:none;" @click="sortUsers('gender')">JK <span style="opacity:.5;font-size:.8rem;">{{ sortIcon('gender') }}</span></th>
          <th class="uth" style="cursor:pointer;user-select:none;" @click="sortUsers('jabatan')">Jabatan <span style="opacity:.5;font-size:.8rem;">{{ sortIcon('jabatan') }}</span></th>
          <th class="uth">Account</th>
          <th class="uth">Role</th>
          <th class="uth">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loadingUsers">
          <td colspan="6" style="padding: 24px; text-align: center; color: var(--muted);">Memuat data user...</td>
        </tr>
        <tr v-else-if="pagedUsers.length === 0">
          <td colspan="6" style="padding: 24px; text-align: center; color: var(--muted);">Tidak ada user yang ditemukan.</td>
        </tr>
        <tr v-for="user in pagedUsers" :key="user.id" v-else style="border-bottom: 1px solid var(--border);">
          <td style="padding: 10px 12px; font-weight: 600; color: #1e293b;">{{ user.name }}</td>
          <td style="padding: 10px 12px; color: var(--muted);">{{ user.gender || '-' }}</td>
          <td style="padding: 10px 12px; color: var(--muted);">{{ user.jabatan || '-' }}</td>
          <td style="padding: 10px 12px;">
            <code style="background: var(--bg-secondary, #f4f6fb); padding: 3px 8px; border-radius: 5px; font-size: 0.85em; color: #2563eb; font-weight: 600;">{{ displayAccount(user.email) }}</code>
          </td>
          <td style="padding: 10px 12px;">
            <span v-for="r in user.roles" :key="r.id" class="role-chip" :class="r.name.toLowerCase()">
              {{ r.name }}
            </span>
            <span v-if="!user.roles?.length" class="role-chip default">User</span>
          </td>
          <td style="padding: 10px 12px;">
            <button
              @click="deleteUser(user)"
              :disabled="deleting === user.id"
              class="btn-table-del"
            >
              {{ deleting === user.id ? '...' : 'Hapus' }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>
    </div>

    <!-- Mobile Native User Cards (< 769px) -->
    <div class="mobile-user-list">
      <div v-if="loadingUsers" style="padding: 32px 16px; text-align: center; color: var(--muted);">
        Memuat data user...
      </div>
      <div v-else-if="pagedUsers.length === 0" style="padding: 32px 16px; text-align: center; color: var(--muted);">
        Tidak ada user yang ditemukan.
      </div>
      <div
        v-for="user in pagedUsers"
        :key="'mob-u-' + user.id"
        class="mob-user-card"
        v-else
      >
        <div class="mob-user-avatar">
          {{ user.name ? user.name.charAt(0).toUpperCase() : 'U' }}
        </div>
        <div class="mob-user-content">
          <div class="mob-user-header">
            <h4 class="mob-user-name">{{ user.name }}</h4>
            <button
              @click="deleteUser(user)"
              :disabled="deleting === user.id"
              class="mob-icon-del"
              title="Hapus user"
            >
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            </button>
          </div>
          
          <div class="mob-user-meta-row">
            <span class="mob-meta-account">{{ displayAccount(user.email) }}</span>
            <span v-if="user.jabatan" class="mob-meta-tag">{{ user.jabatan }}</span>
            <span v-if="user.gender" class="mob-meta-gender">{{ user.gender }}</span>
          </div>

          <div class="mob-user-roles">
            <span v-for="r in user.roles" :key="r.id" class="role-chip" :class="r.name.toLowerCase()">
              {{ r.name }}
            </span>
            <span v-if="!user.roles?.length" class="role-chip default">User</span>
          </div>
        </div>
      </div>
    </div>

    <!-- User Pagination (Desktop & Mobile-Native Dual Mode) -->
    <div v-if="userLastPage > 1" class="user-pagination-wrap">
      <!-- Desktop Pagination (>= 769px) -->
      <div class="desktop-pagination-inner">
        <span class="pagination-info-text">
          Menampilkan <strong>{{ (userPage-1)*userPerPage+1 }}–{{ Math.min(userPage*userPerPage, filteredUsers.length) }}</strong> dari <strong>{{ filteredUsers.length }}</strong> user
        </span>
        <div class="pagination-btn-group">
          <button class="pg-btn" :disabled="userPage<=1" @click="userPage=1" title="Halaman Pertama">«</button>
          <button class="pg-btn" :disabled="userPage<=1" @click="userPage--" title="Halaman Sebelumnya">‹</button>
          <button v-for="n in userPageNumbers()" :key="n" class="pg-btn" :class="{'pg-active':n===userPage}" @click="userPage=n">{{ n }}</button>
          <button class="pg-btn" :disabled="userPage>=userLastPage" @click="userPage++" title="Halaman Selanjutnya">›</button>
          <button class="pg-btn" :disabled="userPage>=userLastPage" @click="userPage=userLastPage" title="Halaman Terakhir">»</button>
        </div>
      </div>

      <!-- Mobile Thumb-Friendly Pagination (< 769px) -->
      <div class="mobile-pagination-inner">
        <button class="mob-pg-action-btn" :disabled="userPage<=1" @click="userPage--">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
          <span>Prev</span>
        </button>

        <div class="mob-pg-indicator">
          <span class="mob-pg-current">Halaman <strong>{{ userPage }}</strong> / {{ userLastPage }}</span>
          <span class="mob-pg-total">({{ filteredUsers.length }} user)</span>
        </div>

        <button class="mob-pg-action-btn" :disabled="userPage>=userLastPage" @click="userPage++">
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
  padding: 10px;
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
  width: 100%; max-width: 800px;
  display: flex; flex-direction: column;
  max-height: 90vh; overflow: hidden;
}
.modal-fade-enter-active { animation: modalIn 0.25s ease; }
.modal-fade-leave-active { animation: modalIn 0.2s ease reverse; }
@keyframes modalIn {
  from { opacity: 0; transform: scale(0.95) translateY(-20px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}

/* ─── Role Chips ─── */
.role-chip {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  margin-right: 4px;
}
.role-chip.admin { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }
.role-chip.ts { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
.role-chip.agent { background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
.role-chip.default { background: #f1f5f9; color: #475569; }

.btn-table-del {
  background: none;
  border: 1px solid #fee2e2;
  color: #dc2626;
  border-radius: 6px;
  padding: 4px 10px;
  cursor: pointer;
  font-size: 0.8rem;
  font-weight: 600;
  transition: all 0.15s;
}
.btn-table-del:hover {
  background: #dc2626;
  color: #fff;
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
.mobile-user-list {
  display: none;
}

@media (max-width: 768px) {
  .desktop-user-table {
    display: none !important;
  }
  .mobile-user-list {
    display: flex !important;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 12px;
  }
  .mob-user-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
    padding: 12px 14px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
  }
  .mob-user-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: #fff;
    font-weight: 800;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .mob-user-content {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .mob-user-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
  }
  .mob-user-name {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.3;
  }
  .mob-icon-del {
    background: #fef2f2;
    border: 1px solid #fee2e2;
    color: #dc2626;
    border-radius: 8px;
    padding: 6px 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .mob-icon-del:active {
    background: #fecaca;
  }
  .mob-user-meta-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
  }
  .mob-meta-account {
    font-family: monospace;
    font-size: 0.76rem;
    background: #f1f5f9;
    color: #2563eb;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 600;
  }
  .mob-meta-tag {
    font-size: 0.72rem;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 600;
  }
  .mob-meta-gender {
    font-size: 0.72rem;
    color: #64748b;
  }
  .mob-user-roles {
    margin-top: 2px;
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
</style>
