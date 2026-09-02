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

// ─── Custom Searchable Select for Cubicle ───
const cubicleSearch = ref('');
const isCubicleOpen = ref(false);
const cubicleSelectRef = ref(null);

const filteredCubicles = computed(() => {
  const q = cubicleSearch.value.toLowerCase().trim();
  let list = cubicles.value;
  if (q) {
    list = list.filter(c => 
      c.nama?.toLowerCase().includes(q) ||
      c.ext?.toLowerCase().includes(q) ||
      c.ip?.toLowerCase().includes(q)
    );
  }
  return list;
});

function selectCubicle(c) {
  if (c === 'Lainnya') {
    form.cubicle = 'Lainnya';
    cubicleSearch.value = 'Lainnya';
  } else {
    form.cubicle = c.nama;
    cubicleSearch.value = c.nama + (c.ext ? ` (Ext: ${c.ext})` : '');
  }
  isCubicleOpen.value = false;
}

function handleCubicleFocus() {
  if (!form.is_massal) {
    isCubicleOpen.value = true;
  }
}

// ─── Custom Searchable Select for Agent ───
const agentSearch    = ref(auth.user?.name || '');
const isAgentOpen    = ref(false);
const agentSelectRef = ref(null);

const filteredAgents = computed(() => {
  if (form.is_massal) return [{ id: 0, name: 'ALL AGENT' }];
  const q = agentSearch.value.toLowerCase().trim();
  if (!q) return agents.value;
  return agents.value.filter(a =>
    a.name?.toLowerCase().includes(q) ||
    a.email?.toLowerCase().includes(q) ||
    a.jabatan?.toLowerCase().includes(q)
  );
});

function selectAgent(a) {
  if (typeof a === 'string') {
    form.agent_name = a;
    agentSearch.value = a;
  } else {
    form.agent_name = a.name;
    agentSearch.value = a.name + (a.jabatan ? ` (${a.jabatan})` : '');
  }
  isAgentOpen.value = false;
}

function handleAgentFocus() {
  if (!form.is_massal) {
    isAgentOpen.value = true;
  }
}

// ─── Custom Select for Kategori ───
const kategoriList = [
  'SYSCCA',
  'SYSCCAE',
  'ICRM+',
  'HARDWARE',
  'BOTIKA',
  'ICONNPAY',
  'SIP',
  'INTERNET',
  'LOCAL NETWORK',
  'MICROSIP'
];
const isKategoriOpen    = ref(false);
const kategoriSelectRef = ref(null);

function selectKategori(kat) {
  form.kategori = kat;
  isKategoriOpen.value = false;
}

function handleClickOutside(e) {
  if (cubicleSelectRef.value && !cubicleSelectRef.value.contains(e.target)) {
    isCubicleOpen.value = false;
  }
  if (agentSelectRef.value && !agentSelectRef.value.contains(e.target)) {
    isAgentOpen.value = false;
  }
  if (kategoriSelectRef.value && !kategoriSelectRef.value.contains(e.target)) {
    isKategoriOpen.value = false;
  }
}
// ─────────────────────────────────────────────

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
    cubicleSearch.value = 'MASSAL';
    agentSearch.value = 'ALL AGENT';
  } else {
    form.cubicle = '';
    form.custom_cubicle = '';
    form.agent_name = auth.hasRole('Agent') ? (auth.user?.name || '') : '';
    cubicleSearch.value = '';
    agentSearch.value = auth.hasRole('Agent') ? (auth.user?.name || '') : '';
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
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  window.removeEventListener('paste', handlePaste);
  document.removeEventListener('click', handleClickOutside);
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
  <section class="create-page-wrap">
    <!-- Page Header -->
    <div class="create-header-card">
      <div class="create-header-inner">
        <div class="create-icon-badge">📝</div>
        <div>
          <h2 class="create-title">Tambah Gangguan</h2>
          <p class="create-subtitle">Isi form berikut untuk melaporkan kendala operasional secara cepat.</p>
        </div>
      </div>
    </div>

    <!-- Main Card -->
    <div class="card form-main-card">
      <form style="display: flex; flex-direction: column; gap: 24px;" @submit.prevent="submit">

        <!-- Opsi Gangguan Massal (Modern Mobile Switch Card) -->
        <div 
          v-if="auth.hasRole('TS') || auth.hasRole('Admin')" 
          class="massal-card" 
          :class="{ 'massal-active': form.is_massal }" 
          @click="form.is_massal = !form.is_massal"
        >
          <div class="massal-card-left">
            <span class="massal-icon">⚠️</span>
            <div class="massal-text-wrap">
              <div class="massal-title">Input sebagai Gangguan Massal (Semua Agent)</div>
              <div class="massal-desc">Aktifkan jika gangguan berdampak menyeluruh pada semua cubicle & agent.</div>
            </div>
          </div>
          
          <div class="toggle-switch-wrap">
            <div class="toggle-switch" :class="{ on: form.is_massal }">
              <div class="toggle-switch-handle"></div>
            </div>
          </div>
        </div>

        <!-- 2-Column Grid Layout -->
        <div class="form-grid">
          
          <!-- Field 1: Searchable Cubicle Select -->
          <div ref="cubicleSelectRef" style="position: relative;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.88rem; color: #334155;">
              Nomor Cubicle <span style="color: var(--danger, #ef4444);">*</span>
            </label>

            <!-- Search Input Box -->
            <div style="position: relative;">
              <input 
                type="text" 
                v-model="cubicleSearch"
                @focus="handleCubicleFocus"
                @input="isCubicleOpen = true"
                placeholder="Cari / Pilih Cubicle (contoh: A-01)..." 
                required
                :disabled="form.is_massal"
                class="form-input"
                style="width: 100%; padding-right: 36px; box-sizing: border-box;"
              />
              <span 
                @click="!form.is_massal && (isCubicleOpen = !isCubicleOpen)"
                style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer; font-size: 0.8rem;"
              >
                ▼
              </span>
            </div>

            <!-- Custom Dropdown List -->
            <div 
              v-if="isCubicleOpen && !form.is_massal" 
              class="dropdown-menu"
            >
              <div 
                v-if="filteredCubicles.length === 0" 
                style="padding: 10px 14px; color: #94a3b8; font-size: 0.85rem; text-align: center;"
              >
                Tidak ada cubicle yang sesuai.
              </div>
              
              <div 
                v-for="c in filteredCubicles" 
                :key="c.id" 
                class="dropdown-item"
                :class="{ selected: form.cubicle === c.nama }"
                @click="selectCubicle(c)"
              >
                <span style="font-weight: 600; color: #1e293b;">{{ c.nama }}</span>
                <span v-if="c.ext || c.ip" style="font-size: 0.78rem; color: #64748b; margin-left: 8px;">
                  <template v-if="c.ext">Ext: {{ c.ext }}</template>
                  <template v-if="c.ext && c.ip"> · </template>
                  <template v-if="c.ip">IP: {{ c.ip }}</template>
                </span>
              </div>

              <!-- Option Lainnya -->
              <div 
                class="dropdown-item"
                :class="{ selected: form.cubicle === 'Lainnya' }"
                @click="selectCubicle('Lainnya')"
                style="border-top: 1px solid #f1f5f9; font-weight: 700; color: #2563eb;"
              >
                + Lainnya (Ketik Manual)
              </div>
            </div>
          </div>

          <!-- Field 2: Searchable Nama Agent -->
          <div ref="agentSelectRef" style="position: relative;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.88rem; color: #334155;">
              Nama Agent <span style="color: var(--danger, #ef4444);">*</span>
            </label>

            <!-- Disabled input for Agent role -->
            <input 
              v-if="auth.hasRole('Agent')" 
              id="agent_name" 
              v-model="form.agent_name" 
              placeholder="Nama lengkap" 
              required 
              disabled 
              class="form-input disabled-input"
            />

            <!-- Searchable Input for TS / Admin -->
            <div v-else style="position: relative;">
              <input 
                type="text" 
                v-model="agentSearch"
                @focus="handleAgentFocus"
                @input="isAgentOpen = true"
                placeholder="Cari / Pilih Nama Agent..." 
                required
                :disabled="form.is_massal"
                class="form-input"
                style="width: 100%; padding-right: 36px; box-sizing: border-box;"
              />
              <span 
                @click="!form.is_massal && (isAgentOpen = !isAgentOpen)"
                style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer; font-size: 0.8rem;"
              >
                ▼
              </span>

              <!-- Custom Dropdown List for Agents -->
              <div 
                v-if="isAgentOpen && !form.is_massal" 
                class="dropdown-menu elegant-scroll"
              >
                <div 
                  v-if="filteredAgents.length === 0" 
                  style="padding: 10px 14px; color: #94a3b8; font-size: 0.85rem; text-align: center;"
                >
                  Tidak ada agent yang sesuai.
                </div>
                
                <div 
                  v-for="a in filteredAgents" 
                  :key="a.id" 
                  class="dropdown-item"
                  :class="{ selected: form.agent_name === a.name }"
                  @click="selectAgent(a)"
                >
                  <span style="font-weight: 600; color: #1e293b;">{{ a.name }}</span>
                  <span v-if="a.jabatan || a.email" style="font-size: 0.78rem; color: #64748b; margin-left: 8px;">
                    <template v-if="a.jabatan">{{ a.jabatan }}</template>
                    <template v-if="a.jabatan && a.email"> · </template>
                    <template v-if="a.email">{{ a.email.split('@')[0] }}</template>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Field 3: Custom Cubicle Input (Conditional) -->
          <div v-if="form.cubicle === 'Lainnya'">
            <label for="custom_cubicle" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.88rem; color: #334155;">
              Nama Cubicle Custom <span style="color: var(--danger, #ef4444);">*</span>
            </label>
            <input 
              id="custom_cubicle" 
              type="text" 
              v-model="form.custom_cubicle" 
              placeholder="Masukkan nama cubicle..." 
              required 
              :disabled="form.is_massal" 
              class="form-input"
            />
          </div>

          <!-- Field 4: Custom Kategori Gangguan Select -->
          <div ref="kategoriSelectRef" style="position: relative;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.88rem; color: #334155;">
              Kategori Gangguan <span style="color: var(--danger, #ef4444);">*</span>
            </label>

            <div style="position: relative;">
              <input 
                type="text" 
                readonly
                :value="form.kategori"
                @click="isKategoriOpen = !isKategoriOpen"
                placeholder="Pilih Kategori Gangguan..." 
                required
                class="form-input"
                style="width: 100%; padding-right: 36px; box-sizing: border-box; cursor: pointer; background: #ffffff;"
              />
              <span 
                @click="isKategoriOpen = !isKategoriOpen"
                style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer; font-size: 0.8rem; pointer-events: none;"
              >
                ▼
              </span>

              <!-- Custom Dropdown Menu for Kategori -->
              <div 
                v-if="isKategoriOpen" 
                class="dropdown-menu elegant-scroll"
              >
                <div 
                  v-for="kat in kategoriList" 
                  :key="kat" 
                  class="dropdown-item"
                  :class="{ selected: form.kategori === kat }"
                  @click="selectKategori(kat)"
                >
                  <span style="font-weight: 600; color: #1e293b;">{{ kat }}</span>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Field 5: Deskripsi Masalah (Full Width) -->
        <div>
          <label for="problem" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.88rem; color: #334155;">
            Deskripsi Masalah <span style="color: var(--danger, #ef4444);">*</span>
          </label>
          <textarea 
            id="problem" 
            v-model="form.problem" 
            rows="4" 
            placeholder="Jelaskan kendala yang dialami secara singkat dan jelas..." 
            required 
            class="form-input"
            style="resize: vertical; font-family: inherit;"
          ></textarea>
        </div>

        <!-- Field 6: Upload Foto / Bukti (Full Width) -->
        <div>
          <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.88rem; color: #334155;">
            Foto / Bukti (opsional)
          </label>
          
          <div 
            class="upload-dropzone" 
            :style="form.evidence ? 'border-color: var(--primary, #2563eb); background: #eff6ff;' : ''"
          >
            <div v-if="!form.evidence">
              <div style="width: 44px; height: 44px; border-radius: 12px; background: #e2e8f0; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px; color: #64748b;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
              </div>
              <p style="margin: 0 0 8px; color: #334155; font-size: 0.9rem; font-weight: 500;">
                Pilih file atau <b>tekan Ctrl+V</b> (Paste) untuk <i>screenshot</i>.
              </p>
              <input id="evidence" type="file" accept="image/*,application/pdf" @change="onFileChange" style="font-family: inherit; margin: 8px auto 0; max-width: 100%;">
            </div>

            <div v-else style="display: flex; flex-direction: column; align-items: center;">
              <p style="margin: 0 0 12px; font-weight: 700; color: var(--primary, #2563eb); font-size: 0.9rem;">
                📎 File terpilih: {{ form.evidence.name }}
              </p>
              <img v-if="previewUrl" :src="previewUrl" style="max-height: 180px; border-radius: 8px; border: 1px solid #cbd5e1; margin-bottom: 12px; object-fit: contain; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);" />
              <div v-else style="width: 48px; height: 48px; background: #cbd5e1; border-radius: 8px; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.2rem;">📄</div>
              <button 
                type="button" 
                @click="clearFile" 
                style="background: white; border: 1px solid #cbd5e1; border-radius: 6px; padding: 5px 14px; font-size: 0.82rem; font-weight: 600; cursor: pointer; color: #475569; transition: all 0.15s;" 
                onmouseover="this.style.background='#f1f5f9'" 
                onmouseout="this.style.background='white'"
              >
                Ganti File
              </button>
            </div>
          </div>
          <p style="font-size: 0.78rem; color: var(--muted); margin-top: 6px;">Format: JPG, PNG, PDF. Maksimal 2 MB.</p>
        </div>

        <!-- Error Message -->
        <div v-if="error" class="alert alert-danger" style="padding: 12px 16px; border-radius: 8px; font-size: 0.9rem;">{{ error }}</div>

        <!-- Form Buttons -->
        <div class="form-buttons-wrap">
          <button type="button" class="btn-ghost btn-cancel" @click="$router.back()">
            Batal
          </button>
          <button 
            type="submit" 
            class="btn-primary btn-submit" 
            :disabled="loading"
          >
            {{ loading ? 'Menyimpan...' : 'Kirim Laporan' }}
          </button>
        </div>

      </form>
    </div>
  </section>
</template>

<style scoped>
.create-page-wrap {
  max-width: 760px;
  margin: 0 auto;
  padding: 10px 0 30px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.create-header-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.create-header-inner {
  display: flex;
  align-items: center;
  gap: 12px;
}

.create-icon-badge {
  display: none;
}

.create-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.create-subtitle {
  color: var(--muted, #64748b);
  font-size: 0.9rem;
  margin: 3px 0 0;
}

.form-main-card {
  padding: 28px;
  border-radius: 16px;
  box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
  border: 1px solid #e2e8f0;
  background: #ffffff;
}

@media (max-width: 768px) {
  .create-header-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 14px 16px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.03);
  }
  .create-icon-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #eff6ff;
    font-size: 1.15rem;
    flex-shrink: 0;
  }
  .create-title {
    font-size: 1.15rem;
  }
  .create-subtitle {
    font-size: 0.76rem;
  }
  .form-main-card {
    padding: 18px 16px !important;
    border-radius: 14px !important;
  }
  .form-grid {
    grid-template-columns: 1fr;
    gap: 14px;
  }
  .form-buttons-wrap {
    flex-direction: column-reverse;
  }
  .btn-cancel, .btn-submit {
    width: 100%;
    padding: 12px;
    font-size: 0.92rem;
  }
}

/* ─── Massal Toggle Switch Card ─── */
.massal-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 18px;
  background: #fffbeb;
  border: 1.5px solid #fde68a;
  border-radius: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
}

.massal-card:hover {
  border-color: #f59e0b;
  background: #fef3c7;
}

.massal-card.massal-active {
  background: #fff7ed;
  border-color: #f97316;
  box-shadow: 0 4px 14px rgba(249, 115, 22, 0.12);
}

.massal-card-left {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.massal-icon {
  font-size: 1.25rem;
  line-height: 1.2;
  flex-shrink: 0;
}

.massal-text-wrap {
  display: flex;
  flex-direction: column;
}

.massal-title {
  font-weight: 700;
  font-size: 0.95rem;
  color: #92400e;
  line-height: 1.3;
}

.massal-desc {
  font-size: 0.8rem;
  color: #b45309;
  margin-top: 3px;
  line-height: 1.4;
}

/* ─── iOS / Android Toggle Switch ─── */
.toggle-switch-wrap {
  flex-shrink: 0;
}

.toggle-switch {
  width: 48px;
  height: 26px;
  background: #cbd5e1;
  border-radius: 999px;
  padding: 2px;
  position: relative;
  transition: background-color 0.25s ease;
  box-sizing: border-box;
}

.toggle-switch.on {
  background: #f59e0b;
}

.toggle-switch-handle {
  width: 22px;
  height: 22px;
  background: #ffffff;
  border-radius: 50%;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
  transition: transform 0.25s ease;
  transform: translateX(0);
}

.toggle-switch.on .toggle-switch-handle {
  transform: translateX(22px);
}

@media (max-width: 640px) {
  .massal-card {
    padding: 12px 14px;
    gap: 10px;
  }
  .massal-title {
    font-size: 0.88rem;
  }
  .massal-desc {
    font-size: 0.75rem;
  }
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.9rem;
  outline: none;
  background: #ffffff;
  transition: border-color 0.2s, box-shadow 0.2s;
  box-sizing: border-box;
}

.form-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

.disabled-input {
  background: #f8fafc;
  color: #64748b;
  cursor: not-allowed;
}

/* Dropdown menu for searchable cubicle */
.dropdown-menu {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  margin-top: 4px;
  max-height: 220px;
  overflow-y: auto;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
  z-index: 999;
}

.dropdown-item {
  padding: 10px 14px;
  cursor: pointer;
  font-size: 0.88rem;
  transition: background 0.15s;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.dropdown-item:hover {
  background: #eff6ff;
}

.dropdown-item.selected {
  background: #dbeafe;
  font-weight: 700;
}

.upload-dropzone {
  border: 2px dashed #cbd5e1;
  padding: 24px 16px;
  border-radius: 12px;
  background: #f8fafc;
  text-align: center;
  transition: all 0.2s ease;
}
</style>
