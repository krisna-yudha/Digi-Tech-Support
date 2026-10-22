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
  fetchActiveIncidents();
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

// ─── Active Incidents & Me-Too Mitigation (Fase 1) ───
const activeIncidents = ref([]);
const checkingIncidents = ref(false);
const showJoinModal = ref(false);
const selectedIncidentToJoin = ref(null);
const joinCubicle = ref('');
const joinNotes = ref('');
const joiningIncidentId = ref(null);

async function fetchActiveIncidents(kat = '') {
  checkingIncidents.value = true;
  try {
    const { data } = await api.get('/gangguan/active-incidents', {
      params: { kategori: kat || undefined }
    });
    activeIncidents.value = Array.isArray(data) ? data : [];
  } catch (err) {
    console.error('Failed to fetch active incidents:', err);
  } finally {
    checkingIncidents.value = false;
  }
}

watch(() => form.kategori, (newKat) => {
  fetchActiveIncidents(newKat);
});

function openJoinModal(inc) {
  selectedIncidentToJoin.value = inc;
  joinCubicle.value = form.cubicle === 'Lainnya' ? form.custom_cubicle : (form.cubicle || '');
  joinNotes.value = '';
  showJoinModal.value = true;
}

async function submitJoinIncident() {
  if (!selectedIncidentToJoin.value) return;
  joiningIncidentId.value = selectedIncidentToJoin.value.id;
  try {
    await api.post(`/gangguan/${selectedIncidentToJoin.value.id}/join-incident`, {
      cubicle: joinCubicle.value || form.cubicle || 'Lainnya',
      notes: joinNotes.value
    });
    showJoinModal.value = false;
    if (auth.hasRole('Agent')) {
      router.push({ name: 'agent-dashboard', query: { notice: 'joined-mass-incident' } });
    } else {
      router.push({ name: 'gangguan-list', query: { notice: 'joined-mass-incident' } });
    }
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal bergabung ke gangguan massal.');
  } finally {
    joiningIncidentId.value = null;
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

    <!-- Active Mass Outage / Spike Incident Banner (Preventive UX) -->
    <div v-if="activeIncidents.length > 0" class="incident-alert-container">
      <div class="incident-alert-header">
        <div class="incident-alert-title-wrap">
          <span class="incident-pulse-dot"></span>
          <span class="incident-alert-title">🚨 Terdeteksi Gangguan Aktif yang Sedang Ditangani TS</span>
        </div>
        <span class="incident-badge-count">{{ activeIncidents.length }} Gangguan Terbuka</span>
      </div>
      <p class="incident-alert-desc">
        Sebelum membuat laporan baru, periksa daftar gangguan aktif di bawah ini. Jika kendala Anda sama, Anda tidak perlu mengisi form ini — cukup klik <b>"Saya Juga Terdampak"</b> untuk langsung tercatat ke tiket penanganan TS.
      </p>

      <div class="incident-items-grid">
        <div v-for="inc in activeIncidents" :key="inc.id" class="incident-item-card">
          <div class="incident-item-main">
            <div class="incident-item-meta">
              <span class="inc-kat-badge">{{ inc.kategori || 'MASSAL' }}</span>
              <span class="inc-ticket-badge">{{ inc.ticket_number }}</span>
              <span class="inc-status-badge" :class="inc.status">{{ inc.status === 'in_progress' ? 'SEDANG DITANGANI' : 'OPEN' }}</span>
            </div>
            <h4 class="incident-item-judul">{{ inc.judul }}</h4>
            <div class="incident-item-footer">
              <span class="inc-impacted-count">👥 <b>{{ inc.jumlah_agent_terdampak }}</b> Agent Terdampak</span>
              <span v-if="inc.assignee_name" class="inc-assignee">🛠️ TS: {{ inc.assignee_name }}</span>
            </div>
          </div>

          <div class="incident-item-action">
            <div v-if="inc.has_joined" class="inc-already-joined-badge">
              ✓ Anda Sudah Terdaftar
            </div>
            <button 
              v-else 
              type="button" 
              class="btn-me-too" 
              @click="openJoinModal(inc)"
            >
              🙋‍♂️ Saya Juga Terdampak (+1)
            </button>
          </div>
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

    <!-- Me-Too Join Modal (Fase 1) -->
    <div v-if="showJoinModal && selectedIncidentToJoin" class="modal-overlay" @click.self="showJoinModal = false">
      <div class="modal-box">
        <div class="modal-header">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.5rem;">🙋‍♂️</span>
            <div>
              <h3 style="margin: 0; font-size: 1.1rem; color: #0f172a;">Konfirmasi Saya Terdampak</h3>
              <p style="margin: 2px 0 0; font-size: 0.82rem; color: #64748b;">
                Tautkan diri Anda ke tiket <b>{{ selectedIncidentToJoin.ticket_number }}</b>
              </p>
            </div>
          </div>
          <button type="button" class="btn-close-modal" @click="showJoinModal = false">✕</button>
        </div>

        <div class="modal-body">
          <div class="incident-summary-box">
            <div style="font-weight: 700; color: #1e293b; font-size: 0.95rem; margin-bottom: 4px;">
              {{ selectedIncidentToJoin.judul }}
            </div>
            <div style="display: flex; gap: 8px; font-size: 0.8rem; color: #475569; flex-wrap: wrap;">
              <span>Kategori: <b>{{ selectedIncidentToJoin.kategori }}</b></span>
              <span>•</span>
              <span>Saat ini: <b>{{ selectedIncidentToJoin.jumlah_agent_terdampak }} agent</b> terdampak</span>
              <span v-if="selectedIncidentToJoin.assignee_name">•</span>
              <span v-if="selectedIncidentToJoin.assignee_name">TS: <b>{{ selectedIncidentToJoin.assignee_name }}</b></span>
            </div>
          </div>

          <div style="margin-bottom: 16px;">
            <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.85rem; color: #334155;">
              Nomor Cubicle Anda (Opsional)
            </label>
            <input 
              type="text" 
              v-model="joinCubicle" 
              placeholder="Contoh: A-01 atau B-04" 
              class="form-input"
            />
          </div>

          <div style="margin-bottom: 8px;">
            <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.85rem; color: #334155;">
              Catatan / Gejala Tambahan (Opsional)
            </label>
            <textarea 
              v-model="joinNotes" 
              rows="2" 
              placeholder="Contoh: Aplikasi blank putih saat membuka menu tiket..." 
              class="form-input" 
              style="resize: vertical;"
            ></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-ghost" @click="showJoinModal = false">Batal</button>
          <button 
            type="button" 
            class="btn-primary" 
            style="padding: 9px 18px; font-weight: 700;"
            :disabled="joiningIncidentId !== null" 
            @click="submitJoinIncident"
          >
            {{ joiningIncidentId !== null ? 'Menyimpan...' : 'Konfirmasi & Catat Terdampak' }}
          </button>
        </div>
      </div>
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

/* ─── Active Incident Banner & Me-Too Styles (Fase 1) ─── */
.incident-alert-container {
  background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
  border: 1.5px solid #f59e0b;
  border-radius: 16px;
  padding: 18px 20px;
  box-shadow: 0 4px 20px -2px rgba(245, 158, 11, 0.15);
  display: flex;
  flex-direction: column;
  gap: 12px;
  animation: fadeIn 0.3s ease-out;
}

.incident-alert-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.incident-alert-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.incident-pulse-dot {
  width: 10px;
  height: 10px;
  background-color: #ef4444;
  border-radius: 50%;
  display: inline-block;
  box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
  animation: pulse-red 1.6s infinite;
}

@keyframes pulse-red {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

.incident-alert-title {
  font-weight: 800;
  font-size: 1rem;
  color: #92400e;
}

.incident-badge-count {
  background: #fde68a;
  color: #78350f;
  font-weight: 700;
  font-size: 0.78rem;
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid #fcd34d;
}

.incident-alert-desc {
  font-size: 0.86rem;
  color: #78350f;
  line-height: 1.5;
  margin: 0;
}

.incident-items-grid {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 4px;
}

.incident-item-card {
  background: #ffffff;
  border: 1px solid #fde68a;
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}

.incident-item-main {
  flex: 1;
  min-width: 0;
}

.incident-item-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
  flex-wrap: wrap;
}

.inc-kat-badge {
  background: #dbeafe;
  color: #1e40af;
  font-size: 0.75rem;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 6px;
}

.inc-ticket-badge {
  background: #f1f5f9;
  color: #475569;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
}

.inc-status-badge {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
}
.inc-status-badge.in_progress { background: #fef3c7; color: #92400e; }
.inc-status-badge.open { background: #fee2e2; color: #991b1b; }

.incident-item-judul {
  margin: 0 0 6px;
  font-size: 0.95rem;
  font-weight: 700;
  color: #0f172a;
}

.incident-item-footer {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 0.8rem;
  color: #64748b;
  flex-wrap: wrap;
}

.btn-me-too {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: #ffffff;
  border: none;
  border-radius: 10px;
  padding: 9px 16px;
  font-weight: 700;
  font-size: 0.85rem;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s ease;
  box-shadow: 0 2px 8px rgba(217, 119, 6, 0.3);
}

.btn-me-too:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(217, 119, 6, 0.4);
}

.inc-already-joined-badge {
  background: #dcfce7;
  color: #166534;
  font-weight: 700;
  font-size: 0.82rem;
  padding: 6px 12px;
  border-radius: 8px;
  white-space: nowrap;
  border: 1px solid #bbf7d0;
}

/* Modal Overlay & Box */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 16px;
}

.modal-box {
  background: #ffffff;
  border-radius: 16px;
  width: 100%;
  max-width: 480px;
  box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.25);
  overflow: hidden;
  animation: modalScale 0.25s ease-out;
}

@keyframes modalScale {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}

.modal-header {
  padding: 16px 20px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
}

.btn-close-modal {
  background: transparent;
  border: none;
  font-size: 1.2rem;
  color: #94a3b8;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 6px;
}
.btn-close-modal:hover { background: #e2e8f0; color: #334155; }

.modal-body {
  padding: 20px;
}

.incident-summary-box {
  background: #f1f5f9;
  border-radius: 10px;
  padding: 12px 14px;
  margin-bottom: 16px;
  border-left: 4px solid #f59e0b;
}

.modal-footer {
  padding: 14px 20px;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
