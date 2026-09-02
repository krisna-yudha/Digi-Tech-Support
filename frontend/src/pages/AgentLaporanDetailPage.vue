<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api, { API_BASE_URL } from '../services/api';

const route = useRoute();
const router = useRouter();
const item = ref(null);
const loading = ref(false);
const error = ref('');
const apiBaseUrl = API_BASE_URL;

function statusLabel(status) {
  return String(status || 'unknown')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function statusStyle(status) {
  const normalized = String(status || '').toLowerCase();
  if (normalized === 'open') return 'background: #e8f7ec; color: #146c2e;';
  if (normalized === 'in_progress') return 'background: #fff1d6; color: #9a5b00;';
  if (normalized === 'closed') return 'background: #e7eefc; color: #234a92;';
  return 'background: #f3f4f6; color: #374151;';
}

function priorityLabel(priority) {
  const map = { low: 'Rendah', medium: 'Sedang', high: 'Tinggi' };
  return map[priority] || priority || '-';
}

function priorityStyle(priority) {
  if (priority === 'high') return 'background: #fde8e8; color: #991b1b;';
  if (priority === 'medium') return 'background: #fff1d6; color: #9a5b00;';
  return 'background: #f3f4f6; color: #374151;';
}

function formatDateTime(value) {
  if (!value) return '-';
  return new Date(value).toLocaleString('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  });
}

async function fetchDetail() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get(`/agent/laporan/${route.params.id}`);
    item.value = data;
  } catch (err) {
    if (err.response?.status === 403) {
      error.value = 'Anda tidak memiliki akses ke laporan ini.';
    } else if (err.response?.status === 404) {
      error.value = 'Laporan tidak ditemukan.';
    } else {
      error.value = 'Terjadi kesalahan saat memuat laporan.';
    }
  } finally {
    loading.value = false;
  }
}

onMounted(fetchDetail);
</script>

<template>
  <section class="grid" style="gap: 20px;">

    <!-- Header -->
    <article class="card" style="grid-column: 1 / -1; display: flex; align-items: center; gap: 14px;">
      <button
        type="button"
        @click="router.push({ name: 'agent-dashboard' })"
        style="display: flex; align-items: center; gap: 6px; background: none; border: 1.5px solid var(--border); border-radius: 10px; padding: 8px 14px; cursor: pointer; font-weight: 600; color: var(--text); transition: background 0.15s;"
        onmouseover="this.style.background='var(--bg)'"
        onmouseout="this.style.background='none'"
      >
        ← Kembali
      </button>
      <div>
        <h2 class="page-title" style="margin: 0;">Detail Laporan</h2>
        <p style="margin: 4px 0 0; color: var(--muted); font-size: 0.88rem;">Informasi lengkap laporan gangguan Anda.</p>
      </div>
    </article>

    <!-- Loading -->
    <article v-if="loading" class="card" style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--muted);">
      <div style="font-size: 2rem; margin-bottom: 10px;">⏳</div>
      Memuat detail laporan...
    </article>

    <!-- Error -->
    <article v-else-if="error" class="card" style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #991b1b; background: #fde8e8; border-color: #fca5a5;">
      <div style="font-size: 2rem; margin-bottom: 10px;">⚠️</div>
      {{ error }}
    </article>

    <!-- Konten Laporan -->
    <template v-else-if="item">

      <!-- Info Utama -->
      <article class="card" style="grid-column: 1 / -1;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 20px;">
          <div>
            <template v-if="item.id_task_sip && item.id_task_sip !== '-'">
              <p style="margin: 0 0 4px; font-size: 0.82rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em;">Nomor Tiket / ID Task SIP</p>
              <h3 style="margin: 0; font-size: 1.3rem; letter-spacing: 0.02em;">{{ item.id_task_sip }}</h3>
            </template>
            <template v-else>
              <p style="margin: 0 0 4px; font-size: 0.82rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em;">Laporan Gangguan</p>
              <h3 style="margin: 0; font-size: 1.3rem; letter-spacing: 0.02em;">{{ item.judul || item.kategori || 'Detail Laporan' }}</h3>
            </template>
          </div>
          <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
            <span
              :style="priorityStyle(item.priority)"
              style="padding: 5px 12px; border-radius: 999px; font-size: 0.8rem; font-weight: 700;"
            >
              Prioritas: {{ priorityLabel(item.priority) }}
            </span>
            <span
              :style="statusStyle(item.status)"
              style="padding: 5px 14px; border-radius: 999px; font-weight: 700;"
            >
              {{ statusLabel(item.status) }}
            </span>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">
          <div style="padding: 14px; border-radius: 12px; background: var(--bg, #f9fafb); border: 1px solid var(--border);">
            <p style="margin: 0 0 4px; font-size: 0.78rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em;">Judul</p>
            <p style="margin: 0; font-weight: 600;">{{ item.judul || '-' }}</p>
          </div>
          <div style="padding: 14px; border-radius: 12px; background: var(--bg, #f9fafb); border: 1px solid var(--border);">
            <p style="margin: 0 0 4px; font-size: 0.78rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em;">Kategori</p>
            <p style="margin: 0; font-weight: 600;">{{ item.kategori || '-' }}</p>
          </div>
          <div style="padding: 14px; border-radius: 12px; background: var(--bg, #f9fafb); border: 1px solid var(--border);">
            <p style="margin: 0 0 4px; font-size: 0.78rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em;">Dibuat Pada</p>
            <p style="margin: 0; font-weight: 600;">{{ formatDateTime(item.created_at) }}</p>
          </div>
          <div v-if="item.durasi" style="padding: 14px; border-radius: 12px; background: var(--bg, #f9fafb); border: 1px solid var(--border);">
            <p style="margin: 0 0 4px; font-size: 0.78rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em;">Durasi</p>
            <p style="margin: 0; font-weight: 600;">{{ item.durasi }} menit</p>
          </div>
        </div>

        <div v-if="item.deskripsi" style="margin-top: 16px; padding: 14px; border-radius: 12px; background: var(--bg, #f9fafb); border: 1px solid var(--border);">
          <p style="margin: 0 0 6px; font-size: 0.78rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em;">Deskripsi</p>
          <p style="margin: 0; white-space: pre-wrap; line-height: 1.6;">{{ item.deskripsi }}</p>
        </div>
      </article>

      <!-- Evidence -->
      <article class="card" style="grid-column: 1 / -1;">
        <h3 style="margin: 0 0 16px;">Evidence / Lampiran</h3>
        <div v-if="item.evidences?.length" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px;">
          <article
            v-for="evidence in item.evidences"
            :key="evidence.id"
            class="card"
            style="padding: 10px;"
          >
            <div style="display: flex; justify-content: space-between; gap: 8px; align-items: center; margin-bottom: 8px;">
              <p style="margin: 0; font-size: 0.78rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                {{ evidence.filename }}
              </p>
              <a
                :href="`${apiBaseUrl}/evidence/${evidence.id}/view`"
                :download="evidence.filename"
                style="white-space: nowrap; color: var(--primary); font-weight: 700; font-size: 0.78rem; flex-shrink: 0;"
              >
                ↓ Unduh
              </a>
            </div>
            <a
              :href="`${apiBaseUrl}/evidence/${evidence.id}/view`"
              target="_blank"
              rel="noopener noreferrer"
              style="display: block;"
            >
              <img
                :src="`${apiBaseUrl}/evidence/${evidence.id}/view`"
                :alt="evidence.filename"
                style="width: 100%; height: 140px; border-radius: 8px; border: 1px solid var(--border); object-fit: cover; background: #f3f4f6; display: block;"
              >
            </a>
          </article>
        </div>
        <div v-else style="text-align: center; padding: 32px 0; color: var(--muted);">
          <div style="font-size: 2rem; margin-bottom: 8px;">📎</div>
          Belum ada evidence yang dilampirkan.
        </div>
      </article>

    </template>

  </section>
</template>

