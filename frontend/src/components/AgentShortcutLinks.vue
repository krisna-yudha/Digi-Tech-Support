<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();

// Dropdown open/close state
const isOpen = ref(false);
const dropdownRef = ref(null);

// Modal Tambah / Edit state
const isModalOpen = ref(false);
const isEditing = ref(false);
const currentEditingId = ref(null);

// Form fields
const formName = ref('');
const formUrl = ref('');
const formCategory = ref('Eskalasi');
const formDescription = ref('');
const formError = ref('');

// Search filter inside dropdown
const searchQuery = ref('');

// Kategori yang didukung
const categories = [
  { value: 'Eskalasi', label: 'Eskalasi', icon: 'escalate', color: 'red' },
  { value: 'Form', label: 'Form', icon: 'form', color: 'blue' },
  { value: 'Portal', label: 'Portal', icon: 'portal', color: 'indigo' },
  { value: 'Spreadsheet', label: 'Spreadsheet', icon: 'spreadsheet', color: 'emerald' },
  { value: 'WhatsApp', label: 'WhatsApp', icon: 'chat', color: 'green' },
  { value: 'Lainnya', label: 'Lainnya', icon: 'link', color: 'slate' }
];

// Default starter shortcuts: kosong
const defaultShortcuts = [];

// Reactive list shortcuts
const shortcuts = ref([]);

// Storage key dinamis per user ID agent
const storageKey = computed(() => `agent_shortcuts_${auth.user?.id || 'default'}`);

function loadShortcuts() {
  try {
    const raw = localStorage.getItem(storageKey.value);
    if (raw) {
      const parsed = JSON.parse(raw);
      const cleaned = Array.isArray(parsed)
        ? parsed.filter(s => !['sc-1', 'sc-2', 'sc-3', 'sc-4', 'sc-5'].includes(s.id))
        : [];
      shortcuts.value = cleaned;
      if (cleaned.length !== (Array.isArray(parsed) ? parsed.length : 0)) {
        saveShortcuts();
      }
    } else {
      shortcuts.value = [];
    }
  } catch (err) {
    console.error('Gagal membaca shortcut dari localStorage:', err);
    shortcuts.value = [];
  }
}

function saveShortcuts() {
  try {
    localStorage.setItem(storageKey.value, JSON.stringify(shortcuts.value));
  } catch (err) {
    console.error('Gagal menyimpan shortcut ke localStorage:', err);
  }
}

// Reload jika user berganti
watch(() => auth.user?.id, () => {
  loadShortcuts();
});

onMounted(() => {
  loadShortcuts();
  document.addEventListener('click', handleOutsideClick);
  document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  document.removeEventListener('click', handleOutsideClick);
  document.removeEventListener('keydown', handleKeyDown);
});

function handleOutsideClick(event) {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    isOpen.value = false;
  }
}

function handleKeyDown(event) {
  if (event.key === 'Escape') {
    if (isModalOpen.value) {
      closeModal();
    } else if (isOpen.value) {
      isOpen.value = false;
    }
  }
}

function toggleDropdown() {
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    searchQuery.value = '';
  }
}

// Filtered shortcuts berdasarkan pencarian
const filteredShortcuts = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return shortcuts.value;
  return shortcuts.value.filter(sc =>
    sc.name.toLowerCase().includes(q) ||
    sc.category.toLowerCase().includes(q) ||
    (sc.description && sc.description.toLowerCase().includes(q)) ||
    sc.url.toLowerCase().includes(q)
  );
});

// Format URL agar selalu diawali https:// atau http://
function normalizeUrl(input) {
  let url = input.trim();
  if (!/^https?:\/\//i.test(url)) {
    url = 'https://' + url;
  }
  return url;
}

// Buka modal Tambah
function openAddModal() {
  isEditing.value = false;
  currentEditingId.value = null;
  formName.value = '';
  formUrl.value = '';
  formCategory.value = 'Eskalasi';
  formDescription.value = '';
  formError.value = '';
  isModalOpen.value = true;
}

// Buka modal Edit
function openEditModal(shortcut, event) {
  if (event) event.stopPropagation();
  isEditing.value = true;
  currentEditingId.value = shortcut.id;
  formName.value = shortcut.name;
  formUrl.value = shortcut.url;
  formCategory.value = shortcut.category || 'Eskalasi';
  formDescription.value = shortcut.description || '';
  formError.value = '';
  isModalOpen.value = true;
}

// Simpan Tambah / Edit
function saveShortcut() {
  formError.value = '';

  const name = formName.value.trim();
  const rawUrl = formUrl.value.trim();

  if (!name) {
    formError.value = 'Nama shortcut wajib diisi.';
    return;
  }

  if (!rawUrl) {
    formError.value = 'URL / tautan link wajib diisi.';
    return;
  }

  const finalUrl = normalizeUrl(rawUrl);

  // Validasi URL sederhana
  try {
    new URL(finalUrl);
  } catch (e) {
    formError.value = 'Format URL tidak valid. Contoh: https://forms.google.com';
    return;
  }

  if (isEditing.value && currentEditingId.value) {
    // Mode Update
    const idx = shortcuts.value.findIndex(s => s.id === currentEditingId.value);
    if (idx !== -1) {
      shortcuts.value[idx] = {
        ...shortcuts.value[idx],
        name,
        url: finalUrl,
        category: formCategory.value,
        description: formDescription.value.trim(),
        updatedAt: new Date().toISOString()
      };
    }
  } else {
    // Mode Tambah Baru
    const newShortcut = {
      id: `sc-${Date.now()}`,
      name,
      url: finalUrl,
      category: formCategory.value,
      description: formDescription.value.trim(),
      createdAt: new Date().toISOString()
    };
    shortcuts.value.unshift(newShortcut);
  }

  saveShortcuts();
  closeModal();
}

// Hapus shortcut
function deleteShortcut(shortcut, event) {
  if (event) event.stopPropagation();
  const confirmDelete = window.confirm(`Apakah Anda yakin ingin menghapus shortcut "${shortcut.name}"?`);
  if (!confirmDelete) return;

  shortcuts.value = shortcuts.value.filter(s => s.id !== shortcut.id);
  saveShortcuts();
}

// Reset ke preset default
function resetToDefaults() {
  const confirmReset = window.confirm('Reset daftar shortcut kembali ke link contoh bawaan?');
  if (!confirmReset) return;
  shortcuts.value = [...defaultShortcuts];
  saveShortcuts();
}

// Buka link di tab baru
function openShortcut(url, event) {
  if (event) event.preventDefault();
  window.open(url, '_blank', 'noopener,noreferrer');
  isOpen.value = false;
}

function closeModal() {
  isModalOpen.value = false;
  formError.value = '';
}

// Ekstrak domain dari URL untuk preview yang bersih
function extractDomain(url) {
  try {
    const parsed = new URL(url);
    return parsed.hostname.replace(/^www\./, '');
  } catch {
    return url;
  }
}
</script>

<template>
  <div class="shortcut-links-wrapper" ref="dropdownRef">
    <!-- Tombol Pemicu di Navbar (Posisi Kotak Merah) -->
    <button
      type="button"
      class="btn-shortcut-trigger"
      :class="{ 'is-active': isOpen }"
      @click="toggleDropdown"
      title="Akses Cepat Link Eskalasi & Form Agent"
      aria-haspopup="true"
      :aria-expanded="isOpen"
    >
      <span class="trigger-icon-wrap">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
          <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
        </svg>
      </span>
      <span class="trigger-label">Link Eskalasi</span>
      <span v-if="shortcuts.length" class="trigger-badge">{{ shortcuts.length }}</span>
      <svg class="trigger-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <polyline points="6 9 12 15 18 9"></polyline>
      </svg>
    </button>

    <!-- Dropdown Popover Menu -->
    <transition name="popover-fade">
      <div v-if="isOpen" class="shortcut-dropdown-panel" @click.stop>
        <!-- Panel Header -->
        <div class="panel-header">
          <div class="header-titles">
            <h3 class="panel-title">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
              </svg>
              <span>Shortcut Link Agent</span>
            </h3>
            <p class="panel-subtitle">Akses cepat form & link eskalasi operasional</p>
          </div>

          <button
            type="button"
            class="btn-add-shortcut-header"
            @click="openAddModal"
            title="Tambah link shortcut baru"
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>Tambah</span>
          </button>
        </div>

        <!-- Search Box (Jika ada lebih dari 3 link) -->
        <div v-if="shortcuts.length > 3" class="panel-search-box">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input
            type="text"
            v-model="searchQuery"
            class="panel-search-input"
            placeholder="Cari form atau link..."
          />
          <button v-if="searchQuery" type="button" class="btn-clear-search" @click="searchQuery = ''">&times;</button>
        </div>

        <!-- List Shortcut Links -->
        <div class="panel-shortcuts-list">
          <template v-if="filteredShortcuts.length > 0">
            <div
              v-for="item in filteredShortcuts"
              :key="item.id"
              class="shortcut-card-item"
              @click="openShortcut(item.url, $event)"
              :title="`Klik untuk buka ${item.name} (${item.url})`"
            >
              <!-- Icon Kategori -->
              <div class="shortcut-icon-badge" :class="'cat-' + (item.category || 'Lainnya').toLowerCase()">
                <!-- Eskalasi Icon -->
                <svg v-if="item.category === 'Eskalasi'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                  <line x1="12" y1="9" x2="12" y2="13"></line>
                  <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <!-- Form Icon -->
                <svg v-else-if="item.category === 'Form'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                  <polyline points="14 2 14 8 20 8"></polyline>
                  <line x1="16" y1="13" x2="8" y2="13"></line>
                  <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
                <!-- Spreadsheet Icon -->
                <svg v-else-if="item.category === 'Spreadsheet'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="3" y1="9" x2="21" y2="9"></line>
                  <line x1="9" y1="21" x2="9" y2="9"></line>
                </svg>
                <!-- WhatsApp Icon -->
                <svg v-else-if="item.category === 'WhatsApp'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                </svg>
                <!-- Portal Icon -->
                <svg v-else-if="item.category === 'Portal'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="2" y1="12" x2="22" y2="12"></line>
                  <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                </svg>
                <!-- Default Link Icon -->
                <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                  <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                </svg>
              </div>

              <!-- Info Shortcut -->
              <div class="shortcut-info-col">
                <div class="shortcut-title-row">
                  <span class="shortcut-name">{{ item.name }}</span>
                  <span class="shortcut-cat-pill" :class="'pill-' + (item.category || 'Lainnya').toLowerCase()">
                    {{ item.category }}
                  </span>
                </div>
                <div class="shortcut-url-row">
                  <span class="shortcut-domain">{{ extractDomain(item.url) }}</span>
                  <span v-if="item.description" class="shortcut-desc-snippet">&bull; {{ item.description }}</span>
                </div>
              </div>

              <!-- Action Buttons (Edit & Delete) -->
              <div class="shortcut-actions-row" @click.stop>
                <button
                  type="button"
                  class="btn-sc-action btn-sc-edit"
                  @click="openEditModal(item, $event)"
                  title="Edit shortcut ini"
                >
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                  </svg>
                </button>
                <button
                  type="button"
                  class="btn-sc-action btn-sc-delete"
                  @click="deleteShortcut(item, $event)"
                  title="Hapus shortcut ini"
                >
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                  </svg>
                </button>
                <a
                  :href="item.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="btn-sc-action btn-sc-external"
                  @click.stop
                  title="Buka di tab baru"
                >
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                    <polyline points="15 3 21 3 21 9"></polyline>
                    <line x1="10" y1="14" x2="21" y2="3"></line>
                  </svg>
                </a>
              </div>
            </div>
          </template>

          <!-- Empty State -->
          <div v-else class="panel-empty-state">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.8">
              <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
              <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
            </svg>
            <p class="empty-title">{{ searchQuery ? 'Tidak ada shortcut yang cocok' : 'Belum ada link shortcut' }}</p>
            <p class="empty-sub">
              {{ searchQuery ? 'Coba gunakan kata kunci pencarian lain.' : 'Tambahkan tautan form Google, portal NOC, atau link eskalasi operasional.' }}
            </p>
            <div class="empty-actions">
              <button type="button" class="btn-primary btn-sm" @click="openAddModal">
                + Tambah Link
              </button>
              <button v-if="!searchQuery && shortcuts.length === 0" type="button" class="btn-link-reset" @click="resetToDefaults">
                Muat Link Contoh
              </button>
            </div>
          </div>
        </div>

        <!-- Panel Footer -->
        <div class="panel-footer">
          <span class="footer-count">{{ shortcuts.length }} tautan tersimpan</span>
          <button type="button" class="btn-reset-text" @click="resetToDefaults" title="Pulihkan link bawaan">
            Reset Bawaan
          </button>
        </div>
      </div>
    </transition>

    <!-- Modal Form Tambah / Edit Shortcut (Teleport ke body agar tidak terpotong navbar) -->
    <Teleport to="body">
      <transition name="modal-fade">
        <div v-if="isModalOpen" class="shortcut-modal-overlay" @click.self="closeModal">
          <div class="shortcut-modal-card" role="dialog" aria-modal="true">
            <!-- Modal Header -->
            <div class="modal-card-header">
              <div class="modal-title-wrap">
                <div class="modal-icon-bubble">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.3">
                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                  </svg>
                </div>
                <div>
                  <h3 class="modal-card-title">
                    {{ isEditing ? 'Edit Shortcut Link' : 'Tambah Shortcut Link Baru' }}
                  </h3>
                  <p class="modal-card-sub">Link cepat eskalasi atau form yang sering diakses agent</p>
                </div>
              </div>
              <button type="button" class="btn-modal-close" @click="closeModal" aria-label="Tutup modal">
                &times;
              </button>
            </div>

            <!-- Modal Form Body -->
            <form @submit.prevent="saveShortcut" class="modal-card-body">
              <div v-if="formError" class="form-error-banner">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="12" y1="8" x2="12" y2="12"></line>
                  <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span>{{ formError }}</span>
              </div>

              <!-- Input Nama -->
              <div class="form-group">
                <label class="form-label">
                  Nama Shortcut <span class="required-star">*</span>
                </label>
                <input
                  type="text"
                  v-model="formName"
                  class="form-control"
                  placeholder="Contoh: Form Eskalasi Core Network, Portal NOC"
                  required
                  autofocus
                />
              </div>

              <!-- Input URL -->
              <div class="form-group">
                <label class="form-label">
                  URL / Tautan Link <span class="required-star">*</span>
                </label>
                <div class="input-url-wrap">
                  <span class="url-prefix-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="2" y1="12" x2="22" y2="12"></line>
                      <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1 4-10z"></path>
                    </svg>
                  </span>
                  <input
                    type="text"
                    v-model="formUrl"
                    class="form-control form-control-url"
                    placeholder="https://forms.google.com/d/e/... atau portal.company.com"
                    required
                  />
                </div>
                <span class="form-hint">Otomatis ditambahkan https:// jika tidak ditulis.</span>
              </div>

              <!-- Pilihan Kategori -->
              <div class="form-group">
                <label class="form-label">Kategori Link</label>
                <div class="category-pills-row">
                  <button
                    v-for="cat in categories"
                    :key="cat.value"
                    type="button"
                    class="btn-cat-pill"
                    :class="{ 'is-selected': formCategory === cat.value, ['pill-' + cat.color]: true }"
                    @click="formCategory = cat.value"
                  >
                    <span>{{ cat.label }}</span>
                  </button>
                </div>
              </div>

              <!-- Deskripsi Tambahan (Opsional) -->
              <div class="form-group">
                <label class="form-label">Catatan / Deskripsi Singkat <span class="optional-tag">(Opsional)</span></label>
                <input
                  type="text"
                  v-model="formDescription"
                  class="form-control"
                  placeholder="Contoh: Form pelaporan gangguan fiber optik"
                />
              </div>

              <!-- Modal Actions -->
              <div class="modal-card-footer">
                <button type="button" class="btn-cancel" @click="closeModal">
                  Batal
                </button>
                <button type="submit" class="btn-primary btn-save">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                  <span>{{ isEditing ? 'Simpan Perubahan' : 'Tambahkan Link' }}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </transition>
    </Teleport>
  </div>
</template>

<style scoped>
.shortcut-links-wrapper {
  position: relative;
  display: inline-flex;
  align-items: center;
}

/* ─── Tombol Pemicu di Navbar (Desktop) ─── */
.btn-shortcut-trigger {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 6px 12px;
  border-radius: 999px;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  color: #334155;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
  user-select: none;
}

.btn-shortcut-trigger:hover {
  background: #eff6ff;
  border-color: #bfdbfe;
  color: #1d4ed8;
}

.btn-shortcut-trigger.is-active {
  background: #eff6ff;
  border-color: #3b82f6;
  color: #1d4ed8;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.trigger-icon-wrap {
  display: flex;
  align-items: center;
  color: #2563eb;
}

.trigger-label {
  white-space: nowrap;
}

.trigger-badge {
  font-size: 0.7rem;
  font-weight: 800;
  padding: 1px 6px;
  border-radius: 999px;
  background: #2563eb;
  color: #ffffff;
  line-height: 1.2;
}

.trigger-chevron {
  transition: transform 0.2s ease;
  color: #64748b;
}

.btn-shortcut-trigger.is-active .trigger-chevron {
  transform: rotate(180deg);
  color: #2563eb;
}

/* ─── Dropdown Popover Panel ─── */
.shortcut-dropdown-panel {
  position: absolute;
  top: calc(100% + 10px);
  left: 0;
  width: 360px;
  max-width: 90vw;
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 14px 35px -6px rgba(15, 23, 42, 0.16), 0 4px 12px rgba(15, 23, 42, 0.05);
  z-index: 1000;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

/* Header */
.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px 12px;
  border-bottom: 1px solid #f1f5f9;
  background: #fafafa;
}

.header-titles {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.panel-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.88rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.panel-subtitle {
  font-size: 0.72rem;
  color: #64748b;
  margin: 0;
}

.btn-add-shortcut-header {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 10px;
  border-radius: 8px;
  background: #2563eb;
  color: #ffffff;
  font-size: 0.74rem;
  font-weight: 700;
  border: none;
  cursor: pointer;
  transition: background 0.15s ease;
}

.btn-add-shortcut-header:hover {
  background: #1d4ed8;
}

/* Search Box */
.panel-search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  background: #ffffff;
  border-bottom: 1px solid #f1f5f9;
}

.panel-search-input {
  flex: 1;
  border: none;
  outline: none;
  font-size: 0.8rem;
  color: #0f172a;
  background: transparent;
}

.panel-search-input::placeholder {
  color: #94a3b8;
}

.btn-clear-search {
  background: none;
  border: none;
  color: #94a3b8;
  font-size: 1rem;
  cursor: pointer;
  padding: 0 4px;
}

/* List Items */
.panel-shortcuts-list {
  max-height: 340px;
  overflow-y: auto;
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.shortcut-card-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: 12px;
  background: #ffffff;
  border: 1px solid #f1f5f9;
  cursor: pointer;
  transition: all 0.15s ease;
  position: relative;
}

.shortcut-card-item:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
  transform: translateX(2px);
}

.shortcut-icon-badge {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

/* Kategori Colors */
.cat-eskalasi {
  background: #fee2e2;
  color: #dc2626;
}

.cat-form {
  background: #dbeafe;
  color: #2563eb;
}

.cat-spreadsheet {
  background: #d1fae5;
  color: #059669;
}

.cat-whatsapp {
  background: #dcfce7;
  color: #16a34a;
}

.cat-portal {
  background: #e0e7ff;
  color: #4f46e5;
}

.cat-lainnya {
  background: #f1f5f9;
  color: #475569;
}

.shortcut-info-col {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.shortcut-title-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.shortcut-name {
  font-size: 0.82rem;
  font-weight: 700;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.shortcut-cat-pill {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.pill-eskalasi {
  background: #fee2e2;
  color: #b91c1c;
}

.pill-form {
  background: #dbeafe;
  color: #1e40af;
}

.pill-spreadsheet {
  background: #d1fae5;
  color: #065f46;
}

.pill-whatsapp {
  background: #dcfce7;
  color: #15803d;
}

.pill-portal {
  background: #e0e7ff;
  color: #3730a3;
}

.pill-lainnya {
  background: #f1f5f9;
  color: #475569;
}

.shortcut-url-row {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.72rem;
  color: #64748b;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.shortcut-domain {
  color: #2563eb;
  font-weight: 600;
}

.shortcut-desc-snippet {
  color: #94a3b8;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Action Buttons (Edit / Delete / External) */
.shortcut-actions-row {
  display: flex;
  align-items: center;
  gap: 4px;
  opacity: 0.6;
  transition: opacity 0.15s ease;
}

.shortcut-card-item:hover .shortcut-actions-row {
  opacity: 1;
}

.btn-sc-action {
  width: 26px;
  height: 26px;
  min-width: 26px;
  min-height: 26px;
  padding: 0 !important;
  margin: 0;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #64748b;
  cursor: pointer;
  transition: all 0.15s ease;
  box-sizing: border-box;
  flex-shrink: 0;
}

.btn-sc-action svg {
  display: block;
  flex-shrink: 0;
  pointer-events: none;
}

.btn-sc-action:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.btn-sc-edit:hover {
  background: #eff6ff;
  color: #2563eb;
  border-color: #bfdbfe;
}

.btn-sc-delete:hover {
  background: #fef2f2;
  color: #dc2626;
  border-color: #fecaca;
}

.btn-sc-external {
  color: #2563eb;
  text-decoration: none;
}

.btn-sc-external:hover {
  background: #eff6ff;
}

/* Empty State */
.panel-empty-state {
  padding: 24px 16px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.empty-title {
  font-size: 0.85rem;
  font-weight: 700;
  color: #334155;
  margin: 4px 0 0;
}

.empty-sub {
  font-size: 0.74rem;
  color: #64748b;
  margin: 0;
  max-width: 240px;
}

.empty-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
}

.btn-link-reset {
  background: none;
  border: none;
  color: #2563eb;
  font-size: 0.76rem;
  font-weight: 700;
  cursor: pointer;
  text-decoration: underline;
}

/* Footer */
.panel-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
  border-top: 1px solid #f1f5f9;
  background: #fafafa;
  font-size: 0.72rem;
  color: #94a3b8;
}

.footer-count {
  font-weight: 600;
}

.btn-reset-text {
  background: none;
  border: none;
  color: #64748b;
  font-size: 0.72rem;
  cursor: pointer;
  transition: color 0.15s ease;
}

.btn-reset-text:hover {
  color: #dc2626;
  text-decoration: underline;
}

/* Popover Transition */
.popover-fade-enter-active,
.popover-fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.popover-fade-enter-from,
.popover-fade-leave-to {
  opacity: 0;
  transform: translateY(-8px) scale(0.98);
}

/* ─── Modal Overlay & Card Styles ─── */
.shortcut-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.shortcut-modal-card {
  width: 100%;
  max-width: 460px;
  background: #ffffff;
  border-radius: 18px;
  box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.25);
  border: 1px solid #e2e8f0;
  overflow: hidden;
  animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalPop {
  from {
    opacity: 0;
    transform: scale(0.95) translateY(10px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.modal-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 22px;
  border-bottom: 1px solid #f1f5f9;
  background: #fafafa;
}

.modal-title-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
}

.modal-icon-bubble {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: #eff6ff;
  border: 1px solid #dbeafe;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-card-title {
  font-size: 1rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.modal-card-sub {
  font-size: 0.75rem;
  color: #64748b;
  margin: 2px 0 0;
}

.btn-modal-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: #94a3b8;
  cursor: pointer;
  line-height: 1;
  padding: 4px;
}

.btn-modal-close:hover {
  color: #0f172a;
}

.modal-card-body {
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-error-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 10px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
  font-size: 0.8rem;
  font-weight: 600;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 0.82rem;
  font-weight: 700;
  color: #334155;
}

.required-star {
  color: #dc2626;
}

.optional-tag {
  font-weight: 500;
  color: #94a3b8;
  font-size: 0.74rem;
}

.form-control {
  width: 100%;
  padding: 9px 12px;
  border-radius: 10px;
  border: 1.5px solid #cbd5e1;
  font-size: 0.86rem;
  color: #0f172a;
  outline: none;
  transition: all 0.15s ease;
  box-sizing: border-box;
}

.form-control:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.input-url-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.url-prefix-icon {
  position: absolute;
  left: 12px;
  color: #64748b;
  display: flex;
  align-items: center;
  pointer-events: none;
}

.form-control-url {
  padding-left: 36px;
}

.form-hint {
  font-size: 0.72rem;
  color: #94a3b8;
}

.category-pills-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.btn-cat-pill {
  padding: 5px 10px;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 700;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-cat-pill:hover {
  background: #f1f5f9;
}

.btn-cat-pill.is-selected {
  border-color: #2563eb;
  background: #eff6ff;
  color: #1d4ed8;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
}

.modal-card-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 10px;
  padding-top: 14px;
  border-top: 1px solid #f1f5f9;
}

.btn-cancel {
  padding: 9px 16px;
  border-radius: 10px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-cancel:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.btn-save {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 18px;
  border-radius: 10px;
  background: #2563eb;
  border: none;
  color: #ffffff;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s ease;
}

.btn-save:hover {
  background: #1d4ed8;
}

/* Modal Fade */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
