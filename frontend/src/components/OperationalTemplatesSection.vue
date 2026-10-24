<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import JSZip from 'jszip';
import mammoth from 'mammoth';
import * as XLSX from 'xlsx';
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

// Ekstrak teks dasar dokumen PDF tanpa dependensi eksternal (pure JS)
// Digunakan murni untuk indexing kata kunci pencarian
async function extractPdfRawText(arrayBuffer) {
  try {
    const bytes = new Uint8Array(arrayBuffer);
    let str = '';
    const len = Math.min(bytes.length, 500000);
    for (let i = 0; i < len; i++) {
      const code = bytes[i];
      if ((code >= 32 && code <= 126) || code === 10 || code === 13) {
        str += String.fromCharCode(code);
      }
    }

    const textPieces = [];
    const streamRegex = /\(([^)]{2,})\)\s*Tj/g;
    let match;
    while ((match = streamRegex.exec(str)) !== null) {
      const clean = match[1].replace(/\\([()\\])/g, '$1').trim();
      if (clean.length > 2) {
        textPieces.push(clean);
      }
    }

    return textPieces.join(' ').trim();
  } catch (err) {
    console.warn('Gagal membaca preview teks PDF:', err);
    return '';
  }
}

// ─── Parser Spreadsheet Excel Menggunakan Library SheetJS (xlsx) ───────────────
function parseExcelWorkbook(arrayBuffer) {
  try {
    const workbook = XLSX.read(arrayBuffer, { type: 'array' });
    const sheetNames = workbook.SheetNames || [];
    const sheets = [];
    let totalRows = 0;
    const allTextPieces = [];

    sheetNames.forEach(sheetName => {
      const ws = workbook.Sheets[sheetName];
      if (!ws) return;

      const rawData = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });
      const filteredRows = rawData.filter(row => Array.isArray(row) && row.some(cell => String(cell).trim() !== ''));

      if (filteredRows.length > 0) {
        totalRows += filteredRows.length;
        filteredRows.forEach(r => allTextPieces.push(r.join(' ')));
      }

      sheets.push({
        name: sheetName,
        rows: filteredRows
      });
    });

    return {
      sheetNames,
      sheets,
      totalRows,
      plainText: allTextPieces.join('\n')
    };
  } catch (err) {
    console.error('Gagal mem-parsing workbook Excel:', err);
    return {
      sheetNames: ['Sheet1'],
      sheets: [{ name: 'Sheet1', rows: [] }],
      totalRows: 0,
      plainText: ''
    };
  }
}

// ─── Component State ──────────────────────────────────────────────────────────
// ─── Component State & Multi-Tab System (Ala Tab Browser) ─────────────────────
// Mode tab aktif: 'catalog' (koleksi template) | 'doc-...' | 'draft-...'
// Tab 'catalog' diintegrasikan ke openTabs sehingga posisinya bisa digeser/ditukar (reorderable)
const openTabs = ref([
  {
    id: 'catalog',
    title: 'Katalog Template',
    type: 'catalog',
    isPermanent: true
  }
]);
const activeTabId = ref('catalog');
const activeView = computed(() => activeTabId.value === 'catalog' ? 'list' : 'document');
const tabsScrollEl = ref(null);
let draftCounter = 1;

// ─── Drag & Drop Reordering State untuk Tab (Bisa Digeser Sesuai Kebutuhan) ───
const draggedTabIndex = ref(null);
const dropTargetIndex = ref(null);
const dropPosition = ref(null); // 'before' | 'after'
let isDraggingTab = false;

// Kontrol Scroll Geser Horizontal Bilah Tab
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

function checkTabScroll() {
  if (!tabsScrollEl.value) return;
  const { scrollLeft, scrollWidth, clientWidth } = tabsScrollEl.value;
  canScrollLeft.value = scrollLeft > 6;
  canScrollRight.value = scrollLeft + clientWidth < scrollWidth - 6;
}

function scrollTabs(direction) {
  if (!tabsScrollEl.value) return;
  const delta = direction === 'left' ? -220 : 220;
  tabsScrollEl.value.scrollBy({ left: delta, behavior: 'smooth' });
  setTimeout(checkTabScroll, 250);
}

// Gulir horizontal bilah tab menggunakan scroll mouse / touchpad
function handleTabsWheel(e) {
  if (e.deltaY !== 0 && tabsScrollEl.value) {
    e.preventDefault();
    tabsScrollEl.value.scrollLeft += e.deltaY;
    checkTabScroll();
  }
}

// Event Handler Drag & Drop Reordering Tab
function onTabDragStart(e, index) {
  draggedTabIndex.value = index;
  isDraggingTab = true;
  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/plain', String(index));
}

function onTabDragOver(e, index) {
  e.preventDefault();
  e.dataTransfer.dropEffect = 'move';

  if (draggedTabIndex.value === null || draggedTabIndex.value === index) {
    dropTargetIndex.value = null;
    dropPosition.value = null;
    return;
  }

  const rect = e.currentTarget.getBoundingClientRect();
  const midpoint = rect.left + rect.width / 2;
  const pos = e.clientX < midpoint ? 'before' : 'after';

  if (
    (pos === 'before' && index === draggedTabIndex.value + 1) ||
    (pos === 'after' && index === draggedTabIndex.value - 1)
  ) {
    dropTargetIndex.value = null;
    dropPosition.value = null;
    return;
  }

  dropTargetIndex.value = index;
  dropPosition.value = pos;
}

function onTabDragLeave(e, index) {
  if (dropTargetIndex.value === index && e.relatedTarget && !e.currentTarget.contains(e.relatedTarget)) {
    dropTargetIndex.value = null;
    dropPosition.value = null;
  }
}

function onTabDrop(e, index) {
  e.preventDefault();
  if (draggedTabIndex.value === null || draggedTabIndex.value === index) {
    cleanupDragState();
    return;
  }

  const fromIndex = draggedTabIndex.value;
  let toIndex = index;

  if (dropPosition.value === 'after') {
    toIndex = fromIndex < index ? index : index + 1;
  } else if (dropPosition.value === 'before') {
    toIndex = fromIndex < index ? index - 1 : index;
  }

  if (fromIndex !== toIndex && toIndex >= 0 && toIndex < openTabs.value.length) {
    const [movedTab] = openTabs.value.splice(fromIndex, 1);
    openTabs.value.splice(toIndex, 0, movedTab);
  }

  cleanupDragState();
  nextTick(checkTabScroll);
}

function onTabDragEnd() {
  cleanupDragState();
}

function cleanupDragState() {
  draggedTabIndex.value = null;
  dropTargetIndex.value = null;
  dropPosition.value = null;
  setTimeout(() => {
    isDraggingTab = false;
  }, 100);
}

const templates = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const selectedCategory = ref('Semua');
const fileInput = ref(null);
const fileProcessing = ref(false);
const uploadError = ref('');
const uploadSuccess = ref('');

// ── Modal Konfirmasi Kustom (Menggantikan confirm/window.confirm agar tidak menampilkan header URL browser '127.0.0.1:5173 says') ──
const confirmModal = ref({
  isOpen: false,
  title: 'Konfirmasi Tindakan',
  message: '',
  confirmText: 'Ya, Hapus',
  cancelText: 'Batal',
  type: 'danger', // 'danger' | 'warning'
  onConfirm: null
});

function openConfirmModal({ title, message, confirmText = 'Ya, Hapus', cancelText = 'Batal', type = 'danger', onConfirm }) {
  confirmModal.value = {
    isOpen: true,
    title,
    message,
    confirmText,
    cancelText,
    type,
    onConfirm
  };
}

function handleConfirmAction() {
  const cb = confirmModal.value.onConfirm;
  confirmModal.value.isOpen = false;
  confirmModal.value.onConfirm = null;
  if (typeof cb === 'function') {
    cb();
  }
}

function handleCancelConfirm() {
  confirmModal.value.isOpen = false;
  confirmModal.value.onConfirm = null;
}

// ── Floating Popup Toast Notifikasi Kustom (Pengganti banner / alert bawaan browser tanpa source link) ──
const toastPopup = ref({
  isOpen: false,
  type: 'success', // 'success' | 'error' | 'info'
  title: 'Berhasil',
  message: '',
  timeoutId: null
});

function showPopupToast({ type = 'success', title = 'Berhasil', message = '' }) {
  if (toastPopup.value.timeoutId) {
    clearTimeout(toastPopup.value.timeoutId);
  }
  toastPopup.value = {
    isOpen: true,
    type,
    title,
    message,
    timeoutId: setTimeout(() => {
      toastPopup.value.isOpen = false;
    }, 4500)
  };
}

function closePopupToast() {
  if (toastPopup.value.timeoutId) {
    clearTimeout(toastPopup.value.timeoutId);
  }
  toastPopup.value.isOpen = false;
}

// State data dokumen aktif (langsung diedit & disalin)
const form = ref({
  id: null,
  title: '',
  category: 'Umum',
  content: '<p></p>',
  original_filename: null,
  file_path: null,
  author_name: '',
  updated_at: null
});

// Deteksi apakah dokumen yang sedang aktif adalah dokumen patokan PDF resmi
const isDocumentPdf = computed(() => {
  return form.value.original_filename?.toLowerCase().endsWith('.pdf') || false;
});

// Deteksi apakah dokumen yang sedang aktif adalah file spreadsheet Excel (.xlsx, .xls, .csv)
const isDocumentExcel = computed(() => {
  const fn = form.value.original_filename?.toLowerCase() || '';
  return fn.endsWith('.xlsx') || fn.endsWith('.xls') || fn.endsWith('.csv');
});

// State khusus Spreadsheet Excel
const excelData = ref(null); // { sheetNames: [], sheets: [{ name, rows: [] }], totalRows: 0 }
const activeExcelSheetIndex = ref(0);
const excelSearchQuery = ref('');
const isExcelCopied = ref(false);
const copiedRowIndex = ref(null);
const excelLoading = ref(false);

// Sheet aktif yang sedang dipilih pada dokumen Excel
const currentActiveSheet = computed(() => {
  if (!excelData.value?.sheets?.length) return null;
  const idx = Math.min(activeExcelSheetIndex.value, excelData.value.sheets.length - 1);
  return excelData.value.sheets[idx] || null;
});

// Daftar kolom header tabel Excel
const excelHeaderColumns = computed(() => {
  if (!currentActiveSheet.value?.rows?.length) return [];
  const firstRow = currentActiveSheet.value.rows[0];
  if (!Array.isArray(firstRow)) return [];

  let maxCols = firstRow.length;
  currentActiveSheet.value.rows.slice(0, 15).forEach(r => {
    if (Array.isArray(r) && r.length > maxCols) maxCols = r.length;
  });

  const headers = [];
  for (let i = 0; i < maxCols; i++) {
    const val = firstRow[i] !== undefined && firstRow[i] !== null ? String(firstRow[i]).trim() : '';
    headers.push(val || `Kolom ${i + 1}`);
  }
  return headers;
});

// Baris data Excel yang sudah difilter berdasarkan pencarian
const filteredExcelRows = computed(() => {
  if (!currentActiveSheet.value?.rows?.length) return [];
  const rows = currentActiveSheet.value.rows.length > 1
    ? currentActiveSheet.value.rows.slice(1)
    : currentActiveSheet.value.rows;

  if (!excelSearchQuery.value.trim()) {
    return rows;
  }

  const q = excelSearchQuery.value.toLowerCase().trim();
  return rows.filter(row => {
    if (!Array.isArray(row)) return false;
    return row.some(cell => String(cell ?? '').toLowerCase().includes(q));
  });
});

// Salin seluruh isi sheet aktif ke clipboard dalam format TSV (kompatibel Excel & Spreadsheet)
async function copyActiveExcelSheet() {
  if (!currentActiveSheet.value?.rows?.length) return;
  const rows = currentActiveSheet.value.rows;
  const tsv = rows.map(r => Array.isArray(r) ? r.join('\t') : String(r)).join('\n');
  try {
    await navigator.clipboard.writeText(tsv);
    isExcelCopied.value = true;
    setTimeout(() => {
      isExcelCopied.value = false;
    }, 2000);
  } catch (err) {
    console.error('Gagal menyalin data sheet Excel:', err);
  }
}

// Salin satu baris tertentu ke clipboard
async function copyExcelRow(row, rowIdx) {
  try {
    const line = Array.isArray(row) ? row.join('\t') : String(row);
    await navigator.clipboard.writeText(line);
    copiedRowIndex.value = rowIdx;
    setTimeout(() => {
      if (copiedRowIndex.value === rowIdx) {
        copiedRowIndex.value = null;
      }
    }, 1500);
  } catch (err) {
    console.error('Gagal menyalin baris:', err);
  }
}

// Unduh file asli Excel
async function downloadActiveExcel() {
  const currentTab = openTabs.value.find(t => t.id === activeTabId.value);
  if (currentTab?.excelBlobUrl) {
    const a = document.createElement('a');
    a.href = currentTab.excelBlobUrl;
    a.download = form.value.original_filename || `${form.value.title || 'dokumen'}.xlsx`;
    a.click();
    return;
  }
  if (form.value.id) {
    await downloadOriginalFile({
      id: form.value.id,
      original_filename: form.value.original_filename || `${form.value.title || 'dokumen'}.xlsx`
    });
  }
}

// Object URL dan loading state untuk menampilkan PDF asli
const pdfBlobUrl = ref('');
const pdfLoading = ref(false);

const saving = ref(false);
const saveError = ref('');
const saveSuccess = ref(false);

// State feedback tombol salin
const copiedId = ref(null);
const isDocumentCopied = ref(false);

// State menu aksi dropdown titik tiga (kebab menu) kartu
const activeMenuId = ref(null);

function toggleCardMenu(event, id) {
  event.stopPropagation();
  activeMenuId.value = activeMenuId.value === id ? null : id;
}

function handleGlobalClick() {
  if (activeMenuId.value) {
    activeMenuId.value = null;
  }
}

function getCategoryBadgeClass(category) {
  switch (category) {
    case 'Script Respon': return 'badge-cat-script';
    case 'Eskalasi':     return 'badge-cat-eskalasi';
    case 'Billing':      return 'badge-cat-billing';
    case 'Teknis':       return 'badge-cat-teknis';
    case 'SOP':          return 'badge-cat-sop';
    default:             return 'badge-cat-umum';
  }
}

function getFileTypeBadge(filename) {
  if (!filename) return null;
  const ext = filename.split('.').pop()?.toLowerCase();
  if (ext === 'docx' || ext === 'doc') return 'DOCX';
  if (ext === 'pdf') return 'PDF';
  if (ext === 'txt') return 'TXT';
  if (ext === 'md') return 'MD';
  if (ext === 'xlsx' || ext === 'xls') return 'XLSX';
  if (ext === 'csv') return 'CSV';
  return null;
}

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

/**
 * Membersihkan kerapatan spasi & baris teks template agar tidak renggang saat di-copy paste ke Live Chat / WA / Tiket.
 * - Mengelompokkan item/field formulir yang berurutan (misal: "Nama terdaftar:", "Detail pelaporan:") dengan single newline (\n).
 * - Menjaga jeda antar paragraf narasi / salam pembuka / penutup dengan single blank line (\n\n).
 * - Menghilangkan baris kosong berlebih (> 2 newline).
 */
function cleanTemplateSpacing(text) {
  if (!text || typeof text !== 'string') return '';

  // 1. Normalisasi line endings & trim trailing whitespace pada setiap baris
  const normalized = text
    .replace(/\r\n/g, '\n')
    .replace(/\r/g, '\n')
    .replace(/[ \t]+$/gm, '')
    .trim();

  if (!normalized) return '';

  // 2. Pecah per baris
  const rawLines = normalized.split('\n');
  const resultLines = [];

  // Helper: Deteksi apakah baris adalah field item, bullet, atau label formulir
  const isFieldOrListItem = (line) => {
    if (!line) return false;
    const trimmed = line.trim();
    if (!trimmed) return false;

    // Bullet / numbering (misal: - item, * item, 1. item, a. item)
    if (/^[-*•–—]\s+/.test(trimmed) || /^\d+[.)]\s+/.test(trimmed) || /^[a-zA-Z][.)]\s+/.test(trimmed)) {
      return true;
    }

    // Label yang diakhiri tanda titik dua (misal: "Nama terdaftar:", "Foto modem tampak depan dan belakang terlihat kode SN:")
    if (/:[- ]*$/.test(trimmed)) {
      return true;
    }

    // Label dengan format "Field : value" atau "Field:" di awal baris pendek (< 90 karakter)
    if (/^[A-Za-z0-9\s()\/_-]{2,35}\s*:\s*/.test(trimmed) && trimmed.length < 90) {
      return true;
    }

    // Baris penutup salam (misal: "Salam hangat,", "Hormat kami,")
    if (/^(salam hangat|hormat kami|terima kasih|best regards|regards)[,.]?$/i.test(trimmed)) {
      return true;
    }

    return false;
  };

  // Helper: Deteksi apakah baris adalah pengantar list (misal: "...sebagai berikut:", "...data berikut:")
  const isListIntroducer = (line) => {
    if (!line) return false;
    const trimmed = line.trim();
    return /:\s*$/.test(trimmed) && trimmed.length > 20;
  };

  let wasPrevEmpty = false;

  for (let i = 0; i < rawLines.length; i++) {
    const line = rawLines[i].trim();

    if (!line) {
      wasPrevEmpty = true;
      continue;
    }

    if (resultLines.length === 0) {
      resultLines.push(line);
      wasPrevEmpty = false;
      continue;
    }

    const prevLine = resultLines[resultLines.length - 1];

    // Tentukan apakah antara prevLine dan line saat ini perlu blank line (empty line)
    let shouldHaveBlankLine = wasPrevEmpty;

    // KONDISI KHUSUS PERBAIKAN SPACING:
    // 1. Jika prevLine adalah pengantar list ("...data sebagai berikut:"), baris pertama list TIDAK boleh diberi jeda kosong!
    if (isListIntroducer(prevLine) && isFieldOrListItem(line)) {
      shouldHaveBlankLine = false;
    }
    // 2. Jika prevLine dan line saat ini sama-sama field item / list item (misal: "Nama terdaftar:" dan "Detail pelaporan:"),
    //    jangan beri blank line agar rapat dan rapi seperti template aslinya!
    else if (isFieldOrListItem(prevLine) && isFieldOrListItem(line)) {
      shouldHaveBlankLine = false;
    }
    // 3. Jika baris ini adalah salam penutup atau instansi (misal: "Customer Care ICONNET" setelah "Salam hangat,")
    else if (/^(salam hangat|hormat kami)[,.]?$/i.test(prevLine)) {
      shouldHaveBlankLine = false;
    }

    if (shouldHaveBlankLine) {
      resultLines.push(''); // Sisipkan 1 baris kosong antar paragraf
    }

    resultLines.push(line);
    wasPrevEmpty = false;
  }

  return resultLines.join('\n');
}

// Helper untuk menyalin isi teks murni template ke clipboard dengan spacing bersih
function extractPlainText(html) {
  if (!html) return '';

  const temp = document.createElement('div');
  temp.innerHTML = html;

  // Ganti <br> dengan newline
  temp.querySelectorAll('br').forEach(br => br.replaceWith('\n'));

  // Beri newline pada elemen blok
  const blockSelectors = 'p, h1, h2, h3, h4, h5, h6, li, tr, blockquote';
  temp.querySelectorAll(blockSelectors).forEach(el => {
    if (!el.textContent.trim()) {
      el.replaceWith('\n');
    } else {
      el.append('\n');
    }
  });

  const rawText = temp.textContent || temp.innerText || '';
  return cleanTemplateSpacing(rawText);
}

// Inisialisasi Tiptap Editor langsung di halaman
const editor = useEditor({
  content: '<p></p>',
  extensions: [StarterKit],
  editorProps: {
    attributes: {
      class: 'tiptap-editor-body'
    },
    // Pastikan saat agent copy text (Ctrl+C) dari dalam editor, spacing naskah rapat & rapi
    clipboardTextSerializer: (slice) => {
      try {
        const raw = slice.content.textBetween(0, slice.content.size, '\n');
        return cleanTemplateSpacing(raw);
      } catch {
        return slice.content.textBetween(0, slice.content.size, '\n\n');
      }
    }
  },
  onUpdate: () => {
    debouncedRefreshHeadings();
    triggerAutoSave();
  }
});

// ─── State Navigasi Heading ala Microsoft Word (Sebelah Kiri) ─────────────────
const showHeadingNav = ref(typeof window !== 'undefined' ? window.innerWidth > 1024 : true);
const isNavDrawerExplicitlyOpened = ref(false);
const isNavPinned = ref(true); // Default true: navigasi tetap terbuka saat judul diklik
const navSearchQuery = ref('');
const activeHeadingId = ref('');
const collapsedHeadings = ref(new Set());
const headingsList = ref([]);
const navActiveTab = ref('headings'); // 'headings' | 'stats'
const copiedHeadingId = ref(null);
let refreshHeadingsTimeout = null;
let isScrollingProgrammatically = false;

function toggleHeadingNav() {
  showHeadingNav.value = !showHeadingNav.value;
  isNavDrawerExplicitlyOpened.value = showHeadingNav.value;
}

function openHeadingNavDrawer() {
  showHeadingNav.value = true;
  isNavDrawerExplicitlyOpened.value = true;
}

function closeHeadingNavDrawer() {
  showHeadingNav.value = false;
  isNavDrawerExplicitlyOpened.value = false;
}

// Lompat ke heading dari Quick-Jump Dropdown di atas template
function onQuickJumpSelect(headingId) {
  if (!headingId) return;
  const target = headingsList.value.find(h => h.id === headingId);
  if (target) {
    scrollToHeading(target);
  }
}

// ─── State Auto Save & Shortcut Ctrl+S ────────────────────────────────────────
const autoSaveStatus = ref(''); // '' | 'unsaved' | 'saving' | 'saved' | 'error'
const lastSavedTime = ref(null);
let autoSaveTimeout = null;

// Picu penyimpanan otomatis setelah jeda mengetik 1.8 detik
function triggerAutoSave() {
  if (activeView.value !== 'document' || isDocumentPdf.value || isDocumentExcel.value) return;

  const currentTab = openTabs.value.find(t => t.id === activeTabId.value);
  if (currentTab) {
    currentTab.isDirty = true;
    currentTab.form.title = form.value.title;
    currentTab.title = form.value.title || (currentTab.type === 'draft' ? 'Draft Baru' : 'Template');
  }

  // Jangan auto-save jika judul belum diisi sama sekali
  if (!form.value.title.trim()) return;

  autoSaveStatus.value = 'unsaved';
  if (currentTab) currentTab.autoSaveStatus = 'unsaved';

  if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
  autoSaveTimeout = setTimeout(() => {
    handleSave(true); // silent auto-save
  }, 1800);
}

// Shortcut Keyboard: Ctrl+S atau Cmd+S untuk simpan seketika
function handleKeydown(e) {
  if (activeView.value === 'document' && !isDocumentPdf.value && !isDocumentExcel.value) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
      e.preventDefault();
      handleSave(false);
    }
  }
}

// Ekstrak & index seluruh heading (H1, H2, H3, H4) di dalam editor TipTap
function refreshHeadings() {
  nextTick(() => {
    const editorEl = document.querySelector('.tiptap-editor-body');
    if (!editorEl) {
      headingsList.value = [];
      return;
    }

    const elements = editorEl.querySelectorAll('h1, h2, h3, h4');
    const items = [];

    elements.forEach((el, index) => {
      const id = `doc-heading-${index}`;
      el.id = id;
      el.setAttribute('data-heading-id', id);

      const tag = el.tagName.toUpperCase();
      const level = parseInt(tag.replace('H', ''), 10) || 1;
      const rawText = (el.innerText || el.textContent || '').trim();

      items.push({
        index,
        id,
        level,
        tag,
        text: rawText || `(Heading ${level} tanpa judul)`,
        hasChildren: false
      });
    });

    // Deteksi relasi parent-child: jika heading berikutnya memiliki level lebih dalam
    for (let i = 0; i < items.length; i++) {
      const current = items[i];
      const next = items[i + 1];
      current.hasChildren = !!(next && next.level > current.level);
    }

    headingsList.value = items;

    // Defaultkan activeHeadingId jika belum terpilih
    if (items.length > 0 && (!activeHeadingId.value || !items.some(h => h.id === activeHeadingId.value))) {
      activeHeadingId.value = items[0].id;
    }
  });
}

function debouncedRefreshHeadings() {
  if (refreshHeadingsTimeout) clearTimeout(refreshHeadingsTimeout);
  refreshHeadingsTimeout = setTimeout(() => {
    refreshHeadings();
  }, 200);
}

// Cek apakah heading disembunyikan karena heading induknya di-collapse
function isHeadingHidden(item) {
  if (navSearchQuery.value.trim()) {
    return false; // Tampilkan semua saat pencarian aktif
  }
  for (let i = item.index - 1; i >= 0; i--) {
    const prev = headingsList.value[i];
    if (prev.level < item.level) {
      if (collapsedHeadings.value.has(prev.id)) {
        return true;
      }
    }
    if (prev.level === 1 && item.level > 1) {
      if (collapsedHeadings.value.has(prev.id)) {
        return true;
      }
      break;
    }
  }
  return false;
}

// Filter heading berdasarkan query pencarian atau status collapse
const filteredHeadings = computed(() => {
  const q = navSearchQuery.value.trim().toLowerCase();
  if (!q) {
    return headingsList.value.filter(h => !isHeadingHidden(h));
  }
  return headingsList.value.filter(h => h.text.toLowerCase().includes(q));
});

// Toggle collapse/expand pada sub-heading tertentu
function toggleCollapseHeading(id, e) {
  if (e) e.stopPropagation();
  const nextSet = new Set(collapsedHeadings.value);
  if (nextSet.has(id)) {
    nextSet.delete(id);
  } else {
    nextSet.add(id);
  }
  collapsedHeadings.value = nextSet;
}

function expandAllHeadings() {
  collapsedHeadings.value = new Set();
}

function collapseAllHeadings() {
  const set = new Set();
  headingsList.value.forEach(h => {
    if (h.hasChildren) set.add(h.id);
  });
  collapsedHeadings.value = set;
}

// Melompat ke posisi heading dengan efek kilau animasi highlight
function scrollToHeading(item) {
  activeHeadingId.value = item.id;
  isScrollingProgrammatically = true;

  let el = document.getElementById(item.id);
  if (!el) {
    const editorEl = document.querySelector('.tiptap-editor-body');
    const all = editorEl?.querySelectorAll('h1, h2, h3, h4');
    if (all && all[item.index]) {
      el = all[item.index];
      el.id = item.id;
    }
  }

  if (el) {
    const rect = el.getBoundingClientRect();
    const topOffset = 85;
    const targetY = window.pageYOffset + rect.top - topOffset;

    window.scrollTo({
      top: Math.max(0, targetY),
      behavior: 'smooth'
    });

    el.classList.remove('heading-jump-highlight');
    void el.offsetWidth;
    el.classList.add('heading-jump-highlight');
    setTimeout(() => {
      el.classList.remove('heading-jump-highlight');
      isScrollingProgrammatically = false;
    }, 1800);
  } else {
    isScrollingProgrammatically = false;
  }

  // Pada tablet / mobile / tab sempit (<= 1024px): sembunyikan drawer HANYA jika TIDAK di-pin
  if (typeof window !== 'undefined' && window.innerWidth <= 1024 && !isNavPinned.value) {
    closeHeadingNavDrawer();
  }
}

// Salin judul heading tertentu
async function copyHeadingText(item) {
  try {
    await navigator.clipboard.writeText(item.text);
    copiedHeadingId.value = item.id;
    setTimeout(() => {
      if (copiedHeadingId.value === item.id) {
        copiedHeadingId.value = null;
      }
    }, 1500);
  } catch (err) {
    console.error('Gagal menyalin judul:', err);
  }
}

// ScrollSpy: Mendeteksi heading aktif saat dokumen digulir
function handleDocumentScroll() {
  if (isScrollingProgrammatically || !headingsList.value.length || activeView.value !== 'document' || isDocumentPdf.value) {
    return;
  }

  const scrollPos = window.scrollY + 120;
  let currentId = null;

  for (let i = 0; i < headingsList.value.length; i++) {
    const h = headingsList.value[i];
    const el = document.getElementById(h.id);
    if (el) {
      if (el.offsetTop <= scrollPos) {
        currentId = h.id;
      } else {
        break;
      }
    }
  }

  if (currentId && currentId !== activeHeadingId.value) {
    activeHeadingId.value = currentId;
    const navItemEl = document.querySelector(`[data-nav-id="${currentId}"]`);
    if (navItemEl) {
      navItemEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
  }
}

// Statistik Distribusi Heading
const headingStats = computed(() => {
  const total = headingsList.value.length;
  const h1Count = headingsList.value.filter(h => h.level === 1).length;
  const h2Count = headingsList.value.filter(h => h.level === 2).length;
  const h3Count = headingsList.value.filter(h => h.level >= 3).length;
  return { total, h1Count, h2Count, h3Count };
});

// Statistik Dokumen Keseluruhan
const documentStats = computed(() => {
  if (!editor.value) {
    return { wordCount: 0, charCount: 0, readTimeMinutes: 1 };
  }
  const text = editor.value.getText() || '';
  const trimmed = text.trim();
  const words = trimmed ? trimmed.split(/\s+/).length : 0;
  const chars = text.length;
  const readTime = Math.max(1, Math.ceil(words / 200));
  return {
    wordCount: words,
    charCount: chars,
    readTimeMinutes: readTime
  };
});

watch(activeView, (newVal) => {
  if (newVal === 'document') {
    nextTick(() => {
      refreshHeadings();
    });
  }
});

function handleWindowResize() {
  checkTabScroll();
  if (typeof window !== 'undefined') {
    if (window.innerWidth <= 1024 && showHeadingNav.value && !isNavDrawerExplicitlyOpened.value) {
      showHeadingNav.value = false;
    } else if (window.innerWidth > 1024 && !showHeadingNav.value && !isNavDrawerExplicitlyOpened.value) {
      showHeadingNav.value = true;
    }
  }
}

onMounted(() => {
  fetchTemplates();
  window.addEventListener('click', handleGlobalClick);
  window.addEventListener('scroll', handleDocumentScroll, { passive: true });
  window.addEventListener('keydown', handleKeydown);
  window.addEventListener('resize', handleWindowResize);
  nextTick(checkTabScroll);
});

onBeforeUnmount(() => {
  window.removeEventListener('click', handleGlobalClick);
  window.removeEventListener('scroll', handleDocumentScroll);
  window.removeEventListener('keydown', handleKeydown);
  window.removeEventListener('resize', handleWindowResize);
  if (autoSaveTimeout) {
    clearTimeout(autoSaveTimeout);
  }
  if (refreshHeadingsTimeout) {
    clearTimeout(refreshHeadingsTimeout);
  }
  if (pdfBlobUrl.value) {
    URL.revokeObjectURL(pdfBlobUrl.value);
  }
  openTabs.value.forEach(tab => {
    if (tab.pdfBlobUrl) {
      URL.revokeObjectURL(tab.pdfBlobUrl);
    }
  });
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

// ─── Multi-Tab Logic: Berpindah, Membuka, dan Menutup Tab ──────────────────
async function switchTab(targetId) {
  if (isDraggingTab) return;
  if (activeTabId.value === targetId) return;

  // 1. Sinkronisasi tab lama sebelum ditinggalkan
  if (activeTabId.value !== 'catalog') {
    const currentTab = openTabs.value.find(t => t.id === activeTabId.value);
    if (currentTab) {
      if (currentTab.type !== 'pdf') {
        currentTab.form.content = editor.value ? editor.value.getHTML() : currentTab.form.content;
      }
      currentTab.form.title = form.value.title;
      currentTab.title = form.value.title || currentTab.title;
      currentTab.form.category = form.value.category;
      currentTab.autoSaveStatus = autoSaveStatus.value;
      currentTab.lastSavedTime = lastSavedTime.value;
      currentTab.activeHeadingId = activeHeadingId.value;
      currentTab.scrollPosition = window.scrollY;
    }
  }

  // 2. Alihkan tab aktif
  activeTabId.value = targetId;

  // 3. Jika berpindah ke katalog
  if (targetId === 'catalog') {
    window.scrollTo({ top: 0, behavior: 'smooth' });
    nextTick(checkTabScroll);
    return;
  }

  // 4. Jika berpindah ke tab dokumen
  const targetTab = openTabs.value.find(t => t.id === targetId);
  if (!targetTab) {
    activeTabId.value = 'catalog';
    return;
  }

  // Muat state tab ke reactive form
  form.value = {
    id: targetTab.form.id,
    title: targetTab.form.title,
    category: targetTab.form.category || 'Umum',
    content: targetTab.form.content || '<p></p>',
    original_filename: targetTab.form.original_filename,
    file_path: targetTab.form.file_path,
    author_name: targetTab.form.author_name || 'Agent',
    updated_at: targetTab.form.updated_at
  };

  autoSaveStatus.value = targetTab.autoSaveStatus || '';
  lastSavedTime.value = targetTab.lastSavedTime || null;
  saveError.value = '';
  saveSuccess.value = false;

  // Tangani tampilan PDF
  if (targetTab.type === 'pdf') {
    pdfBlobUrl.value = targetTab.pdfBlobUrl || '';
    pdfLoading.value = targetTab.pdfLoading || false;

    if (!targetTab.pdfBlobUrl && targetTab.form.id) {
      targetTab.pdfLoading = true;
      pdfLoading.value = true;
      try {
        const res = await api.get(`/operational-templates/${targetTab.form.id}/file`, {
          responseType: 'blob'
        });
        const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
        targetTab.pdfBlobUrl = url;
        if (activeTabId.value === targetId) {
          pdfBlobUrl.value = url;
        }
      } catch (err) {
        console.error('Gagal mengambil PDF asli:', err);
      } finally {
        targetTab.pdfLoading = false;
        if (activeTabId.value === targetId) {
          pdfLoading.value = false;
        }
      }
    }
  } else if (targetTab.type === 'xlsx') {
    // Tangani tampilan Spreadsheet Excel
    excelData.value = targetTab.excelData || null;
    activeExcelSheetIndex.value = targetTab.activeExcelSheetIndex || 0;
    excelSearchQuery.value = '';

    if (!targetTab.excelData && targetTab.form.id) {
      excelLoading.value = true;
      try {
        const res = await api.get(`/operational-templates/${targetTab.form.id}/file`, {
          responseType: 'blob'
        });
        const blob = new Blob([res.data]);
        targetTab.excelBlobUrl = URL.createObjectURL(blob);
        const arrayBuffer = await blob.arrayBuffer();
        const parsed = parseExcelWorkbook(arrayBuffer);
        targetTab.excelData = parsed;
        if (activeTabId.value === targetId) {
          excelData.value = parsed;
        }
      } catch (err) {
        console.error('Gagal memuat file Excel dari server:', err);
      } finally {
        excelLoading.value = false;
      }
    }
  } else {
    // Non-PDF / Non-Excel (Word / TXT): Isi editor TipTap dan muat navigasi heading
    nextTick(() => {
      if (editor.value) {
        editor.value.setEditable(true);
        editor.value.commands.setContent(targetTab.form.content || '<p></p>', false);
        refreshHeadings();
        if (targetTab.activeHeadingId) {
          activeHeadingId.value = targetTab.activeHeadingId;
        }
      }
    });
  }

  // Pulihkan posisi scroll
  nextTick(() => {
    if (targetTab.scrollPosition) {
      window.scrollTo({ top: targetTab.scrollPosition, behavior: 'instant' });
    } else {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    checkTabScroll();
  });
}

// Buka dokumen dari katalog (klik kartu)
async function openDocument(template) {
  // Cek apakah dokumen ini sudah dibuka dalam salah satu tab
  const existingTab = openTabs.value.find(t => t.templateId === template.id);
  if (existingTab) {
    await switchTab(existingTab.id);
    return;
  }

  const isPdf = template.original_filename?.toLowerCase().endsWith('.pdf') || false;
  const isXlsx = template.original_filename?.toLowerCase().match(/\.(xlsx|xls|csv)$/) || false;
  const isDocx = template.original_filename?.toLowerCase().match(/\.(docx|doc)$/) || false;
  const tabType = isPdf ? 'pdf' : (isXlsx ? 'xlsx' : (isDocx ? 'docx' : 'txt'));

  const tabId = `doc-${template.id}`;
  const newTab = {
    id: tabId,
    title: template.title || 'Template Tanpa Judul',
    type: tabType,
    templateId: template.id,
    form: {
      id: template.id,
      title: template.title || '',
      category: template.category || 'Umum',
      content: template.content || '',
      original_filename: template.original_filename || null,
      file_path: template.file_path || null,
      author_name: template.user?.name || 'Agent',
      updated_at: template.updated_at
    },
    excelData: null,
    activeExcelSheetIndex: 0,
    excelBlobUrl: '',
    pdfBlobUrl: '',
    pdfLoading: false,
    autoSaveStatus: '',
    lastSavedTime: null,
    isDirty: false,
    activeHeadingId: '',
    scrollPosition: 0
  };

  openTabs.value.push(newTab);
  await switchTab(tabId);
}

// Buka lembar draft baru dalam tab baru
async function openCreate() {
  const draftId = `draft-${Date.now()}`;
  const draftNum = draftCounter++;
  const title = `Draft Baru #${draftNum}`;

  const newTab = {
    id: draftId,
    title,
    type: 'draft',
    templateId: null,
    form: {
      id: null,
      title,
      category: 'Umum',
      content: '<p></p>',
      original_filename: null,
      file_path: null,
      author_name: auth.user?.name || 'Agent',
      updated_at: new Date()
    },
    pdfBlobUrl: '',
    pdfLoading: false,
    autoSaveStatus: '',
    lastSavedTime: null,
    isDirty: false,
    activeHeadingId: '',
    scrollPosition: 0
  };

  openTabs.value.push(newTab);
  await switchTab(draftId);

  nextTick(() => {
    if (editor.value) {
      editor.value.commands.focus();
    }
  });
}

// Eksekusi penutupan tab
function executeCloseTab(tabId) {
  const idx = openTabs.value.findIndex(t => t.id === tabId);
  if (idx === -1) return;

  const tabToClose = openTabs.value[idx];

  // Bersihkan Blob URL jika ada
  if (tabToClose.pdfBlobUrl) {
    URL.revokeObjectURL(tabToClose.pdfBlobUrl);
  }

  // Jika tab yang ditutup sedang aktif, pindahkan ke tab terdekat atau ke katalog
  if (activeTabId.value === tabId) {
    const remainingDocTabs = openTabs.value.filter(t => t.id !== tabId && t.id !== 'catalog');
    if (remainingDocTabs.length > 0) {
      const nextTab = idx > 0 ? openTabs.value[idx - 1] : openTabs.value[idx + 1];
      switchTab(nextTab?.id || 'catalog');
    } else {
      switchTab('catalog');
    }
  }

  // Hapus tab dari daftar
  openTabs.value.splice(idx, 1);
  nextTick(checkTabScroll);
}

// Tutup tab tertentu
function closeTab(tabId, event) {
  if (event) event.stopPropagation();
  if (tabId === 'catalog') return; // Tab katalog permanen, tidak bisa ditutup

  const idx = openTabs.value.findIndex(t => t.id === tabId);
  if (idx === -1) return;

  const tabToClose = openTabs.value[idx];

  // Peringatan jika masih ada perubahan yang belum tersimpan
  if (tabToClose.isDirty || tabToClose.autoSaveStatus === 'unsaved') {
    openConfirmModal({
      title: 'Perubahan Belum Disimpan',
      message: `Template "${tabToClose.form?.title || tabToClose.title}" memiliki perubahan yang belum disimpan. Tetap ingin menutup tab ini?`,
      confirmText: 'Tutup Tab',
      cancelText: 'Batal',
      type: 'warning',
      onConfirm: () => {
        executeCloseTab(tabId);
      }
    });
    return;
  }

  executeCloseTab(tabId);
}

// Kembali ke katalog tanpa menutup tab
function backToList() {
  switchTab('catalog');
}

function triggerFileInput() {
  uploadError.value = '';
  uploadSuccess.value = '';
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
  uploadSuccess.value = '';

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

      const rawBody = result.value || '<p></p>';
      // Normalisasi kerapatan spasi: jangan biarkan paragraf kosong beruntun yang membuat template renggang
      const cleanBody = rawBody
        .replace(/(<p[^>]*>(\s*(&nbsp;|<br\s*\/?>)?\s*)*<\/p>\s*){2,}/gi, '<p></p>')
        .trim();
      parsedHtml = docxHeaderHtml ? (docxHeaderHtml + cleanBody) : cleanBody;

      const formData = new FormData();
      formData.append('file', file);
      formData.append('title', cleanTitle);
      formData.append('category', 'SOP');
      formData.append('content', parsedHtml);

      await api.post('/operational-templates', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });
      await fetchTemplates();

      const successMsg = `Template "${cleanTitle}" (.docx) berhasil ditambahkan ke katalog.`;
      uploadSuccess.value = successMsg;
      showPopupToast({
        type: 'success',
        title: 'Berhasil Ditambahkan',
        message: successMsg
      });
      setTimeout(() => {
        uploadSuccess.value = '';
      }, 5000);
      return;

    } else if (ext === 'pdf') {
      const arrayBuffer = await file.arrayBuffer();
      const rawSearchText = await extractPdfRawText(arrayBuffer);

      const formData = new FormData();
      formData.append('file', file);
      formData.append('title', cleanTitle);
      formData.append('category', 'SOP');
      formData.append('content', rawSearchText || cleanTitle);

      await api.post('/operational-templates', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });
      await fetchTemplates();

      const successMsg = `Dokumen patokan resmi "${cleanTitle}" (PDF) berhasil ditambahkan ke katalog.`;
      uploadSuccess.value = successMsg;
      showPopupToast({
        type: 'success',
        title: 'Berhasil Ditambahkan',
        message: successMsg
      });
      setTimeout(() => {
        uploadSuccess.value = '';
      }, 5000);
      return;

    } else if (ext === 'xlsx' || ext === 'xls' || ext === 'csv') {
      const arrayBuffer = await file.arrayBuffer();
      const parsed = parseExcelWorkbook(arrayBuffer);

      // Simpan file asli ke backend via FormData
      const formData = new FormData();
      formData.append('file', file);
      formData.append('title', cleanTitle);
      formData.append('category', 'SOP');
      formData.append('content', parsed.plainText.substring(0, 50000) || cleanTitle);

      await api.post('/operational-templates', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });
      await fetchTemplates();

      const successMsg = `File spreadsheet Excel "${fileName}" (${parsed.sheetNames.length} Sheet, ${parsed.totalRows} baris) berhasil ditambahkan ke katalog.`;
      uploadSuccess.value = successMsg;
      showPopupToast({
        type: 'success',
        title: 'Berhasil Ditambahkan',
        message: successMsg
      });
      setTimeout(() => {
        uploadSuccess.value = '';
      }, 5000);
      return;

    } else if (ext === 'txt' || ext === 'md') {
      const rawText = await file.text();
      parsedHtml = parsePlainTextWithHeadings(rawText);

      const formData = new FormData();
      formData.append('file', file);
      formData.append('title', cleanTitle);
      formData.append('category', 'Umum');
      formData.append('content', parsedHtml);

      await api.post('/operational-templates', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });
      await fetchTemplates();

      const successMsg = `Template teks "${cleanTitle}" berhasil ditambahkan ke katalog.`;
      uploadSuccess.value = successMsg;
      showPopupToast({
        type: 'success',
        title: 'Berhasil Ditambahkan',
        message: successMsg
      });
      setTimeout(() => {
        uploadSuccess.value = '';
      }, 5000);
      return;
    } else {
      const errMsg = 'Format file tidak didukung. Harap upload dokumen .docx, .pdf, .txt, .md, .xlsx, atau .xls';
      uploadError.value = errMsg;
      showPopupToast({
        type: 'error',
        title: 'Format Tidak Didukung',
        message: errMsg
      });
      return;
    }
  } catch (err) {
    console.error('Gagal membaca file template:', err);
    const errMsg = err.response?.data?.message || 'Gagal memproses file dokumen. Pastikan file tidak rusak.';
    uploadError.value = errMsg;
    showPopupToast({
      type: 'error',
      title: 'Gagal Upload Dokumen',
      message: errMsg
    });
  } finally {
    fileProcessing.value = false;
  }
}

// Simpan Dokumen Langsung dari Halaman (Mendukung Simpan Manual & Auto-Save Latar Belakang)
async function handleSave(isSilent = false) {
  if (autoSaveTimeout) {
    clearTimeout(autoSaveTimeout);
  }

  if (isDocumentPdf.value) {
    if (!isSilent) saveError.value = 'Dokumen patokan PDF bersifat baku dan tidak dapat diubah.';
    return;
  }

  const htmlContent = editor.value ? editor.value.getHTML() : '';
  const plainText = editor.value ? editor.value.getText().trim() : '';

  if (!form.value.title.trim()) {
    if (!isSilent) saveError.value = 'Judul template wajib diisi.';
    return;
  }

  if (!plainText && (!htmlContent || htmlContent === '<p></p>')) {
    if (!isSilent) saveError.value = 'Konten template tidak boleh kosong.';
    return;
  }

  if (isSilent) {
    autoSaveStatus.value = 'saving';
  } else {
    saving.value = true;
    saveError.value = '';
    saveSuccess.value = false;
  }

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

    const now = new Date();
    lastSavedTime.value = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    autoSaveStatus.value = 'saved';

    // Perbarui data tab aktif di openTabs
    const currentTab = openTabs.value.find(t => t.id === activeTabId.value);
    if (currentTab) {
      currentTab.templateId = savedItem?.id || form.value.id;
      currentTab.form.id = savedItem?.id || form.value.id;
      currentTab.form.updated_at = form.value.updated_at;
      currentTab.form.author_name = form.value.author_name;
      currentTab.form.title = form.value.title;
      currentTab.title = form.value.title;
      currentTab.form.content = htmlContent;
      currentTab.isDirty = false;
      currentTab.autoSaveStatus = 'saved';
      currentTab.lastSavedTime = lastSavedTime.value;
      if (currentTab.type === 'draft') {
        currentTab.type = currentTab.form.original_filename?.toLowerCase().endsWith('.docx') ? 'docx' : 'txt';
      }
    }

    if (!isSilent) {
      saveSuccess.value = true;
      setTimeout(() => {
        saveSuccess.value = false;
      }, 2500);
    }

    await fetchTemplates();
  } catch (err) {
    console.error('Gagal menyimpan template:', err);
    autoSaveStatus.value = 'error';
    if (!isSilent) {
      if (err.response?.status === 401) {
        saveError.value = 'Sesi login Anda belum terdaftar di backend lokal. Silakan klik Logout di pojok kanan atas lalu Login kembali.';
      } else {
        saveError.value = err.response?.data?.message || 'Gagal menyimpan template operasional.';
      }
    }
  } finally {
    if (!isSilent) {
      saving.value = false;
    }
  }
}

// Salin teks langsung dari editor dokumen aktif
async function copyDocumentText() {
  try {
    let text = '';
    if (isDocumentPdf.value) {
      text = form.value.content || '';
    } else if (editor.value) {
      // Gunakan extractPlainText dari HTML editor untuk kerapatan teks yang presisi
      const html = editor.value.getHTML();
      text = extractPlainText(html);
      if (!text.trim()) {
        text = editor.value.getText({ blockSeparator: '\n' });
      }
    }
    const cleanText = cleanTemplateSpacing(text);
    await navigator.clipboard.writeText(cleanText);
    isDocumentCopied.value = true;
    showPopupToast({
      type: 'success',
      title: 'Tersalin!',
      message: 'Naskah template berhasil disalin dengan kerapatan teks yang rapi.'
    });
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
    const isPdf = template.original_filename?.toLowerCase().endsWith('.pdf');
    const text = isPdf ? (template.content || '') : extractPlainText(template.content);
    const cleanText = cleanTemplateSpacing(text);
    await navigator.clipboard.writeText(cleanText);
    copiedId.value = template.id;
    showPopupToast({
      type: 'success',
      title: 'Tersalin!',
      message: `Template "${template.title}" berhasil disalin.`
    });
    setTimeout(() => {
      if (copiedId.value === template.id) {
        copiedId.value = null;
      }
    }, 2000);
  } catch (err) {
    console.error('Gagal menyalin template:', err);
  }
}

// Download file PDF asli dari dokumen aktif
async function downloadActivePdf() {
  if (pdfBlobUrl.value) {
    const a = document.createElement('a');
    a.href = pdfBlobUrl.value;
    a.download = form.value.original_filename || `${form.value.title || 'dokumen'}.pdf`;
    a.click();
    return;
  }
  if (form.value.id) {
    await downloadOriginalPdf({
      id: form.value.id,
      original_filename: form.value.original_filename,
      title: form.value.title
    });
  }
}

// Download file asli dari kartu atau tab aktif
async function downloadOriginalFile(template) {
  try {
    const res = await api.get(`/operational-templates/${template.id}/file`, {
      responseType: 'blob'
    });
    const blob = new Blob([res.data]);
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = template.original_filename || `${template.title || 'dokumen'}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
  } catch (err) {
    console.error('Gagal mengunduh file asli:', err);
    showPopupToast({
      type: 'error',
      title: 'Gagal Download',
      message: 'Gagal mengunduh file asli dari server.'
    });
  }
}

// Download file PDF asli dari kartu
async function downloadOriginalPdf(template) {
  try {
    const res = await api.get(`/operational-templates/${template.id}/file`, {
      responseType: 'blob'
    });
    const blob = new Blob([res.data], { type: 'application/pdf' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = template.original_filename || `${template.title || 'dokumen'}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
  } catch (err) {
    console.error('Gagal mengunduh file PDF asli:', err);
    showPopupToast({
      type: 'error',
      title: 'Gagal Download',
      message: 'Gagal mengunduh file PDF asli dari server.'
    });
  }
}

// Download DOCX / Word format dari dokumen aktif
function downloadActiveDocx() {
  const title = form.value.title || 'Template';
  const content = editor.value ? editor.value.getHTML() : '';
  const header = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${title}</title><style>body{font-family:'Calibri',Arial,sans-serif;font-size:11pt;line-height:1.28;letter-spacing:-0.012em;color:#1e293b;}p{margin:0 0 0.35em;line-height:1.28;}h1,h2,h3{margin:0.6em 0 0.2em;}</style></head><body><h2>${title}</h2>${content}</body></html>`;

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
  const header = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${title}</title><style>body{font-family:'Calibri',Arial,sans-serif;font-size:11pt;line-height:1.28;letter-spacing:-0.012em;color:#1e293b;}p{margin:0 0 0.35em;line-height:1.28;}h1,h2,h3{margin:0.6em 0 0.2em;}</style></head><body><h2>${title}</h2>${content}</body></html>`;

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
function deleteActiveDocument() {
  if (!form.value.id) {
    executeCloseTab(activeTabId.value);
    return;
  }
  const titleToDelete = form.value.title;
  openConfirmModal({
    title: 'Hapus Template Aktif?',
    message: `Apakah Anda yakin ingin menghapus template "${titleToDelete}"? Dokumen ini akan dihapus dari katalog dan tab ditutup.`,
    confirmText: 'Ya, Hapus',
    cancelText: 'Batal',
    type: 'danger',
    onConfirm: async () => {
      try {
        await api.delete(`/operational-templates/${form.value.id}`);
        executeCloseTab(activeTabId.value);
        await fetchTemplates();
        showPopupToast({
          type: 'success',
          title: 'Template Dihapus',
          message: `Template "${titleToDelete}" berhasil dihapus dari katalog.`
        });
      } catch (err) {
        showPopupToast({
          type: 'error',
          title: 'Gagal Menghapus',
          message: err.response?.data?.message || 'Gagal menghapus template dari server.'
        });
      }
    }
  });
}

// Hapus template dari kartu katalog
function deleteTemplate(template) {
  openConfirmModal({
    title: 'Hapus Template?',
    message: `Apakah Anda yakin ingin menghapus template "${template.title}" dari katalog operasional? Tindakan ini tidak dapat dibatalkan.`,
    confirmText: 'Ya, Hapus',
    cancelText: 'Batal',
    type: 'danger',
    onConfirm: async () => {
      try {
        await api.delete(`/operational-templates/${template.id}`);
        const tabIdx = openTabs.value.findIndex(t => t.templateId === template.id);
        if (tabIdx !== -1) {
          executeCloseTab(openTabs.value[tabIdx].id);
        }
        await fetchTemplates();
        showPopupToast({
          type: 'success',
          title: 'Template Dihapus',
          message: `Template "${template.title}" berhasil dihapus dari katalog.`
        });
      } catch (err) {
        showPopupToast({
          type: 'error',
          title: 'Gagal Menghapus',
          message: err.response?.data?.message || 'Gagal menghapus template dari server.'
        });
      }
    }
  });
}

function formatDate(val) {
  if (!val) return '-';
  return new Date(val).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  });
}

</script>

<template>
  <div class="templates-container">
    
    <!-- ══════════════════════════════════════════════════════════════════════════
         BILAH TAB GAYA BROWSER (DYNAMIC MULTI-TAB NAVIGATION - DRAGGABLE & REORDERABLE)
         Setiap tab (Katalog maupun Dokumen) dapat digeser-geser / ditukar posisinya (drag & drop)
         sesuai kebutuhan kerja agen.
         ══════════════════════════════════════════════════════════════════════════ -->
    <nav class="browser-tabs-shell" aria-label="Tab Dokumen Operasional">
      <!-- Tombol Navigasi Geser Tab ke Kiri -->
      <button
        v-if="canScrollLeft"
        type="button"
        class="btn-tab-scroll-arrow btn-scroll-prev"
        @click="scrollTabs('left')"
        title="Geser bilah tab ke kiri"
        aria-label="Geser tab ke kiri"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
      </button>

      <div
        class="tabs-scroll-wrapper"
        ref="tabsScrollEl"
        @wheel="handleTabsWheel"
        @scroll="checkTabScroll"
      >
        <!-- Daftar Seluruh Tab (Katalog & Dokumen) Yang Dapat Digeser Posisi / Urutannya -->
        <div
          v-for="(tab, index) in openTabs"
          :key="tab.id"
          class="browser-tab"
          :class="[
            { active: activeTabId === tab.id },
            'tab-' + tab.type,
            { 'is-catalog-tab': tab.type === 'catalog' },
            { 'tab-is-dragging': draggedTabIndex === index },
            { 'tab-drop-before': dropTargetIndex === index && dropPosition === 'before' },
            { 'tab-drop-after': dropTargetIndex === index && dropPosition === 'after' }
          ]"
          draggable="true"
          @dragstart="onTabDragStart($event, index)"
          @dragover="onTabDragOver($event, index)"
          @dragleave="onTabDragLeave($event, index)"
          @drop="onTabDrop($event, index)"
          @dragend="onTabDragEnd"
          @click="switchTab(tab.id)"
          :title="tab.type === 'catalog' ? 'Katalog Template (Tahan & geser untuk atur posisi tab)' : (tab.form?.title || tab.title) + ' (Tahan & geser untuk atur posisi tab)'"
        >
          <!-- JIKA TAB KATALOG -->
          <template v-if="tab.type === 'catalog'">
            <div class="tab-icon-wrap icon-catalog">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7" rx="1.5"></rect>
                <rect x="14" y="3" width="7" height="7" rx="1.5"></rect>
                <rect x="14" y="14" width="7" height="7" rx="1.5"></rect>
                <rect x="3" y="14" width="7" height="7" rx="1.5"></rect>
              </svg>
            </div>
            <span class="tab-label">Katalog Template</span>
            <span v-if="templates.length" class="tab-count-pill">{{ templates.length }}</span>
            <span class="tab-drag-grip" title="Tahan & geser untuk atur urutan">
              <svg width="8" height="12" viewBox="0 0 8 12" fill="currentColor">
                <circle cx="2" cy="2" r="1.2"></circle><circle cx="6" cy="2" r="1.2"></circle>
                <circle cx="2" cy="6" r="1.2"></circle><circle cx="6" cy="6" r="1.2"></circle>
                <circle cx="2" cy="10" r="1.2"></circle><circle cx="6" cy="10" r="1.2"></circle>
              </svg>
            </span>
          </template>

          <!-- JIKA TAB DOKUMEN (Word, PDF, Teks, Draft) -->
          <template v-else>
            <!-- Document Type Icon -->
            <div class="tab-icon-wrap" :class="'icon-' + tab.type">
              <!-- DOCX Icon -->
              <svg v-if="tab.type === 'docx'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
              </svg>
              <!-- XLSX Icon -->
              <svg v-else-if="tab.type === 'xlsx'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <path d="M8 13h8M8 17h8M12 9v12"></path>
              </svg>
              <!-- PDF Icon -->
              <svg v-else-if="tab.type === 'pdf'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="12" y1="18" x2="12" y2="12"></line>
                <line x1="9" y1="15" x2="15" y2="15"></line>
              </svg>
              <!-- TXT Icon -->
              <svg v-else-if="tab.type === 'txt'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
              </svg>
              <!-- Draft / Blank Icon -->
              <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
              </svg>
            </div>

            <!-- Tab Title -->
            <span class="tab-label">
              {{ (activeTabId === tab.id ? form.title : (tab.form?.title || tab.title)) || 'Draft Baru' }}
            </span>

            <!-- Type Chip (Kecil di sebelah judul tab) -->
            <span class="tab-type-tag" :class="'type-' + tab.type">
              {{ tab.type.toUpperCase() }}
            </span>

            <!-- Unsaved dot indicator -->
            <span
              v-if="tab.isDirty || (activeTabId === tab.id && autoSaveStatus === 'unsaved')"
              class="tab-dirty-indicator"
              title="Perubahan belum tersimpan"
            ></span>

            <!-- Drag Grip Handle -->
            <span class="tab-drag-grip" title="Tahan & geser untuk atur urutan">
              <svg width="8" height="12" viewBox="0 0 8 12" fill="currentColor">
                <circle cx="2" cy="2" r="1.2"></circle><circle cx="6" cy="2" r="1.2"></circle>
                <circle cx="2" cy="6" r="1.2"></circle><circle cx="6" cy="6" r="1.2"></circle>
                <circle cx="2" cy="10" r="1.2"></circle><circle cx="6" cy="10" r="1.2"></circle>
              </svg>
            </span>

            <!-- Close button × -->
            <button
              type="button"
              class="btn-tab-close"
              @click.stop="closeTab(tab.id, $event)"
              title="Tutup tab"
              aria-label="Tutup tab"
            >
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
          </template>
        </div>

        <!-- Add New Tab '+' Button -->
        <button
          type="button"
          class="btn-tab-new"
          @click="openCreate"
          title="Buka lembar template baru (+)"
          aria-label="Buka lembar template baru"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
        </button>

      </div>

      <!-- Tombol Navigasi Geser Tab ke Kanan -->
      <button
        v-if="canScrollRight"
        type="button"
        class="btn-tab-scroll-arrow btn-scroll-next"
        @click="scrollTabs('right')"
        title="Geser bilah tab ke kanan"
        aria-label="Geser tab ke kanan"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
      </button>
    </nav>
    
    <!-- ══════════════════════════════════════════════════════════════════════════
         TAMPILAN 1: DAFTAR TEMPLATE (GRID DAFTAR TEMPLATE)
         ══════════════════════════════════════════════════════════════════════════ -->
    <article v-if="activeView === 'list'" class="card templates-section-card">
      <!-- Header Section -->
      <div class="templates-header">
        <div class="header-left">
          <div class="header-title-row">
            <div class="header-icon-circle">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
              </svg>
            </div>
            <div>
              <h3 class="section-heading">Template Operasional</h3>
              <p class="section-caption">
                Simpan naskah respons & SOP untuk mempercepat penanganan kendala pelanggan.
              </p>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="header-actions-group">
          <input
            type="file"
            ref="fileInput"
            accept=".docx,.pdf,.txt,.md,.xlsx,.xls,.csv"
            class="hidden-file-input"
            @change="handleFileChange"
          />

          <button
            type="button"
            class="btn-outline-upload"
            @click="triggerFileInput"
            :disabled="fileProcessing"
            title="Unggah template dari file .docx, .pdf, .txt, atau spreadsheet .xlsx/.xls/.csv"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="17 8 12 3 7 8"></polyline>
              <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
            <span>{{ fileProcessing ? 'Membaca...' : 'Upload Dokumen' }}</span>
          </button>

          <button
            type="button"
            class="btn-primary btn-add-template"
            @click="openCreate"
            title="Tulis template operasional baru"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>Template Baru</span>
          </button>
        </div>
      </div>

      <!-- Alert success jika upload PDF patokan berhasil -->
      <div v-if="uploadSuccess" class="alert-success-banner">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
          <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        <span>{{ uploadSuccess }}</span>
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
          <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
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
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
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
        <div class="loading-spinner-sm"></div>
        <span>Memuat daftar template operasional...</span>
      </div>

      <div v-else-if="filteredTemplates.length" class="templates-grid">
        <div
          v-for="template in filteredTemplates"
          :key="template.id"
          class="template-card"
          @click="openDocument(template)"
        >
          <!-- Card Top: Category & File Extension Tag -->
          <div class="tcard-top">
            <span class="badge-category" :class="getCategoryBadgeClass(template.category)">
              {{ template.category || 'Umum' }}
            </span>
            <span v-if="getFileTypeBadge(template.original_filename)" class="badge-file-type" :class="'badge-' + getFileTypeBadge(template.original_filename).toLowerCase()">
              {{ getFileTypeBadge(template.original_filename) === 'PDF' ? 'PDF PATOKAN' : getFileTypeBadge(template.original_filename) }}
            </span>
          </div>

          <!-- Title -->
          <h4 class="tcard-title" :title="template.title">
            {{ template.title }}
          </h4>

          <!-- Text Preview Snippet -->
          <p class="tcard-preview">
            {{ extractPlainText(template.content) || '(Konten kosong)' }}
          </p>

          <!-- Meta info -->
          <div class="tcard-meta">
            <span class="meta-author">
              {{ template.user?.name || 'Agent' }} &bull; {{ formatDate(template.updated_at) }}
            </span>
            <span v-if="template.original_filename?.toLowerCase().endsWith('.pdf')" class="meta-locked-tag">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
              <span>Terkunci</span>
            </span>
          </div>

          <!-- Action Buttons -->
          <div class="tcard-actions" @click.stop>
            <div class="tcard-actions-left">
              <button
                type="button"
                class="btn-card-action"
                :class="template.original_filename?.toLowerCase().endsWith('.pdf') ? 'btn-card-view-locked' : 'btn-card-edit'"
                @click="openDocument(template)"
                :title="template.original_filename?.toLowerCase().endsWith('.pdf') ? 'Lihat isi naskah dokumen patokan PDF ini' : 'Buka dan langsung edit template ini'"
              >
                <!-- Icon Eye jika PDF patokan, Icon Edit jika template biasa -->
                <svg v-if="template.original_filename?.toLowerCase().endsWith('.pdf')" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                <span>{{ template.original_filename?.toLowerCase().endsWith('.pdf') ? 'Lihat' : 'Buka' }}</span>
              </button>

              <button
                type="button"
                class="btn-card-action btn-card-copy"
                :class="{ 'btn-card-copied': copiedId === template.id }"
                @click="copyCardText(template)"
                title="Salin isi template ke clipboard"
              >
                <svg v-if="copiedId === template.id" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
                <span>{{ copiedId === template.id ? 'Tersalin!' : 'Salin' }}</span>
              </button>
            </div>

            <div class="tcard-actions-right">
              <button
                type="button"
                class="btn-card-kebab"
                :class="{ active: activeMenuId === template.id }"
                @click="toggleCardMenu($event, template.id)"
                title="Opsi dokumen lainnya"
                aria-label="Opsi dokumen lainnya"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                  <circle cx="12" cy="5" r="2.2"></circle>
                  <circle cx="12" cy="12" r="2.2"></circle>
                  <circle cx="12" cy="19" r="2.2"></circle>
                </svg>
              </button>

              <!-- Kebab Popover Dropdown -->
              <div v-if="activeMenuId === template.id" class="card-popover-menu" @click.stop>
                <button
                  v-if="template.original_filename?.toLowerCase().endsWith('.pdf')"
                  type="button"
                  class="popover-item"
                  @click="downloadOriginalPdf(template); activeMenuId = null"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="12" y1="18" x2="12" y2="12"></line>
                    <line x1="9" y1="15" x2="15" y2="15"></line>
                  </svg>
                  <span>Unduh PDF Asli (.pdf)</span>
                </button>

                <button
                  v-else-if="template.original_filename?.toLowerCase().match(/\.(xlsx|xls|csv)$/)"
                  type="button"
                  class="popover-item"
                  @click="downloadOriginalFile(template); activeMenuId = null"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <path d="M8 13h8M8 17h8M12 9v12"></path>
                  </svg>
                  <span>Unduh Excel Asli (.xlsx)</span>
                </button>

                <template v-else>
                  <button type="button" class="popover-item" @click="downloadAsDocx(template); activeMenuId = null">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                      <polyline points="14 2 14 8 20 8"></polyline>
                      <line x1="16" y1="13" x2="8" y2="13"></line>
                      <line x1="16" y1="17" x2="8" y2="17"></line>
                    </svg>
                    <span>Unduh Word (.doc)</span>
                  </button>

                  <button type="button" class="popover-item" @click="downloadAsTxt(template); activeMenuId = null">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                      <polyline points="14 2 14 8 20 8"></polyline>
                      <line x1="12" y1="18" x2="12" y2="12"></line>
                      <line x1="9" y1="15" x2="15" y2="15"></line>
                    </svg>
                    <span>Unduh Teks (.txt)</span>
                  </button>
                </template>

                <div class="popover-divider"></div>

                <button type="button" class="popover-item item-danger" @click="deleteTemplate(template); activeMenuId = null">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                  </svg>
                  <span>Hapus Template</span>
                </button>
              </div>
            </div>
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
          {{ searchQuery || selectedCategory !== 'Semua' ? 'Tidak ditemukan template yang cocok dengan filter pencarian.' : 'Unggah file dokumen DOCX/PDF/TXT atau buat template baru untuk mempercepat penanganan kendala.' }}
        </p>
        <div v-if="!searchQuery && selectedCategory === 'Semua'" class="empty-action-row">
          <button type="button" class="btn-outline-upload" @click="triggerFileInput">
            Upload File Dokumen
          </button>
          <button type="button" class="btn-primary" @click="openCreate">
            Buat Template Baru
          </button>
        </div>
      </div>
    </article>

    <!-- ══════════════════════════════════════════════════════════════════════════
         TAMPILAN 2: DOKUMEN (INLINE DI HALAMAN)
         - PDF: Terkunci (Read-Only) sebagai Patokan Resmi Tanpa Edit
         - DOCX/TXT: Bisa Diedit Langsung
         ══════════════════════════════════════════════════════════════════════════ -->
    <article v-else-if="activeView === 'document'" class="card inline-document-card">
      
      <!-- Top Navigation & Actions Bar -->
      <div class="inline-nav-bar">
        <div class="inline-nav-left-group">
          <button type="button" class="btn-back-link" @click="backToList" title="Buka katalog template tanpa menutup tab ini">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            <span>Katalog</span>
          </button>
          <button type="button" class="btn-close-tab-inline" @click="closeTab(activeTabId)" title="Tutup tab dokumen ini">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
            <span>Tutup Tab</span>
          </button>
        </div>

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
            <span class="btn-text-responsive">{{ isDocumentCopied ? 'Tersalin' : 'Salin' }}</span>
          </button>

          <!-- Export DOCX / TXT untuk Word/Text, atau Unduh PDF / Excel -->
          <button
            v-if="isDocumentPdf"
            type="button"
            class="btn-action btn-download btn-download-pdf"
            @click="downloadActivePdf"
            title="Unduh file dokumen PDF asli"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="7 10 12 15 17 10"></polyline>
              <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            <span class="btn-text-responsive">Unduh PDF</span>
          </button>

          <button
            v-else-if="isDocumentExcel"
            type="button"
            class="btn-action btn-download btn-download-excel"
            @click="downloadActiveExcel"
            title="Unduh file spreadsheet Excel asli (.xlsx)"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="7 10 12 15 17 10"></polyline>
              <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            <span class="btn-text-responsive">Unduh Excel</span>
          </button>

          <div v-else class="download-dropdown">
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
            <span class="btn-text-responsive">Hapus</span>
          </button>

          <!-- Status Terkunci (Jika Dokumen Patokan PDF) -->
          <span v-if="isDocumentPdf" class="badge-locked-status" title="Dokumen patokan PDF terkunci dari pengeditan">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <span>Read-Only</span>
          </span>

          <!-- Simpan Dokumen (Hanya untuk template non-PDF) -->
          <div v-else class="save-actions-wrap">
            <!-- Indikator Auto-Save Status -->
            <div v-if="autoSaveStatus" class="autosave-tag" :class="'as-' + autoSaveStatus">
              <span v-if="autoSaveStatus === 'saving'" class="as-saving-text">
                <span class="loading-spinner-xs"></span>
                <span>Menyimpan otomatis...</span>
              </span>
              <span v-else-if="autoSaveStatus === 'saved'" class="as-saved-text" :title="'Tersimpan otomatis pukul ' + (lastSavedTime || '')">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span>Tersimpan otomatis ({{ lastSavedTime }})</span>
              </span>
              <span v-else-if="autoSaveStatus === 'unsaved'" class="as-unsaved-text" title="Tekan Ctrl+S atau tunggu sebentar untuk auto-save">
                <span class="unsaved-pulse-dot"></span>
                <span>Menunggu simpan...</span>
              </span>
              <span v-else-if="autoSaveStatus === 'error'" class="as-error-text">
                <span>Gagal auto-save &bull; Klik Simpan</span>
              </span>
            </div>

            <button
              type="button"
              class="btn-primary btn-save"
              :class="{ 'btn-save-success': saveSuccess }"
              @click="handleSave(false)"
              :disabled="saving"
              title="Simpan dokumen ini (atau tekan Ctrl+S)"
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
              <span v-else class="btn-save-text-wrap">
                <span>Simpan</span>
                <kbd class="kbd-shortcut">Ctrl+S</kbd>
              </span>
            </button>
          </div>
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

      <!-- Dokumen Header: Judul & Meta Inline -->
      <div class="doc-header-block">
        <!-- Judul Readonly jika PDF, Input jika non-PDF -->
        <div v-if="isDocumentPdf" class="doc-title-locked">
          <h2 class="title-text-locked">{{ form.title }}</h2>
        </div>
        <input
          v-else
          type="text"
          v-model="form.title"
          @input="triggerAutoSave"
          class="doc-title-input"
          placeholder="Ketik judul template dokumen..."
          maxlength="255"
          required
        />

        <div class="doc-meta-bar">
          <!-- Kategori Selector (Readonly jika PDF) -->
          <div class="inline-meta-item">
            <label class="meta-label">Kategori:</label>
            <span v-if="isDocumentPdf" class="badge-category" :class="getCategoryBadgeClass(form.category)">
              {{ form.category }}
            </span>
            <select v-else v-model="form.category" @change="triggerAutoSave" class="inline-select">
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

      <!-- Toolbar Formatting Word (Hanya Muncul jika Dokumen Non-PDF) -->
      <div v-if="editor && !isDocumentPdf" class="editor-toolbar">
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

        <div class="toolbar-spacer"></div>

        <!-- Tombol Toggle Navigasi Heading (MS Word Style) -->
        <div class="toolbar-nav-toggle-group">
          <button
            type="button"
            class="tb-btn tb-btn-nav"
            :class="{ active: showHeadingNav }"
            @click="toggleHeadingNav"
            :title="showHeadingNav ? 'Tutup panel navigasi heading' : 'Buka panel navigasi heading di sebelah kiri'"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="21" y1="6" x2="3" y2="6"></line>
              <line x1="15" y1="12" x2="3" y2="12"></line>
              <line x1="17" y1="18" x2="3" y2="18"></line>
            </svg>
            <span class="tb-btn-nav-text">Navigasi Heading</span>
            <span v-if="headingsList.length" class="tb-nav-count-badge">
              {{ headingsList.length }}
            </span>
          </button>
        </div>
      </div>

      <!-- Jika Dokumen PDF Patokan Baku: Tampilkan Langsung File PDF Asli Tanpa Mengubah Format -->
      <div v-if="isDocumentPdf" class="pdf-viewer-wrapper">
        <!-- PDF Action Bar -->
        <div class="pdf-quick-toolbar">
          <div class="pdf-toolbar-info">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
            </svg>
            <span class="pdf-filename-tag">{{ form.original_filename || 'Dokumen Patokan.pdf' }}</span>
          </div>

          <div class="pdf-toolbar-actions">
            <a
              v-if="pdfBlobUrl"
              :href="pdfBlobUrl"
              target="_blank"
              class="btn-pdf-tool"
              title="Buka PDF di tab baru atau layar penuh"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
              </svg>
              <span>Buka Layar Penuh</span>
            </a>

            <button
              type="button"
              class="btn-pdf-tool btn-pdf-download"
              @click="downloadActivePdf"
              title="Unduh file PDF asli tanpa perubahan format"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
              </svg>
              <span>Unduh PDF Asli</span>
            </button>
          </div>
        </div>

        <!-- Frame Preview PDF Asli -->
        <div class="pdf-frame-container">
          <div v-if="pdfLoading" class="pdf-loading-state">
            <div class="loading-spinner-sm"></div>
            <span>Memuat file PDF asli...</span>
          </div>
          <iframe
            v-else-if="pdfBlobUrl"
            :src="pdfBlobUrl"
            class="pdf-native-frame"
            title="Preview Naskah PDF Asli"
          ></iframe>
          <div v-else class="pdf-fallback-state">
            <p>File PDF tidak dapat dimuat langsung di browser ini.</p>
            <button type="button" class="btn-primary" @click="downloadActivePdf">Unduh Dokumen PDF</button>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════════════════════
           JIKA DOKUMEN SPREADSHEET EXCEL (.XLSX / .XLS / .CSV)
           Tampilkan Spreadsheet Grid Interaktif dengan Pilihan Sheet, Pencarian Baris,
           dan Salin Tabel / Baris
           ══════════════════════════════════════════════════════════════════════════ -->
      <div v-else-if="isDocumentExcel" class="excel-viewer-wrapper">
        <!-- Top Toolbar Excel -->
        <div class="excel-quick-toolbar">
          <div class="excel-info-left">
            <div class="excel-file-badge">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="8" y1="13" x2="16" y2="13"></line>
                <line x1="8" y1="17" x2="16" y2="17"></line>
                <line x1="10" y1="9" x2="8" y2="9"></line>
              </svg>
              <span>{{ form.original_filename || 'Dokumen.xlsx' }}</span>
            </div>
            <span v-if="excelData" class="excel-stats-pill">
              {{ excelData.sheetNames.length }} Sheet &bull; {{ currentActiveSheet?.rows?.length || 0 }} Baris
            </span>
          </div>

          <div class="excel-actions-right">
            <!-- Search baris dalam sheet Excel -->
            <div class="excel-search-box">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
              </svg>
              <input
                type="text"
                v-model="excelSearchQuery"
                class="excel-search-input"
                placeholder="Cari baris/data di sheet ini..."
              />
              <button
                v-if="excelSearchQuery"
                type="button"
                class="btn-clear-excel-search"
                @click="excelSearchQuery = ''"
              >
                &times;
              </button>
            </div>

            <!-- Tombol Salin Seluruh Sheet (TSV / Format Excel) -->
            <button
              type="button"
              class="btn-excel-tool btn-copy-excel"
              :class="{ 'btn-excel-copied': isExcelCopied }"
              @click="copyActiveExcelSheet"
              title="Salin seluruh data sheet ke clipboard (format tabel)"
            >
              <svg v-if="isExcelCopied" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
              </svg>
              <span>{{ isExcelCopied ? 'Tersalin!' : 'Salin Sheet' }}</span>
            </button>

            <!-- Unduh File Asli Excel -->
            <button
              type="button"
              class="btn-excel-tool btn-download-excel"
              @click="downloadActiveExcel"
              title="Unduh file spreadsheet Excel asli (.xlsx)"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
              </svg>
              <span>Unduh .xlsx</span>
            </button>
          </div>
        </div>

        <!-- Sheet Selector Tabs (Jika Workbook Memiliki Beberapa Sheet) -->
        <div v-if="excelData && excelData.sheetNames.length > 1" class="excel-sheet-tabs-bar">
          <button
            v-for="(sheetName, idx) in excelData.sheetNames"
            :key="sheetName"
            type="button"
            class="excel-sheet-tab"
            :class="{ active: activeExcelSheetIndex === idx }"
            @click="activeExcelSheetIndex = idx"
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="3" y1="9" x2="21" y2="9"></line>
              <line x1="9" y1="21" x2="9" y2="9"></line>
            </svg>
            <span>{{ sheetName }}</span>
            <span class="sheet-count-tag">{{ excelData.sheets[idx]?.rows?.length || 0 }}</span>
          </button>
        </div>

        <!-- Loading State Excel -->
        <div v-if="excelLoading" class="excel-loading-state">
          <div class="loading-spinner-sm"></div>
          <span>Membaca dan memproses spreadsheet Excel...</span>
        </div>

        <!-- Grid Tabel Spreadsheet Interaktif -->
        <div v-else-if="filteredExcelRows.length > 0" class="excel-grid-container">
          <table class="excel-rendered-table">
            <thead>
              <tr>
                <th class="th-row-num">#</th>
                <th
                  v-for="(headerCol, colIdx) in excelHeaderColumns"
                  :key="colIdx"
                  class="th-col"
                >
                  {{ headerCol }}
                </th>
                <th class="th-action">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, rowIdx) in filteredExcelRows"
                :key="rowIdx"
                class="excel-row"
              >
                <!-- Row Number -->
                <td class="td-row-num">{{ rowIdx + 1 }}</td>
                <!-- Cell Data -->
                <td
                  v-for="(colName, colIdx) in excelHeaderColumns"
                  :key="colIdx"
                  class="td-cell"
                  :title="String(row[colIdx] ?? '')"
                >
                  {{ row[colIdx] ?? '' }}
                </td>
                <!-- Copy Row Button -->
                <td class="td-action">
                  <button
                    type="button"
                    class="btn-copy-row"
                    @click="copyExcelRow(row, rowIdx)"
                    :title="copiedRowIndex === rowIdx ? 'Tersalin!' : 'Salin baris ini'"
                  >
                    <svg v-if="copiedRowIndex === rowIdx" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                      <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty / No Rows Matching State -->
        <div v-else class="excel-empty-state">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.8">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="3" y1="9" x2="21" y2="9"></line>
            <line x1="9" y1="21" x2="9" y2="9"></line>
          </svg>
          <p class="empty-title">
            {{ excelSearchQuery ? 'Tidak ada data yang cocok dengan pencarian' : 'Sheet ini kosong atau tidak memiliki baris data' }}
          </p>
          <button v-if="excelSearchQuery" type="button" class="btn-clear-search-pill" @click="excelSearchQuery = ''">
            Reset Pencarian
          </button>
        </div>
      </div>

      <!-- Jika Non-PDF (Word/TXT): Lembar Dokumen TipTap Langsung Diedit dengan Navigasi Heading di Kiri -->
      <div v-else class="doc-body-wrapper">
        <!-- Quick Jump Heading Bar (Sangat praktis untuk navigasi instan tanpa memakan ruang samping) -->
        <div v-if="headingsList.length > 0" class="quick-jump-bar">
          <div class="quick-jump-label">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 2 2h12a2 2 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
            <span class="quick-jump-text">Lompat ke Judul:</span>
          </div>

          <div class="quick-jump-select-wrap">
            <select
              class="quick-jump-select"
              :value="activeHeadingId"
              @change="onQuickJumpSelect($event.target.value)"
              aria-label="Pilih judul template untuk melompat"
            >
              <option value="" disabled>-- Pilih Bagian Template ({{ headingsList.length }} judul) --</option>
              <option
                v-for="item in headingsList"
                :key="item.id"
                :value="item.id"
              >
                {{ item.level === 1 ? '📌 ' : item.level === 2 ? '   ↳ ' : '      • ' }}{{ item.text }}
              </option>
            </select>
            <span class="quick-jump-arrow">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </span>
          </div>

          <button
            type="button"
            class="btn-toggle-nav-inline"
            :class="{ active: showHeadingNav }"
            @click="toggleHeadingNav"
            :title="showHeadingNav ? 'Tutup sidebar navigasi Word' : 'Buka sidebar navigasi Word lengkap'"
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <line x1="21" y1="6" x2="3" y2="6"></line>
              <line x1="15" y1="12" x2="3" y2="12"></line>
              <line x1="17" y1="18" x2="3" y2="18"></line>
            </svg>
            <span>{{ showHeadingNav ? 'Tutup Sidebar' : 'Sidebar Lengkap' }}</span>
          </button>
        </div>

        <div class="doc-editor-layout">
          
          <!-- Backdrop Navigasi saat layar <= 1024px (Mobile/Tablet/Narrow Tab) -->
          <transition name="fade-nav-backdrop">
            <div
              v-if="showHeadingNav"
              class="word-nav-backdrop"
              @click="closeHeadingNavDrawer"
              aria-label="Tutup navigasi"
            ></div>
          </transition>

          <!-- Panel Navigasi Heading ala Microsoft Word di Sebelah KIRI -->
          <aside
            v-if="showHeadingNav"
            class="word-nav-pane"
            aria-label="Panel Navigasi Heading Word"
          >
            <!-- Header Panel Navigasi -->
            <div class="word-nav-header">
              <div class="word-nav-title-wrap">
                <svg class="word-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 2-2V8z"></path>
                  <polyline points="14 2 14 8 20 8"></polyline>
                  <line x1="16" y1="13" x2="8" y2="13"></line>
                  <line x1="16" y1="17" x2="8" y2="17"></line>
                  <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span class="word-nav-title">Navigation</span>
                <span v-if="headingsList.length" class="word-nav-badge">
                  {{ headingsList.length }}
                </span>
              </div>
              <div class="word-nav-header-actions">
                <button
                  type="button"
                  class="btn-word-nav-pin"
                  :class="{ 'is-pinned': isNavPinned }"
                  @click="isNavPinned = !isNavPinned"
                  :title="isNavPinned ? 'Navigasi di-pin (tetap terbuka saat judul diklik)' : 'Navigasi tidak di-pin (otomatis tutup saat judul diklik)'"
                  aria-label="Pin Navigasi"
                >
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <line x1="12" y1="17" x2="12" y2="22"></line>
                    <path d="M5 17h14v-2l-2-2V5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v8l-2 2v2z"></path>
                  </svg>
                  <span class="pin-status-label">{{ isNavPinned ? 'Tetap Buka' : 'Auto-tutup' }}</span>
                </button>
                <button
                  type="button"
                  class="btn-word-nav-close"
                  @click="closeHeadingNavDrawer"
                  title="Tutup panel navigasi"
                  aria-label="Tutup panel navigasi"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                  </svg>
                </button>
              </div>
            </div>

            <!-- Search Document Box (Persis MS Word) -->
            <div class="word-nav-search-wrap">
              <svg class="word-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
              </svg>
              <input
                type="text"
                v-model="navSearchQuery"
                class="word-search-input"
                placeholder="Search document..."
                aria-label="Search document"
              />
              <button
                v-if="navSearchQuery"
                type="button"
                class="btn-word-search-clear"
                @click="navSearchQuery = ''"
                aria-label="Hapus pencarian"
              >
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                  <line x1="18" y1="6" x2="6" y2="18"></line>
                  <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
              </button>
            </div>

            <!-- Tab Menu Navigasi (Headings / Ringkasan) -->
            <div class="word-nav-tabs">
              <button
                type="button"
                class="word-tab-btn"
                :class="{ active: navActiveTab === 'headings' }"
                @click="navActiveTab = 'headings'"
              >
                <span>Headings</span>
                <span class="tab-count-pill">{{ filteredHeadings.length }}</span>
              </button>
              <button
                type="button"
                class="word-tab-btn"
                :class="{ active: navActiveTab === 'stats' }"
                @click="navActiveTab = 'stats'"
              >
                <span>Ringkasan</span>
              </button>
            </div>

            <!-- TAB 1: HEADINGS TREE VIEW -->
            <div v-if="navActiveTab === 'headings'" class="word-nav-content">
              <!-- Controls Bar: Expand / Collapse All -->
              <div v-if="headingsList.length && !navSearchQuery" class="word-nav-controls">
                <span class="controls-label">STRUKTUR JUDUL</span>
                <div class="controls-btns">
                  <button
                    type="button"
                    class="btn-ctrl-action"
                    @click="expandAllHeadings"
                    title="Buka semua sub-heading"
                  >
                    Buka Semua
                  </button>
                  <span class="ctrl-sep">&bull;</span>
                  <button
                    type="button"
                    class="btn-ctrl-action"
                    @click="collapseAllHeadings"
                    title="Tutup semua sub-heading"
                  >
                    Tutup Semua
                  </button>
                </div>
              </div>

              <!-- Hasil Pencarian Info Banner -->
              <div v-if="navSearchQuery" class="word-nav-search-status">
                <span>Ditemukan <strong>{{ filteredHeadings.length }}</strong> hasil untuk "{{ navSearchQuery }}"</span>
              </div>

              <!-- Empty state: jika dokumen belum punya heading -->
              <div v-if="!headingsList.length" class="word-nav-empty">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.8">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                  <polyline points="14 2 14 8 20 8"></polyline>
                  <line x1="16" y1="13" x2="8" y2="13"></line>
                  <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
                <p class="empty-nav-title">Belum ada heading</p>
                <p class="empty-nav-desc">
                  Gunakan tombol <strong>H1</strong>, <strong>H2</strong>, atau <strong>H3</strong> pada toolbar di atas untuk memberi judul bagian naskah.
                </p>
              </div>

              <!-- Empty search: jika kata kunci pencarian tidak ditemukan -->
              <div v-else-if="!filteredHeadings.length" class="word-nav-empty">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.8">
                  <circle cx="11" cy="11" r="8"></circle>
                  <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <p class="empty-nav-title">Tidak ditemukan</p>
                <p class="empty-nav-desc">Tidak ada heading yang cocok dengan "{{ navSearchQuery }}"</p>
                <button type="button" class="btn-clear-search-sm" @click="navSearchQuery = ''">
                  Reset Pencarian
                </button>
              </div>

              <!-- Daftar Heading Tree List (Sesuai Hirarki H1, H2, H3 seperti di Word) -->
              <div v-else class="word-heading-list" role="tree">
                <div
                  v-for="item in filteredHeadings"
                  :key="item.id"
                  class="word-heading-item"
                  :class="[
                    'level-' + item.level,
                    { 'active-heading': activeHeadingId === item.id }
                  ]"
                  :data-nav-id="item.id"
                  @click="scrollToHeading(item)"
                  :title="item.text"
                  role="treeitem"
                >
                  <!-- Caret Expand / Collapse jika punya anak -->
                  <button
                    v-if="item.hasChildren && !navSearchQuery"
                    type="button"
                    class="btn-heading-caret"
                    :class="{ collapsed: collapsedHeadings.has(item.id) }"
                    @click.stop="toggleCollapseHeading(item.id, $event)"
                    :title="collapsedHeadings.has(item.id) ? 'Buka sub-heading' : 'Tutup sub-heading'"
                    aria-label="Toggle sub-headings"
                  >
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                      <polygon points="6 9 12 15 18 9"></polygon>
                    </svg>
                  </button>
                  <span v-else class="caret-spacer"></span>

                  <!-- Badge Level (H1/H2/H3) -->
                  <span class="heading-tag" :class="'tag-h' + item.level">
                    H{{ item.level }}
                  </span>

                  <!-- Teks Judul Heading -->
                  <span class="heading-label">
                    {{ item.text }}
                  </span>

                  <!-- Tombol Quick Copy Judul Section -->
                  <button
                    type="button"
                    class="btn-copy-heading-text"
                    @click.stop="copyHeadingText(item)"
                    title="Salin teks judul ini"
                  >
                    <svg v-if="copiedHeadingId === item.id" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                      <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- TAB 2: RINGKASAN & STATISTIK DOKUMEN -->
            <div v-else-if="navActiveTab === 'stats'" class="word-nav-content word-nav-stats">
              <div class="stats-card-group">
                <div class="stat-box">
                  <span class="stat-num">{{ headingStats.total }}</span>
                  <span class="stat-lbl">Total Headings</span>
                </div>
                <div class="stat-box">
                  <span class="stat-num">{{ documentStats.wordCount }}</span>
                  <span class="stat-lbl">Kata</span>
                </div>
              </div>

              <div class="stats-breakdown">
                <h5 class="breakdown-title">Distribusi Heading</h5>
                <div class="breakdown-row">
                  <span class="breakdown-badge tag-h1">H1 (Utama)</span>
                  <span class="breakdown-val">{{ headingStats.h1Count }} judul</span>
                </div>
                <div class="breakdown-row">
                  <span class="breakdown-badge tag-h2">H2 (Subjudul)</span>
                  <span class="breakdown-val">{{ headingStats.h2Count }} judul</span>
                </div>
                <div class="breakdown-row">
                  <span class="breakdown-badge tag-h3">H3 (Sub-bagian)</span>
                  <span class="breakdown-val">{{ headingStats.h3Count }} judul</span>
                </div>
              </div>

              <div class="stats-doc-info">
                <h5 class="breakdown-title">Info Naskah</h5>
                <div class="info-item-row">
                  <span class="info-k">Karakter:</span>
                  <span class="info-v">{{ documentStats.charCount }}</span>
                </div>
                <div class="info-item-row">
                  <span class="info-k">Estimasi Baca:</span>
                  <span class="info-v">~{{ documentStats.readTimeMinutes }} menit</span>
                </div>
                <div class="info-item-row">
                  <span class="info-k">Format Asli:</span>
                  <span class="info-v">{{ form.original_filename || 'Dokumen Word/Teks' }}</span>
                </div>
              </div>
            </div>

            <!-- Footer Panel Navigasi -->
            <div class="word-nav-footer">
              <span class="nav-footer-hint">
                Klik judul untuk melompat langsung ke bagian naskah.
              </span>
            </div>
          </aside>

          <!-- Lembar Kertas Dokumen TipTap Utama di Sebelah KANAN -->
          <div class="doc-paper-sheet">
            <EditorContent :editor="editor" />
          </div>

          <!-- Tombol Mengambang (Floating Action) untuk memunculkan kembali Navigasi saat ditutup di layar <= 1024px -->
          <button
            v-if="!showHeadingNav && headingsList.length > 0"
            type="button"
            class="floating-nav-trigger"
            @click="openHeadingNavDrawer"
            title="Buka Navigasi Heading"
            aria-label="Buka Navigasi Heading"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 2 2h12a2 2 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
            <span>Daftar Isi ({{ headingsList.length }})</span>
          </button>

        </div>
      </div>

      <!-- Bottom Quick Action Row -->
      <div class="doc-footer-bar">
        <div class="footer-left-info">
          <span v-if="!isDocumentPdf" class="info-tip">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="16" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            Klik di mana saja pada lembar dokumen di atas untuk langsung mengedit teks.
          </span>
        </div>

        <div class="inline-actions-group">
          <button type="button" class="btn-cancel" @click="backToList" title="Lihat katalog template">
            Ke Katalog
          </button>
          <button type="button" class="btn-cancel btn-cancel-close-tab" @click="closeTab(activeTabId)" title="Tutup tab ini">
            Tutup Tab
          </button>

          <!-- Jika PDF, tombol aksi utama adalah Salin Teks -->
          <button
            v-if="isDocumentPdf"
            type="button"
            class="btn-primary btn-copy-prominent"
            :class="{ 'btn-save-success': isDocumentCopied }"
            @click="copyDocumentText"
          >
            <svg v-if="isDocumentCopied" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
            <span>{{ isDocumentCopied ? 'Teks Tersalin!' : 'Salin Seluruh Naskah' }}</span>
          </button>

          <!-- Jika Non-PDF, tombol aksi utama adalah Simpan Dokumen -->
          <button
            v-else
            type="button"
            class="btn-primary btn-save"
            :class="{ 'btn-save-success': saveSuccess }"
            @click="handleSave(false)"
            :disabled="saving"
            title="Simpan dokumen (Ctrl+S)"
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
            <span v-else class="btn-save-text-wrap">
              <span>Simpan Dokumen</span>
              <kbd class="kbd-shortcut">Ctrl+S</kbd>
            </span>
          </button>
        </div>
      </div>

    </article>

    <!-- ── Modal Konfirmasi Kustom (Tanpa Header URL Browser / Source Link) ── -->
    <Teleport to="body">
      <transition name="modal-fade">
        <div v-if="confirmModal.isOpen" class="custom-dialog-overlay" @click.self="handleCancelConfirm">
          <div class="custom-dialog-box" role="dialog" aria-modal="true">
            <div class="custom-dialog-header">
              <div class="dialog-icon-circle" :class="confirmModal.type === 'danger' ? 'icon-danger' : 'icon-warning'">
                <svg v-if="confirmModal.type === 'danger'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="3 6 5 6 21 6"></polyline>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                  <line x1="10" y1="11" x2="10" y2="17"></line>
                  <line x1="14" y1="11" x2="14" y2="17"></line>
                </svg>
                <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                  <line x1="12" y1="9" x2="12" y2="13"></line>
                  <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
              </div>
              <button type="button" class="dialog-close-btn" @click="handleCancelConfirm" aria-label="Tutup dialog">
                &times;
              </button>
            </div>

            <div class="custom-dialog-content">
              <h4 class="custom-dialog-title">{{ confirmModal.title }}</h4>
              <p class="custom-dialog-message">{{ confirmModal.message }}</p>
            </div>

            <div class="custom-dialog-footer">
              <button type="button" class="dialog-btn-cancel" @click="handleCancelConfirm">
                {{ confirmModal.cancelText }}
              </button>
              <button
                type="button"
                class="dialog-btn-action"
                :class="confirmModal.type === 'danger' ? 'dialog-btn-danger' : 'dialog-btn-warning'"
                @click="handleConfirmAction"
              >
                {{ confirmModal.confirmText }}
              </button>
            </div>
          </div>
        </div>
      </transition>
    </Teleport>

    <!-- ── Floating Popup Toast Notifikasi (Tanpa Source Link Browser) ── -->
    <Teleport to="body">
      <transition name="toast-slide">
        <div v-if="toastPopup.isOpen" class="popup-toast-wrapper">
          <div class="popup-toast-box" :class="'toast-' + toastPopup.type" role="alert">
            <div class="toast-icon-circle">
              <svg v-if="toastPopup.type === 'success'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
              </svg>
              <svg v-else-if="toastPopup.type === 'error'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
              </svg>
              <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
              </svg>
            </div>

            <div class="toast-content-col">
              <span class="toast-title-text">{{ toastPopup.title }}</span>
              <span class="toast-desc-text">{{ toastPopup.message }}</span>
            </div>

            <button type="button" class="toast-dismiss-btn" @click="closePopupToast" aria-label="Tutup notifikasi">
              &times;
            </button>
          </div>
        </div>
      </transition>
    </Teleport>

  </div>
</template>

<style scoped>
.templates-container {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 0;
  grid-column: 1 / -1;
}

/* ─── Browser-Style Multi-Tab Navigation Shell ─────────────────────────────── */
.browser-tabs-shell {
  width: 100%;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 14px 14px 0 0;
  padding: 8px 8px 0 8px;
  box-shadow: inset 0 -1px 0 #e2e8f0;
  margin-bottom: -1px;
  position: sticky;
  top: 56px;
  z-index: 25;
  display: flex;
  align-items: flex-end;
  gap: 4px;
}

.tabs-scroll-wrapper {
  display: flex;
  align-items: flex-end;
  gap: 6px;
  overflow-x: auto;
  overflow-y: hidden;
  scrollbar-width: none;
  padding-bottom: 0px;
  flex: 1;
}

.tabs-scroll-wrapper::-webkit-scrollbar {
  display: none;
}

.browser-tab {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 40px;
  padding: 0 12px 0 14px;
  border-radius: 10px 10px 0 0;
  border: 1.5px solid transparent;
  border-bottom: none;
  background: #e2e8f0;
  color: #475569;
  font-size: 0.84rem;
  font-weight: 600;
  cursor: grab;
  white-space: nowrap;
  user-select: none;
  transition: transform 0.15s ease, background 0.16s ease, border-color 0.16s ease, opacity 0.15s ease;
  position: relative;
  max-width: 260px;
  flex-shrink: 0;
}

.browser-tab:active {
  cursor: grabbing;
}

.browser-tab:hover:not(.active) {
  background: #ffffffa0;
  color: #0f172a;
  border-color: #cbd5e1;
}

/* Sedang di-drag */
.tab-is-dragging {
  opacity: 0.45 !important;
  transform: scale(0.96);
  border: 1.5px dashed #06b6d4 !important;
  border-bottom: none !important;
  background: #e0f2fe !important;
}

/* Indikator posisi drop */
.tab-drop-before {
  position: relative;
}

.tab-drop-before::before {
  content: '';
  position: absolute;
  left: -4px;
  top: 4px;
  bottom: 0px;
  width: 4px;
  background: #06b6d4;
  border-radius: 4px;
  box-shadow: 0 0 8px rgba(6, 182, 212, 0.9);
  z-index: 30;
  pointer-events: none;
  animation: dropGlow 0.8s infinite alternate;
}

.tab-drop-after {
  position: relative;
}

.tab-drop-after::after {
  content: '';
  position: absolute;
  right: -4px;
  top: 4px;
  bottom: 0px;
  width: 4px;
  background: #06b6d4;
  border-radius: 4px;
  box-shadow: 0 0 8px rgba(6, 182, 212, 0.9);
  z-index: 30;
  pointer-events: none;
  animation: dropGlow 0.8s infinite alternate;
}

@keyframes dropGlow {
  0% { opacity: 0.6; }
  100% { opacity: 1; transform: scaleY(1.05); }
}

.tab-drag-grip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  opacity: 0.35;
  margin-left: 2px;
  transition: opacity 0.15s;
  pointer-events: none;
}

.browser-tab:hover .tab-drag-grip {
  opacity: 0.8;
  color: #06b6d4;
}

/* Tombol panah geser bilah tab */
.btn-tab-scroll-arrow {
  width: 28px;
  height: 32px;
  margin-bottom: 4px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: all 0.15s ease;
}

.btn-tab-scroll-arrow:hover {
  background: #06b6d4;
  color: #ffffff;
  border-color: #06b6d4;
  box-shadow: 0 2px 6px rgba(6, 182, 212, 0.25);
}

/* Active tab style matching reference image with cyan/teal accent */
.browser-tab.active {
  background: #ffffff;
  color: #0f172a;
  font-weight: 700;
  border-color: #06b6d4;
  box-shadow: 0 -2px 10px rgba(6, 182, 212, 0.12);
  z-index: 2;
  position: relative;
}

.browser-tab.active::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: -2px;
  height: 3px;
  background: #ffffff;
}

.tab-icon-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 6px;
  background: rgba(0, 0, 0, 0.04);
  flex-shrink: 0;
}

.icon-catalog {
  color: #2563eb;
  background: #eff6ff;
}

.icon-docx {
  color: #0284c7;
  background: #e0f2fe;
}

.icon-xlsx {
  color: #059669;
  background: #d1fae5;
}

.icon-pdf {
  color: #dc2626;
  background: #fee2e2;
}

.icon-txt {
  color: #0d9488;
  background: #ccfbf1;
}

.icon-draft {
  color: #d97706;
  background: #fef3c7;
}

.tab-label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 140px;
  font-size: 0.83rem;
}

.tab-count-pill {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 999px;
  background: #dbeafe;
  color: #1e40af;
}

.tab-type-tag {
  font-size: 0.65rem;
  font-weight: 800;
  padding: 1px 5px;
  border-radius: 4px;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.type-docx {
  background: #e0f2fe;
  color: #0369a1;
}

.type-xlsx {
  background: #d1fae5;
  color: #047857;
}

.type-pdf {
  background: #fee2e2;
  color: #b91c1c;
}

.type-txt {
  background: #ccfbf1;
  color: #0f766e;
}

.type-draft {
  background: #fef3c7;
  color: #b45309;
}

.badge-xlsx,
.badge-csv {
  background: #d1fae5;
  color: #047857;
  border: 1px solid #6ee7b7;
}

/* ─── Excel Spreadsheet Interactive Viewer Styles ─────────────────────────── */
.excel-viewer-wrapper {
  display: flex;
  flex-direction: column;
  gap: 14px;
  background: #ffffff;
  border-radius: 12px;
  padding: 16px;
  border: 1px solid #e2e8f0;
}

.excel-quick-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding-bottom: 12px;
  border-bottom: 1px solid #f1f5f9;
}

.excel-info-left {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.excel-file-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border-radius: 8px;
  background: #ecfdf5;
  color: #065f46;
  font-size: 0.84rem;
  font-weight: 700;
  border: 1px solid #a7f3d0;
}

.excel-stats-pill {
  font-size: 0.78rem;
  font-weight: 600;
  color: #64748b;
  background: #f1f5f9;
  padding: 4px 10px;
  border-radius: 999px;
}

.excel-actions-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.excel-search-box {
  position: relative;
  display: flex;
  align-items: center;
}

.excel-search-box svg {
  position: absolute;
  left: 10px;
  color: #94a3b8;
  pointer-events: none;
}

.excel-search-input {
  height: 34px;
  padding: 0 28px 0 32px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  font-size: 0.8rem;
  width: 220px;
  background: #f8fafc;
  color: #1e293b;
  transition: all 0.15s ease;
}

.excel-search-input:focus {
  outline: none;
  border-color: #10b981;
  background: #ffffff;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
}

.btn-clear-excel-search {
  position: absolute;
  right: 6px;
  background: transparent;
  border: none;
  color: #94a3b8;
  font-size: 16px;
  cursor: pointer;
  line-height: 1;
}

.btn-excel-tool {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 34px;
  padding: 0 12px;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  user-select: none;
}

.btn-copy-excel {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #15803d;
}

.btn-copy-excel:hover {
  background: #dcfce7;
  border-color: #86efac;
}

.btn-excel-copied {
  background: #15803d !important;
  color: #ffffff !important;
  border-color: #15803d !important;
}

.btn-download-excel {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #334155;
}

.btn-download-excel:hover {
  background: #ecfdf5;
  border-color: #10b981;
  color: #047857;
}

/* Sheet Tabs */
.excel-sheet-tabs-bar {
  display: flex;
  align-items: center;
  gap: 6px;
  overflow-x: auto;
  padding: 4px 0;
  border-bottom: 1px solid #e2e8f0;
}

.excel-sheet-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 8px 8px 0 0;
  border: 1px solid transparent;
  border-bottom: none;
  background: #f1f5f9;
  color: #64748b;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.excel-sheet-tab:hover:not(.active) {
  background: #e2e8f0;
  color: #0f172a;
}

.excel-sheet-tab.active {
  background: #ecfdf5;
  border-color: #a7f3d0;
  color: #065f46;
  font-weight: 700;
  border-bottom: 2px solid #10b981;
}

.sheet-count-tag {
  font-size: 0.68rem;
  padding: 1px 6px;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.05);
}

.excel-sheet-tab.active .sheet-count-tag {
  background: #d1fae5;
  color: #047857;
}

/* Grid Table */
.excel-grid-container {
  max-height: 550px;
  overflow: auto;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #ffffff;
}

.excel-rendered-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
  text-align: left;
}

.excel-rendered-table thead {
  position: sticky;
  top: 0;
  z-index: 10;
  background: #f8fafc;
}

.excel-rendered-table th {
  padding: 9px 12px;
  font-weight: 700;
  color: #1e293b;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  white-space: nowrap;
}

.th-row-num {
  width: 45px;
  text-align: center;
  color: #94a3b8;
  background: #f1f5f9 !important;
}

.th-action {
  width: 55px;
  text-align: center;
}

.excel-row {
  transition: background 0.1s ease;
}

.excel-row:nth-child(even) {
  background: #fafafa;
}

.excel-row:hover {
  background: #ecfdf5 !important;
}

.td-row-num {
  text-align: center;
  color: #94a3b8;
  font-weight: 600;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.td-cell {
  padding: 7px 12px;
  border: 1px solid #e2e8f0;
  color: #334155;
  max-width: 320px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.td-action {
  text-align: center;
  border: 1px solid #e2e8f0;
  padding: 4px;
}

.btn-copy-row {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 4px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #64748b;
  cursor: pointer;
  transition: all 0.12s ease;
}

.btn-copy-row:hover {
  background: #ecfdf5;
  border-color: #10b981;
  color: #047857;
}

.excel-loading-state,
.excel-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  gap: 10px;
  color: #64748b;
}

.btn-clear-search-pill {
  padding: 6px 14px;
  border-radius: 8px;
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  color: #475569;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}

.tab-dirty-indicator {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #f59e0b;
  flex-shrink: 0;
  box-shadow: 0 0 6px rgba(245, 158, 11, 0.6);
  animation: pulseDirty 1.5s infinite;
}

@keyframes pulseDirty {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.4; transform: scale(0.85); }
}

.btn-tab-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: none;
  background: transparent;
  color: #94a3b8;
  cursor: pointer;
  padding: 0;
  margin-left: 2px;
  flex-shrink: 0;
  transition: all 0.15s ease;
}

.btn-tab-close:hover {
  background: #fee2e2;
  color: #dc2626;
}

.btn-tab-new {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 8px;
  border: 1.5px dashed #cbd5e1;
  background: transparent;
  color: #64748b;
  cursor: pointer;
  margin-bottom: 3px;
  margin-left: 4px;
  flex-shrink: 0;
  transition: all 0.15s ease;
}

.btn-tab-new:hover {
  background: #ffffff;
  color: #06b6d4;
  border-color: #06b6d4;
  box-shadow: 0 2px 6px rgba(6, 182, 212, 0.15);
}

.inline-nav-left-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-close-tab-inline {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #64748b;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-close-tab-inline:hover {
  background: #fee2e2;
  border-color: #fca5a5;
  color: #dc2626;
}

.btn-cancel-close-tab {
  color: #b91c1c !important;
  background: #fef2f2 !important;
  border-color: #fecaca !important;
}

.btn-cancel-close-tab:hover {
  background: #fee2e2 !important;
}

.templates-section-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: #ffffff;
  border-radius: 0 0 16px 16px;
  border: 1px solid #e2e8f0;
  border-top: none;
  padding: 20px;
}

.inline-document-card {
  border-top-left-radius: 0 !important;
  border-top-right-radius: 0 !important;
  border-top: none !important;
}

/* Header Section */
.templates-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.header-left {
  flex: 1;
  min-width: 240px;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-icon-circle {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: #eff6ff;
  color: #2563eb;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.section-heading {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.01em;
}

.section-caption {
  margin: 3px 0 0;
  font-size: 0.82rem;
  color: #64748b;
  line-height: 1.4;
}

.header-actions-group {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.hidden-file-input {
  display: none;
}

.btn-outline-upload {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  color: #334155;
  border-radius: 10px;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
  user-select: none;
}

.btn-outline-upload:hover:not(:disabled) {
  border-color: #2563eb;
  color: #2563eb;
  background: #eff6ff;
}

.btn-outline-upload:active:not(:disabled) {
  transform: scale(0.96);
}

.btn-add-template {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  font-size: 0.82rem;
  font-weight: 700;
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
  user-select: none;
}

.btn-add-template:active {
  transform: scale(0.96);
}

.alert-error-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: #fee2e2;
  color: #991b1b;
  border-radius: 10px;
  font-size: 0.84rem;
  border: 1px solid #fecaca;
}

.alert-success-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: #ecfdf5;
  color: #065f46;
  border-radius: 10px;
  font-size: 0.84rem;
  border: 1px solid #a7f3d0;
  animation: fadeIn 0.15s ease-out;
}

/* Filter Controls (Search + Category Pills) */
.filter-controls-row {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.search-input-wrap {
  position: relative;
  width: 100%;
}

.search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  pointer-events: none;
}

.search-field {
  width: 100%;
  padding: 10px 40px 10px 42px;
  font-size: 0.9rem;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  background: #f8fafc;
  outline: none;
  transition: all 0.15s ease;
}

.search-field:focus {
  background: #ffffff;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.btn-clear-search {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  padding: 0;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  color: #94a3b8;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-clear-search:hover {
  background: #f1f5f9;
  color: #334155;
}

.category-pills-bar {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding: 4px 2px 6px;
  scrollbar-width: none;
  -ms-overflow-style: none;
  -webkit-overflow-scrolling: touch;
}

.category-pills-bar::-webkit-scrollbar {
  display: none;
}

.cat-pill {
  padding: 6px 14px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #64748b;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
}

.cat-pill:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.cat-pill:active {
  transform: scale(0.94);
}

.cat-pill.active {
  background: #2563eb;
  color: #ffffff;
  border-color: #2563eb;
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
}

.state-notice {
  text-align: center;
  padding: 36px 20px;
  color: #64748b;
  font-size: 0.88rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.loading-spinner-sm {
  width: 18px;
  height: 18px;
  border: 2px solid #cbd5e1;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Templates Grid */
.templates-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  gap: 14px;
}

.template-card {
  display: flex;
  flex-direction: column;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 16px;
  cursor: pointer;
  position: relative;
  transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
}

.template-card:hover {
  transform: translateY(-2px);
  border-color: #cbd5e1;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
}

.template-card:active {
  transform: scale(0.985);
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
  padding: 3px 8px;
  border-radius: 6px;
  border: 1px solid transparent;
}

.badge-cat-umum {
  background: #f1f5f9;
  color: #475569;
  border-color: #e2e8f0;
}

.badge-cat-script {
  background: #eef2ff;
  color: #4f46e5;
  border-color: #c7d2fe;
}

.badge-cat-eskalasi {
  background: #fffbeb;
  color: #b45309;
  border-color: #fde68a;
}

.badge-cat-billing {
  background: #ecfdf5;
  color: #047857;
  border-color: #a7f3d0;
}

.badge-cat-teknis {
  background: #f0f9ff;
  color: #0284c7;
  border-color: #bae6fd;
}

.badge-cat-sop {
  background: #fff1f2;
  color: #be123c;
  border-color: #fecdd3;
}

.badge-file-type {
  font-size: 0.66rem;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 4px;
  letter-spacing: 0.04em;
}

.badge-docx {
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
}

.badge-pdf {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.badge-txt, .badge-md {
  background: #f8fafc;
  color: #475569;
  border: 1px solid #e2e8f0;
}

.tcard-title {
  margin: 0 0 6px;
  font-size: 0.96rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.tcard-preview {
  margin: 0 0 10px;
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

.tcard-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-size: 0.74rem;
  color: #94a3b8;
  margin-top: auto;
  padding-top: 8px;
  border-top: 1px dashed #f1f5f9;
}

.meta-author {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.meta-locked-tag {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 0.68rem;
  font-weight: 700;
  color: #dc2626;
  background: #fef2f2;
  border: 1px solid #fee2e2;
  padding: 1px 6px;
  border-radius: 4px;
}

/* Card Actions (Bottom row) */
.tcard-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
  position: relative;
}

.tcard-actions-left {
  display: flex;
  align-items: center;
  gap: 6px;
}

.tcard-actions-right {
  position: relative;
}

.btn-card-action {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.12s ease;
  user-select: none;
}

.btn-card-action:active {
  transform: scale(0.94);
}

.btn-card-edit {
  background: #eff6ff;
  color: #2563eb;
  border-color: #bfdbfe;
}

.btn-card-edit:hover {
  background: #dbeafe;
}

.btn-card-view-locked {
  background: #f8fafc;
  color: #334155;
  border-color: #cbd5e1;
}

.btn-card-view-locked:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.btn-card-copy {
  background: #f8fafc;
  color: #334155;
  border-color: #e2e8f0;
}

.btn-card-copy:hover {
  background: #f1f5f9;
}

.btn-card-copied {
  background: #ecfdf5 !important;
  color: #059669 !important;
  border-color: #a7f3d0 !important;
}

.btn-card-kebab {
  width: 32px;
  height: 32px;
  padding: 0;
  margin: 0;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
  flex-shrink: 0;
}

.btn-card-kebab:hover,
.btn-card-kebab.active {
  background: #f1f5f9;
  border-color: #cbd5e1;
  color: #0f172a;
}

.btn-card-kebab:active {
  transform: scale(0.92);
}

.btn-card-kebab svg {
  display: block;
  pointer-events: none;
}

/* Card Popover Dropdown */
.card-popover-menu {
  position: absolute;
  right: 0;
  bottom: 38px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.12);
  padding: 6px;
  z-index: 30;
  min-width: 175px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  animation: popoverFadeIn 0.12s ease-out;
}

@keyframes popoverFadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}

.popover-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  font-size: 0.78rem;
  font-weight: 600;
  color: #334155;
  border-radius: 8px;
  cursor: pointer;
  border: none;
  background: transparent;
  width: 100%;
  text-align: left;
  transition: background 0.12s ease;
}

.popover-item:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.popover-divider {
  height: 1px;
  background: #f1f5f9;
  margin: 4px 0;
}

.popover-item.item-danger {
  color: #dc2626;
}

.popover-item.item-danger:hover {
  background: #fef2f2;
  color: #b91c1c;
}

/* Empty State */
.empty-state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  text-align: center;
  border: 2px dashed #e2e8f0;
  border-radius: 14px;
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
  border-radius: 16px;
  border: 1px solid #e2e8f0;
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

.btn-back-link:active {
  transform: scale(0.96);
}

.inline-actions-group {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-action {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 7px 12px;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.12s ease;
  user-select: none;
}

.btn-action:active {
  transform: scale(0.95);
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
  background: #ecfdf5 !important;
  color: #059669 !important;
  border-color: #a7f3d0 !important;
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

.btn-download:first-child {
  border-radius: 8px 0 0 8px;
}

.btn-txt-opt {
  padding: 7px 8px;
  font-size: 0.74rem;
  border-left: none;
  border-radius: 0 8px 8px 0;
}

.btn-delete-view {
  background: #fee2e2;
  color: #dc2626;
  border-color: #fecaca;
}

.btn-delete-view:hover {
  background: #fecaca;
}

.badge-locked-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 700;
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

.btn-copy-prominent {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 18px;
  font-size: 0.85rem;
  font-weight: 700;
  border-radius: 8px;
  background: #2563eb;
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
  transition: all 0.15s ease;
}

.btn-copy-prominent:hover {
  background: #1d4ed8;
}

.btn-copy-prominent:active {
  transform: scale(0.96);
}

.save-actions-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
}

.autosave-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.78rem;
  font-weight: 600;
  padding: 5px 10px;
  border-radius: 8px;
  animation: fadeIn 0.15s ease-out;
}

.as-saving {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #cbd5e1;
}

.as-saving-text {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.loading-spinner-xs {
  width: 12px;
  height: 12px;
  border: 2px solid #94a3b8;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.as-saved {
  background: #ecfdf5;
  color: #059669;
  border: 1px solid #a7f3d0;
}

.as-saved-text {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.as-unsaved {
  background: #fffbeb;
  color: #b45309;
  border: 1px solid #fde68a;
}

.as-unsaved-text {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.unsaved-pulse-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #f59e0b;
  box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.3);
  animation: pulseDot 1.4s infinite ease-in-out;
}

@keyframes pulseDot {
  0% { transform: scale(0.9); opacity: 0.8; }
  50% { transform: scale(1.25); opacity: 1; }
  100% { transform: scale(0.9); opacity: 0.8; }
}

.as-error {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.btn-save-text-wrap {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.kbd-shortcut {
  display: inline-block;
  background: rgba(255, 255, 255, 0.22);
  padding: 1px 5px;
  border-radius: 4px;
  font-size: 0.72rem;
  font-family: inherit;
  font-weight: 700;
  letter-spacing: 0.02em;
  border: 1px solid rgba(255, 255, 255, 0.35);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}

.btn-save {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 18px;
  font-size: 0.85rem;
  font-weight: 700;
  border-radius: 8px;
  transition: all 0.15s ease;
}

.btn-save:active {
  transform: scale(0.96);
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

/* Banner Dokumen Patokan PDF Resmi */
.pdf-locked-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: #fef2f2;
  border: 1px solid #fee2e2;
  border-left: 4px solid #dc2626;
  border-radius: 10px;
}

.locked-icon-wrap {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #fee2e2;
  color: #dc2626;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.locked-text-wrap {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.locked-text-wrap strong {
  font-size: 0.88rem;
  font-weight: 700;
  color: #991b1b;
}

.locked-text-wrap p {
  margin: 0;
  font-size: 0.8rem;
  color: #7f1d1d;
  line-height: 1.45;
}

/* Dokumen Header: Judul & Meta */
.doc-header-block {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.doc-title-locked {
  padding: 4px 6px;
}

.title-text-locked {
  margin: 0;
  font-size: 1.45rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.01em;
  word-break: break-word;
}

.doc-title-input {
  width: 100%;
  font-size: 1.45rem;
  font-weight: 800;
  color: #0f172a;
  border: 1px solid transparent;
  border-radius: 10px;
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
  padding: 4px 10px;
  font-size: 0.8rem;
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

/* Toolbar TipTap Formatting */
.editor-toolbar {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 8px 12px;
  background: #f1f5f9;
  border-radius: 12px 12px 0 0;
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
  user-select: none;
}

.tb-btn:hover:not(:disabled) {
  background: #e2e8f0;
  color: #0f172a;
}

.tb-btn:active:not(:disabled) {
  transform: scale(0.92);
}

.tb-btn.active {
  background: #2563eb;
  color: #ffffff;
}

.tb-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.toolbar-spacer {
  flex: 1;
}

.toolbar-nav-toggle-group {
  display: flex;
  align-items: center;
}

.tb-btn-nav {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 10px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #334155;
  border-radius: 6px;
  font-weight: 700;
  font-size: 0.8rem;
  transition: all 0.15s ease;
}

.tb-btn-nav:hover {
  background: #eff6ff;
  border-color: #bfdbfe;
  color: #2563eb;
}

.tb-btn-nav.active {
  background: #2563eb;
  color: #ffffff;
  border-color: #2563eb;
}

.tb-nav-count-badge {
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 8px;
}

.tb-btn-nav.active .tb-nav-count-badge {
  background: rgba(255, 255, 255, 0.25);
  color: #ffffff;
}

/* Lembar Kertas Dokumen */
.doc-body-wrapper {
  background: #f8fafc;
  padding: 14px;
  border-radius: 12px;
  border: 1px solid #cbd5e1;
}

.editor-toolbar + .doc-body-wrapper {
  border-radius: 0 0 12px 12px;
}

/* ─── Quick Jump Heading Bar di Atas Lembar Template ─── */
.quick-jump-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 9px;
  padding: 8px 12px;
  margin-bottom: 12px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
  flex-wrap: wrap;
}

.quick-jump-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.82rem;
  font-weight: 700;
  color: #1e293b;
  white-space: nowrap;
}

.quick-jump-label svg {
  color: #2563eb;
}

.quick-jump-select-wrap {
  flex: 1 1 200px;
  position: relative;
  min-width: 160px;
}

.quick-jump-select {
  width: 100%;
  appearance: none;
  -webkit-appearance: none;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  border-radius: 7px;
  padding: 6px 28px 6px 10px;
  font-size: 0.83rem;
  font-weight: 600;
  color: #1e293b;
  cursor: pointer;
  outline: none;
  transition: all 0.15s ease;
  text-overflow: ellipsis;
  white-space: nowrap;
  overflow: hidden;
}

.quick-jump-select:hover {
  background: #ffffff;
  border-color: #94a3b8;
}

.quick-jump-select:focus {
  background: #ffffff;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

.quick-jump-arrow {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
  color: #64748b;
  display: flex;
  align-items: center;
}

.btn-toggle-nav-inline {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  border-radius: 7px;
  font-size: 0.78rem;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}

.btn-toggle-nav-inline:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.btn-toggle-nav-inline.active {
  background: #eff6ff;
  border-color: #bfdbfe;
  color: #1d4ed8;
}

.doc-editor-layout {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  position: relative;
  width: 100%;
}

.doc-paper-sheet {
  flex: 1 1 0%;
  min-width: 0;
  background: #ffffff;
  padding: 32px 42px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  font-family: 'Calibri', 'Aptos', 'Segoe UI', -apple-system, BlinkMacSystemFont, Arial, sans-serif;
  font-size: 11pt;
  line-height: 1.25;
  letter-spacing: -0.012em;
  color: #1e293b;
  min-height: 480px;
  cursor: text;
}

/* ══════════════════════════════════════════════════════════════════════════
   PANEL NAVIGASI HEADING PERSIS MICROSOFT WORD (DI SEBELAH KANAN)
   ══════════════════════════════════════════════════════════════════════════ */
.word-nav-pane {
  width: 310px;
  flex-shrink: 0;
  position: sticky;
  top: 16px;
  max-height: calc(100vh - 90px);
  display: flex;
  flex-direction: column;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
  overflow: hidden;
  transition: all 0.2s ease;
  user-select: none;
}

/* Backdrop navigasi mobile/tablet (default tersembunyi pada desktop) */
.word-nav-backdrop {
  display: none;
}

/* Tombol mengambang buka navigasi (default tersembunyi pada desktop) */
.floating-nav-trigger {
  display: none;
}

.fade-nav-backdrop-enter-active,
.fade-nav-backdrop-leave-active {
  transition: opacity 0.2s ease;
}

.fade-nav-backdrop-enter-from,
.fade-nav-backdrop-leave-to {
  opacity: 0;
}

.word-nav-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.word-nav-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #1e293b;
  font-weight: 700;
  font-size: 0.88rem;
}

.word-nav-icon {
  color: #2563eb;
}

.word-nav-badge {
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 1px 7px;
  border-radius: 10px;
  border: 1px solid #dbeafe;
}

.word-nav-header-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-word-nav-pin {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #64748b;
  font-size: 0.72rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-word-nav-pin:hover {
  background: #f8fafc;
  color: #1e293b;
  border-color: #cbd5e1;
}

.btn-word-nav-pin.is-pinned {
  background: #eff6ff;
  color: #2563eb;
  border-color: #bfdbfe;
  font-weight: 700;
}

.btn-word-nav-close {
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.12s ease;
}

.btn-word-nav-close:hover {
  background: #e2e8f0;
  color: #0f172a;
}

/* Search Box ala MS Word */
.word-nav-search-wrap {
  position: relative;
  padding: 8px 12px;
  background: #ffffff;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  align-items: center;
}

.word-search-icon {
  position: absolute;
  left: 20px;
  color: #94a3b8;
  pointer-events: none;
}

.word-search-input {
  width: 100%;
  padding: 6px 28px 6px 30px;
  font-size: 0.82rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  outline: none;
  background: #f8fafc;
  color: #1e293b;
  transition: all 0.15s ease;
}

.word-search-input:focus {
  background: #ffffff;
  border-color: #2563eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

.btn-word-search-clear {
  position: absolute;
  right: 18px;
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 2px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-word-search-clear:hover {
  color: #334155;
}

/* Tabs ala MS Word (Headings / Ringkasan) */
.word-nav-tabs {
  display: flex;
  background: #f1f5f9;
  border-bottom: 1px solid #e2e8f0;
  padding: 2px 4px 0;
  gap: 4px;
}

.word-tab-btn {
  flex: 1;
  padding: 7px 10px;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  font-size: 0.78rem;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  transition: all 0.12s ease;
}

.word-tab-btn:hover {
  color: #1e293b;
}

.word-tab-btn.active {
  color: #2563eb;
  font-weight: 700;
  border-bottom-color: #2563eb;
  background: #ffffff;
  border-radius: 6px 6px 0 0;
}

.tab-count-pill {
  font-size: 0.7rem;
  background: #e2e8f0;
  color: #475569;
  padding: 1px 6px;
  border-radius: 8px;
}

.word-tab-btn.active .tab-count-pill {
  background: #eff6ff;
  color: #1d4ed8;
}

/* Kontainer List Heading */
.word-nav-content {
  flex: 1;
  overflow-y: auto;
  max-height: calc(100vh - 240px);
  min-height: 240px;
  padding: 6px 0;
}

.word-nav-content::-webkit-scrollbar {
  width: 5px;
}
.word-nav-content::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
.word-nav-content::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

.word-nav-controls {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 4px 12px 6px;
  font-size: 0.72rem;
  border-bottom: 1px solid #f1f5f9;
  margin-bottom: 4px;
}

.controls-label {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  color: #94a3b8;
}

.controls-btns {
  display: flex;
  align-items: center;
  gap: 4px;
}

.btn-ctrl-action {
  background: transparent;
  border: none;
  color: #2563eb;
  font-size: 0.72rem;
  font-weight: 600;
  cursor: pointer;
  padding: 1px 4px;
  border-radius: 4px;
}

.btn-ctrl-action:hover {
  text-decoration: underline;
}

.ctrl-sep {
  color: #cbd5e1;
  font-size: 0.7rem;
}

.word-nav-search-status {
  padding: 6px 12px;
  background: #f8fafc;
  font-size: 0.75rem;
  color: #64748b;
  border-bottom: 1px solid #f1f5f9;
}

.word-heading-list {
  display: flex;
  flex-direction: column;
}

.word-heading-item {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  cursor: pointer;
  user-select: none;
  transition: background 0.1s ease, color 0.1s ease;
  border-left: 3px solid transparent;
}

.word-heading-item:hover {
  background: #f1f5f9;
}

.word-heading-item.active-heading {
  background: #eff6ff;
  border-left-color: #2563eb;
}

.word-heading-item.active-heading .heading-label {
  color: #1d4ed8;
  font-weight: 700;
}

/* Indentasi Hirarki Persis MS Word */
.word-heading-item.level-1 {
  padding-left: 8px;
}
.word-heading-item.level-1 .heading-label {
  font-weight: 700;
  color: #0f172a;
  font-size: 0.83rem;
}

.word-heading-item.level-2 {
  padding-left: 20px;
}
.word-heading-item.level-2 .heading-label {
  font-weight: 500;
  color: #334155;
  font-size: 0.79rem;
}

.word-heading-item.level-3 {
  padding-left: 32px;
}
.word-heading-item.level-3 .heading-label {
  font-weight: 400;
  color: #64748b;
  font-size: 0.76rem;
}

.word-heading-item.level-4 {
  padding-left: 42px;
}
.word-heading-item.level-4 .heading-label {
  font-weight: 400;
  color: #94a3b8;
  font-size: 0.74rem;
}

.btn-heading-caret {
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 2px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 3px;
  transition: transform 0.15s ease;
  flex-shrink: 0;
}

.btn-heading-caret:hover {
  background: #cbd5e1;
  color: #0f172a;
}

.btn-heading-caret.collapsed {
  transform: rotate(-90deg);
}

.caret-spacer {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

.heading-tag {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 1px 4px;
  border-radius: 4px;
  letter-spacing: 0.02em;
  flex-shrink: 0;
}

.tag-h1 {
  background: #eff6ff;
  color: #2563eb;
  border: 1px solid #bfdbfe;
}

.tag-h2 {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
}

.tag-h3, .tag-h4 {
  background: #faf5ff;
  color: #7e22ce;
  border: 1px solid #f3e8ff;
}

.heading-label {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.4;
}

.btn-copy-heading-text {
  opacity: 0;
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 2px 4px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.12s ease;
  flex-shrink: 0;
}

.word-heading-item:hover .btn-copy-heading-text {
  opacity: 1;
}

.btn-copy-heading-text:hover {
  background: #e2e8f0;
  color: #1e293b;
}

.word-nav-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 30px 16px;
  text-align: center;
  gap: 6px;
}

.empty-nav-title {
  margin: 6px 0 0;
  font-size: 0.85rem;
  font-weight: 700;
  color: #475569;
}

.empty-nav-desc {
  margin: 0;
  font-size: 0.76rem;
  color: #64748b;
  line-height: 1.45;
}

.btn-clear-search-sm {
  margin-top: 8px;
  padding: 4px 10px;
  background: #eff6ff;
  color: #2563eb;
  border: 1px solid #bfdbfe;
  border-radius: 6px;
  font-size: 0.74rem;
  font-weight: 600;
  cursor: pointer;
}

/* Stats Tab Styling */
.word-nav-stats {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.stats-card-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.stat-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 8px 10px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.stat-num {
  font-size: 1.25rem;
  font-weight: 800;
  color: #2563eb;
  line-height: 1.2;
}

.stat-lbl {
  font-size: 0.7rem;
  color: #64748b;
  font-weight: 600;
  margin-top: 2px;
}

.stats-breakdown, .stats-doc-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.breakdown-title {
  margin: 0 0 2px;
  font-size: 0.76rem;
  font-weight: 700;
  color: #334155;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.breakdown-row, .info-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.78rem;
  padding: 4px 6px;
  background: #f8fafc;
  border-radius: 6px;
}

.breakdown-badge {
  font-size: 0.7rem;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 4px;
}

.breakdown-val, .info-v {
  font-weight: 600;
  color: #1e293b;
}

.info-k {
  color: #64748b;
}

.word-nav-footer {
  padding: 8px 12px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  font-size: 0.72rem;
  color: #94a3b8;
  text-align: center;
}

/* Efek Kilau Highlight saat Melompat ke Heading */
:deep(.heading-jump-highlight) {
  animation: headingPulse 2.2s ease-out;
  border-radius: 6px;
}

@keyframes headingPulse {
  0% {
    background-color: #fef08a;
    box-shadow: 0 0 0 6px #fef08a;
  }
  30% {
    background-color: #fef9c3;
    box-shadow: 0 0 0 4px #fef9c3;
  }
  100% {
    background-color: transparent;
    box-shadow: 0 0 0 0 transparent;
  }
}

.doc-paper-locked {
  cursor: default !important;
  background: #fafbfc;
  border-color: #e2e8f0;
}

.doc-paper-locked :deep(.tiptap-editor-body) {
  cursor: default !important;
  user-select: text;
}

/* Container Viewer Dokumen PDF Asli Tanpa Perubahan Format */
.pdf-viewer-wrapper {
  display: flex;
  flex-direction: column;
  gap: 10px;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #cbd5e1;
  padding: 12px;
}

.pdf-quick-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 12px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  flex-wrap: wrap;
}

.pdf-toolbar-info {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #dc2626;
  font-weight: 700;
  font-size: 0.82rem;
}

.pdf-filename-tag {
  color: #1e293b;
  font-weight: 600;
  max-width: 320px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pdf-toolbar-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-pdf-tool {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 0.78rem;
  font-weight: 700;
  background: #f1f5f9;
  color: #334155;
  border: 1px solid #cbd5e1;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.12s ease;
  user-select: none;
}

.btn-pdf-tool:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.btn-pdf-download {
  background: #eff6ff;
  color: #2563eb;
  border-color: #bfdbfe;
}

.btn-pdf-download:hover {
  background: #dbeafe;
}

.pdf-frame-container {
  width: 100%;
  min-height: 640px;
  background: #475569;
  border-radius: 8px;
  overflow: hidden;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pdf-native-frame {
  width: 100%;
  height: 640px;
  border: none;
  display: block;
}

.pdf-loading-state,
.pdf-fallback-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: #f1f5f9;
  font-size: 0.88rem;
  padding: 40px 20px;
}

.btn-download-pdf {
  background: #fef2f2;
  color: #dc2626;
  border-color: #fecaca;
}

.btn-download-pdf:hover {
  background: #fee2e2;
}

:deep(.tiptap-editor-body) {
  outline: none;
  min-height: 350px;
  font-family: 'Calibri', 'Aptos', 'Segoe UI', -apple-system, BlinkMacSystemFont, Arial, sans-serif;
  font-size: 11pt;
  line-height: 1.28;
  letter-spacing: -0.012em;
  color: #1e293b;
  text-rendering: optimizeLegibility;
  -webkit-font-smoothing: antialiased;
}

:deep(.tiptap-editor-body h1) {
  font-size: 1.35rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.22;
  letter-spacing: -0.015em;
  margin: 0.75em 0 0.25em;
  border-bottom: 1.5px solid #e2e8f0;
  padding-bottom: 4px;
}

:deep(.tiptap-editor-body h1:first-child) {
  margin-top: 0;
}

:deep(.tiptap-editor-body h2) {
  font-size: 1.18rem;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.25;
  letter-spacing: -0.012em;
  margin: 0.65em 0 0.2em;
}

:deep(.tiptap-editor-body h3) {
  font-size: 1.05rem;
  font-weight: 700;
  color: #334155;
  line-height: 1.25;
  letter-spacing: -0.01em;
  margin: 0.55em 0 0.18em;
}

:deep(.tiptap-editor-body p) {
  margin: 0 0 0.2em;
  line-height: 1.25;
  letter-spacing: -0.012em;
}

/* Kerapatan baris kosong / enter pada dokumen Word */
:deep(.tiptap-editor-body p:empty),
:deep(.tiptap-editor-body p:has(> br:only-child)) {
  margin: 0.06em 0;
  min-height: 0.95em;
}

:deep(.tiptap-editor-body ul),
:deep(.tiptap-editor-body ol) {
  padding-left: 22px;
  margin: 0 0 0.35em;
  line-height: 1.28;
}

:deep(.tiptap-editor-body li) {
  margin-bottom: 0.12em;
  letter-spacing: -0.012em;
}

:deep(.tiptap-editor-body blockquote) {
  border-left: 3.5px solid #2563eb;
  padding: 6px 14px;
  background: #f8fafc;
  color: #475569;
  font-style: italic;
  border-radius: 0 6px 6px 0;
  margin: 0.5em 0;
  line-height: 1.3;
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
  margin: 0.8em 0;
}

:deep(.tiptap-editor-body .docx-header-line) {
  background: #eff6ff;
  border-left: 4px solid #2563eb;
  padding: 6px 12px;
  color: #1d4ed8;
  font-weight: 600;
  font-size: 0.82rem;
  border-radius: 0 6px 6px 0;
  margin-bottom: 12px;
  line-height: 1.28;
}

:deep(.tiptap-editor-body table) {
  border-collapse: collapse;
  width: 100%;
  margin: 0.6em 0;
  font-size: 10pt;
}

:deep(.tiptap-editor-body th),
:deep(.tiptap-editor-body td) {
  border: 1px solid #cbd5e1;
  padding: 4px 8px;
  text-align: left;
  line-height: 1.25;
  letter-spacing: -0.01em;
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

/* ══════════════════════════════════════════════════════════════════════════
   RESPONSIVE DESIGN SAAT TAB / JENDELA DIKECILKAN (<= 1024px)
   - Lembar template dibuat LEBAR & LEGA (tidak terhimpit sidebar navigasi)
   - Panel navigasi menjadi slide-over drawer interaktif dengan backdrop
   ══════════════════════════════════════════════════════════════════════════ */
@media (max-width: 1024px) {
  .doc-editor-layout {
    display: block;
    width: 100%;
    position: relative;
  }

  .doc-paper-sheet {
    width: 100% !important;
    max-width: 100% !important;
    flex: none;
    padding: 24px 28px;
    font-size: 11.5pt;
    line-height: 1.34;
    box-sizing: border-box;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    border-radius: 10px;
  }

  :deep(.tiptap-editor-body) {
    font-size: 11.5pt;
    line-height: 1.34;
  }

  :deep(.tiptap-editor-body h1) {
    font-size: 1.38rem;
  }

  :deep(.tiptap-editor-body h2) {
    font-size: 1.2rem;
  }

  /* Backdrop gelap dinonaktifkan agar template di belakang tetap terang dan mudah dibaca */
  .word-nav-backdrop {
    display: none !important;
  }

  /* Panel Navigasi Mode Floating Sidebar */
  .word-nav-pane {
    position: fixed;
    top: 60px;
    left: 12px;
    bottom: 24px;
    height: auto;
    max-height: calc(100vh - 84px);
    width: min(320px, 84vw);
    z-index: 9999;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-radius: 14px;
    border: 1px solid #cbd5e1;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.22);
    animation: slideNavIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  }

  /* Tombol Mengambang (Floating Action) untuk memunculkan kembali Navigasi */
  .floating-nav-trigger {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    position: fixed;
    bottom: 24px;
    left: 20px;
    z-index: 950;
    padding: 9px 16px;
    background: #2563eb;
    color: #ffffff;
    font-size: 0.86rem;
    font-weight: 600;
    border: none;
    border-radius: 999px;
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.38);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  }

  .floating-nav-trigger:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(37, 99, 235, 0.48);
  }

  .floating-nav-trigger svg {
    color: #ffffff;
  }
}

@keyframes slideNavIn {
  from {
    transform: translateX(-100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

/* ══════════════════════════════════════════════════════════════════════════
   MOBILE RESPONSIVENESS (<= 640px) - NATIVE APP LOOK & FEEL
   ══════════════════════════════════════════════════════════════════════════ */
@media (max-width: 640px) {
  .templates-section-card {
    padding: 16px 14px;
    border-radius: 14px;
  }

  .templates-header {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }

  .header-actions-group {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    width: 100%;
  }

  .btn-outline-upload,
  .btn-add-template {
    justify-content: center;
    padding: 10px 12px;
    font-size: 0.8rem;
    height: 40px;
  }

  .search-field {
    font-size: 16px; /* Cegah auto-zoom iOS */
    padding: 10px 36px 10px 38px;
  }

  .templates-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .template-card {
    padding: 14px;
  }

  .tcard-title {
    font-size: 0.94rem;
  }

  /* Document View on Mobile */
  .inline-document-card {
    padding: 16px 12px 90px; /* ruang bawah aman untuk mobile dock */
    border-radius: 14px;
  }

  .inline-nav-bar {
    align-items: center;
    gap: 8px;
    padding-bottom: 10px;
  }

  .btn-back-link {
    padding: 6px 8px;
    font-size: 0.82rem;
  }

  .btn-text-responsive {
    display: none; /* Sembunyikan teks label, tampilkan icon pada layar sempit */
  }

  .doc-title-input {
    font-size: 1.2rem;
    padding: 4px 6px;
  }

  .title-text-locked {
    font-size: 1.2rem;
    padding: 0;
  }

  .pdf-locked-banner {
    padding: 10px 12px;
    gap: 10px;
  }

  .locked-icon-wrap {
    width: 32px;
    height: 32px;
  }

  .pdf-native-frame {
    height: 480px;
  }

  .pdf-quick-toolbar {
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
  }

  .pdf-toolbar-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
  }

  .btn-pdf-tool {
    justify-content: center;
    padding: 8px 10px;
  }

  /* Sticky Horizontal Formatting Toolbar on Mobile */
  .editor-toolbar {
    overflow-x: auto;
    white-space: nowrap;
    flex-wrap: nowrap;
    padding: 8px 6px;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
    position: sticky;
    top: 56px;
    z-index: 20;
    background: rgba(241, 245, 249, 0.98);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border-radius: 10px 10px 0 0;
  }

  .editor-toolbar::-webkit-scrollbar {
    display: none;
  }

  .tb-btn {
    min-width: 32px;
    height: 32px;
  }

  .doc-body-wrapper {
    padding: 6px;
  }

  .doc-paper-sheet {
    padding: 16px 12px;
    font-size: 0.92rem;
    min-height: 320px;
  }

  :deep(.tiptap-editor-body) {
    min-height: 280px;
  }

  /* Floating Bottom Dock on Mobile (Above Bottom Nav) */
  .doc-footer-bar {
    position: fixed;
    bottom: 68px;
    left: 12px;
    right: 12px;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid #cbd5e1;
    border-radius: 14px;
    padding: 8px 12px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.15);
    z-index: 980;
    margin: 0;
  }

  .footer-left-info {
    display: none;
  }

  .doc-footer-bar .inline-actions-group {
    width: 100%;
    justify-content: space-between;
  }

  .doc-footer-bar .btn-save,
  .doc-footer-bar .btn-copy-prominent {
    flex: 1;
    justify-content: center;
    height: 40px;
  }

  .doc-footer-bar .btn-cancel {
    height: 40px;
  }

  /* Responsive Heading Navigation Panel pada Mobile */
  .word-nav-pane {
    top: 56px;
    left: 8px;
    bottom: 76px;
    width: min(300px, calc(100vw - 16px));
    height: auto;
    max-height: calc(100vh - 136px);
    border-radius: 12px;
  }

  .floating-nav-trigger {
    bottom: 78px;
    left: 14px;
    padding: 7px 14px;
    font-size: 0.82rem;
  }

  .tb-btn-nav-text {
    display: none;
  }
}

/* ─── Custom In-App Popup Confirm Dialog (No Browser Origin / Source Link) ─── */
.custom-dialog-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 99999;
  padding: 16px;
}

.custom-dialog-box {
  background: #ffffff;
  border-radius: 18px;
  width: 100%;
  max-width: 440px;
  box-shadow: 0 20px 40px -8px rgba(15, 23, 42, 0.25), 0 1px 3px rgba(15, 23, 42, 0.08);
  border: 1px solid #e2e8f0;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  animation: dialogPopIn 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes dialogPopIn {
  0% {
    opacity: 0;
    transform: scale(0.95) translateY(6px);
  }
  100% {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.custom-dialog-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.dialog-icon-circle {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.dialog-icon-circle.icon-danger {
  background: #fee2e2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.dialog-icon-circle.icon-warning {
  background: #fef3c7;
  color: #d97706;
  border: 1px solid #fde68a;
}

.dialog-close-btn {
  background: none;
  border: none;
  color: #94a3b8;
  font-size: 1.4rem;
  line-height: 1;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 6px;
  transition: all 0.15s ease;
}

.dialog-close-btn:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.custom-dialog-content {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.custom-dialog-title {
  margin: 0;
  font-size: 1.12rem;
  font-weight: 700;
  color: #0f172a;
}

.custom-dialog-message {
  margin: 0;
  font-size: 0.88rem;
  color: #475569;
  line-height: 1.5;
}

.custom-dialog-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 8px;
}

.dialog-btn-cancel {
  padding: 9px 16px;
  border-radius: 10px;
  border: 1.5px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  font-size: 0.84rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.dialog-btn-cancel:hover {
  background: #f1f5f9;
  color: #0f172a;
  border-color: #94a3b8;
}

.dialog-btn-action {
  padding: 9px 18px;
  border-radius: 10px;
  border: none;
  font-size: 0.84rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.dialog-btn-danger {
  background: #dc2626;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
}

.dialog-btn-danger:hover {
  background: #b91c1c;
  transform: translateY(-1px);
}

.dialog-btn-warning {
  background: #d97706;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
}

.dialog-btn-warning:hover {
  background: #b45309;
  transform: translateY(-1px);
}

/* ─── Floating Popup Toast Notification (Top Center) ─── */
.popup-toast-wrapper {
  position: fixed;
  top: 24px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 100000;
  pointer-events: none;
  width: 90%;
  max-width: 520px;
}

.popup-toast-box {
  pointer-events: auto;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 18px;
  border-radius: 14px;
  background: #ffffff;
  box-shadow: 0 16px 36px -6px rgba(15, 23, 42, 0.18), 0 4px 10px rgba(15, 23, 42, 0.06);
  border: 1.5px solid #e2e8f0;
}

.popup-toast-box.toast-success {
  border-color: #86efac;
  background: #f0fdf4;
}

.popup-toast-box.toast-success .toast-icon-circle {
  background: #dcfce7;
  color: #15803d;
}

.popup-toast-box.toast-error {
  border-color: #fca5a5;
  background: #fef2f2;
}

.popup-toast-box.toast-error .toast-icon-circle {
  background: #fee2e2;
  color: #dc2626;
}

.toast-icon-circle {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.toast-content-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 0;
}

.toast-title-text {
  font-size: 0.88rem;
  font-weight: 800;
  color: #0f172a;
}

.toast-desc-text {
  font-size: 0.82rem;
  color: #475569;
  line-height: 1.4;
  word-break: break-word;
}

.toast-dismiss-btn {
  background: none;
  border: none;
  color: #94a3b8;
  font-size: 1.4rem;
  line-height: 1;
  cursor: pointer;
  padding: 2px 6px;
  border-radius: 6px;
  transition: all 0.15s ease;
  flex-shrink: 0;
}

.toast-dismiss-btn:hover {
  background: rgba(0, 0, 0, 0.06);
  color: #0f172a;
}

/* Transitions */
.toast-slide-enter-active,
.toast-slide-leave-active {
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.toast-slide-enter-from,
.toast-slide-leave-to {
  opacity: 0;
  transform: translate(-50%, -20px) scale(0.96);
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
