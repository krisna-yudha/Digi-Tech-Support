<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import JSZip from 'jszip';
import mammoth from 'mammoth';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import api from '../services/api';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();

// ─── Word Style Mapping ──────────────────────────────────────────────────────
const docxStyleMap = [
  // English Word Headings
  "p[style-name='Heading 1'] => h1:fresh",
  "p[style-name='heading 1'] => h1:fresh",
  "p[style-name='Heading 2'] => h2:fresh",
  "p[style-name='heading 2'] => h2:fresh",
  "p[style-name='Heading 3'] => h3:fresh",
  "p[style-name='heading 3'] => h3:fresh",
  "p[style-name='Heading 4'] => h3:fresh",
  "p[style-name='Title'] => h1:fresh",
  "p[style-name='title'] => h1:fresh",
  "p[style-name='Subtitle'] => h2:fresh",
  "p[style-name='subtitle'] => h2:fresh",
  "p[style-name='Header'] => h2:fresh",
  "p[style-name='header'] => h2:fresh",

  // Indonesian Word Headings
  "p[style-name='Judul 1'] => h1:fresh",
  "p[style-name='judul 1'] => h1:fresh",
  "p[style-name='Judul 2'] => h2:fresh",
  "p[style-name='judul 2'] => h2:fresh",
  "p[style-name='Judul 3'] => h3:fresh",
  "p[style-name='judul 3'] => h3:fresh",
  "p[style-name='Judul'] => h1:fresh",
  "p[style-name='judul'] => h1:fresh",
  "p[style-name='Subjudul'] => h2:fresh",
  "p[style-name='subjudul'] => h2:fresh",
  "p[style-name='Tajuk 1'] => h1:fresh",
  "p[style-name='Tajuk 2'] => h2:fresh",
  "p[style-name='Tajuk 3'] => h3:fresh",

  // Outline Levels
  "p:has(pPr > outlineLvl[val='0']) => h1:fresh",
  "p:has(pPr > outlineLvl[val='1']) => h2:fresh",
  "p:has(pPr > outlineLvl[val='2']) => h3:fresh",

  // Formatting & Tables
  "table => table",
  "r[style-name='Strong'] => strong",
  "r[style-name='Emphasis'] => em",
  "u => u",
  "strike => s",
  "highlight => mark"
];

// Ekstrak Kop / Header Dokumen Word dari word/header*.xml
async function extractWordHeaders(arrayBuffer) {
  try {
    const zip = await JSZip.loadAsync(arrayBuffer);
    const headerFiles = Object.keys(zip.files).filter((name) =>
      /^word\/header\d*\.xml$/i.test(name)
    );

    if (!headerFiles.length) return '';

    const parser = new DOMParser();
    const collectedHeaders = [];

    for (const filename of headerFiles) {
      const xmlStr = await zip.files[filename].async('text');
      const xmlDoc = parser.parseFromString(xmlStr, 'text/xml');
      const paragraphs = xmlDoc.getElementsByTagName('w:p');

      for (let i = 0; i < paragraphs.length; i++) {
        const textNodes = paragraphs[i].getElementsByTagName('w:t');
        let pText = '';
        for (let j = 0; j < textNodes.length; j++) {
          pText += textNodes[j].textContent || '';
        }
        pText = pText.trim();
        if (pText && !collectedHeaders.includes(pText)) {
          collectedHeaders.push(pText);
        }
      }
    }

    if (collectedHeaders.length > 0) {
      return `<p class="docx-header-line"><strong>[HEADER DOKUMEN]</strong> ${collectedHeaders.join(' &bull; ')}</p>`;
    }
    return '';
  } catch (err) {
    console.warn('Gagal membaca header XML Word:', err);
    return '';
  }
}

// Konversi teks polos / Markdown sederhana ke HTML kaya
function parsePlainTextWithHeadings(rawText) {
  const lines = rawText.split(/\r?\n/);
  const result = [];
  let inUl = false;
  let inOl = false;

  for (const line of lines) {
    const trimmed = line.trim();

    if (!trimmed) {
      if (inUl) { result.push('</ul>'); inUl = false; }
      if (inOl) { result.push('</ol>'); inOl = false; }
      continue;
    }

    if (trimmed.startsWith('# ')) {
      if (inUl) { result.push('</ul>'); inUl = false; }
      if (inOl) { result.push('</ol>'); inOl = false; }
      result.push(`<h1>${trimmed.substring(2)}</h1>`);
    } else if (trimmed.startsWith('## ')) {
      if (inUl) { result.push('</ul>'); inUl = false; }
      if (inOl) { result.push('</ol>'); inOl = false; }
      result.push(`<h2>${trimmed.substring(3)}</h2>`);
    } else if (trimmed.startsWith('### ')) {
      if (inUl) { result.push('</ul>'); inUl = false; }
      if (inOl) { result.push('</ol>'); inOl = false; }
      result.push(`<h3>${trimmed.substring(4)}</h3>`);
    } else if (/^[-*]\s+/.test(trimmed)) {
      if (inOl) { result.push('</ol>'); inOl = false; }
      if (!inUl) { result.push('<ul>'); inUl = true; }
      result.push(`<li>${trimmed.replace(/^[-*]\s+/, '')}</li>`);
    } else if (/^\d+\.\s+/.test(trimmed)) {
      if (inUl) { result.push('</ul>'); inUl = false; }
      if (!inOl) { result.push('<ol>'); inOl = true; }
      result.push(`<li>${trimmed.replace(/^\d+\.\s+/, '')}</li>`);
    } else {
      if (inUl) { result.push('</ul>'); inUl = false; }
      if (inOl) { result.push('</ol>'); inOl = false; }
      result.push(`<p>${trimmed}</p>`);
    }
  }

  if (inUl) result.push('</ul>');
  if (inOl) result.push('</ol>');

  return result.join('');
}

// ─── Component State ──────────────────────────────────────────────────────────
// Mode tampilan: 'list' (koleksi template) | 'document' (dokumen langsung terbuka & bisa langsung diedit)
const activeView = ref('list');

const templates = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const selectedCategory = ref('Semua');
const fileInput = ref(null);
const fileProcessing = ref(false);
const uploadError = ref('');

// State data dokumen aktif (langsung diedit & disalin)
const form = ref({
  id: null,
  title: '',
  category: 'Umum',
  content: '<p></p>',
  original_filename: null,
  author_name: '',
  updated_at: null
});

const saving = ref(false);
const saveError = ref('');
const saveSuccess = ref(false);

// State feedback tombol salin
const copiedId = ref(null);
const isDocumentCopied = ref(false);

const categories = [
  'Semua',
  'Umum',
  'Script Respon',
  'Eskalasi',
  'Billing',
  'Teknis',
  'SOP'
];

const formCategories = [
  'Umum',
  'Script Respon',
  'Eskalasi',
  'Billing',
  'Teknis',
  'SOP'
];

// Inisialisasi Tiptap Editor langsung di halaman
const editor = useEditor({
  content: '<p></p>',
  extensions: [StarterKit],
  editorProps: {
    attributes: {
      class: 'tiptap-editor-body'
    }
  }
});

onBeforeUnmount(() => {
  if (editor.value) {
    editor.value.destroy();
  }
});

async function fetchTemplates() {
  loading.value = true;
  try {
    const { data } = await api.get('/operational-templates');
    templates.value = data.data || [];
  } catch (err) {
    console.error('Gagal mengambil template operasional:', err);
  } finally {
    loading.value = false;
  }
}

const filteredTemplates = computed(() => {
  return templates.value.filter((item) => {
    const matchCategory = selectedCategory.value === 'Semua' || item.category === selectedCategory.value;
    if (!matchCategory) return false;

    if (!searchQuery.value.trim()) return true;

    const q = searchQuery.value.toLowerCase();
    const titleMatch = (item.title || '').toLowerCase().includes(q);
    const contentMatch = (item.content || '').toLowerCase().includes(q);
    const categoryMatch = (item.category || '').toLowerCase().includes(q);

    return titleMatch || contentMatch || categoryMatch;
  });
});

// ─── Buka Dokumen: Langsung Bisa Diedit di Halaman (Bebas Pop-up) ────────────
function openDocument(template) {
  form.value = {
    id: template.id,
    title: template.title || '',
    category: template.category || 'Umum',
    content: template.content || '<p></p>',
    original_filename: template.original_filename || null,
    author_name: template.user?.name || 'Agent',
    updated_at: template.updated_at
  };
  saveError.value = '';
  saveSuccess.value = false;
  activeView.value = 'document';

  nextTick(() => {
    if (editor.value) {
      editor.value.commands.setContent(template.content || '<p></p>');
    }
  });
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function openCreate() {
  form.value = {
    id: null,
    title: '',
    category: 'Umum',
    content: '<p></p>',
    original_filename: null,
    author_name: auth.user?.name || 'Agent',
    updated_at: new Date()
  };
  saveError.value = '';
  saveSuccess.value = false;
  activeView.value = 'document';

  nextTick(() => {
    if (editor.value) {
      editor.value.commands.setContent('<p></p>');
      editor.value.commands.focus();
    }
  });
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function backToList() {
  activeView.value = 'list';
}

function triggerFileInput() {
  uploadError.value = '';
  if (fileInput.value) {
    fileInput.value.value = '';
    fileInput.value.click();
  }
}

async function handleFileChange(event) {
  const file = event.target.files?.[0];
  if (!file) return;

  const fileName = file.name;
  const ext = fileName.split('.').pop()?.toLowerCase();

  fileProcessing.value = true;
  uploadError.value = '';

  try {
    let parsedHtml = '';
    const cleanTitle = fileName.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');

    if (ext === 'docx') {
      const arrayBuffer = await file.arrayBuffer();

      // 1. Ekstrak Header Dokumen Word dari word/header*.xml (jika ada)
      const docxHeaderHtml = await extractWordHeaders(arrayBuffer);

      // 2. Konversi Body Dokumen dengan Mammoth menggunakan pemetaan Heading & Style Word
      const result = await mammoth.convertToHtml(
        { arrayBuffer },
        { styleMap: docxStyleMap }
      );

      const bodyHtml = result.value || '<p></p>';
      parsedHtml = docxHeaderHtml ? (docxHeaderHtml + bodyHtml) : bodyHtml;

    } else if (ext === 'txt' || ext === 'md') {
      const rawText = await file.text();
      parsedHtml = parsePlainTextWithHeadings(rawText);
    } else {
      uploadError.value = 'Format file tidak didukung. Harap upload dokumen .docx, .txt, atau .md';
      return;
    }

    // Buka editor langsung di halaman tanpa pop-up!
    form.value = {
      id: null,
      title: cleanTitle,
      category: 'Umum',
      content: parsedHtml,
      original_filename: fileName,
      author_name: auth.user?.name || 'Agent',
      updated_at: new Date()
    };
    saveError.value = '';
    saveSuccess.value = false;
    activeView.value = 'document';

    nextTick(() => {
      if (editor.value) {
        editor.value.commands.setContent(parsedHtml || '<p></p>');
      }
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } catch (err) {
    console.error('Gagal membaca file template:', err);
    uploadError.value = 'Gagal memproses file dokumen. Pastikan file tidak rusak.';
  } finally {
    fileProcessing.value = false;
  }
}

// Simpan Dokumen Langsung dari Halaman
async function handleSave() {
  saveError.value = '';
  saveSuccess.value = false;

  const htmlContent = editor.value ? editor.value.getHTML() : '';
  const plainText = editor.value ? editor.value.getText().trim() : '';

  if (!form.value.title.trim()) {
    saveError.value = 'Judul template wajib diisi.';
    return;
  }

  if (!plainText && (!htmlContent || htmlContent === '<p></p>')) {
    saveError.value = 'Konten template tidak boleh kosong.';
    return;
  }

  saving.value = true;
  try {
    const payload = {
      title: form.value.title.trim(),
      category: form.value.category,
      content: htmlContent,
      original_filename: form.value.original_filename
    };

    let savedItem;
    if (form.value.id) {
      const { data } = await api.put(`/operational-templates/${form.value.id}`, payload);
      savedItem = data.data;
    } else {
      const { data } = await api.post('/operational-templates', payload);
      savedItem = data.data;
      if (savedItem?.id) {
        form.value.id = savedItem.id;
      }
    }

    if (savedItem) {
      form.value.updated_at = savedItem.updated_at;
      form.value.author_name = savedItem.user?.name || form.value.author_name;
    }

    saveSuccess.value = true;
    setTimeout(() => {
      saveSuccess.value = false;
    }, 2500);

    await fetchTemplates();
  } catch (err) {
    if (err.response?.status === 401) {
      saveError.value = 'Sesi login Anda belum terdaftar di backend lokal. Silakan klik Logout di pojok kanan atas lalu Login kembali.';
    } else {
      saveError.value = err.response?.data?.message || 'Gagal menyimpan template operasional.';
    }
  } finally {
    saving.value = false;
  }
}

// Helper untuk menyalin isi teks murni template ke clipboard
function extractPlainText(html) {
  const temp = document.createElement('div');
  temp.innerHTML = html || '';
  return temp.textContent || temp.innerText || '';
}

// Salin teks langsung dari editor dokumen aktif
async function copyDocumentText() {
  try {
    const text = editor.value ? editor.value.getText() : '';
    await navigator.clipboard.writeText(text);
    isDocumentCopied.value = true;
    setTimeout(() => {
      isDocumentCopied.value = false;
    }, 2000);
  } catch (err) {
    console.error('Gagal menyalin:', err);
  }
}

// Salin teks dari kartu template di daftar
async function copyCardText(template) {
  try {
    const text = extractPlainText(template.content);
    await navigator.clipboard.writeText(text);
    copiedId.value = template.id;
    setTimeout(() => {
      if (copiedId.value === template.id) {
        copiedId.value = null;
      }
    }, 2000);
  } catch (err) {
    console.error('Gagal menyalin template:', err);
  }
}

// Download DOCX / Word format dari dokumen aktif
function downloadActiveDocx() {
  const title = form.value.title || 'Template';
  const content = editor.value ? editor.value.getHTML() : '';
  const header = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${title}</title><style>body{font-family:Calibri,Arial,sans-serif;font-size:11pt;line-height:1.5;color:#1e293b;}</style></head><body><h2>${title}</h2>${content}</body></html>`;

  const blob = new Blob(['\ufeff' + header], {
    type: 'application/msword;charset=utf-8'
  });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${title}.doc`;
  a.click();
  URL.revokeObjectURL(url);
}

// Download TXT dari dokumen aktif
function downloadActiveTxt() {
  const title = form.value.title || 'Template';
  const text = editor.value ? editor.value.getText() : '';
  const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${title}.txt`;
  a.click();
  URL.revokeObjectURL(url);
}

// Download TXT dari kartu
function downloadAsTxt(template) {
  const text = extractPlainText(template.content);
  const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${template.title || 'template'}.txt`;
  a.click();
  URL.revokeObjectURL(url);
}

// Download DOCX dari kartu
function downloadAsDocx(template) {
  const title = template.title || 'Template';
  const content = template.content || '';
  const header = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${title}</title><style>body{font-family:Calibri,Arial,sans-serif;font-size:11pt;line-height:1.5;color:#1e293b;}</style></head><body><h2>${title}</h2>${content}</body></html>`;

  const blob = new Blob(['\ufeff' + header], {
    type: 'application/msword;charset=utf-8'
  });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${title}.doc`;
  a.click();
  URL.revokeObjectURL(url);
}

// Hapus dokumen aktif
async function deleteActiveDocument() {
  if (!form.value.id) {
    backToList();
    return;
  }
  if (!confirm(`Hapus template "${form.value.title}"?`)) return;

  try {
    await api.delete(`/operational-templates/${form.value.id}`);
    backToList();
    fetchTemplates();
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal menghapus template.');
  }
}

// Hapus template dari kartu
async function deleteTemplate(template) {
  if (!confirm(`Hapus template "${template.title}"?`)) return;

  try {
    await api.delete(`/operational-templates/${template.id}`);
    if (activeView.value === 'document' && form.value.id === template.id) {
      backToList();
    }
    fetchTemplates();
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal menghapus template.');
  }
}

function formatDate(val) {
  if (!val) return '-';
  return new Date(val).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  });
}

onMounted(() => {
  fetchTemplates();
});
</script>

<template>
  <div class="templates-container">
    
    <!-- ══════════════════════════════════════════════════════════════════════════
         TAMPILAN 1: DAFTAR TEMPLATE (GRID DAFTAR TEMPLATE)
         ══════════════════════════════════════════════════════════════════════════ -->
    <article v-if="activeView === 'list'" class="card templates-section-card">
      <!-- Header Section -->
      <div class="templates-header">
        <div class="header-left">
          <div class="header-title-row">
            <svg class="header-svg-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
              <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
            <h3 class="section-heading">Template Operasional Agent</h3>
          </div>
          <p class="section-caption">
            Upload file DOCX/TXT atau klik template untuk langsung melihat dan mengedit isinya.
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="header-actions-group">
          <input
            type="file"
            ref="fileInput"
            accept=".docx,.txt,.md"
            class="hidden-file-input"
            @change="handleFileChange"
          />

          <button
            type="button"
            class="btn-outline-upload"
            @click="triggerFileInput"
            :disabled="fileProcessing"
            title="Unggah template dari file .docx atau .txt"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="17 8 12 3 7 8"></polyline>
              <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
            <span>{{ fileProcessing ? 'Membaca File...' : 'Upload Dokumen' }}</span>
          </button>

          <button
            type="button"
            class="btn-primary btn-add-template"
            @click="openCreate"
            title="Tulis template operasional baru"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>Template Baru</span>
          </button>
        </div>
      </div>

      <!-- Alert error jika upload gagal -->
      <div v-if="uploadError" class="alert-error-banner">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span>{{ uploadError }}</span>
      </div>

      <!-- Search & Category Filter Bar -->
      <div class="filter-controls-row">
        <div class="search-input-wrap">
          <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input
            type="text"
            v-model="searchQuery"
            class="search-field"
            placeholder="Cari judul, kategori, atau isi template..."
          />
          <button
            v-if="searchQuery"
            type="button"
            class="btn-clear-search"
            @click="searchQuery = ''"
            aria-label="Bersihkan pencarian"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>

        <div class="category-pills-bar">
          <button
            v-for="cat in categories"
            :key="cat"
            type="button"
            class="cat-pill"
            :class="{ active: selectedCategory === cat }"
            @click="selectedCategory = cat"
          >
            {{ cat }}
          </button>
        </div>
      </div>

      <!-- Template Cards Grid -->
      <div v-if="loading" class="state-notice">
        Memuat daftar template operasional...
      </div>

      <div v-else-if="filteredTemplates.length" class="templates-grid">
        <div
          v-for="template in filteredTemplates"
          :key="template.id"
          class="template-card"
        >
          <!-- Card Top: Category -->
          <div class="tcard-top">
            <span class="badge-category">{{ template.category || 'Umum' }}</span>
          </div>

          <!-- Title (Clickable Langsung Buka Dokumen) -->
          <h4 class="tcard-title clickable-title" :title="template.title" @click="openDocument(template)">
            {{ template.title }}
          </h4>

          <!-- Text Preview Snippet (Clickable Langsung Buka Dokumen) -->
          <p class="tcard-preview clickable-preview" @click="openDocument(template)">
            {{ extractPlainText(template.content) || '(Konten kosong)' }}
          </p>

          <!-- Meta info -->
          <div class="tcard-meta">
            <span v-if="template.original_filename" class="meta-source" :title="template.original_filename">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                <polyline points="13 2 13 9 20 9"></polyline>
              </svg>
              {{ template.original_filename }}
            </span>
            <span class="meta-author">
              {{ template.user?.name || 'Agent' }} &bull; {{ formatDate(template.updated_at) }}
            </span>
          </div>

          <!-- Action Buttons -->
          <div class="tcard-actions">
            <button
              type="button"
              class="btn-action btn-view"
              @click="openDocument(template)"
              title="Buka dan langsung edit template ini"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
              </svg>
              <span>Buka & Edit</span>
            </button>

            <button
              type="button"
              class="btn-action btn-copy"
              :class="{ 'btn-copied': copiedId === template.id }"
              @click="copyCardText(template)"
              title="Salin isi template ke clipboard"
            >
              <svg v-if="copiedId === template.id" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
              </svg>
              <span>{{ copiedId === template.id ? 'Tersalin' : 'Salin' }}</span>
            </button>

            <div class="download-dropdown">
              <button
                type="button"
                class="btn-action btn-download"
                @click="downloadAsDocx(template)"
                title="Unduh dokumen Word (.doc)"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                  <polyline points="7 10 12 15 17 10"></polyline>
                  <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>DOCX</span>
              </button>
              <button
                type="button"
                class="btn-action btn-download btn-txt-opt"
                @click="downloadAsTxt(template)"
                title="Unduh format teks murni (.txt)"
              >
                TXT
              </button>
            </div>

            <button
              type="button"
              class="btn-action btn-delete"
              @click="deleteTemplate(template)"
              title="Hapus template ini"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="empty-state-box">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="12" y1="18" x2="12" y2="12"></line>
          <line x1="9" y1="15" x2="15" y2="15"></line>
        </svg>
        <p class="empty-title">Belum ada template operasional</p>
        <p class="empty-subtitle">
          {{ searchQuery || selectedCategory !== 'Semua' ? 'Tidak ditemukan template yang cocok dengan filter pencarian.' : 'Unggah file dokumen DOCX/TXT atau buat template baru untuk mempercepat penanganan kendala.' }}
        </p>
        <div v-if="!searchQuery && selectedCategory === 'Semua'" class="empty-action-row">
          <button type="button" class="btn-outline-upload" @click="triggerFileInput">
            Upload File DOCX/TXT
          </button>
          <button type="button" class="btn-primary" @click="openCreate">
            Buat Template Baru
          </button>
        </div>
      </div>
    </article>

    <!-- ══════════════════════════════════════════════════════════════════════════
         TAMPILAN 2: DOKUMEN LANGSUNG BISA DIEDIT (INLINE TANPA POP-UP)
         ══════════════════════════════════════════════════════════════════════════ -->
    <article v-else-if="activeView === 'document'" class="card inline-document-card">
      
      <!-- Top Navigation & Actions Bar -->
      <div class="inline-nav-bar">
        <button type="button" class="btn-back-link" @click="backToList">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"></polyline>
          </svg>
          <span>Kembali ke Daftar Template</span>
        </button>

        <div class="inline-actions-group">
          <!-- Salin Teks -->
          <button
            type="button"
            class="btn-action btn-copy"
            :class="{ 'btn-copied': isDocumentCopied }"
            @click="copyDocumentText"
            title="Salin isi teks dokumen ke clipboard"
          >
            <svg v-if="isDocumentCopied" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
            <span>{{ isDocumentCopied ? 'Tersalin' : 'Salin Teks' }}</span>
          </button>

          <!-- Export DOCX / TXT -->
          <div class="download-dropdown">
            <button
              type="button"
              class="btn-action btn-download"
              @click="downloadActiveDocx"
              title="Unduh sebagai dokumen Word (.doc)"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
              </svg>
              <span>DOCX</span>
            </button>
            <button
              type="button"
              class="btn-action btn-download btn-txt-opt"
              @click="downloadActiveTxt"
              title="Unduh teks murni (.txt)"
            >
              TXT
            </button>
          </div>

          <!-- Hapus Template (Jika Dokumen Sudah Tersimpan) -->
          <button
            v-if="form.id"
            type="button"
            class="btn-action btn-delete-view"
            @click="deleteActiveDocument"
            title="Hapus template ini"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="3 6 5 6 21 6"></polyline>
              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            </svg>
            <span>Hapus</span>
          </button>

          <!-- Simpan Dokumen -->
          <button
            type="button"
            class="btn-primary btn-save"
            :class="{ 'btn-save-success': saveSuccess }"
            @click="handleSave"
            :disabled="saving"
            title="Simpan perubahan template ini"
          >
            <svg v-if="saveSuccess" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <svg v-else-if="!saving" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
              <polyline points="17 21 17 13 7 13 7 21"></polyline>
              <polyline points="7 3 7 8 15 8"></polyline>
            </svg>
            <span v-if="saving">Menyimpan...</span>
            <span v-else-if="saveSuccess">Tersimpan!</span>
            <span v-else>Simpan Dokumen</span>
          </button>
        </div>
      </div>

      <!-- Alert Error jika Gagal Simpan -->
      <div v-if="saveError" class="alert-error-banner">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span>{{ saveError }}</span>
      </div>

      <!-- Dokumen Header: Judul Langsung Bisa Diedit + Meta Inline -->
      <div class="doc-header-block">
        <input
          type="text"
          v-model="form.title"
          class="doc-title-input"
          placeholder="Ketik judul template dokumen..."
          maxlength="255"
          required
        />

        <div class="doc-meta-bar">
          <!-- Kategori Selector -->
          <div class="inline-meta-item">
            <label class="meta-label">Kategori:</label>
            <select v-model="form.category" class="inline-select">
              <option v-for="cat in formCategories" :key="cat" :value="cat">{{ cat }}</option>
            </select>
          </div>

          <!-- Dokumen Sumber Asli -->
          <span v-if="form.original_filename" class="meta-source-small" :title="form.original_filename">
            Sumber: {{ form.original_filename }}
          </span>

          <!-- Info Pembuat & Tanggal -->
          <span class="meta-author-small">
            Oleh: {{ form.author_name || 'Agent' }} &bull; Diperbarui {{ formatDate(form.updated_at) }}
          </span>
        </div>
      </div>

      <!-- Toolbar Formatting Word Layaknya Word -->
      <div v-if="editor" class="editor-toolbar">
        <div class="toolbar-group">
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('heading', { level: 1 }) }"
            @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
            title="Heading 1 (Judul Utama)"
          >
            H1
          </button>
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('heading', { level: 2 }) }"
            @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
            title="Heading 2 (Subjudul)"
          >
            H2
          </button>
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('heading', { level: 3 }) }"
            @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
            title="Heading 3 (Sub-bagian)"
          >
            H3
          </button>
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('paragraph') }"
            @click="editor.chain().focus().setParagraph().run()"
            title="Paragraf Biasa"
          >
            P
          </button>
        </div>

        <div class="toolbar-divider"></div>

        <div class="toolbar-group">
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('bold') }"
            @click="editor.chain().focus().toggleBold().run()"
            title="Tebal (Ctrl+B)"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path>
              <path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path>
            </svg>
          </button>
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('italic') }"
            @click="editor.chain().focus().toggleItalic().run()"
            title="Miring (Ctrl+I)"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="19" y1="4" x2="10" y2="4"></line>
              <line x1="14" y1="20" x2="5" y2="20"></line>
              <line x1="15" y1="4" x2="9" y2="20"></line>
            </svg>
          </button>
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('strike') }"
            @click="editor.chain().focus().toggleStrike().run()"
            title="Coretan"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M16 4H9a3 3 0 0 0-2.83 4"></path>
              <path d="M14 12a4 4 0 0 1 0 8H6"></path>
              <line x1="4" y1="12" x2="20" y2="12"></line>
            </svg>
          </button>
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('code') }"
            @click="editor.chain().focus().toggleCode().run()"
            title="Inline Code / Variabel"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="16 18 22 12 16 6"></polyline>
              <polyline points="8 6 2 12 8 18"></polyline>
            </svg>
          </button>
        </div>

        <div class="toolbar-divider"></div>

        <div class="toolbar-group">
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('bulletList') }"
            @click="editor.chain().focus().toggleBulletList().run()"
            title="Daftar Poin"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="9" y1="6" x2="20" y2="6"></line>
              <line x1="9" y1="12" x2="20" y2="12"></line>
              <line x1="9" y1="18" x2="20" y2="18"></line>
              <circle cx="4" cy="6" r="2"></circle>
              <circle cx="4" cy="12" r="2"></circle>
              <circle cx="4" cy="18" r="2"></circle>
            </svg>
          </button>
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('orderedList') }"
            @click="editor.chain().focus().toggleOrderedList().run()"
            title="Daftar Nomor"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="10" y1="6" x2="21" y2="6"></line>
              <line x1="10" y1="12" x2="21" y2="12"></line>
              <line x1="10" y1="18" x2="21" y2="18"></line>
              <path d="M4 6h1v4"></path>
              <path d="M4 10h2"></path>
              <path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path>
            </svg>
          </button>
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('blockquote') }"
            @click="editor.chain().focus().toggleBlockquote().run()"
            title="Kutipan / Catatan Penting"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
          </button>
          <button
            type="button"
            class="tb-btn"
            @click="editor.chain().focus().setHorizontalRule().run()"
            title="Garis Pemisah"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="3" y1="12" x2="21" y2="12"></line>
            </svg>
          </button>
        </div>

        <div class="toolbar-divider"></div>

        <div class="toolbar-group">
          <button
            type="button"
            class="tb-btn"
            :disabled="!editor.can().undo()"
            @click="editor.chain().focus().undo().run()"
            title="Undo (Ctrl+Z)"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="1 4 1 10 7 10"></polyline>
              <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
            </svg>
          </button>
          <button
            type="button"
            class="tb-btn"
            :disabled="!editor.can().redo()"
            @click="editor.chain().focus().redo().run()"
            title="Redo (Ctrl+Y)"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="23 4 23 10 17 10"></polyline>
              <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
            </svg>
          </button>
        </div>
      </div>

      <!-- Lembar Dokumen (Langsung Bisa Diklik & Diedit di Sini) -->
      <div class="doc-body-wrapper">
        <div class="doc-paper-sheet">
          <EditorContent :editor="editor" />
        </div>
      </div>

      <!-- Bottom Quick Action Row -->
      <div class="doc-footer-bar">
        <div class="footer-left-info">
          <span class="info-tip">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="16" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            Klik di mana saja pada lembar dokumen di atas untuk langsung mengedit teks, lalu klik <strong>Simpan Dokumen</strong>.
          </span>
        </div>

        <div class="inline-actions-group">
          <button type="button" class="btn-cancel" @click="backToList">
            Kembali ke Daftar
          </button>
          <button
            type="button"
            class="btn-primary btn-save"
            :class="{ 'btn-save-success': saveSuccess }"
            @click="handleSave"
            :disabled="saving"
          >
            <svg v-if="saveSuccess" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <svg v-else-if="!saving" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
              <polyline points="17 21 17 13 7 13 7 21"></polyline>
              <polyline points="7 3 7 8 15 8"></polyline>
            </svg>
            <span v-if="saving">Menyimpan...</span>
            <span v-else-if="saveSuccess">Tersimpan!</span>
            <span v-else>Simpan Dokumen</span>
          </button>
        </div>
      </div>

    </article>

  </div>
</template>

<style scoped>
.templates-container {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 16px;
  grid-column: 1 / -1;
}

.templates-section-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: #ffffff;
  border-radius: var(--radius, 16px);
  border: 1px solid var(--border, #e2e8f0);
}

.templates-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  flex-wrap: wrap;
}

.header-left {
  flex: 1;
  min-width: 250px;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-svg-icon {
  color: #2563eb;
  flex-shrink: 0;
}

.section-heading {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
  color: #0f172a;
}

.section-caption {
  margin: 4px 0 0;
  font-size: 0.84rem;
  color: var(--muted, #64748b);
}

.header-actions-group {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.hidden-file-input {
  display: none;
}

.btn-outline-upload {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  color: #334155;
  border-radius: 8px;
  font-size: 0.84rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-outline-upload:hover:not(:disabled) {
  border-color: #2563eb;
  color: #2563eb;
  background: #eff6ff;
}

.btn-add-template {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  font-size: 0.84rem;
  font-weight: 700;
}

.alert-error-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: #fee2e2;
  color: #991b1b;
  border-radius: 8px;
  font-size: 0.84rem;
  border: 1px solid #fecaca;
}

.filter-controls-row {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.search-input-wrap {
  position: relative;
  width: 100%;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  pointer-events: none;
}

.search-field {
  width: 100%;
  padding: 9px 36px 9px 36px;
  font-size: 0.86rem;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #f8fafc;
  outline: none;
  transition: all 0.15s ease;
}

.search-field:focus {
  background: #ffffff;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.btn-clear-search {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  padding: 4px;
  color: #94a3b8;
  cursor: pointer;
  display: flex;
  align-items: center;
}

.btn-clear-search:hover {
  color: #334155;
}

.category-pills-bar {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 2px;
  scrollbar-width: thin;
}

.cat-pill {
  padding: 5px 12px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #64748b;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.cat-pill:hover {
  background: #e2e8f0;
  color: #1e293b;
}

.cat-pill.active {
  background: #2563eb;
  color: #ffffff;
  border-color: #2563eb;
}

.state-notice {
  text-align: center;
  padding: 30px;
  color: #64748b;
  font-size: 0.88rem;
}

.templates-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 14px;
}

.template-card {
  display: flex;
  flex-direction: column;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 14px;
  transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}

.template-card:hover {
  transform: translateY(-2px);
  border-color: #cbd5e1;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
}

.tcard-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}

.badge-category {
  font-size: 0.72rem;
  font-weight: 700;
  background: #eff6ff;
  color: #1d4ed8;
  padding: 3px 8px;
  border-radius: 6px;
}

.badge-shared {
  font-size: 0.68rem;
  font-weight: 700;
  background: #e8f7ec;
  color: #146c2e;
  padding: 2px 7px;
  border-radius: 999px;
}

.badge-private {
  font-size: 0.68rem;
  font-weight: 700;
  background: #f1f5f9;
  color: #64748b;
  padding: 2px 7px;
  border-radius: 999px;
}

.tcard-title {
  margin: 0 0 6px;
  font-size: 0.95rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.35;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.clickable-title {
  cursor: pointer;
  transition: color 0.15s ease;
}
.clickable-title:hover {
  color: #2563eb;
}

.tcard-preview {
  margin: 0 0 12px;
  font-size: 0.8rem;
  color: #64748b;
  line-height: 1.45;
  height: 38px;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
}

.clickable-preview {
  cursor: pointer;
}
.clickable-preview:hover {
  color: #1e293b;
}

.tcard-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 0.74rem;
  color: #94a3b8;
  margin-top: auto;
  padding-top: 8px;
  border-top: 1px dashed #f1f5f9;
}

.meta-source {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: #2563eb;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.tcard-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
}

.btn-action {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 10px;
  border-radius: 6px;
  font-size: 0.76rem;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.12s ease;
  user-select: none;
}

.btn-view {
  background: #f0fdf4;
  color: #166534;
  border-color: #bbf7d0;
}
.btn-view:hover {
  background: #dcfce7;
  color: #14532d;
}

.btn-copy {
  background: #eff6ff;
  color: #1d4ed8;
  border-color: #dbeafe;
}
.btn-copy:hover {
  background: #dbeafe;
}

.btn-copied {
  background: #dcfce7 !important;
  color: #15803d !important;
  border-color: #bbf7d0 !important;
}

.download-dropdown {
  display: flex;
  align-items: center;
}

.btn-download {
  background: #f8fafc;
  color: #475569;
  border-color: #e2e8f0;
}
.btn-download:hover {
  background: #e2e8f0;
}

.btn-txt-opt {
  padding: 6px 6px;
  font-size: 0.72rem;
  border-left: none;
  border-radius: 0 6px 6px 0;
}

.btn-download:first-child {
  border-radius: 6px 0 0 6px;
}

.btn-delete {
  background: transparent;
  color: #94a3b8;
  padding: 6px 8px;
  margin-left: auto;
}
.btn-delete:hover {
  background: #fee2e2;
  color: #dc2626;
}

.empty-state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  text-align: center;
  border: 2px dashed #e2e8f0;
  border-radius: 12px;
  background: #fafafa;
}

.empty-title {
  margin: 12px 0 4px;
  font-weight: 700;
  font-size: 0.95rem;
  color: #334155;
}

.empty-subtitle {
  margin: 0 0 16px;
  font-size: 0.82rem;
  color: #64748b;
  max-width: 420px;
}

.empty-action-row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: center;
}

/* ══════════════════════════════════════════════════════════════════════════
   TAMPILAN DOKUMEN LANGSUNG BISA DIEDIT (INLINE TANPA POP-UP)
   ══════════════════════════════════════════════════════════════════════════ */
.inline-document-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: #ffffff;
  border-radius: var(--radius, 16px);
  border: 1px solid var(--border, #e2e8f0);
  padding: 24px;
  animation: fadeIn 0.18s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}

.inline-nav-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding-bottom: 14px;
  border-bottom: 1px solid #e2e8f0;
  flex-wrap: wrap;
}

.btn-back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: transparent;
  border: none;
  color: #2563eb;
  font-weight: 700;
  font-size: 0.88rem;
  padding: 6px 10px;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.15s ease;
}
.btn-back-link:hover {
  background: #eff6ff;
}

.inline-actions-group {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-delete-view {
  background: #fee2e2;
  color: #dc2626;
  border-color: #fecaca;
}
.btn-delete-view:hover {
  background: #fecaca;
}

.btn-save {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 18px;
  font-size: 0.85rem;
  font-weight: 700;
  transition: all 0.15s ease;
}

.btn-save-success {
  background: #16a34a !important;
  color: #ffffff !important;
}

.btn-cancel {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #475569;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 0.84rem;
  font-weight: 600;
  cursor: pointer;
}
.btn-cancel:hover {
  background: #f1f5f9;
  color: #0f172a;
}

/* Header Dokumen yang Langsung Editable */
.doc-header-block {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.doc-title-input {
  width: 100%;
  font-size: 1.45rem;
  font-weight: 800;
  color: #0f172a;
  border: 1px solid transparent;
  border-radius: 8px;
  padding: 6px 10px;
  background: transparent;
  outline: none;
  transition: all 0.15s ease;
}
.doc-title-input:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}
.doc-title-input:focus {
  background: #ffffff;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.doc-meta-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  padding: 0 4px;
}

.inline-meta-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.meta-label {
  font-size: 0.78rem;
  font-weight: 600;
  color: #64748b;
}

.inline-select {
  padding: 3px 8px;
  font-size: 0.78rem;
  font-weight: 600;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #f8fafc;
  color: #1e293b;
  outline: none;
}
.inline-select:focus {
  border-color: #2563eb;
  background: #ffffff;
}

.meta-source-small {
  font-size: 0.76rem;
  color: #2563eb;
  font-weight: 600;
  background: #eff6ff;
  padding: 2px 8px;
  border-radius: 6px;
  max-width: 250px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.meta-author-small {
  font-size: 0.76rem;
  color: #64748b;
}

/* Toolbar TipTap */
.editor-toolbar {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 8px 12px;
  background: #f1f5f9;
  border-radius: 10px 10px 0 0;
  border: 1px solid #cbd5e1;
  border-bottom: none;
  flex-wrap: wrap;
}

.toolbar-group {
  display: flex;
  align-items: center;
  gap: 2px;
}

.toolbar-divider {
  width: 1px;
  height: 20px;
  background: #cbd5e1;
  margin: 0 4px;
}

.tb-btn {
  background: transparent;
  border: 1px solid transparent;
  padding: 5px 8px;
  border-radius: 6px;
  font-size: 0.82rem;
  font-weight: 700;
  color: #334155;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 28px;
  height: 28px;
  transition: all 0.12s ease;
}

.tb-btn:hover:not(:disabled) {
  background: #e2e8f0;
  color: #0f172a;
}

.tb-btn.active {
  background: #2563eb;
  color: #ffffff;
}

.tb-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

/* Lembar Kertas Dokumen */
.doc-body-wrapper {
  background: #f8fafc;
  padding: 16px;
  border-radius: 0 0 12px 12px;
  border: 1px solid #cbd5e1;
}

.doc-paper-sheet {
  background: #ffffff;
  padding: 28px 36px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  font-size: 0.95rem;
  line-height: 1.7;
  color: #1e293b;
  min-height: 380px;
  cursor: text;
}

:deep(.tiptap-editor-body) {
  outline: none;
  min-height: 350px;
}

:deep(.tiptap-editor-body h1) {
  font-size: 1.45rem;
  font-weight: 800;
  color: #0f172a;
  margin: 1.2em 0 0.4em;
  border-bottom: 2px solid #e2e8f0;
  padding-bottom: 6px;
}
:deep(.tiptap-editor-body h1:first-child) {
  margin-top: 0;
}

:deep(.tiptap-editor-body h2) {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1e293b;
  margin: 1.1em 0 0.35em;
}

:deep(.tiptap-editor-body h3) {
  font-size: 1.08rem;
  font-weight: 700;
  color: #334155;
  margin: 0.9em 0 0.3em;
}

:deep(.tiptap-editor-body p) {
  margin: 0 0 0.85em;
}

:deep(.tiptap-editor-body ul),
:deep(.tiptap-editor-body ol) {
  padding-left: 24px;
  margin: 0 0 0.85em;
}

:deep(.tiptap-editor-body li) {
  margin-bottom: 0.25em;
}

:deep(.tiptap-editor-body blockquote) {
  border-left: 4px solid #2563eb;
  padding: 10px 16px;
  background: #f8fafc;
  color: #475569;
  font-style: italic;
  border-radius: 0 8px 8px 0;
  margin: 1em 0;
}

:deep(.tiptap-editor-body code) {
  background: #e2e8f0;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 0.85em;
  font-family: monospace;
  color: #b91c1c;
}

:deep(.tiptap-editor-body hr) {
  border: none;
  border-top: 1px solid #cbd5e1;
  margin: 1.2em 0;
}

:deep(.tiptap-editor-body .docx-header-line) {
  background: #eff6ff;
  border-left: 4px solid #2563eb;
  padding: 8px 14px;
  color: #1d4ed8;
  font-weight: 600;
  font-size: 0.82rem;
  border-radius: 0 8px 8px 0;
  margin-bottom: 16px;
}

:deep(.tiptap-editor-body table) {
  border-collapse: collapse;
  width: 100%;
  margin: 1em 0;
}

:deep(.tiptap-editor-body th),
:deep(.tiptap-editor-body td) {
  border: 1px solid #cbd5e1;
  padding: 8px 12px;
  text-align: left;
}

:deep(.tiptap-editor-body th) {
  background: #f8fafc;
  font-weight: 700;
}

.doc-footer-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding-top: 10px;
  border-top: 1px solid #e2e8f0;
  flex-wrap: wrap;
}

.footer-left-info {
  flex: 1;
}

.info-tip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.82rem;
  color: #64748b;
}

@media (max-width: 640px) {
  .templates-header {
    flex-direction: column;
    align-items: stretch;
  }
  .header-actions-group {
    width: 100%;
  }
  .header-actions-group button {
    flex: 1;
  }
  .inline-nav-bar {
    flex-direction: column;
    align-items: stretch;
  }
  .inline-actions-group {
    justify-content: flex-start;
  }
  .doc-paper-sheet {
    padding: 18px;
  }
}
</style>
