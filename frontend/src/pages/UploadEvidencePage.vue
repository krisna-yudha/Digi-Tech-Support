<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../services/api';

const route = useRoute();
const router = useRouter();
const selectedFile = ref(null);
const previewUrl = ref(null);
const loading = ref(false);
const message = ref('');
const isError = ref(false);
const showPopup = ref(false);

function onFileChange(event) {
  handleFileSelection(event.target.files?.[0] ?? null);
}

function handleFileSelection(file) {
  selectedFile.value = file;
  message.value = '';
  isError.value = false;
  
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = null;
  }
  
  if (file && file.type.startsWith('image/')) {
    previewUrl.value = URL.createObjectURL(file);
  }
}

function clearFile() {
  selectedFile.value = null;
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = null;
  }
}

function handlePaste(e) {
  const items = e.clipboardData?.items;
  if (!items) return;
  for (let i = 0; i < items.length; i++) {
    if (items[i].type.indexOf('image') !== -1) {
      const file = items[i].getAsFile();
      const ext = file.type.split('/')[1] || 'png';
      const newFile = new File([file], `Screenshot-${Date.now()}.${ext}`, { type: file.type });
      handleFileSelection(newFile);
      break;
    }
  }
}

onMounted(() => {
  window.addEventListener('paste', handlePaste);
});

onUnmounted(() => {
  window.removeEventListener('paste', handlePaste);
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
});

async function upload() {
  if (!selectedFile.value) {
    message.value = 'Silakan pilih file atau paste gambar terlebih dahulu.';
    isError.value = true;
    return;
  }

  loading.value = true;
  message.value = '';
  isError.value = false;

  const formData = new FormData();
  formData.append('file', selectedFile.value);

  try {
    await api.post(`/gangguan/${route.params.id}/upload`, formData);
    showPopup.value = true;
    setTimeout(() => {
      router.push(`/gangguan/${route.params.id}`);
    }, 1800);
  } catch (err) {
    message.value = err.response?.data?.message || 'Upload evidence gagal.';
    isError.value = true;
    loading.value = false;
  }
}
</script>

<template>
  <div>
    <!-- Tombol Kembali -->
    <button
      type="button"
      @click="router.back()"
      style="display:inline-flex; align-items:center; gap:6px; background:#fff; border:1px solid #cbd5e1; border-radius:8px; padding:6px 14px; cursor:pointer; font-weight:600; font-size:0.85rem; color:#334155; margin-bottom: 20px; transition:all 0.15s;"
      onmouseover="this.style.background='#f8fafc'"
      onmouseout="this.style.background='#fff'"
    >
      <span style="font-size:1rem; line-height:1;">&larr;</span> Kembali
    </button>

    <section class="card" style="padding: 24px; max-width: 600px;">
      <h2 class="page-title" style="margin: 0 0 8px; font-size: 1.4rem;">Upload Evidence</h2>
      <p style="color: var(--muted); margin: 0 0 24px; font-size: 0.95rem;">Lampirkan bukti foto penanganan tiket gangguan.</p>
      
      <div style="margin-bottom: 20px;">
        <label style="display:block;margin-bottom:8px;font-size:.85rem;font-weight:600;color:#334155;">Upload atau Paste (Ctrl+V)</label>
        <div style="border: 2px dashed #cbd5e1; padding: 32px 16px; border-radius: 8px; background: #f8fafc; text-align: center; transition: all 0.2s;" :style="selectedFile ? 'border-color: var(--primary); background: #eff6ff;' : ''">
          <div v-if="!selectedFile">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="#94a3b8" viewBox="0 0 24 24" style="margin-bottom:12px; display:inline-block;"><path d="M19 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
            <p style="margin:0 0 16px; color: #475569; font-size: 0.95rem;">Pilih file dari perangkat Anda atau <b>tekan Ctrl+V</b> (Paste) untuk mengunggah <i>screenshot</i>.</p>
            <input type="file" accept="image/*" @change="onFileChange" style="font-family: inherit; margin: 0 auto; max-width: 100%;">
          </div>
          <div v-else style="display:flex; flex-direction:column; align-items:center;">
            <p style="margin:0 0 12px; font-weight: 700; color: var(--primary);">File terpilih: {{ selectedFile.name }}</p>
            <img v-if="previewUrl" :src="previewUrl" style="max-height: 250px; border-radius: 8px; border: 1px solid #cbd5e1; margin-bottom: 16px; object-fit: contain; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <div v-else style="width: 64px; height: 64px; background: #cbd5e1; border-radius: 8px; margin-bottom: 16px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.5rem;">📄</div>
            <button type="button" @click="clearFile" style="background: white; border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 16px; font-size: 0.85rem; font-weight:600; cursor: pointer; color: #475569; transition: background 0.15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">Ganti File</button>
          </div>
        </div>
      </div>
      
      <div style="display: flex; align-items: center; gap: 12px;">
        <button 
          @click="upload" 
          :disabled="loading" 
          style="padding:10px 24px;border-radius:8px;border:none;cursor:pointer;font-weight:700;font-size:.9rem;background:var(--primary);color:#fff;transition:opacity .15s;"
          :style="loading ? 'opacity:0.6; cursor:not-allowed;' : ''"
        >
          {{ loading ? 'Mengunggah...' : 'Upload File' }}
        </button>
        <span v-if="message && !showPopup" :style="isError ? 'color: #dc2626;' : 'color: #10b981;'" style="font-weight: 600; font-size: 0.85rem;">
          {{ message }}
        </span>
      </div>
    </section>

    <!-- Popup Animasi Berhasil -->
    <div v-if="showPopup" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
      <div class="popup-box">
        <div class="popup-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        </div>
        <h3 style="margin: 0 0 8px; font-size: 1.25rem; color: #0f172a;">Upload Berhasil!</h3>
        <p style="margin: 0; color: #64748b; font-size: 0.95rem;">Mengalihkan ke halaman detail...</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.popup-box {
  background: white; 
  padding: 40px; 
  border-radius: 16px; 
  text-align: center; 
  box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
  animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
.popup-icon {
  width: 64px; 
  height: 64px; 
  background: #10b981; 
  border-radius: 50%; 
  color: white; 
  display: flex; 
  align-items: center; 
  justify-content: center; 
  margin: 0 auto 16px; 
  animation: scaleUp 0.4s 0.1s both cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

@keyframes popIn {
  0% { transform: scale(0.8); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}
@keyframes scaleUp {
  0% { transform: scale(0); }
  100% { transform: scale(1); }
}
</style>
