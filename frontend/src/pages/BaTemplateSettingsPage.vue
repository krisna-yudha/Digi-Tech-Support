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
  <div style="display: flex; flex-direction: column; gap: 20px; width: 100%;">
    
    <!-- Notification Alert -->
    <div v-if="msg" style="padding: 12px 16px; background: #d1fae5; color: #065f46; border-radius: 8px; font-size: 0.88rem; font-weight: 600;">
      ✅ {{ msg }}
    </div>
    <div v-if="error" style="padding: 12px 16px; background: #fee2e2; color: #991b1b; border-radius: 8px; font-size: 0.88rem; font-weight: 600;">
      ❌ {{ error }}
    </div>

    <!-- Card Upload Tanda Tangan Per Akun TS (Untuk User Yang Login) -->
    <div class="card" style="padding: 24px; border-radius: 12px; background: #ffffff; border: 1.5px solid #93c5fd; box-shadow: 0 4px 14px rgba(37,99,235,0.06);">
      <div style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
        <div>
          <h3 style="margin: 0 0 4px; font-size: 1.05rem; font-weight: 700; color: #1e3a8a;">
            ✍️ Tanda Tangan Digital Saya (Akun: {{ auth.user?.name }})
          </h3>
          <p style="margin: 0; font-size: 0.83rem; color: #475569;">
            Pilih file atau <strong>Paste Gambar Screenshot (Ctrl + V)</strong> pada area di bawah ini.
          </p>
        </div>
        <span style="font-size: 0.75rem; font-weight: 700; background: #dbeafe; color: #1e40af; padding: 4px 10px; border-radius: 999px;">
          Role: {{ auth.user?.roles?.[0]?.name || 'User' }}
        </span>
      </div>

      <div 
        tabindex="0"
        @paste="onUserSigPaste"
        class="paste-dropzone"
        style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap; background: #f8fafc; padding: 18px; border-radius: 10px; border: 2px dashed #93c5fd; outline: none; cursor: pointer;"
      >
        <!-- Preview Signature Saya -->
        <div style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
          <div style="width: 160px; height: 80px; border: 1px dashed #cbd5e1; border-radius: 8px; background: #ffffff; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative;">
            <!-- Preview file baru yang di-paste / di-select -->
            <img v-if="userSigPreview" :src="userSigPreview" style="max-width: 100%; max-height: 100%; object-fit: contain; border: 2px solid #2563eb;" title="Preview Gambar Baru" />
            <img v-else-if="auth.user?.signature_url" :src="auth.user.signature_url" style="max-width: 100%; max-height: 100%; object-fit: contain;" />
            <span v-else style="font-size: 0.75rem; color: #94a3b8; text-align: center; padding: 4px;">Belum Ada Tanda Tangan</span>
          </div>

          <!-- Tombol Hapus Gambar Tersimpan -->
          <button v-if="auth.user?.signature_url && !userSigPreview" @click.stop="deleteUploadedUserSig" class="btn-delete-link">
            🗑️ Hapus Tanda Tangan
          </button>
        </div>

        <!-- Upload Form & Paste Hint -->
        <div style="flex: 1; min-width: 240px; display: flex; flex-direction: column; gap: 10px;">
          <div style="display: flex; align-items: center; gap: 8px; font-size: 0.83rem; font-weight: 700; color: #1d4ed8;">
            <span>📋 Klik di sini lalu tekan <kbd style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-family: monospace;">Ctrl + V</kbd> untuk Paste Screenshot</span>
          </div>

          <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <input type="file" accept="image/*" @change="onUserSigSelected" class="setting-input" style="padding: 6px 10px; font-size: 0.82rem; flex: 1; min-width: 200px;" />
            <button @click="uploadUserSig" :disabled="!userSigFile || userSigUploading" class="btn-primary" style="white-space: nowrap; padding: 8px 18px; font-size: 0.83rem; font-weight: 700;">
              {{ userSigUploading ? 'Mengunggah...' : 'Upload Tanda Tangan Saya' }}
            </button>
          </div>

          <!-- Indicator File Terdeteksi + Button Batal Draft -->
          <div v-if="userSigFile" style="display: flex; align-items: center; justify-content: space-between; background: #e0f2fe; padding: 6px 12px; border-radius: 6px; border: 1px solid #bae6fd;">
            <span style="font-size: 0.78rem; color: #0369a1; font-weight: 700;">
              ✓ Gambar terdeteksi: {{ userSigFile.name }} (Siap di-upload)
            </span>
            <button @click="clearUserSigDraft" style="background: none; border: none; color: #ef4444; font-weight: 700; cursor: pointer; font-size: 0.78rem;" title="Batal pilihan gambar ini">
              ✕ Batal / Hapus Draft
            </button>
          </div>

        </div>
      </div>
    </div>

    <!-- Main Card Settings Template BA (Admin Only / General) -->
    <div class="card" style="padding: 24px; border-radius: 12px; background: #ffffff;">
      <div style="margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 14px;">
        <h3 style="margin: 0 0 4px; font-size: 1.1rem; font-weight: 700; color: #0f172a;">
          📄 Pengaturan Template Berita Acara & Logo KOP (Admin)
        </h3>
        <p style="margin: 0; font-size: 0.85rem; color: #64748b;">
          Atur logo instansi, tanda tangan koordinator utama, kota, dan judul resmi pada dokumen PDF & Excel Berita Acara.
        </p>
      </div>

      <div v-if="loading" style="padding: 30px; text-align: center; color: #94a3b8;">
        Memuat pengaturan template...
      </div>

      <div v-else style="display: flex; flex-direction: column; gap: 20px;">

        <!-- Section 1: Upload Logo KOP & Tanda Tangan Koordinator -->
        <div style="background: #f8fafc; padding: 16px; border-radius: 10px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 16px;">
          <h4 style="margin: 0; font-size: 0.92rem; font-weight: 700; color: #1e293b;">1. Upload Logo KOP & Tanda Tangan Koordinator</h4>
          
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            
            <!-- Upload Logo KOP -->
            <div 
              tabindex="0"
              @paste="onLogoPaste"
              class="paste-dropzone"
              style="background: #ffffff; padding: 14px; border-radius: 8px; border: 1.5px dashed #cbd5e1; display: flex; flex-direction: column; gap: 10px; outline: none; cursor: pointer;"
            >
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <label style="font-size: 0.82rem; font-weight: 700; color: #1e293b; margin: 0;">🖼️ Logo KOP (Kanan)</label>
                <button v-if="form.ba_logo_url && !logoPreview" @click.stop="deleteUploadedLogo" class="btn-delete-link">
                  🗑️ Hapus Logo
                </button>
              </div>
              
              <div style="height: 75px; border: 1px dashed #cbd5e1; border-radius: 6px; display: flex; align-items: center; justify-content: center; background: #fafafa; overflow: hidden; position: relative;">
                <img v-if="logoPreview" :src="logoPreview" style="max-height: 100%; max-width: 100%; object-fit: contain; border: 2px solid #2563eb;" title="Preview Logo Baru" />
                <img v-else-if="form.ba_logo_url" :src="form.ba_logo_url" style="max-height: 100%; max-width: 100%; object-fit: contain;" />
                <span v-else style="font-size: 0.75rem; color: #94a3b8;">Default: Logo PLN Icon Plus</span>
              </div>

              <div style="font-size: 0.75rem; color: #475569; font-weight: 600;">
                📋 Klik di sini lalu <kbd style="background: #e2e8f0; padding: 1px 5px; border-radius: 3px;">Ctrl + V</kbd> untuk copas logo
              </div>

              <!-- Indicator File Logo Terdeteksi -->
              <div v-if="logoFile" style="display: flex; align-items: center; justify-content: space-between; background: #e0f2fe; padding: 4px 8px; border-radius: 4px;">
                <span style="font-size: 0.75rem; color: #0369a1; font-weight: 700;">✓ Draft siap upload</span>
                <button @click.stop="clearLogoDraft" style="background: none; border: none; color: #ef4444; font-weight: 700; cursor: pointer; font-size: 0.75rem;">✕ Batal</button>
              </div>

              <input type="file" accept="image/*" @change="onLogoSelected" class="setting-input" style="padding: 5px; font-size: 0.78rem;" />
              <button @click="uploadLogo" :disabled="!logoFile || logoUploading" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; font-weight: 700;">
                {{ logoUploading ? 'Mengunggah Logo...' : 'Upload Logo KOP' }}
              </button>
            </div>

            <!-- Upload Tanda Tangan Koordinator -->
            <div 
              tabindex="0"
              @paste="onKoordSigPaste"
              class="paste-dropzone"
              style="background: #ffffff; padding: 14px; border-radius: 8px; border: 1.5px dashed #cbd5e1; display: flex; flex-direction: column; gap: 10px; outline: none; cursor: pointer;"
            >
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <label style="font-size: 0.82rem; font-weight: 700; color: #1e293b; margin: 0;">✒️ TTD Koordinator (Admin)</label>
                <button v-if="form.ba_koord_signature_url && !koordSigPreview" @click.stop="deleteUploadedKoordSig" class="btn-delete-link">
                  🗑️ Hapus TTD
                </button>
              </div>
              
              <div style="height: 75px; border: 1px dashed #cbd5e1; border-radius: 6px; display: flex; align-items: center; justify-content: center; background: #fafafa; overflow: hidden; position: relative;">
                <img v-if="koordSigPreview" :src="koordSigPreview" style="max-height: 100%; max-width: 100%; object-fit: contain; border: 2px solid #2563eb;" title="Preview Tanda Tangan Baru" />
                <img v-else-if="form.ba_koord_signature_url" :src="form.ba_koord_signature_url" style="max-height: 100%; max-width: 100%; object-fit: contain;" />
                <span v-else style="font-size: 0.75rem; color: #94a3b8;">Belum Ada Tanda Tangan Koordinator</span>
              </div>

              <div style="font-size: 0.75rem; color: #475569; font-weight: 600;">
                📋 Klik di sini lalu <kbd style="background: #e2e8f0; padding: 1px 5px; border-radius: 3px;">Ctrl + V</kbd> untuk copas ttd
              </div>

              <!-- Indicator File Koord Sig Terdeteksi -->
              <div v-if="koordSigFile" style="display: flex; align-items: center; justify-content: space-between; background: #e0f2fe; padding: 4px 8px; border-radius: 4px;">
                <span style="font-size: 0.75rem; color: #0369a1; font-weight: 700;">✓ Draft siap upload</span>
                <button @click.stop="clearKoordSigDraft" style="background: none; border: none; color: #ef4444; font-weight: 700; cursor: pointer; font-size: 0.75rem;">✕ Batal</button>
              </div>

              <input type="file" accept="image/*" @change="onKoordSigSelected" class="setting-input" style="padding: 5px; font-size: 0.78rem;" />
              <button @click="uploadKoordSig" :disabled="!koordSigFile || koordSigUploading" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; font-weight: 700;">
                {{ koordSigUploading ? 'Mengunggah Tanda Tangan...' : 'Upload Tanda Tangan Koordinator' }}
              </button>
            </div>

          </div>
        </div>

        <!-- Section 2: Form Pengaturan Teks KOP & Penandatangan -->
        <form @submit.prevent="saveSettings" style="display: flex; flex-direction: column; gap: 18px;">
          
          <div style="background: #f8fafc; padding: 16px; border-radius: 10px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 14px;">
            <h4 style="margin: 0; font-size: 0.92rem; font-weight: 700; color: #1e293b;">2. Header & Teks KOP</h4>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
              <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Nama Brand / Instansi (Kop Kanan)</label>
                <input type="text" v-model="form.ba_brand_name" class="setting-input" placeholder="contoh: PLN Icon Plus" required />
              </div>
              <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Departemen (Kop Kiri)</label>
                <input type="text" v-model="form.ba_departemen" class="setting-input" placeholder="contoh: Divisi Perencanaan Ops Ritel" required />
              </div>
            </div>

            <div>
              <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Judul Dokumen Berita Acara (Kop Tengah)</label>
              <input type="text" v-model="form.ba_title" class="setting-input" placeholder="contoh: BERITA ACARA KRONOLOGIS GANGGUAN APLIKASI/JARINGAN" required />
            </div>
          </div>

          <div style="background: #f8fafc; padding: 16px; border-radius: 10px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 14px;">
            <h4 style="margin: 0; font-size: 0.92rem; font-weight: 700; color: #1e293b;">3. Data Penandatangan & Lokasi</h4>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
              <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Kota / Lokasi Cetak</label>
                <input type="text" v-model="form.ba_location" class="setting-input" placeholder="contoh: SEMARANG" required />
              </div>
              <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Nama Koordinator (Penandatangan Kiri)</label>
                <input type="text" v-model="form.ba_koord_name" class="setting-input" placeholder="contoh: AHMAD ZAENAL ARIFIN" required />
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
              <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Jabatan Penandatangan Kiri</label>
                <input type="text" v-model="form.ba_koord_title" class="setting-input" placeholder="contoh: KOORDINATOR" required />
              </div>
              <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 6px;">Jabatan Penandatangan Kanan (TS)</label>
                <input type="text" v-model="form.ba_ts_title" class="setting-input" placeholder="contoh: TECHNICAL SUPPORT" required />
              </div>
            </div>
          </div>

          <div style="background: #f8fafc; padding: 16px; border-radius: 10px; border: 1px solid #e2e8f0;">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
              <input type="checkbox" v-model="form.ba_show_evidence" true-value="true" false-value="false" style="width: 18px; height: 18px; cursor: pointer;" />
              <span style="font-size: 0.88rem; font-weight: 600; color: #1e293b;">
                Sertakan Section Lampiran (Evidence Screenshot) pada PDF & Excel Berita Acara
              </span>
            </label>
          </div>

          <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
            <button type="submit" class="btn-primary" :disabled="saving" style="padding: 10px 24px; font-weight: 700; font-size: 0.9rem;">
              {{ saving ? 'Menyimpan...' : '💾 Simpan Pengaturan Template' }}
            </button>
          </div>

        </form>

      </div>
    </div>
  </div>
</template>

<style scoped>
.setting-input {
  width: 100%;
  padding: 9px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.88rem;
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
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  padding: 2px 6px;
  border-radius: 4px;
}
.btn-delete-link:hover {
  background: #fee2e2;
  text-decoration: underline;
}
</style>
