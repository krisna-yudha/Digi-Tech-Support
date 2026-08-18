<script setup>
import { reactive, ref, onMounted, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';

const router  = useRouter();
const auth    = useAuthStore();
const loading = ref(false);
const error   = ref('');
const agents  = ref([]);

const blocks = [
  { id: 'A', count: 20 },
  { id: 'B', count: 16 },
  { id: 'C', count: 27 },
  { id: 'D', count: 26 },
  { id: 'E', count: 36 },
  { id: 'F', count: 28 },
  { id: 'Lainnya', count: 0 },
];

const form = reactive({
  block:      '',
  cubicle:    '',
  custom_cubicle: '',
  agent_name: auth.user?.name || '',
  problem:    '',
  evidence:   null,
});

const availableCubicles = computed(() => {
  const block = blocks.find(b => b.id === form.block);
  if (!block || block.id === 'Lainnya') return [];
  return Array.from({ length: block.count }, (_, i) => `${block.id}-${String(i + 1).padStart(2, '0')}`);
});

watch(() => form.block, () => {
  form.cubicle = '';
  form.custom_cubicle = '';
});

onMounted(async () => {
  if (!auth.hasRole('Agent')) {
    form.agent_name = ''; // force TS/Admin to select an agent
    try {
      const { data } = await api.get('/users', { params: { role: 'Agent' } });
      agents.value = data;
    } catch (err) {
      console.error('Failed to load agents:', err);
    }
  }
});

function onFileChange(e) {
  form.evidence = e.target.files?.[0] ?? null;
}

async function submit() {
  loading.value = true;
  error.value   = '';
  try {
    const { data } = await api.post('/gangguan', {
      cubicle:    form.block === 'Lainnya' ? form.custom_cubicle : form.cubicle,
      agent_name: form.agent_name,
      problem:    form.problem,
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

        <div class="grid-cols-2">
          <div>
            <label for="block">Blok Cubicle <span style="color:var(--danger);">*</span></label>
            <select id="block" v-model="form.block" required>
              <option value="" disabled>Pilih Blok</option>
              <option v-for="b in blocks" :key="b.id" :value="b.id">
                {{ b.id === 'Lainnya' ? 'Lainnya' : `Block ${b.id}` }}
              </option>
            </select>
          </div>
          <div>
            <label for="cubicle">Nomor Cubicle <span style="color:var(--danger);">*</span></label>
            <select v-if="form.block !== 'Lainnya'" id="cubicle" v-model="form.cubicle" required :disabled="!form.block">
              <option value="" disabled>Pilih Cubicle</option>
              <option v-for="c in availableCubicles" :key="c" :value="c">{{ c }}</option>
            </select>
            <input v-else type="text" v-model="form.custom_cubicle" placeholder="Masukkan nama cubicle..." required />
          </div>
        </div>

        <div>
          <label for="agent_name">Nama Agent <span style="color:var(--danger);">*</span></label>
          <input v-if="auth.hasRole('Agent')" id="agent_name" v-model="form.agent_name" placeholder="Nama lengkap" required disabled>
          <select v-else id="agent_name" v-model="form.agent_name" required>
            <option value="" disabled>Pilih Agent</option>
            <option v-for="agent in agents" :key="agent.id" :value="agent.name">{{ agent.name }}</option>
          </select>
        </div>

        <div>
          <label for="problem">Deskripsi Masalah <span style="color:var(--danger);">*</span></label>
          <textarea id="problem" v-model="form.problem" rows="4" placeholder="Jelaskan kendala yang dialami secara singkat dan jelas..." required></textarea>
        </div>

        <div>
          <label for="evidence">Foto / Bukti (opsional)</label>
          <input id="evidence" type="file" accept="image/*,application/pdf" @change="onFileChange" style="padding:7px 10px;">
          <p style="font-size:0.76rem;color:var(--muted);margin-top:4px;">Format: JPG, PNG, PDF. Maks 2 MB.</p>
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
