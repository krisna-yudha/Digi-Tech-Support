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
  assigned_to: ''
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

function fmtTime(value) {
  if (!value) return '-';
  return new Date(value).toLocaleTimeString('id-ID', {
    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
  }).replace(/\./g, ':');
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
    form.value.assigned_to = data.assigned_to || ''; // Sync form
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
    form.value.assigned_to = data.assigned_to || ''; // Sync form
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
    // Update local state by filtering out the deleted evidence
    if (item.value && item.value.evidences) {
      item.value.evidences = item.value.evidences.filter(e => e.id !== evidenceId);
    }
    successMsg.value = 'Evidence berhasil dihapus.';
  } catch (err) {
    errorMsg.value = err.response?.data?.message || 'Gagal menghapus evidence.';
  }
}

onMounted(async () => {
  await fetchDetail();
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

    <!-- Header / Breadcrumb -->
    <div style="grid-column:1/-1; display:flex; flex-direction:column; gap:12px; margin-bottom: 4px;">
      <div>
        <h2 class="page-title" style="margin:0; font-size:1.6rem; color:#0f172a; font-weight:700;">Detail Gangguan</h2>
        <p class="page-desc" style="margin:4px 0 0; color:#475569; font-size:0.95rem;">Monitoring &amp; penanganan tiket gangguan.</p>
      </div>
      <div>
        <button
          type="button"
          @click="router.back()"
          style="display:inline-flex; align-items:center; gap:6px; background:#fff; border:1px solid #cbd5e1; border-radius:8px; padding:6px 14px; cursor:pointer; font-weight:600; font-size:0.85rem; color:#334155; transition:all 0.15s;"
          onmouseover="this.style.background='#f8fafc'"
          onmouseout="this.style.background='#fff'"
        >
          <span style="font-size:1rem; line-height:1;">&larr;</span> Kembali
        </button>
      </div>
    </div>

    <!-- Loading -->
    <article v-if="loading" class="card" style="grid-column:1/-1;text-align:center;padding:48px;color:var(--muted);">
      <div style="font-size:2rem;margin-bottom:10px;">⏳</div>
      Memuat data tiket...
    </article>

    <template v-else-if="item">

      <!-- ═══════════════════════════════════
           HEADER: Nomor Tiket & Status
           ═══════════════════════════════════ -->
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

      <!-- ═══════════════════════════════════
           ROW 1: Timeline | Dampak | Umum
           ═══════════════════════════════════ -->
      <div style="grid-column:1/-1; display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:20px; align-items:stretch;">
        
        <!-- Progress Timeline -->
        <article class="card" style="padding:20px; display:flex; flex-direction:column; flex: 1.5;">
          <h3 style="margin:0 0 24px; font-size:1.05rem; color:var(--text-main);">Progress Timeline</h3>
          
          <div style="position: relative; display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
            <!-- Line Background -->
            <div style="position: absolute; top: 14px; left: 16.66%; right: 16.66%; height: 3px; background: #e2e8f0; z-index: 1;">
              <div :style="`height: 100%; background: #3b82f6; transition: width 0.3s; width: ${item.resolved_at ? '100%' : (item.read_at ? '50%' : '0%')};`"></div>
            </div>
            
            <!-- Step 1: Req -->
            <div style="position: relative; z-index: 2; text-align: center; width: 33.33%;">
              <div style="width: 32px; height: 32px; border-radius: 50%; background: #3b82f6; box-shadow: 0 0 0 4px #fff; color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; transition: background 0.3s;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
              </div>
              <div style="display:flex; flex-direction:column; align-items:center; gap:3px;">
                <span style="font-size: 0.75rem; font-weight: 700; color: #334155;">{{ fmtDate(item.created_at) }}</span>
                <span style="font-size: 0.7rem; font-weight: 600; color: #64748b; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; box-shadow: inset 0 0 0 1px #e2e8f0;">{{ fmtTime(item.created_at) }}</span>
              </div>
            </div>
            
            <!-- Step 2: Read -->
            <div style="position: relative; z-index: 2; text-align: center; width: 33.33%;">
              <div :style="`width: 32px; height: 32px; border-radius: 50%; background: ${item.read_at ? '#3b82f6' : '#e2e8f0'}; box-shadow: 0 0 0 4px #fff; color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; transition: background 0.3s;`">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
              </div>
              <div v-if="item.read_at" style="display:flex; flex-direction:column; align-items:center; gap:3px;">
                <span style="font-size: 0.75rem; font-weight: 700; color: #334155;">{{ fmtDate(item.read_at) }}</span>
                <span style="font-size: 0.7rem; font-weight: 600; color: #64748b; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; box-shadow: inset 0 0 0 1px #e2e8f0;">{{ fmtTime(item.read_at) }}</span>
              </div>
              <span v-else style="font-size: 0.75rem; font-weight: 700; color: #94a3b8;">-</span>
            </div>
            
            <!-- Step 3: Resolved -->
            <div style="position: relative; z-index: 2; text-align: center; width: 33.33%;">
              <div :style="`width: 32px; height: 32px; border-radius: 50%; background: ${item.resolved_at ? '#10b981' : '#e2e8f0'}; box-shadow: 0 0 0 4px #fff; color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; transition: background 0.3s;`">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
              </div>
              <div v-if="item.resolved_at" style="display:flex; flex-direction:column; align-items:center; gap:3px;">
                <span style="font-size: 0.75rem; font-weight: 700; color: #334155;">{{ fmtDate(item.resolved_at) }}</span>
                <span style="font-size: 0.7rem; font-weight: 600; color: #047857; background: #d1fae5; padding: 2px 6px; border-radius: 4px; box-shadow: inset 0 0 0 1px #a7f3d0;">{{ fmtTime(item.resolved_at) }}</span>
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
              <p style="margin:0 0 4px; font-size:0.7rem; color:#dc2626; font-weight: 700; text-transform: uppercase; display: flex; align-items: center; gap: 4px;">▶ IMPACT</p>
              <p style="margin:0 0 8px; font-weight: 700; font-size: 1.15rem; color: #0f172a;">{{ item.impact || '-' }}</p>
              <div style="display: flex; align-items: flex-end; gap: 3px; height: 20px; opacity: 0.9;">
                <div style="width: 7px; height: 40%; background: #94a3b8; border-radius: 2px 2px 0 0;"></div>
                <div style="width: 7px; height: 100%; background: #3b82f6; border-radius: 2px 2px 0 0;"></div>
                <div style="width: 7px; height: 60%; background: #f59e0b; border-radius: 2px 2px 0 0;"></div>
                <div style="width: 7px; height: 80%; background: #94a3b8; border-radius: 2px 2px 0 0;"></div>
                <div style="width: 7px; height: 30%; background: #94a3b8; border-radius: 2px 2px 0 0;"></div>
                <div style="width: 7px; height: 90%; background: #94a3b8; border-radius: 2px 2px 0 0;"></div>
              </div>
            </div>
            
            <div style="width: 1px; height: 50px; background: #e2e8f0; margin: 0 16px;"></div>
            
            <div style="display: flex; flex-direction: column; align-items: flex-start; min-width: 140px;">
              <p style="margin:0 0 4px; font-size:0.7rem; color:#475569; font-weight: 700; text-transform: uppercase; display:flex; align-items:center; gap:4px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                JML AGENT TERDAMPAK
              </p>
              <p style="margin:0 0 8px; font-weight: 700; font-size: 1.15rem; color: #0f172a;">{{ item.jumlah_agent_terdampak || 1 }} Agent</p>
              <div style="display: flex; flex-wrap: wrap; gap: 4px; max-width: 120px;">
                <svg v-for="i in Math.min(8, item.jumlah_agent_terdampak || 1)" :key="i" xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="#4338ca" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                <span v-if="(item.jumlah_agent_terdampak || 1) > 8" style="font-size: 12px; font-weight: 700; color: #64748b; margin-left: 2px; align-self: center;">+</span>
              </div>
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

      <!-- ═══════════════════════════════════
           ROW 2: Deskripsi & Penyelesaian
           ═══════════════════════════════════ -->
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

      <!-- ═══════════════════════════════════
           ROW 3: Tindakan TS & Analisa Teknis
           ═══════════════════════════════════ -->
      <div style="grid-column:1/-1; display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:20px; align-items:stretch;">
        
        <!-- Tindakan TS -->
        <article class="card" style="padding:20px; display:flex; flex-direction:column;">
          <h3 style="margin:0 0 16px;font-size:1.05rem;color:var(--text-main);">🔧 Tindakan TS</h3>

          <!-- Notifikasi -->
          <div v-if="successMsg" style="background:#e8f7ec;color:#146c2e;padding:10px 14px;border-radius:8px;margin-bottom:12px;font-weight:600;font-size:0.85rem;">
            ✅ {{ successMsg }}
          </div>
          <div v-if="errorMsg" style="background:#fef2f2;color:#dc2626;padding:10px 14px;border-radius:8px;margin-bottom:12px;font-weight:600;font-size:0.85rem;">
            ❌ {{ errorMsg }}
          </div>

          <!-- Form Update Detail Penanganan (Hanya jika In Progress) -->
          <div v-if="item.status === 'in_progress'" style="display:flex;flex-direction:column;gap:12px;margin-bottom:16px;">
            <div v-if="auth.hasRole('Admin')">
              <label style="display:block;margin-bottom:4px;font-size:.85rem;font-weight:600;">Petugas TS (Shift)</label>
              <select v-model="form.assigned_to" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);font-family:inherit;background:#fff;">
                <option value="">-- Pilih Petugas TS --</option>
                <option v-for="ts in tsList" :key="ts.id" :value="ts.id">{{ ts.name }}</option>
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
              <p v-if="item.jenis_gangguan !== 'Massal'" style="margin:4px 0 0;font-size:.75rem;color:var(--muted);">Karena ini adalah kendala Personal, jumlah agent terdampak otomatis ditetapkan 1.</p>
            </div>
            <div>
              <label style="display:block;margin-bottom:4px;font-size:.85rem;font-weight:600;">Analisa</label>
              <textarea v-model="form.analisa" rows="2" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);font-family:inherit;"></textarea>
            </div>
            <div>
              <label style="display:block;margin-bottom:4px;font-size:.85rem;font-weight:600;">Waktu Selesai (End Downtime)</label>
              <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <input type="datetime-local" v-model="form.end_time" style="padding:9px 12px;border-radius:8px;border:1px solid var(--border);font-family:inherit;background:#fff;font-size:0.9rem; width: auto; min-width: 200px;">
                <button @click="setNow" type="button" style="padding: 9px 16px; border-radius: 8px; border: 1px solid var(--primary); background: var(--primary-lt, #eff6ff); color: var(--primary-dk, #1e40af); font-weight: 700; font-size:0.85rem; cursor: pointer; white-space: nowrap; transition: 0.2s;">
                  ⏱️ Set Sekarang
                </button>
              </div>
              <p style="margin:4px 0 0;font-size:.75rem;color:var(--muted);">Kosongkan untuk otomatis mengisi waktu saat ini ketika status diubah menjadi Closed.</p>
            </div>
            <div>
              <button
                :disabled="savingForm"
                @click="updateDetail"
                style="padding:8px 16px;border-radius:8px;border:none;cursor:pointer;font-weight:700;font-size:.85rem;background:var(--primary);color:#fff;transition:opacity .15s;"
                :style="savingForm ? 'opacity:.6;cursor:not-allowed;' : ''"
              >
                {{ savingForm ? 'Menyimpan...' : '💾 Simpan Detail Penanganan' }}
              </button>
            </div>
          </div>
          <hr v-if="item.status !== 'open'" style="border:none;border-top:1px dashed var(--border);margin:10px 0 20px 0;">

          <!-- Tombol ubah status -->
          <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:8px;">
            <button
              v-if="item.status === 'open'"
              :disabled="saving"
              @click="updateStatus('in_progress')"
              style="padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:700;font-size:.85rem;background:#fff1d6;color:#9a5b00;transition:opacity .15s;"
              :style="saving ? 'opacity:.6;cursor:not-allowed;' : ''"
            >
              {{ saving ? 'Menyimpan...' : '▶ Mulai Tangani (In Progress)' }}
            </button>
            <button
              v-if="item.status === 'in_progress'"
              :disabled="saving"
              @click="updateStatus('closed')"
              style="padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:700;font-size:.85rem;background:#e8f7ec;color:#146c2e;transition:opacity .15s;"
              :style="saving ? 'opacity:.6;cursor:not-allowed;' : ''"
            >
              {{ saving ? 'Menyimpan...' : '✓ Tandai Selesai (Closed)' }}
            </button>
            <button
              v-if="item.status === 'closed'"
              :disabled="saving"
              @click="updateStatus('open')"
              style="padding:10px 16px;border-radius:8px;border:1px solid #cbd5e1;cursor:pointer;font-weight:700;font-size:.85rem;background:#fff;color:#334155;transition:background .15s;"
              :style="saving ? 'opacity:.6;cursor:not-allowed;' : ''"
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

      <!-- ═══════════════════════════════════
           BLOK 3 : Evidence / Lampiran
           ═══════════════════════════════════ -->
      <article class="card" style="grid-column:1/-1;">
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

    </template>

  </section>
</template>
