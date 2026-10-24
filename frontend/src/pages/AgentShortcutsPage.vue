<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useAuthStore } from '../stores/auth';
import { scrapeLinkMetadata, getGoogleFavicon, extractDomain, normalizeUrl } from '../utils/linkIconHelper';

const auth = useAuthStore();

// Storage key dinamis per user ID agent
const storageKey = computed(() => `agent_shortcuts_${auth.user?.id || 'default'}`);

// Default starter shortcuts: kosong agar agent bebas mengisi tautan mandiri
const defaultShortcuts = [];

// List shortcuts
const shortcuts = ref([]);

// Search & Filter state
const searchQuery = ref('');
const selectedCategory = ref('Semua');

// Modal state
const isModalOpen = ref(false);
const isEditing = ref(false);
const currentEditingId = ref(null);

// Form fields
const formName = ref('');
const formUrl = ref('');
const formCategory = ref('Eskalasi');
const formDescription = ref('');
const formIconUrl = ref('');
const isScrapingIcon = ref(false);
const formError = ref('');

// State tracking icon gambar yang gagal dimuat (fallback ke SVG kategori)
const failedIcons = ref({});

// Copy tooltip state
const copiedId = ref(null);

// Delete confirmation modal state
const isDeleteModalOpen = ref(false);
const shortcutToDelete = ref(null);

// Kategori yang didukung
const categories = [
  { value: 'Eskalasi', label: 'Eskalasi', color: 'red' },
  { value: 'Form', label: 'Form', color: 'blue' },
  { value: 'Portal', label: 'Portal', color: 'indigo' },
  { value: 'Spreadsheet', label: 'Spreadsheet', color: 'emerald' },
  { value: 'WhatsApp', label: 'WhatsApp', color: 'green' },
  { value: 'Lainnya', label: 'Lainnya', color: 'slate' }
];

function loadShortcuts() {
  try {
    const raw = localStorage.getItem(storageKey.value);
    if (raw) {
      const parsed = JSON.parse(raw);
      // Bersihkan jika sebelumnya pernah terisi dummy bawaan (sc-1 s/d sc-5)
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

watch(() => auth.user?.id, () => {
  loadShortcuts();
});

onMounted(() => {
  loadShortcuts();
});

// Live Icon Scraping debounced
let scrapeTimeout = null;
async function triggerIconScrape(immediate = false) {
  if (scrapeTimeout) clearTimeout(scrapeTimeout);
  const raw = formUrl.value.trim();
  if (!raw || raw.length < 4) {
    formIconUrl.value = '';
    return;
  }

  const runScrape = async () => {
    isScrapingIcon.value = true;
    try {
      const meta = await scrapeLinkMetadata(formUrl.value);
      if (meta?.iconUrl) {
        formIconUrl.value = meta.iconUrl;
      }
      if (!formName.value && meta?.title) {
        formName.value = meta.title;
      }
      if (meta?.categorySuggestion && formCategory.value === 'Eskalasi') {
        formCategory.value = meta.categorySuggestion;
      }
    } catch (e) {
      console.warn('Scraping icon error:', e);
    } finally {
      isScrapingIcon.value = false;
    }
  };

  if (immediate) {
    runScrape();
  } else {
    scrapeTimeout = setTimeout(runScrape, 400);
  }
}

// Filtered shortcuts
const filteredShortcuts = computed(() => {
  let list = shortcuts.value;

  // Filter Kategori
  if (selectedCategory.value !== 'Semua') {
    list = list.filter(s => (s.category || 'Lainnya') === selectedCategory.value);
  }

  // Filter Pencarian
  const q = searchQuery.value.trim().toLowerCase();
  if (q) {
    list = list.filter(s =>
      s.name.toLowerCase().includes(q) ||
      (s.category && s.category.toLowerCase().includes(q)) ||
      (s.description && s.description.toLowerCase().includes(q)) ||
      s.url.toLowerCase().includes(q)
    );
  }

  return list;
});

// Hitung jumlah per kategori
function getCategoryCount(catName) {
  if (catName === 'Semua') return shortcuts.value.length;
  return shortcuts.value.filter(s => (s.category || 'Lainnya') === catName).length;
}

// Buka Modal Tambah
function openAddModal() {
  if (scrapeTimeout) clearTimeout(scrapeTimeout);
  isEditing.value = false;
  currentEditingId.value = null;
  formName.value = '';
  formUrl.value = '';
  formCategory.value = 'Eskalasi';
  formDescription.value = '';
  formIconUrl.value = '';
  isScrapingIcon.value = false;
  formError.value = '';
  isModalOpen.value = true;
}

// Buka Modal Edit
function openEditModal(item) {
  if (scrapeTimeout) clearTimeout(scrapeTimeout);
  isEditing.value = true;
  currentEditingId.value = item.id;
  formName.value = item.name;
  formUrl.value = item.url;
  formCategory.value = item.category || 'Eskalasi';
  formDescription.value = item.description || '';
  formIconUrl.value = item.iconUrl || getGoogleFavicon(item.url);
  isScrapingIcon.value = false;
  formError.value = '';
  isModalOpen.value = true;
}

// Simpan Tambah / Edit
function saveShortcut() {
  formError.value = '';
  const name = formName.value.trim();
  const rawUrl = formUrl.value.trim();

  if (!name) {
    formError.value = 'Nama shortcut link wajib diisi.';
    return;
  }

  if (!rawUrl) {
    formError.value = 'Tautan link URL wajib diisi.';
    return;
  }

  const finalUrl = normalizeUrl(rawUrl);

  try {
    new URL(finalUrl);
  } catch (e) {
    formError.value = 'Format URL tidak valid. Contoh: https://forms.google.com';
    return;
  }

  const iconToSave = formIconUrl.value || getGoogleFavicon(finalUrl);

  if (isEditing.value && currentEditingId.value) {
    const idx = shortcuts.value.findIndex(s => s.id === currentEditingId.value);
    if (idx !== -1) {
      shortcuts.value[idx] = {
        ...shortcuts.value[idx],
        name,
        url: finalUrl,
        iconUrl: iconToSave,
        category: formCategory.value,
        description: formDescription.value.trim(),
        updatedAt: new Date().toISOString()
      };
    }
  } else {
    const newItem = {
      id: `sc-${Date.now()}`,
      name,
      url: finalUrl,
      iconUrl: iconToSave,
      category: formCategory.value,
      description: formDescription.value.trim(),
      createdAt: new Date().toISOString()
    };
    shortcuts.value.unshift(newItem);
  }

  saveShortcuts();
  isModalOpen.value = false;
}

// Buka Konfirmasi Hapus
function confirmDelete(item) {
  shortcutToDelete.value = item;
  isDeleteModalOpen.value = true;
}

function executeDelete() {
  if (shortcutToDelete.value) {
    shortcuts.value = shortcuts.value.filter(s => s.id !== shortcutToDelete.value.id);
    saveShortcuts();
  }
  isDeleteModalOpen.value = false;
  shortcutToDelete.value = null;
}

// Buka Link Shortcut di Tab Baru
function openShortcutUrl(url) {
  if (!url) return;
  const target = normalizeUrl(url);
  window.open(target, '_blank', 'noopener,noreferrer');
}

// Salin Link ke Clipboard
async function copyLink(item) {
  try {
    await navigator.clipboard.writeText(item.url);
    copiedId.value = item.id;
    setTimeout(() => {
      if (copiedId.value === item.id) {
        copiedId.value = null;
      }
    }, 2000);
  } catch (e) {
    console.error('Gagal menyalin link:', e);
  }
}
</script>

<template>
  <div class="shortcuts-page-container">
    <!-- Header Page -->
    <div class="shortcuts-header-row">
      <div class="header-titles">
        <div class="header-badge-pill">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
          </svg>
          <span>Akses Cepat Operasional</span>
        </div>
        <h2 class="page-title">Link Eskalasi & Form Agent</h2>
        <p class="page-desc">
          Katalog tautan cepat untuk form Google, portal internal NOC, spreadsheet rekap, dan link eskalasi penanganan kendala pelanggan.
        </p>
      </div>

      <div class="header-actions-group">
        <button type="button" class="btn-primary btn-add-shortcut" @click="openAddModal">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          <span>Tambah Link Baru</span>
        </button>
      </div>
    </div>

    <!-- Toolbar: Search & Category Filter Pills -->
    <div class="shortcuts-toolbar-card">
      <!-- Search Input -->
      <div class="search-input-wrapper">
        <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input
          type="text"
          v-model="searchQuery"
          class="search-input"
          placeholder="Cari nama form, link URL, atau catatan..."
        />
        <button
          v-if="searchQuery"
          type="button"
          class="btn-clear-search"
          @click="searchQuery = ''"
          title="Hapus pencarian"
        >
          &times;
        </button>
      </div>

      <!-- Category Filter Pills -->
      <div class="category-filter-scroll">
        <button
          type="button"
          class="filter-pill"
          :class="{ active: selectedCategory === 'Semua' }"
          @click="selectedCategory = 'Semua'"
        >
          <span>Semua</span>
          <span class="pill-count">{{ getCategoryCount('Semua') }}</span>
        </button>

        <button
          v-for="cat in categories"
          :key="cat.value"
          type="button"
          class="filter-pill"
          :class="{ active: selectedCategory === cat.value, ['filter-' + cat.color]: true }"
          @click="selectedCategory = cat.value"
        >
          <span>{{ cat.label }}</span>
          <span class="pill-count">{{ getCategoryCount(cat.value) }}</span>
        </button>
      </div>
    </div>

    <!-- Grid List Shortcut Cards (Spacious & Easy to Custom/View) -->
    <div v-if="filteredShortcuts.length > 0" class="shortcuts-grid">
      <div
        v-for="item in filteredShortcuts"
        :key="item.id"
        class="shortcut-card"
        @click="openShortcutUrl(item.url)"
        role="button"
        tabindex="0"
        @keydown.enter="openShortcutUrl(item.url)"
        :title="`Klik untuk membuka tautan ${item.name}`"
      >
        <!-- Card Top Bar: Category Pill & Menu Actions -->
        <div class="card-top-bar" @click.stop>
          <span class="cat-pill" :class="'pill-' + (item.category || 'Lainnya').toLowerCase()">
            <!-- Category Icon -->
            <svg v-if="item.category === 'Eskalasi'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
              <line x1="12" y1="9" x2="12" y2="13"></line>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <svg v-else-if="item.category === 'Form'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
            <svg v-else-if="item.category === 'Spreadsheet'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="3" y1="9" x2="21" y2="9"></line>
              <line x1="9" y1="21" x2="9" y2="9"></line>
            </svg>
            <svg v-else-if="item.category === 'WhatsApp'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
            </svg>
            <svg v-else-if="item.category === 'Portal'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="2" y1="12" x2="22" y2="12"></line>
              <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1 4-10z"></path>
            </svg>
            <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
              <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
            </svg>
            <span>{{ item.category || 'Lainnya' }}</span>
          </span>

          <div class="card-edit-actions" @click.stop>
            <!-- Tombol Edit -->
            <button
              type="button"
              class="btn-icon-action btn-icon-edit"
              @click.stop="openEditModal(item)"
              title="Edit nama dan link shortcut ini"
            >
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
              </svg>
            </button>

            <!-- Tombol Hapus -->
            <button
              type="button"
              class="btn-icon-action btn-icon-delete"
              @click.stop="confirmDelete(item)"
              title="Hapus shortcut ini"
            >
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
              </svg>
            </button>
          </div>
        </div>

        <!-- Card Main Identity (Icon Favicon Hasil Scraping & Judul) -->
        <div class="card-identity-header">
          <div class="card-favicon-bubble" :class="'avatar-' + (item.category || 'Lainnya').toLowerCase()">
            <img
              v-if="(item.iconUrl || getGoogleFavicon(item.url)) && !failedIcons[item.id]"
              :src="item.iconUrl || getGoogleFavicon(item.url)"
              class="card-favicon-img"
              alt=""
              @error="failedIcons[item.id] = true"
            />
            <!-- Fallback SVG icon -->
            <svg v-else-if="item.category === 'Eskalasi'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
              <line x1="12" y1="9" x2="12" y2="13"></line>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <svg v-else-if="item.category === 'Form'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
            <svg v-else-if="item.category === 'Spreadsheet'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="3" y1="9" x2="21" y2="9"></line>
              <line x1="9" y1="21" x2="9" y2="9"></line>
            </svg>
            <svg v-else-if="item.category === 'WhatsApp'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
            </svg>
            <svg v-else-if="item.category === 'Portal'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="2" y1="12" x2="22" y2="12"></line>
              <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
            </svg>
            <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
              <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
            </svg>
          </div>

          <div class="card-titles-col">
            <h3 class="card-title" :title="item.name">{{ item.name }}</h3>
            <!-- Domain Tag -->
            <div class="card-domain-row">
              <span class="domain-text" :title="item.url">{{ extractDomain(item.url) }}</span>
            </div>
          </div>
        </div>

        <!-- Description (Catatan Penggunaan) -->
        <p class="card-desc">
          {{ item.description || 'Tidak ada catatan tambahan untuk link ini.' }}
        </p>

        <!-- Card Bottom Buttons: Salin URL & Indikator Klik Buka Link -->
        <div class="card-footer-buttons" @click.stop>
          <button
            type="button"
            class="btn-card-copy"
            @click.stop="copyLink(item)"
            :title="copiedId === item.id ? 'Tersalin ke clipboard!' : 'Salin tautan link'"
          >
            <svg v-if="copiedId === item.id" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
            <span>{{ copiedId === item.id ? 'Tersalin!' : 'Salin' }}</span>
          </button>

          <div class="card-click-hint">
            <span>Buka link</span>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
              <polyline points="15 3 21 3 21 9"></polyline>
              <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="shortcuts-empty-card">
      <div class="empty-icon-box">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.8">
          <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
          <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
        </svg>
      </div>
      <h3 class="empty-title">
        {{ searchQuery || selectedCategory !== 'Semua' ? 'Tidak ada shortcut yang cocok' : 'Belum Ada Link Shortcut' }}
      </h3>
      <p class="empty-desc">
        {{ searchQuery || selectedCategory !== 'Semua'
          ? 'Coba ubah kata kunci pencarian atau pilih kategori lain.'
          : 'Tambahkan tautan form Google, portal eskalasi, spreadsheet, atau grup WA yang sering Anda gunakan.' }}
      </p>
      <div class="empty-btn-group">
        <button
          v-if="searchQuery || selectedCategory !== 'Semua'"
          type="button"
          class="btn-outline-reset"
          @click="searchQuery = ''; selectedCategory = 'Semua'"
        >
          Bersihkan Filter
        </button>
        <button type="button" class="btn-primary" @click="openAddModal">
          + Tambah Link Baru
        </button>
      </div>
    </div>

    <!-- ── Modal Form Tambah / Edit Shortcut ── -->
    <Teleport to="body">
      <transition name="modal-fade">
        <div v-if="isModalOpen" class="shortcut-modal-overlay" @click.self="isModalOpen = false">
          <div class="shortcut-modal-card" role="dialog" aria-modal="true">
            <div class="modal-card-header">
              <div class="modal-title-wrap">
                <div class="modal-icon-bubble">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.3">
                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                  </svg>
                </div>
                <div>
                  <h3 class="modal-card-title">
                    {{ isEditing ? 'Edit Shortcut Link' : 'Tambah Shortcut Link Baru' }}
                  </h3>
                  <p class="modal-card-sub">Simpan link eskalasi atau form operasional agar mudah dibuka</p>
                </div>
              </div>
              <button type="button" class="btn-modal-close" @click="isModalOpen = false" aria-label="Tutup modal">
                &times;
              </button>
            </div>

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
                  Nama Shortcut Link <span class="required-star">*</span>
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
                    <img
                      v-if="formIconUrl"
                      :src="formIconUrl"
                      class="prefix-icon-img"
                      alt=""
                      @error="formIconUrl = ''"
                    />
                    <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
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
                    @input="triggerIconScrape(false)"
                    @blur="triggerIconScrape(true)"
                    @paste="setTimeout(() => triggerIconScrape(true), 50)"
                  />
                </div>

                <!-- Live icon scraping status & preview -->
                <div v-if="isScrapingIcon" class="icon-scrape-status scraping">
                  <span class="scrape-spinner"></span>
                  <span>Mendeteksi icon link...</span>
                </div>
                <div v-else-if="formIconUrl" class="icon-scrape-status detected">
                  <img :src="formIconUrl" class="detected-icon-thumb" alt="" @error="formIconUrl = ''" />
                  <span class="detected-text">Icon link berhasil dideteksi otomatis</span>
                </div>
                <span v-else class="form-hint">Icon website & favicon akan di-scrape otomatis begitu link diisi.</span>
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

              <!-- Catatan Tambahan (Opsional) -->
              <div class="form-group">
                <label class="form-label">Catatan / Deskripsi Singkat <span class="optional-tag">(Opsional)</span></label>
                <input
                  type="text"
                  v-model="formDescription"
                  class="form-control"
                  placeholder="Contoh: Form pelaporan gangguan fiber optik ke tim NOC"
                />
              </div>

              <!-- Modal Footer -->
              <div class="modal-card-footer">
                <button type="button" class="btn-cancel" @click="isModalOpen = false">
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

    <!-- ── Modal Konfirmasi Hapus ── -->
    <Teleport to="body">
      <transition name="modal-fade">
        <div v-if="isDeleteModalOpen" class="shortcut-modal-overlay" @click.self="isDeleteModalOpen = false">
          <div class="shortcut-modal-card modal-card-sm" role="dialog" aria-modal="true">
            <div class="delete-modal-inner">
              <div class="delete-icon-bubble">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="15" y1="9" x2="9" y2="15"></line>
                  <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
              </div>

              <h3 class="delete-modal-title">Hapus Shortcut Link?</h3>
              <p class="delete-modal-desc">
                Apakah Anda yakin ingin menghapus tautan <strong>"{{ shortcutToDelete?.name }}"</strong>? Tindakan ini tidak dapat dibatalkan.
              </p>

              <div class="delete-modal-actions">
                <button type="button" class="btn-cancel" @click="isDeleteModalOpen = false">
                  Batal
                </button>
                <button type="button" class="btn-danger-delete" @click="executeDelete">
                  Ya, Hapus
                </button>
              </div>
            </div>
          </div>
        </div>
      </transition>
    </Teleport>
  </div>
</template>

<style scoped>
.shortcuts-page-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
  width: 100%;
}

/* ─── Header Page ─── */
.shortcuts-header-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.header-titles {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-width: 680px;
}

.header-badge-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 3px 10px;
  border-radius: 999px;
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #dbeafe;
  width: fit-content;
}

.page-title {
  font-size: 1.55rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
  letter-spacing: -0.02em;
}

.page-desc {
  font-size: 0.88rem;
  color: #64748b;
  margin: 0;
  line-height: 1.5;
}

.header-actions-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.btn-outline-reset {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 14px;
  border-radius: 10px;
  border: 1.5px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-outline-reset:hover {
  background: #f1f5f9;
  color: #0f172a;
  border-color: #94a3b8;
}

.btn-add-shortcut {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 18px;
  border-radius: 10px;
  background: #2563eb;
  border: none;
  color: #ffffff;
  font-size: 0.84rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
  transition: all 0.15s ease;
}

.btn-add-shortcut:hover {
  background: #1d4ed8;
  transform: translateY(-1px);
}

/* ─── Toolbar (Search & Filter) ─── */
.shortcuts-toolbar-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
}

.search-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}

.search-icon {
  position: absolute;
  left: 14px;
  color: #94a3b8;
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 10px 14px 10px 40px;
  border-radius: 10px;
  border: 1.5px solid #cbd5e1;
  font-size: 0.88rem;
  color: #0f172a;
  background: #f8fafc;
  outline: none;
  transition: all 0.15s ease;
  box-sizing: border-box;
}

.search-input:focus {
  background: #ffffff;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.btn-clear-search {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  font-size: 1.2rem;
  color: #94a3b8;
  cursor: pointer;
  padding: 2px 6px;
}

.category-filter-scroll {
  display: flex;
  align-items: center;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 2px;
}

.filter-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #475569;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.filter-pill:hover {
  background: #f1f5f9;
  border-color: #cbd5e1;
}

.filter-pill.active {
  background: #eff6ff;
  border-color: #3b82f6;
  color: #1d4ed8;
}

.pill-count {
  font-size: 0.68rem;
  font-weight: 800;
  padding: 1px 6px;
  border-radius: 999px;
  background: #e2e8f0;
  color: #475569;
}

.filter-pill.active .pill-count {
  background: #3b82f6;
  color: #ffffff;
}

/* ─── Grid List Shortcut Cards (Spacious) ─── */
.shortcuts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
  gap: 16px;
}

.shortcut-card {
  background: #ffffff;
  border: 1.5px solid #e2e8f0;
  border-radius: 16px;
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  box-shadow: 0 3px 10px rgba(15, 23, 42, 0.03);
  transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
  position: relative;
  cursor: pointer;
}

.shortcut-card:hover {
  transform: translateY(-3px);
  border-color: #93c5fd;
  box-shadow: 0 12px 28px -4px rgba(37, 99, 235, 0.12), 0 4px 8px -2px rgba(15, 23, 42, 0.04);
}

.shortcut-card:focus-visible {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
}

.card-top-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.cat-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.68rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 3px 8px;
  border-radius: 6px;
}

/* Category Pill Colors */
.pill-eskalasi {
  background: #fee2e2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.pill-form {
  background: #dbeafe;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
}

.pill-portal {
  background: #e0e7ff;
  color: #4338ca;
  border: 1px solid #c7d2fe;
}

.pill-spreadsheet {
  background: #d1fae5;
  color: #047857;
  border: 1px solid #a7f3d0;
}

.pill-whatsapp {
  background: #dcfce7;
  color: #15803d;
  border: 1px solid #bbf7d0;
}

.pill-lainnya {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
}

.card-edit-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-icon-action {
  width: 32px;
  height: 32px;
  min-width: 32px;
  min-height: 32px;
  padding: 0 !important;
  margin: 0;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
  box-sizing: border-box;
  flex-shrink: 0;
}

.btn-icon-action svg {
  display: block;
  flex-shrink: 0;
  pointer-events: none;
}

/* Edit button: Soft blue with blue icon */
.btn-icon-edit {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  color: #2563eb;
}

.btn-icon-edit:hover {
  background: #2563eb;
  border-color: #2563eb;
  color: #ffffff;
  transform: translateY(-1px);
  box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
}

/* Delete button: Soft red with red icon */
.btn-icon-delete {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
}

.btn-icon-delete:hover {
  background: #dc2626;
  border-color: #dc2626;
  color: #ffffff;
  transform: translateY(-1px);
  box-shadow: 0 2px 6px rgba(220, 38, 38, 0.25);
}

.card-identity-header {
  display: flex;
  align-items: center;
  gap: 12px;
}

.card-favicon-bubble {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 1px solid transparent;
  transition: transform 0.15s ease;
}

.shortcut-card:hover .card-favicon-bubble {
  transform: scale(1.05);
}

.card-favicon-img {
  width: 24px;
  height: 24px;
  object-fit: contain;
  border-radius: 4px;
}

.avatar-eskalasi {
  background: #fee2e2;
  border-color: #fecaca;
  color: #b91c1c;
}

.avatar-form {
  background: #dbeafe;
  border-color: #bfdbfe;
  color: #1d4ed8;
}

.avatar-portal {
  background: #e0e7ff;
  border-color: #c7d2fe;
  color: #4338ca;
}

.avatar-spreadsheet {
  background: #d1fae5;
  border-color: #a7f3d0;
  color: #047857;
}

.avatar-whatsapp {
  background: #dcfce7;
  border-color: #bbf7d0;
  color: #15803d;
}

.avatar-lainnya {
  background: #f1f5f9;
  border-color: #e2e8f0;
  color: #475569;
}

.card-titles-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.card-title {
  font-size: 1.02rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.card-desc {
  font-size: 0.82rem;
  color: #64748b;
  margin: 0;
  line-height: 1.45;
  min-height: 38px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.card-domain-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.74rem;
  color: #64748b;
  padding: 1px 0;
}

.domain-text {
  color: #2563eb;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.card-footer-buttons {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: auto;
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
}

.btn-card-copy {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1.5px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-card-copy:hover {
  background: #eff6ff;
  color: #1d4ed8;
  border-color: #93c5fd;
}

.card-click-hint {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.76rem;
  font-weight: 600;
  color: #94a3b8;
  transition: all 0.15s ease;
  pointer-events: none;
}

.shortcut-card:hover .card-click-hint {
  color: #2563eb;
  transform: translateX(2px);
}

/* ─── Empty State ─── */
.shortcuts-empty-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 40px 24px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}

.empty-icon-box {
  width: 64px;
  height: 64px;
  border-radius: 16px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.empty-desc {
  font-size: 0.86rem;
  color: #64748b;
  margin: 0;
  max-width: 440px;
}

.empty-btn-group {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 10px;
  flex-wrap: wrap;
  justify-content: center;
}

/* ─── Modal Styles ─── */
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
  max-width: 480px;
  background: #ffffff;
  border-radius: 18px;
  box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.25);
  border: 1px solid #e2e8f0;
  overflow: hidden;
  animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.modal-card-sm {
  max-width: 400px;
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
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: #eff6ff;
  border: 1px solid #dbeafe;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-card-title {
  font-size: 1.05rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.modal-card-sub {
  font-size: 0.76rem;
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
  padding: 10px 14px;
  border-radius: 10px;
  border: 1.5px solid #cbd5e1;
  font-size: 0.88rem;
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

.prefix-icon-img {
  width: 16px;
  height: 16px;
  border-radius: 3px;
  object-fit: contain;
}

.icon-scrape-status {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 0.76rem;
  padding: 4px 8px;
  border-radius: 6px;
  margin-top: 2px;
}

.icon-scrape-status.scraping {
  color: #2563eb;
  background: #eff6ff;
  border: 1px solid #dbeafe;
}

.icon-scrape-status.detected {
  color: #047857;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  font-weight: 600;
}

.detected-icon-thumb {
  width: 16px;
  height: 16px;
  border-radius: 3px;
  object-fit: contain;
}

.scrape-spinner {
  width: 12px;
  height: 12px;
  border: 2px solid #bfdbfe;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
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
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 0.76rem;
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

/* Delete Modal */
.delete-modal-inner {
  padding: 24px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.delete-icon-bubble {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: #fef2f2;
  border: 1px solid #fee2e2;
  display: flex;
  align-items: center;
  justify-content: center;
}

.delete-modal-title {
  font-size: 1.1rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.delete-modal-desc {
  font-size: 0.84rem;
  color: #64748b;
  margin: 0;
  line-height: 1.45;
}

.delete-modal-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  margin-top: 10px;
}

.delete-modal-actions .btn-cancel {
  flex: 1;
}

.btn-danger-delete {
  flex: 1;
  padding: 9px 16px;
  border-radius: 10px;
  background: #dc2626;
  border: none;
  color: #ffffff;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s ease;
}

.btn-danger-delete:hover {
  background: #b91c1c;
}

/* Modal Fade Animation */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

@media (max-width: 640px) {
  .shortcuts-header-row {
    flex-direction: column;
    align-items: stretch;
  }
  .header-actions-group {
    width: 100%;
    justify-content: space-between;
  }
  .shortcuts-grid {
    grid-template-columns: 1fr;
  }
}
</style>
