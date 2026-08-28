<script setup>
import { reactive, ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';

const router  = useRouter();
const auth    = useAuthStore();
const loading = ref(false);
const error   = ref('');
const agents  = ref([]);

const cubicles = ref([]);

const form = reactive({
  is_massal:  false,
  block:      '',
  cubicle:    '',
  custom_cubicle: '',
  agent_name: auth.user?.name || '',
  kategori:   '',
  problem:    '',
  evidence:   null,
});

const previewUrl = ref(null);

watch(() => form.cubicle, (val) => {
  if (val !== 'Lainnya' && !form.is_massal) {
    form.custom_cubicle = '';
  }
});

watch(() => form.is_massal, (val) => {
  if (val) {
    form.cubicle = 'Lainnya';
    form.custom_cubicle = 'MASSAL';
    form.agent_name = 'ALL AGENT';
  } else {
    form.cubicle = '';
    form.custom_cubicle = '';
    form.agent_name = '';
  }
});

onMounted(async () => {
  try {
    const resCubicles = await api.get('/cubicles');
    cubicles.value = Array.isArray(resCubicles.data) ? resCubicles.data : (resCubicles.data.data || []);
  } catch (err) {
    console.error('Failed to load cubicles:', err);
  }

  if (!auth.hasRole('Agent')) {
    form.agent_name = ''; // force TS/Admin to select an agent
    try {
      const { data } = await api.get('/users', { params: { role: 'Agent' } });
      agents.value = data;
    } catch (err) {
      console.error('Failed to load agents:', err);
    }
  }

  window.addEventListener('paste', handlePaste);
});

onUnmounted(() => {
  window.removeEventListener('paste', handlePaste);
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
});

function handleFileSelection(file) {
  form.evidence = file;
  
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = null;
  }
  
  if (file && file.type.startsWith('image/')) {
    previewUrl.value = URL.createObjectURL(file);
  }
}

function clearFile() {
  form.evidence = null;
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = null;
  }
  const fileInput = document.getElementById('evidence');
  if (fileInput) fileInput.value = '';
}

function onFileChange(e) {
  handleFileSelection(e.target.files?.[0] ?? null);
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

async function submit() {
  loading.value = true;
  error.value   = '';
  try {
    const { data } = await api.post('/gangguan', {
      cubicle:    form.cubicle === 'Lainnya' ? form.custom_cubicle : form.cubicle,
      agent_name: form.agent_name,
      kategori:   form.kategori,
      problem:    form.problem,
      jenis_gangguan: form.is_massal ? 'Massal' : 'Personal',
    });
    if (form.evidence) {
      const fd = new FormData();
      fd.append('file', form.evidence);
      await api.post(`/gangguan/${data.id}/upload`, fd);
    }
    if (auth.hasRole('Agent')) {
      router.push({ name: 'agent-dashboard', query: { notice: 'report-submitted' } });
    } else {
      router.push({ name: 'gangguan-list', query: { notice: 'report-submitted' } });
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal menyimpan laporan.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <section style="max-width:640px;margin:0 auto;">
    <div class="page-title-wrap" style="margin-bottom: 24px;">
      <h2 class="page-title">Tambah Gangguan</h2>
      <p class="page-desc">Isi form berikut untuk melaporkan gangguan.</p>
    </div>

    <div class="card">
      <form class="grid" style="gap:20px;" @submit.prevent="submit">

        <!-- Opsi Gangguan Massal Khusus TS/Admin -->
        <div v-if="auth.hasRole('TS') || auth.hasRole('Admin')" style="display:flex; align-items:center; gap:8px; padding:12px 16px; background:var(--warn-lt); border:1px solid var(--warn); border-radius:8px;">
          <input type="checkbox" id="is_massal" v-model="form.is_massal" style="width:18px; height:18px; margin:0; cursor:pointer;" />
          <label for="is_massal" style="margin:0; text-transform:none; font-size:0.9rem; color:var(--warn); cursor:pointer; font-weight:700;">
            Input sebagai Gangguan Massal (Semua Agent/Area)
          </label>
        </div>

        <div class="grid-cols-1" style="gap: 20px;">
          <div>
            <label for="cubicle">Nomor Cubicle <span style="color:var(--danger);">*</span></label>
            <select id="cubicle" v-model="form.cubicle" required :disabled="form.is_massal">
              <option value="" disabled>Pilih Cubicle</option>
              <option v-for="c in cubicles" :key="c.id" :value="c.nama">{{ c.nama }}</option>
              <option value="Lainnya">Lainnya</option>
            </select>
          </div>
          <div v-if="form.cubicle === 'Lainnya'">
            <label for="custom_cubicle">Nama Cubicle Custom <span style="color:var(--danger);">*</span></label>
            <input id="custom_cubicle" type="text" v-model="form.custom_cubicle" placeholder="Masukkan nama cubicle..." required :disabled="form.is_massal" />
          </div>
        </div>

        <div>
          <label for="agent_name">Nama Agent <span style="color:var(--danger);">*</span></label>
          <input v-if="auth.hasRole('Agent')" id="agent_name" v-model="form.agent_name" placeholder="Nama lengkap" required disabled>
          <select v-else id="agent_name" v-model="form.agent_name" required :disabled="form.is_massal">
            <option value="" disabled>Pilih Agent</option>
            <option v-if="form.is_massal" value="ALL AGENT">ALL AGENT</option>
            <option v-for="agent in agents" :key="agent.id" :value="agent.name">{{ agent.name }}</option>
          </select>
        </div>

        <div>
          <label for="kategori">Kategori Gangguan <span style="color:var(--danger);">*</span></label>
          <select id="kategori" v-model="form.kategori" required>
            <option value="" disabled>Pilih Kategori Gangguan</option>
            <option value="SYSCCA">SYSCCA</option>
            <option value="SYSCCAE">SYSCCAE</option>
            <option value="ICRM+">ICRM+</option>
            <option value="HARDWARE">HARDWARE</option>
            <option value="BOTIKA">BOTIKA</option>
            <option value="ICONNPAY">ICONNPAY</option>
            <option value="SIP">SIP</option>
            <option value="INTERNET">INTERNET</option>
            <option value="LOCAL NETWORK">LOCAL NETWORK</option>
            <option value="MICROSIP">MICROSIP</option>
          </select>
        </div>

        <div>
          <label for="problem">Deskripsi Masalah <span style="color:var(--danger);">*</span></label>
          <textarea id="problem" v-model="form.problem" rows="4" placeholder="Jelaskan kendala yang dialami secara singkat dan jelas..." required></textarea>
        </div>

        <div style="margin-top: 10px;">
          <label style="display:block;margin-bottom:8px;font-size:.85rem;font-weight:600;color:#334155;">Foto / Bukti (opsional)</label>
          <div style="border: 2px dashed #cbd5e1; padding: 24px 16px; border-radius: 8px; background: #f8fafc; text-align: center; transition: all 0.2s;" :style="form.evidence ? 'border-color: var(--primary); background: #eff6ff;' : ''">
            <div v-if="!form.evidence">
              <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#94a3b8" viewBox="0 0 24 24" style="margin-bottom:12px; display:inline-block;"><path d="M19 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
              <p style="margin:0 0 12px; color: #475569; font-size: 0.9rem;">Pilih file atau <b>tekan Ctrl+V</b> (Paste) untuk <i>screenshot</i>.</p>
              <input id="evidence" type="file" accept="image/*,application/pdf" @change="onFileChange" style="font-family: inherit; margin: 0 auto; max-width: 100%;">
            </div>
            <div v-else style="display:flex; flex-direction:column; align-items:center;">
              <p style="margin:0 0 12px; font-weight: 700; color: var(--primary);">File terpilih: {{ form.evidence.name }}</p>
              <img v-if="previewUrl" :src="previewUrl" style="max-height: 200px; border-radius: 8px; border: 1px solid #cbd5e1; margin-bottom: 12px; object-fit: contain; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
              <div v-else style="width: 48px; height: 48px; background: #cbd5e1; border-radius: 8px; margin-bottom: 12px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.2rem;">📄</div>
              <button type="button" @click="clearFile" style="background: white; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 12px; font-size: 0.8rem; font-weight:600; cursor: pointer; color: #475569; transition: background 0.15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">Ganti File</button>
            </div>
          </div>
          <p style="font-size:0.76rem;color:var(--muted);margin-top:8px;">Format: JPG, PNG, PDF. Maks 2 MB.</p>
        </div>

        <div v-if="error" class="alert alert-danger">{{ error }}</div>

        <div style="display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn-ghost" @click="$router.back()">Batal</button>
          <button type="submit" class="btn-primary" :disabled="loading">
            {{ loading ? 'Menyimpan...' : 'Kirim Laporan' }}
          </button>
        </div>

      </form>
    </div>
  </section>
</template>
