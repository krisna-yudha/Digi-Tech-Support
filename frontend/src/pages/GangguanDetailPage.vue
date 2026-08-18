<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../services/api';

const route  = useRoute();
const router = useRouter();
const item   = ref(null);
const loading = ref(false);
const saving  = ref(false);
const successMsg = ref('');
const errorMsg   = ref('');
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
  } finally {
    loading.value = false;
  }
}

// ───── update status ─────
async function updateStatus(newStatus) {
  if (saving.value) return;
  saving.value = true;
  successMsg.value = '';
  errorMsg.value   = '';
  try {
    const { data } = await api.patch(`/gangguan/${route.params.id}`, { status: newStatus });
    item.value = data;
    successMsg.value = `Status berhasil diubah menjadi "${statusLabel(newStatus)}".`;
  } catch (err) {
    errorMsg.value = err.response?.data?.message || 'Gagal mengubah status.';
  } finally {
    saving.value = false;
  }
}

onMounted(fetchDetail);
</script>

<template>
  <section class="grid" style="gap: 20px;">

    <!-- Header / Breadcrumb -->
    <div class="page-title-wrap" style="grid-column:1/-1; display:flex; justify-content:space-between; align-items:flex-end;">
      <div>
        <h2 class="page-title" style="margin:0;">Detail Gangguan</h2>
        <p class="page-desc" style="margin:4px 0 0;">Monitoring &amp; penanganan tiket gangguan.</p>
      </div>
      <button
        type="button"
        @click="router.back()"
        style="display:flex;align-items:center;gap:6px;background:none;border:1.5px solid var(--border);border-radius:10px;padding:8px 14px;cursor:pointer;font-weight:600;color:var(--text);transition:background 0.15s;"
        onmouseover="this.style.background='#fff'"
        onmouseout="this.style.background='none'"
      >← Kembali</button>
    </div>

    <!-- Loading -->
    <article v-if="loading" class="card" style="grid-column:1/-1;text-align:center;padding:48px;color:var(--muted);">
      <div style="font-size:2rem;margin-bottom:10px;">⏳</div>
      Memuat data tiket...
    </article>

    <template v-else-if="item">

      <!-- ═══════════════════════════════════
           BLOK 1 : Info Pelaporan (dari Agent)
           ═══════════════════════════════════ -->
      <article class="card" style="grid-column:1/-1;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:18px;">
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

        <!-- Grid timestamp ala Digi TS -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:16px;">

          <!-- Tgl Req (dibuat Agent) -->
          <div style="padding:13px 16px;border-radius:12px;background:var(--bg,#f9fafb);border:1px solid var(--border);">
            <p style="margin:0 0 3px;font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;">📅 Tgl Req (Laporan Masuk)</p>
            <p style="margin:0;font-weight:700;font-size:.95rem;">{{ fmt(item.created_at) }}</p>
            <p v-if="item.creator" style="margin:2px 0 0;font-size:.78rem;color:var(--muted);">oleh: {{ item.creator.name }}</p>
          </div>

          <!-- Tgl Read (TS buka tiket) -->
          <div style="padding:13px 16px;border-radius:12px;border:1px solid var(--border);"
               :style="item.read_at ? 'background:#fffbeb;' : 'background:#fef2f2;'">
            <p style="margin:0 0 3px;font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;">👁️ Tgl Read (Dibaca TS)</p>
            <p style="margin:0;font-weight:700;font-size:.95rem;">{{ fmt(item.read_at) }}</p>
            <p v-if="item.reader" style="margin:2px 0 0;font-size:.78rem;color:var(--muted);">oleh: {{ item.reader.name }}</p>
            <p v-else-if="!item.read_at" style="margin:2px 0 0;font-size:.78rem;color:#dc2626;">Belum dibaca</p>
          </div>

          <!-- Tgl Selesai (resolved) -->
          <div style="padding:13px 16px;border-radius:12px;border:1px solid var(--border);"
               :style="item.resolved_at ? 'background:#e8f7ec;' : 'background:#f3f4f6;'">
            <p style="margin:0 0 3px;font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;">✅ Tgl Selesai (Resolved)</p>
            <p style="margin:0;font-weight:700;font-size:.95rem;">{{ fmt(item.resolved_at) }}</p>
            <p v-if="item.resolver" style="margin:2px 0 0;font-size:.78rem;color:var(--muted);">oleh: {{ item.resolver.name }}</p>
            <p v-else-if="!item.resolved_at" style="margin:2px 0 0;font-size:.78rem;color:var(--muted);">Belum diselesaikan</p>
          </div>

          <!-- Durasi Penanganan -->
          <div style="padding:13px 16px;border-radius:12px;background:var(--bg,#f9fafb);border:1px solid var(--border);">
            <p style="margin:0 0 3px;font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;">⏱️ Durasi</p>
            <p style="margin:0;font-weight:700;font-size:.95rem;">
              {{ item.durasi ? item.durasi + ' menit' : '-' }}
            </p>
            <p style="margin:2px 0 0;font-size:.78rem;color:var(--muted);">
              {{ item.start_time ? fmt(item.start_time) : '' }}
              {{ item.start_time && item.end_time ? '→' : '' }}
              {{ item.end_time ? fmt(item.end_time) : '' }}
            </p>
          </div>

        </div>

        <!-- Detail Info -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-bottom:14px;">
          <div style="padding:11px 14px;border-radius:10px;background:var(--bg,#f9fafb);border:1px solid var(--border);">
            <p style="margin:0 0 2px;font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;">Judul</p>
            <p style="margin:0;font-weight:600;">{{ item.judul || '-' }}</p>
          </div>
          <div style="padding:11px 14px;border-radius:10px;background:var(--bg,#f9fafb);border:1px solid var(--border);">
            <p style="margin:0 0 2px;font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;">Kategori</p>
            <p style="margin:0;font-weight:600;">{{ item.kategori || '-' }}</p>
          </div>
          <div style="padding:11px 14px;border-radius:10px;background:var(--bg,#f9fafb);border:1px solid var(--border);">
            <p style="margin:0 0 2px;font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;">Ditangani Oleh</p>
            <p style="margin:0;font-weight:600;">{{ item.assignee?.name || '-' }}</p>
          </div>
        </div>

        <!-- Deskripsi -->
        <div v-if="item.deskripsi" style="padding:13px 16px;border-radius:12px;background:var(--bg,#f9fafb);border:1px solid var(--border);">
          <p style="margin:0 0 6px;font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;">Deskripsi / Keterangan Gangguan</p>
          <p style="margin:0;white-space:pre-wrap;line-height:1.7;font-size:.95rem;">{{ item.deskripsi }}</p>
        </div>
      </article>

      <!-- ═══════════════════════════════════
           BLOK 2 : Aksi TS
           ═══════════════════════════════════ -->
      <article class="card" style="grid-column:1/-1;">
        <h3 style="margin:0 0 14px;">🔧 Tindakan TS</h3>

        <!-- Notifikasi -->
        <div v-if="successMsg" style="margin-bottom:12px;padding:11px 14px;border-radius:10px;background:#e8f7ec;border:1px solid #bfe3c6;color:#146c2e;font-weight:600;">
          ✅ {{ successMsg }}
        </div>
        <div v-if="errorMsg" style="margin-bottom:12px;padding:11px 14px;border-radius:10px;background:#fde8e8;border:1px solid #fca5a5;color:#991b1b;font-weight:600;">
          ⚠️ {{ errorMsg }}
        </div>

        <!-- Tombol ubah status -->
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
          <button
            v-if="item.status === 'open'"
            :disabled="saving"
            @click="updateStatus('in_progress')"
            style="padding:10px 20px;border-radius:10px;border:none;cursor:pointer;font-weight:700;font-size:.9rem;background:#fff1d6;color:#9a5b00;transition:opacity .15s;"
            :style="saving ? 'opacity:.6;cursor:not-allowed;' : ''"
          >
            {{ saving ? 'Menyimpan...' : '▶ Mulai Tangani (In Progress)' }}
          </button>
          <button
            v-if="item.status === 'in_progress'"
            :disabled="saving"
            @click="updateStatus('closed')"
            style="padding:10px 20px;border-radius:10px;border:none;cursor:pointer;font-weight:700;font-size:.9rem;background:#e8f7ec;color:#146c2e;transition:opacity .15s;"
            :style="saving ? 'opacity:.6;cursor:not-allowed;' : ''"
          >
            {{ saving ? 'Menyimpan...' : '✓ Tandai Selesai (Closed)' }}
          </button>
          <button
            v-if="item.status === 'closed'"
            :disabled="saving"
            @click="updateStatus('open')"
            style="padding:10px 20px;border-radius:10px;border:none;cursor:pointer;font-weight:700;font-size:.9rem;background:#f3f4f6;color:#374151;transition:opacity .15s;"
            :style="saving ? 'opacity:.6;cursor:not-allowed;' : ''"
          >
            ↩ Buka Kembali
          </button>

          <RouterLink
            :to="`/gangguan/${route.params.id}/upload`"
            style="display:inline-flex;align-items:center;padding:10px 20px;border-radius:10px;background:var(--primary,#2563eb);color:#fff;font-weight:700;font-size:.9rem;text-decoration:none;"
          >
            📎 Upload Evidence
          </RouterLink>
        </div>
      </article>

      <!-- ═══════════════════════════════════
           BLOK 3 : Evidence / Lampiran
           ═══════════════════════════════════ -->
      <article class="card" style="grid-column:1/-1;">
        <h3 style="margin:0 0 16px;">📁 Evidence / Lampiran</h3>
        <div v-if="item.evidences?.length" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;">
          <article v-for="evidence in item.evidences" :key="evidence.id" class="card" style="padding:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px;">
              <p style="margin:0;font-size:.78rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ evidence.filename }}</p>
              <a
                :href="`${apiBaseUrl}/evidence/${evidence.id}/view`"
                :download="evidence.filename"
                style="white-space:nowrap;color:var(--primary);font-weight:700;font-size:.78rem;flex-shrink:0;"
              >↓ Unduh</a>
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
