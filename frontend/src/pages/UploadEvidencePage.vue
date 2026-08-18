<script setup>
import { ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../services/api';

const route = useRoute();
const selectedFile = ref(null);
const loading = ref(false);
const message = ref('');

function onFileChange(event) {
  selectedFile.value = event.target.files?.[0] ?? null;
}

async function upload() {
  if (!selectedFile.value) {
    message.value = 'Pilih file terlebih dahulu.';
    return;
  }

  loading.value = true;
  message.value = '';

  const formData = new FormData();
  formData.append('file', selectedFile.value);

  try {
    await api.post(`/gangguan/${route.params.id}/upload`, formData);
    message.value = 'Upload evidence berhasil.';
  } catch (err) {
    message.value = err.response?.data?.message || 'Upload evidence gagal.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <section class="card">
    <h2 class="page-title">Upload Evidence</h2>
    <p style="color: var(--muted);">Integrasi dengan endpoint POST /gangguan/{id}/upload.</p>
    <label>
      Pilih File
      <input type="file" @change="onFileChange">
    </label>
    <div style="margin-top: 12px;">
      <button class="btn-primary" :disabled="loading" @click="upload">{{ loading ? 'Uploading...' : 'Upload' }}</button>
    </div>
    <p v-if="message" style="margin-top: 12px; color: var(--muted);">{{ message }}</p>
  </section>
</template>
