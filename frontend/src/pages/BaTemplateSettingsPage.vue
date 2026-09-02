<script setup>
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();

const loading  = ref(false);
const saving   = ref(false);
const msg      = ref('');
const error    = ref('');

// State Logo
const logoFile = ref(null);
const logoPreview = ref(null);
const logoUploading = ref(false);

// State Koord Sig
const koordSigFile = ref(null);
const koordSigPreview = ref(null);
const koordSigUploading = ref(false);

// State User Sig
const userSigFile = ref(null);
const userSigPreview = ref(null);
const userSigUploading = ref(false);

const form = ref({
  ba_brand_name:            'PLN Icon Plus',
  ba_departemen:            'Divisi Perencanaan Ops Ritel',
  ba_title:                 'BERITA ACARA KRONOLOGIS GANGGUAN APLIKASI/JARINGAN',
  ba_location:              'SEMARANG',
  ba_koord_name:            'AHMAD ZAENAL ARIFIN',
  ba_koord_title:           'KOORDINATOR',
  ba_ts_title:              'TECHNICAL SUPPORT',
  ba_show_evidence:         'true',
  ba_logo_url:              null,
  ba_koord_signature_url:   null,
});

async function fetchSettings() {
  loading.value = true;
  try {
    const { data } = await api.get('/settings');
    if (data) {
      form.value = { ...form.value, ...data };
    }
  } catch (err) {
    console.error('Failed to load settings:', err);
  } finally {
    loading.value = false;
  }
}

async function saveSettings() {
  saving.value = true;
  msg.value   = '';
  error.value = '';
  try {
    const { data } = await api.post('/settings', form.value);
    msg.value = data.message || 'Pengaturan template Berita Acara berhasil disimpan.';
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal menyimpan pengaturan template.';
  } finally {
    saving.value = false;
  }
}

// ───── Helper Extract Image File From Paste Event ─────
function extractImageFromPaste(e) {
  const items = e.clipboardData?.items;
  if (!items) return null;

  for (let i = 0; i < items.length; i++) {
    if (items[i].type.indexOf('image') !== -1) {
      const blob = items[i].getAsFile();
      if (blob) {
        return new File([blob], `pasted_image_${Date.now()}.png`, { type: blob.type });
      }
    }
  }
  return null;
}

// ───── LOGO KOP ─────
function setLogoFile(file) {
  if (!file) return;
  logoFile.value = file;
  logoPreview.value = URL.createObjectURL(file);
}

function clearLogoDraft() {
  logoFile.value = null;
  logoPreview.value = null;
}

function onLogoSelected(e) {
  const file = e.target.files[0];
  if (file) setLogoFile(file);
}

function onLogoPaste(e) {
  const file = extractImageFromPaste(e);
  if (file) {
    e.preventDefault();
    setLogoFile(file);
    msg.value = 'Gambar screenshot berhasil disalin dari clipboard!';
  }
}

async function uploadLogo() {
  if (!logoFile.value) return;
  logoUploading.value = true;
  msg.value   = '';
  error.value = '';

  const fd = new FormData();
  fd.append('logo', logoFile.value);

  try {
    const { data } = await api.post('/settings/upload-logo', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    form.value.ba_logo_url = data.logo_url;
    msg.value = data.message || 'Logo KOP berhasil diunggah.';
    clearLogoDraft();
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal mengunggah logo KOP.';
  } finally {
    logoUploading.value = false;
  }
}

async function deleteUploadedLogo() {
  if (!confirm('Apakah Anda yakin ingin menghapus Logo KOP ini?')) return;
  try {
    const { data } = await api.delete('/settings/logo');
    form.value.ba_logo_url = null;
    clearLogoDraft();
    msg.value = data.message || 'Logo KOP berhasil dihapus.';
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal menghapus Logo KOP.';
  }
}

// ───── KOORDINATOR SIGNATURE ─────
function setKoordSigFile(file) {
  if (!file) return;
  koordSigFile.value = file;
  koordSigPreview.value = URL.createObjectURL(file);
}

function clearKoordSigDraft() {
  koordSigFile.value = null;
  koordSigPreview.value = null;
}

function onKoordSigSelected(e) {
  const file = e.target.files[0];
  if (file) setKoordSigFile(file);
}

function onKoordSigPaste(e) {
  const file = extractImageFromPaste(e);
  if (file) {
    e.preventDefault();
    setKoordSigFile(file);
    msg.value = 'Gambar screenshot tanda tangan Koordinator berhasil disalin dari clipboard!';
  }
}

async function uploadKoordSig() {
  if (!koordSigFile.value) return;
  koordSigUploading.value = true;
  msg.value   = '';
  error.value = '';

  const fd = new FormData();
  fd.append('signature', koordSigFile.value);

  try {
    const { data } = await api.post('/settings/upload-koord-signature', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    form.value.ba_koord_signature_url = data.signature_url;
    msg.value = data.message || 'Tanda tangan Koordinator berhasil diunggah.';
    clearKoordSigDraft();
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal mengunggah tanda tangan Koordinator.';
  } finally {
    koordSigUploading.value = false;
  }
}

async function deleteUploadedKoordSig() {
  if (!confirm('Apakah Anda yakin ingin menghapus Tanda Tangan Koordinator ini?')) return;
  try {
    const { data } = await api.delete('/settings/koord-signature');
    form.value.ba_koord_signature_url = null;
    clearKoordSigDraft();
    msg.value = data.message || 'Tanda tangan Koordinator berhasil dihapus.';
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal menghapus tanda tangan Koordinator.';
  }
}

// ───── USER SIGNATURE (TS) ─────
function setUserSigFile(file) {
  if (!file) return;
  userSigFile.value = file;
  userSigPreview.value = URL.createObjectURL(file);
}

function clearUserSigDraft() {
  userSigFile.value = null;
  userSigPreview.value = null;
}

function onUserSigSelected(e) {
  const file = e.target.files[0];
  if (file) setUserSigFile(file);
}

function onUserSigPaste(e) {
  const file = extractImageFromPaste(e);
  if (file) {
    e.preventDefault();
    setUserSigFile(file);
    msg.value = 'Gambar screenshot tanda tangan Anda berhasil disalin dari clipboard!';
  }
}

async function uploadUserSig() {
  if (!userSigFile.value) return;
  userSigUploading.value = true;
  msg.value   = '';
  error.value = '';

  const fd = new FormData();
  fd.append('signature', userSigFile.value);

  try {
    const { data } = await api.post('/users/upload-signature', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    if (auth.user) {
      auth.user.signature_url = data.signature_url;
    }
    msg.value = data.message || 'Tanda tangan akun Anda berhasil diunggah.';
    clearUserSigDraft();
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal mengunggah tanda tangan akun Anda.';
  } finally {
    userSigUploading.value = false;
  }
}

async function deleteUploadedUserSig() {
  if (!confirm('Apakah Anda yakin ingin menghapus tanda tangan akun Anda?')) return;
  try {
    const { data } = await api.delete('/users/signature');
    if (auth.user) {
      auth.user.signature_url = null;
    }
    clearUserSigDraft();
    msg.value = data.message || 'Tanda tangan akun Anda berhasil dihapus.';
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal menghapus tanda tangan akun Anda.';
  }
}

onMounted(() => {
  fetchSettings();
});
</script>

<template>
  <div class="template-settings-wrap">
    
    <!-- Notification Alert -->
    <div v-if="msg" class="alert-box alert-success">
      ✅ {{ msg }}
    </div>
    <div v-if="error" class="alert-box alert-error">
      ❌ {{ error }}
    </div>

    <!-- Card Upload Tanda Tangan Per Akun TS (Untuk User Yang Login) -->
    <div class="card my-sig-card">
      <div class="sig-card-header">
        <div>
          <h3 class="sig-card-title">
            ✍️ Tanda Tangan Digital Saya
          </h3>
          <p class="sig-card-subtitle">
            Akun: <strong>{{ auth.user?.name }}</strong> &bull; Paste Screenshot (Ctrl + V) atau pilih file.
          </p>
        </div>
        <span class="role-badge-pill">
          {{ auth.user?.roles?.[0]?.name || 'User' }}
        </span>
      </div>

      <div 
        tabindex="0"
        @paste="onUserSigPaste"
        class="paste-dropzone user-sig-dropzone"
      >
        <!-- Preview Signature Saya -->
        <div class="sig-preview-col">
          <div class="sig-preview-frame">
            <img v-if="userSigPreview" :src="userSigPreview" class="sig-preview-img active" title="Preview Gambar Baru" />
            <img v-else-if="auth.user?.signature_url" :src="auth.user.signature_url" class="sig-preview-img" />
            <span v-else class="sig-empty-text">Belum Ada Tanda Tangan</span>
          </div>

          <button v-if="auth.user?.signature_url && !userSigPreview" @click.stop="deleteUploadedUserSig" class="btn-delete-link">
            🗑️ Hapus Tanda Tangan
          </button>
        </div>

        <!-- Upload Form & Paste Hint -->
        <div class="sig-upload-controls">
          <div class="sig-paste-hint">
            <span>📋 Klik di sini lalu tekan <kbd>Ctrl + V</kbd> untuk Paste Screenshot</span>
          </div>

          <div class="sig-action-row">
            <input type="file" accept="image/*" @change="onUserSigSelected" class="setting-input file-input-control" />
            <button @click="uploadUserSig" :disabled="!userSigFile || userSigUploading" class="btn-primary btn-upload-sig">
              {{ userSigUploading ? 'Mengunggah...' : 'Upload Tanda Tangan' }}
            </button>
          </div>

          <!-- Indicator File Terdeteksi + Button Batal Draft -->
          <div v-if="userSigFile" class="file-draft-indicator">
            <span class="file-draft-text">
              ✓ Gambar terdeteksi: {{ userSigFile.name }}
            </span>
            <button @click="clearUserSigDraft" class="file-draft-cancel" title="Batal pilihan gambar ini">
              ✕ Batal
            </button>
          </div>

        </div>
      </div>
    </div>

    <!-- Main Card Settings Template BA (Admin Only / General) -->
    <div class="card ba-config-card">
      <div class="ba-card-header">
        <h3 class="ba-header-title">
          📄 Pengaturan Template Berita Acara & Logo KOP (Admin)
        </h3>
        <p class="ba-header-subtitle">
          Atur logo instansi, tanda tangan koordinator utama, kota, dan judul resmi pada dokumen PDF & Excel Berita Acara.
        </p>
      </div>

      <div v-if="loading" style="padding: 30px; text-align: center; color: #94a3b8;">
        Memuat pengaturan template...
      </div>

      <div v-else style="display: flex; flex-direction: column; gap: 16px;">

        <!-- Section 1: Upload Logo KOP & Tanda Tangan Koordinator -->
        <div class="config-section-card">
          <h4 class="config-section-title">1. Upload Logo KOP & Tanda Tangan Koordinator</h4>
          
          <div class="upload-dual-grid">
            
            <!-- Upload Logo KOP -->
            <div 
              tabindex="0"
              @paste="onLogoPaste"
              class="paste-dropzone mini-dropzone"
            >
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <label style="font-size: 0.82rem; font-weight: 700; color: #1e293b; margin: 0;">🖼️ Logo KOP (Kanan)</label>
                <button v-if="form.ba_logo_url && !logoPreview" @click.stop="deleteUploadedLogo" class="btn-delete-link">
                  🗑️ Hapus Logo
                </button>
              </div>
              
              <div class="mini-preview-box">
                <img v-if="logoPreview" :src="logoPreview" class="mini-preview-img active" title="Preview Logo Baru" />
                <img v-else-if="form.ba_logo_url" :src="form.ba_logo_url" class="mini-preview-img" />
                <span v-else style="font-size: 0.75rem; color: #94a3b8;">Default: Logo PLN Icon Plus</span>
              </div>

              <div class="mini-hint">
                📋 Klik di sini lalu <kbd>Ctrl + V</kbd> untuk copas logo
              </div>

              <!-- Indicator File Logo Terdeteksi -->
              <div v-if="logoFile" class="file-draft-indicator">
                <span class="file-draft-text">✓ Draft siap upload</span>
                <button @click.stop="clearLogoDraft" class="file-draft-cancel">✕ Batal</button>
              </div>

              <input type="file" accept="image/*" @change="onLogoSelected" class="setting-input file-input-control" />
              <button @click="uploadLogo" :disabled="!logoFile || logoUploading" class="btn-primary btn-full-action">
                {{ logoUploading ? 'Mengunggah Logo...' : 'Upload Logo KOP' }}
              </button>
            </div>

            <!-- Upload Tanda Tangan Koordinator -->
            <div 
              tabindex="0"
              @paste="onKoordSigPaste"
              class="paste-dropzone mini-dropzone"
            >
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <label style="font-size: 0.82rem; font-weight: 700; color: #1e293b; margin: 0;">✒️ TTD Koordinator (Admin)</label>
                <button v-if="form.ba_koord_signature_url && !koordSigPreview" @click.stop="deleteUploadedKoordSig" class="btn-delete-link">
                  🗑️ Hapus TTD
                </button>
              </div>
              
              <div class="mini-preview-box">
                <img v-if="koordSigPreview" :src="koordSigPreview" class="mini-preview-img active" title="Preview Tanda Tangan Baru" />
                <img v-else-if="form.ba_koord_signature_url" :src="form.ba_koord_signature_url" class="mini-preview-img" />
                <span v-else style="font-size: 0.75rem; color: #94a3b8;">Belum Ada Tanda Tangan Koordinator</span>
              </div>

              <div class="mini-hint">
                📋 Klik di sini lalu <kbd>Ctrl + V</kbd> untuk copas ttd
              </div>

              <!-- Indicator File Koord Sig Terdeteksi -->
              <div v-if="koordSigFile" class="file-draft-indicator">
                <span class="file-draft-text">✓ Draft siap upload</span>
                <button @click.stop="clearKoordSigDraft" class="file-draft-cancel">✕ Batal</button>
              </div>

              <input type="file" accept="image/*" @change="onKoordSigSelected" class="setting-input file-input-control" />
              <button @click="uploadKoordSig" :disabled="!koordSigFile || koordSigUploading" class="btn-primary btn-full-action">
                {{ koordSigUploading ? 'Mengunggah TTD...' : 'Upload TTD Koordinator' }}
              </button>
            </div>

          </div>
        </div>

        <!-- Section 2: Form Pengaturan Teks KOP & Penandatangan -->
        <form @submit.prevent="saveSettings" style="display: flex; flex-direction: column; gap: 14px;">
          
          <div class="config-section-card">
            <h4 class="config-section-title">2. Header & Teks KOP</h4>
            
            <div class="form-responsive-grid">
              <div>
                <label class="form-field-label">Nama Brand / Instansi (Kop Kanan)</label>
                <input type="text" v-model="form.ba_brand_name" class="setting-input" placeholder="contoh: PLN Icon Plus" required />
              </div>
              <div>
                <label class="form-field-label">Departemen (Kop Kiri)</label>
                <input type="text" v-model="form.ba_departemen" class="setting-input" placeholder="contoh: Divisi Perencanaan Ops Ritel" required />
              </div>
            </div>

            <div>
              <label class="form-field-label">Judul Dokumen Berita Acara (Kop Tengah)</label>
              <input type="text" v-model="form.ba_title" class="setting-input" placeholder="contoh: BERITA ACARA KRONOLOGIS GANGGUAN APLIKASI/JARINGAN" required />
            </div>
          </div>

          <div class="config-section-card">
            <h4 class="config-section-title">3. Data Penandatangan & Lokasi</h4>

            <div class="form-responsive-grid">
              <div>
                <label class="form-field-label">Kota / Lokasi Cetak</label>
                <input type="text" v-model="form.ba_location" class="setting-input" placeholder="contoh: SEMARANG" required />
              </div>
              <div>
                <label class="form-field-label">Nama Koordinator (Penandatangan Kiri)</label>
                <input type="text" v-model="form.ba_koord_name" class="setting-input" placeholder="contoh: AHMAD ZAENAL ARIFIN" required />
              </div>
            </div>

            <div class="form-responsive-grid">
              <div>
                <label class="form-field-label">Jabatan Penandatangan Kiri</label>
                <input type="text" v-model="form.ba_koord_title" class="setting-input" placeholder="contoh: KOORDINATOR" required />
              </div>
              <div>
                <label class="form-field-label">Jabatan Penandatangan Kanan (TS)</label>
                <input type="text" v-model="form.ba_ts_title" class="setting-input" placeholder="contoh: TECHNICAL SUPPORT" required />
              </div>
            </div>
          </div>

          <div class="config-section-card">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
              <input type="checkbox" v-model="form.ba_show_evidence" true-value="true" false-value="false" style="width: 18px; height: 18px; cursor: pointer; accent-color: #2563eb;" />
              <span style="font-size: 0.86rem; font-weight: 600; color: #1e293b;">
                Sertakan Section Lampiran (Evidence Screenshot) pada PDF & Excel Berita Acara
              </span>
            </label>
          </div>

          <div style="display: flex; justify-content: flex-end; margin-top: 4px;">
            <button type="submit" class="btn-primary btn-save-template" :disabled="saving">
              {{ saving ? 'Menyimpan...' : '💾 Simpan Pengaturan Template' }}
            </button>
          </div>

        </form>

      </div>
    </div>
  </div>
</template>

<style scoped>
.template-settings-wrap {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
}

.alert-box {
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 0.88rem;
  font-weight: 600;
}
.alert-success { background: #d1fae5; color: #065f46; }
.alert-error { background: #fee2e2; color: #991b1b; }

.my-sig-card {
  padding: 20px 22px;
  border-radius: 14px;
  background: #ffffff;
  border: 1.5px solid #93c5fd;
  box-shadow: 0 4px 14px rgba(37,99,235,0.06);
}

.sig-card-header {
  margin-bottom: 14px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
}

.sig-card-title {
  margin: 0 0 4px;
  font-size: 1.05rem;
  font-weight: 700;
  color: #1e3a8a;
}

.sig-card-subtitle {
  margin: 0;
  font-size: 0.82rem;
  color: #475569;
}

.role-badge-pill {
  font-size: 0.72rem;
  font-weight: 700;
  background: #dbeafe;
  color: #1e40af;
  padding: 3px 10px;
  border-radius: 999px;
  white-space: nowrap;
}

.user-sig-dropzone {
  display: flex;
  align-items: center;
  gap: 16px;
  background: #f8fafc;
  padding: 16px;
  border-radius: 12px;
  border: 2px dashed #93c5fd;
  outline: none;
  cursor: pointer;
}

.sig-preview-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.sig-preview-frame {
  width: 140px;
  height: 75px;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.sig-preview-img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.sig-preview-img.active {
  border: 2px solid #2563eb;
}

.sig-empty-text {
  font-size: 0.72rem;
  color: #94a3b8;
  text-align: center;
  padding: 4px;
}

.sig-upload-controls {
  flex: 1;
  min-width: 200px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.sig-paste-hint {
  font-size: 0.8rem;
  font-weight: 700;
  color: #1d4ed8;
}

.sig-paste-hint kbd {
  background: #e2e8f0;
  padding: 2px 6px;
  border-radius: 4px;
  font-family: monospace;
}

.sig-action-row {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}

.file-input-control {
  padding: 6px 10px;
  font-size: 0.82rem;
  flex: 1;
  min-width: 160px;
}

.btn-upload-sig {
  white-space: nowrap;
  padding: 8px 16px;
  font-size: 0.82rem;
  font-weight: 700;
}

.file-draft-indicator {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #e0f2fe;
  padding: 5px 10px;
  border-radius: 6px;
  border: 1px solid #bae6fd;
}

.file-draft-text {
  font-size: 0.76rem;
  color: #0369a1;
  font-weight: 700;
}

.file-draft-cancel {
  background: none;
  border: none;
  color: #ef4444;
  font-weight: 700;
  cursor: pointer;
  font-size: 0.76rem;
}

.ba-config-card {
  padding: 20px 22px;
  border-radius: 14px;
  background: #ffffff;
}

.ba-card-header {
  margin-bottom: 16px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 12px;
}

.ba-header-title {
  margin: 0 0 4px;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
}

.ba-header-subtitle {
  margin: 0;
  font-size: 0.82rem;
  color: #64748b;
}

.config-section-card {
  background: #f8fafc;
  padding: 14px 16px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.config-section-title {
  margin: 0;
  font-size: 0.9rem;
  font-weight: 700;
  color: #1e293b;
}

.upload-dual-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.mini-dropzone {
  background: #ffffff;
  padding: 12px;
  border-radius: 10px;
  border: 1.5px dashed #cbd5e1;
  display: flex;
  flex-direction: column;
  gap: 8px;
  outline: none;
  cursor: pointer;
}

.mini-preview-box {
  height: 65px;
  border: 1px dashed #cbd5e1;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fafafa;
  overflow: hidden;
}

.mini-preview-img {
  max-height: 100%;
  max-width: 100%;
  object-fit: contain;
}

.mini-preview-img.active {
  border: 2px solid #2563eb;
}

.mini-hint {
  font-size: 0.72rem;
  color: #475569;
  font-weight: 600;
}

.mini-hint kbd {
  background: #e2e8f0;
  padding: 1px 4px;
  border-radius: 3px;
}

.btn-full-action {
  padding: 7px 12px;
  font-size: 0.8rem;
  font-weight: 700;
  width: 100%;
}

.form-responsive-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.form-field-label {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  color: #334155;
  margin-bottom: 5px;
}

.btn-save-template {
  padding: 10px 22px;
  font-weight: 700;
  font-size: 0.88rem;
}

.setting-input {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.85rem;
  outline: none;
  background: #ffffff;
  box-sizing: border-box;
  transition: border-color 0.15s;
}

.setting-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

.paste-dropzone:focus {
  border-color: #2563eb !important;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
  background: #eff6ff !important;
}

.btn-delete-link {
  background: none;
  border: none;
  color: #dc2626;
  font-size: 0.72rem;
  font-weight: 700;
  cursor: pointer;
  padding: 2px 4px;
  border-radius: 4px;
}

.btn-delete-link:hover {
  background: #fee2e2;
  text-decoration: underline;
}

@media (max-width: 768px) {
  .my-sig-card, .ba-config-card {
    padding: 14px;
    border-radius: 12px;
  }
  .user-sig-dropzone {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
    padding: 12px;
  }
  .sig-preview-frame {
    width: 100%;
    height: 90px;
  }
  .sig-action-row {
    flex-direction: column;
    align-items: stretch;
  }
  .btn-upload-sig {
    width: 100%;
    padding: 10px;
  }
  .upload-dual-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }
  .form-responsive-grid {
    grid-template-columns: 1fr;
    gap: 10px;
  }
  .btn-save-template {
    width: 100%;
    padding: 12px;
  }
}
</style>
