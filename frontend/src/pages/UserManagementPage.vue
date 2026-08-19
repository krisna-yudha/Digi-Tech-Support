<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../services/api';

const fileInput  = ref(null);
const uploading  = ref(false);
const progress   = ref(0);      // 0-100 for the progress bar
const toast      = ref(null);   // { type: 'success'|'error', text: '' }
const users      = ref([]);
const loadingUsers = ref(false);
let toastTimer   = null;

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

  // Simulate progress while waiting for server
  const interval = setInterval(() => {
    if (progress.value < 85) progress.value += Math.random() * 12;
  }, 180);

  try {
    const { data } = await api.post('/users/import', formData);
    clearInterval(interval);
    progress.value = 100;
    setTimeout(() => {
      uploading.value = false;
      progress.value  = 0;
      showToast('success', data.message);
      fetchUsers();
    }, 400);
  } catch (error) {
    clearInterval(interval);
    uploading.value = false;
    progress.value  = 0;
    
    // Check for conflict / confirmation required
    if (error.response?.status === 409 && error.response?.data?.require_confirmation) {
      if (confirm(error.response.data.message)) {
        formData.append('overwrite', '1');
        await submitImport(formData);
        return;
      } else {
        showToast('error', 'Import dibatalkan oleh pengguna.');
        return;
      }
    }

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

// Helper: strip internal domain for display
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
      <!-- Success icon -->
      <svg v-if="toast.type === 'success'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M20 6L9 17l-5-5"/></svg>
      <!-- Error icon -->
      <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span>{{ toast.text }}</span>
    </div>
  </Transition>

  <section class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
      <div>
        <h2 class="page-title">User Management</h2>
        <p style="color: var(--muted);">Kelola user dan role (Admin, TS, Agent).</p>
      </div>
      <div>
        <input type="file" ref="fileInput" @change="onFileChange" accept=".csv,.txt" style="display: none;" />
        <button class="btn-primary" @click="triggerFileInput" :disabled="uploading">
          <span style="display:flex;align-items:center;gap:8px;">
            <svg v-if="!uploading" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            {{ uploading ? 'Memproses...' : 'Import Data Naker (CSV)' }}
          </span>
        </button>
      </div>
    </div>

    <!-- Progress Bar -->
    <div v-if="uploading" style="margin-bottom: 16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
        <span style="font-size:0.85rem;color:var(--primary);font-weight:500;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
          Mengunggah dan memproses data naker...
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
          <input v-model="userSearch" @input="userPage=1" type="text" placeholder="Cari nama, jabatan, akun..."
            style="width:100%;padding:8px 12px 8px 34px;border:1px solid #e2e8f0;border-radius:8px;font-size:0.85rem;outline:none;background:#fff;box-sizing:border-box;">
          <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;">🔍</span>
        </div>
        <select v-model="userPerPage" @change="userPage=1"
          style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:0.85rem;background:#fff;cursor:pointer;color:#334155;">
          <option :value="10">10 / halaman</option>
          <option :value="25">25 / halaman</option>
          <option :value="50">50 / halaman</option>
        </select>
        <span style="font-size:0.8rem;color:#64748b;">Total: <strong>{{ filteredUsers.length }}</strong> user</span>
      </div>

      <table style="width: 100%; border-collapse: collapse;">
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
          <td style="padding: 8px 10px; font-weight: 500;">{{ user.name }}</td>
          <td style="padding: 8px 10px; color: var(--muted);">{{ user.gender || '-' }}</td>
          <td style="padding: 8px 10px; color: var(--muted);">{{ user.jabatan || '-' }}</td>
          <td style="padding: 8px 10px;">
            <code style="background: var(--bg-secondary, #f4f6fb); padding: 2px 7px; border-radius: 5px; font-size: 0.85em;">{{ displayAccount(user.email) }}</code>
          </td>
          <td style="padding: 8px 10px;">{{ user.roles?.map(r => r.name).join(', ') || '-' }}</td>
          <td style="padding: 8px 10px;">
            <button
              @click="deleteUser(user)"
              :disabled="deleting === user.id"
              style="background: none; border: 1px solid var(--danger, #e74c3c); color: var(--danger, #e74c3c); border-radius: 6px; padding: 4px 12px; cursor: pointer; font-size: 0.82em; transition: background 0.15s, color 0.15s;"
              onmouseover="this.style.background='#e74c3c';this.style.color='#fff';"
              onmouseout="this.style.background='none';this.style.color='var(--danger, #e74c3c)';"
            >
              {{ deleting === user.id ? 'Menghapus...' : 'Hapus' }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>

      <!-- User Pagination -->
      <div v-if="userLastPage > 1" style="display:flex;justify-content:space-between;align-items:center;padding:12px 4px;flex-wrap:wrap;gap:8px;">
        <span style="font-size:0.8rem;color:#64748b;">
          Menampilkan {{ (userPage-1)*userPerPage+1 }}–{{ Math.min(userPage*userPerPage, filteredUsers.length) }} dari {{ filteredUsers.length }}
        </span>
        <div style="display:flex;gap:4px;">
          <button class="pg-btn" :disabled="userPage<=1" @click="userPage=1">«</button>
          <button class="pg-btn" :disabled="userPage<=1" @click="userPage--">‹</button>
          <button v-for="n in userPageNumbers()" :key="n" class="pg-btn" :class="{'pg-active':n===userPage}" @click="userPage=n">{{ n }}</button>
          <button class="pg-btn" :disabled="userPage>=userLastPage" @click="userPage++">›</button>
          <button class="pg-btn" :disabled="userPage>=userLastPage" @click="userPage=userLastPage">»</button>
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
