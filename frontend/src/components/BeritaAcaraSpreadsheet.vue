<script setup>
import { ref, computed, watch } from 'vue';
import ExcelJS from 'exceljs';
import * as XLSX from 'xlsx';
import { API_BASE_URL, STORAGE_BASE_URL } from '../services/api';

// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps({
  item:       { type: Object, required: true },
  baForm:     { type: Object, required: true },
  baSettings: { type: Object, required: true },
});

const emit = defineEmits(['close']);

// ── State ─────────────────────────────────────────────────────────────────────
const zoomLevel     = ref(100);
const exporting     = ref(false);
const exportSuccess = ref('');

// Setting Evidence Visibility
const showEvidence = computed(() => {
  const v = props.baSettings.ba_show_evidence;
  return v !== 'false' && v !== false && v !== '0' && v !== 0;
});

// Editable Form Fields (Synced with baForm & item)
const form = ref({
  nomorSurat:    props.baForm.nomorSurat    || props.item.nomor_surat || '',
  namaPerangkat: props.baForm.namaPerangkat || props.item.kategori || '',
  kode:          props.baForm.kode          || props.item.kode || '',
  kubikal:       props.baForm.kubikal       || props.item.cubicle_name || props.item.kategori || '',
  extIp:         props.baForm.extIp         || (props.item.cubicle_ext ? `${props.item.cubicle_ext} / ${props.item.cubicle_ip || ''}` : (props.item.cubicle_ip || '')),
  idTaskSip:     props.baForm.idTaskSip     || props.item.id_task_sip || '',
  lokasi:        props.baSettings.ba_location || 'SEMARANG',
  koordName:     props.baSettings.ba_koord_name || 'AHMAD ZAENAL ARIFIN',
  tsName:        props.item.assignee?.name || 'Technical Support',
});

// ── Storage URL Resolver (Dynamic with backend & local proxy support) ────────
function resolveStorageUrl(url) {
  if (!url) return '';
  if (url.startsWith('data:image/')) return url;
  if (url.includes('/storage/')) {
    const rel = url.split('/storage/')[1];
    return `/storage/${rel}`;
  }
  if (url.startsWith('evidences/') || url.startsWith('settings/') || url.startsWith('signatures/')) {
    return `/storage/${url}`;
  }
  if (url.startsWith('http://') || url.startsWith('https://')) {
    return url;
  }
  return `/storage/${url.replace(/^\//, '')}`;
}

function resolveEvidenceUrl(ev) {
  if (!ev) return '';
  if (ev.url) return resolveStorageUrl(ev.url);
  if (ev.filepath) {
    if (ev.filepath.startsWith('data:image/')) return ev.filepath;
    if (ev.filepath.startsWith('http://') || ev.filepath.startsWith('https://')) {
      return resolveStorageUrl(ev.filepath);
    }
    const cleanPath = ev.filepath.replace(/^\/?storage\/?/, '');
    return '/storage/' + cleanPath;
  }
  if (ev.filename) {
    return '/storage/evidences/' + ev.filename;
  }
  if (ev.id) {
    return `${API_BASE_URL}/evidence/${ev.id}/view`;
  }
  return '';
}

// Helper: Strip Data-URI header so ExcelJS gets valid raw Base64 bytes
function toCleanBase64(b64OrDataUri) {
  if (!b64OrDataUri) return null;
  return b64OrDataUri.replace(/^data:image\/[a-zA-Z0-9+]+;base64,/, '').trim();
}

function getImageDimensions(b64OrUrl) {
  return new Promise((resolve) => {
    const img = new Image();
    img.onload = () => {
      resolve({
        width: img.naturalWidth || img.width || 300,
        height: img.naturalHeight || img.height || 100,
      });
    };
    img.onerror = () => resolve({ width: 300, height: 100 });
    img.src = b64OrUrl.startsWith('data:') ? b64OrUrl : `data:image/png;base64,${b64OrUrl}`;
  });
}

// ── Date & Time Helpers ───────────────────────────────────────────────────────
function fmtDateFull(value) {
  if (!value) return '-';
  try {
    return new Date(value).toLocaleDateString('id-ID', {
      day: '2-digit', month: 'long', year: 'numeric'
    });
  } catch(_) { return String(value); }
}

function fmtDateShort(value) {
  if (!value) return '-';
  try {
    const dt = new Date(value);
    const dd = String(dt.getDate()).padStart(2, '0');
    const mm = String(dt.getMonth() + 1).padStart(2, '0');
    const yyyy = dt.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
  } catch(_) { return String(value); }
}

function fmtDateCustom(value) {
  if (!value) return '-';
  try {
    const dt = new Date(value);
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    return `${dt.getDate()} ${months[dt.getMonth()]} ${dt.getFullYear()}`;
  } catch(_) { return String(value); }
}

function fmtTime(value) {
  if (!value) return '-';
  try {
    return new Date(value).toLocaleTimeString('id-ID', {
      hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
    }).replace(/\./g, ':');
  } catch(_) { return String(value); }
}

function parseTimeToSeconds(tStr) {
  if (!tStr || tStr === '-') return null;
  const parts = String(tStr).trim().split(':');
  if (parts.length === 3) {
    const h = parseInt(parts[0], 10) || 0;
    const m = parseInt(parts[1], 10) || 0;
    const s = parseInt(parts[2], 10) || 0;
    return h * 3600 + m * 60 + s;
  }
  return null;
}

function secondsToHms(sec) {
  if (sec == null || isNaN(sec) || sec < 0) return '-';
  const h = Math.floor(sec / 3600);
  const m = Math.floor((sec % 3600) / 60);
  const s = Math.floor(sec % 60);
  return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function calcDurasi(start, end) {
  if (!start || !end) return '-';
  try {
    const sDate = new Date(start);
    const eDate = new Date(end);
    if (!isNaN(sDate.getTime()) && !isNaN(eDate.getTime()) && String(start).includes('-')) {
      const diffMs = eDate - sDate;
      if (diffMs < 0) return '-';
      return secondsToHms(Math.floor(diffMs / 1000));
    }
    const sSec = parseTimeToSeconds(start);
    const eSec = parseTimeToSeconds(end);
    if (sSec !== null && eSec !== null) {
      let diff = eSec - sSec;
      if (diff < 0) diff += 86400;
      return secondsToHms(diff);
    }
    return '-';
  } catch(_) { return '-'; }
}

function getMonthName(dateStr) {
  if (!dateStr) return 'AGUSTUS';
  try {
    const months = ['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
    return months[new Date(dateStr).getMonth()] || 'AGUSTUS';
  } catch(_) { return 'AGUSTUS'; }
}

function getYear(dateStr) {
  if (!dateStr) return new Date().getFullYear();
  try { return new Date(dateStr).getFullYear(); } catch(_) { return new Date().getFullYear(); }
}

// ── Editable Table Rows ───────────────────────────────────────────────────────
const rows = ref([]);

// Watch props.item to dynamically initialize form & rows for the selected gangguan
watch(() => props.item, (d) => {
  if (!d) return;
  form.value.nomorSurat = props.baForm.nomorSurat || d.nomor_surat || '';
  form.value.namaPerangkat = props.baForm.namaPerangkat || d.kategori || '';
  form.value.kode = props.baForm.kode || d.kode || '';
  form.value.kubikal = props.baForm.kubikal || d.cubicle_name || d.kategori || '';
  form.value.extIp = props.baForm.extIp || (d.cubicle_ext ? `${d.cubicle_ext} / ${d.cubicle_ip || ''}` : (d.cubicle_ip || ''));
  form.value.idTaskSip = props.baForm.idTaskSip || d.id_task_sip || '';
  form.value.tsName = d.assignee?.name || props.baSettings.ba_ts_name || 'Technical Support';

  rows.value = [
    {
      no:          1,
      hariTanggal: fmtDateCustom(d.created_at),
      startDt:     fmtTime(d.start_time || d.created_at),
      endDt:       fmtTime(d.end_time   || d.resolved_at),
      namaAgent:   d.jenis_gangguan === 'Massal' ? 'ALL AGENT' : (d.creator?.name || 'Agent'),
      extIp:       form.value.extIp || '-',
      idTaskSip:   form.value.idTaskSip || '-',
      penyebab:    d.penyebab_permasalahan || d.deskripsi || '-',
      penyelesaian:d.penyelesaian_masalah || '-',
      impact:      d.impact || '-',
      durasi:      calcDurasi(d.start_time || d.created_at, d.end_time || d.resolved_at),
      petugasTs:   d.assignee?.name || form.value.tsName || 'Technical Support',
      analisa:     d.analisa || '-',
    }
  ];
}, { immediate: true, deep: true });

function onTimeChange(r) {
  const d = calcDurasi(r.startDt, r.endDt);
  if (d !== '-') {
    r.durasi = d;
  }
}

watch(() => form.value.extIp, (val) => {
  if (rows.value[0]) rows.value[0].extIp = val;
});
watch(() => form.value.idTaskSip, (val) => {
  if (rows.value[0]) rows.value[0].idTaskSip = val;
});
watch(() => form.value.tsName, (val) => {
  if (rows.value[0]) rows.value[0].petugasTs = val;
});

const totalDurasi = computed(() => {
  let totalSeconds = 0;
  let hasValid = false;
  rows.value.forEach(r => {
    const sec = parseTimeToSeconds(r.durasi);
    if (sec !== null) {
      totalSeconds += sec;
      hasValid = true;
    }
  });
  return hasValid ? secondsToHms(totalSeconds) : (rows.value[0]?.durasi || '-');
});

function addRow() {
  const d = props.item;
  rows.value.push({
    no:          rows.value.length + 1,
    hariTanggal: fmtDateCustom(d.created_at),
    startDt:     fmtTime(d.start_time || d.created_at),
    endDt:       fmtTime(d.end_time   || d.resolved_at),
    namaAgent:   d.jenis_gangguan === 'Massal' ? 'ALL AGENT' : (d.creator?.name || 'Agent'),
    extIp:       form.value.extIp || '-',
    idTaskSip:   form.value.idTaskSip || '-',
    penyebab:    '-',
    penyelesaian:'-',
    impact:      '-',
    durasi:      '-',
    petugasTs:   form.value.tsName || 'Technical Support',
    analisa:     '-',
  });
}

function removeRow(index) {
  if (rows.value.length <= 1) return;
  rows.value.splice(index, 1);
  rows.value.forEach((r, i) => { r.no = i + 1; });
}

// ── Image Fetcher (Handles local storage and remote backend URLs) ────────────
async function getImageBlobAsBase64(urlOrPath) {
  if (!urlOrPath) return null;

  if (urlOrPath.startsWith('data:image/')) {
    const extMatch = urlOrPath.match(/data:image\/([a-zA-Z0-9+]+);base64,/);
    const ext = extMatch && extMatch[1]?.toLowerCase().includes('jpeg') ? 'jpeg' : 'png';
    return { base64: urlOrPath, extension: ext };
  }

  const candidates = [];
  if (urlOrPath.includes('/storage/')) {
    candidates.push('/storage/' + urlOrPath.split('/storage/')[1]);
  } else if (urlOrPath.startsWith('evidences/') || urlOrPath.startsWith('settings/') || urlOrPath.startsWith('signatures/')) {
    candidates.push('/storage/' + urlOrPath);
  }
  candidates.push(urlOrPath);

  // Fallbacks for known critical assets if custom filename fails
  if (urlOrPath.includes('settings') || urlOrPath.includes('logo')) {
    candidates.push('/storage/settings/UleypQxj4bu6Yq4msTTksQNR6nZBCRLKmy7GLYjy.png');
  }
  if (urlOrPath.includes('koord') || urlOrPath.includes('signatures')) {
    candidates.push('/storage/signatures/BE23nftxGKGEys8EleZS64BEaPYykuSJuzV9rzay.png');
    candidates.push('/storage/signatures/EJmqDWmhwAKzpNrzaEKVDp6jZDJtmzoVxM23cLgY.png');
  }

  for (const targetUrl of candidates) {
    try {
      const res = await fetch(targetUrl);
      if (res.ok) {
        const blob = await res.blob();
        if (blob && blob.size > 0 && !blob.type?.includes('html')) {
          let ext = 'png';
          if (blob.type?.includes('jpeg') || blob.type?.includes('jpg')) ext = 'jpeg';
          else if (blob.type?.includes('gif')) ext = 'gif';
          else if (targetUrl.toLowerCase().endsWith('.jpg') || targetUrl.toLowerCase().endsWith('.jpeg')) ext = 'jpeg';

          return await new Promise((resolve) => {
            const reader = new FileReader();
            reader.onloadend = () => {
              resolve({ base64: reader.result, extension: ext });
            };
            reader.onerror = () => resolve(null);
            reader.readAsDataURL(blob);
          });
        }
      }
    } catch (_) {}
  }

  return null;
}

// ── EXPORT PERFECT .XLSX (Full Native OpenXML with Images & Signatures) ──────
async function exportExcelXlsx() {
  exporting.value = true;
  try {
    const d = props.item;
    const s = props.baSettings;
    const f = form.value;

    const workbook = new ExcelJS.Workbook();
    workbook.creator = 'PLN Icon Plus';
    workbook.created = new Date();

    const sheet = workbook.addWorksheet('Berita Acara', {
      views: [{ state: 'normal', activeCell: 'A1', showGridLines: true }],
      pageSetup: {
        orientation: 'landscape',
        paperSize: 9, // A4
        fitToPage: true,
        fitToWidth: 1,
        fitToHeight: 0,
        margins: { left: 0.25, right: 0.25, top: 0.3, bottom: 0.3, header: 0.2, footer: 0.2 }
      }
    });

    // 13 Columns matching reference exactly
    sheet.columns = [
      { key: 'c1',  width: 6 },   // No
      { key: 'c2',  width: 14 },  // Hari/Tanggal
      { key: 'c3',  width: 12 },  // Start Downtime
      { key: 'c4',  width: 12 },  // End Downtime
      { key: 'c5',  width: 22 },  // Nama Agent
      { key: 'c6',  width: 15 },  // Ext / IP
      { key: 'c7',  width: 24 },  // ID Task SIP
      { key: 'c8',  width: 26 },  // Penyebab Permasalahan
      { key: 'c9',  width: 24 },  // Penyelesaian Masalah
      { key: 'c10', width: 24 },  // Impact
      { key: 'c11', width: 14 },  // Durasi Downtime
      { key: 'c12', width: 20 },  // Petugas TS (Shift ... )
      { key: 'c13', width: 26 },  // Analisa & Logo KOP
    ];

    // Helper to style a single cell
    const setCell = (cell, { bg, bold, align, vAlign, border, size, color }) => {
      if (bg) {
        cell.fill = {
          type: 'pattern',
          pattern: 'solid',
          fgColor: { argb: bg.replace('#', 'FF') }
        };
      }
      cell.font = {
        name: 'Calibri',
        size: size || 8.5,
        bold: !!bold,
        color: { argb: color ? color.replace('#', 'FF') : 'FF000000' }
      };
      cell.alignment = {
        horizontal: align || 'left',
        vertical: vAlign || 'middle',
        wrapText: true
      };
      if (border !== false) {
        cell.border = {
          top:    { style: 'thin', color: { argb: 'FF000000' } },
          left:   { style: 'thin', color: { argb: 'FF000000' } },
          bottom: { style: 'thin', color: { argb: 'FF000000' } },
          right:  { style: 'thin', color: { argb: 'FF000000' } }
        };
      }
    };

    // ── ROW 1 & 2: KOP Table ──────────────────────────────────────────────────
    sheet.addRow(['Halaman', 'Tanggal Berlaku', 'Departemen:', '', '', '', '', `${s.ba_brand_name || 'CONTACT CENTER ICONNET'}`, '', '', '', '', '']);
    sheet.addRow(['1', fmtDateShort(d.created_at), `${s.ba_departemen || 'Divisi Perencanaan Ops Ritel'}`, '', '', '', '', `${s.ba_title || 'BERITA ACARA KRONOLOGIS GANGGUAN APLIKASI/JARINGAN'}` , '', '', '', '', '']);

    sheet.getRow(1).height = 24;
    sheet.getRow(2).height = 28;

    sheet.mergeCells('C1:G1');
    sheet.mergeCells('H1:L1');
    sheet.mergeCells('C2:G2');
    sheet.mergeCells('H2:L2');
    sheet.mergeCells('M1:M2');

    // Style Kop cells
    for (let r = 1; r <= 2; r++) {
      for (let c = 1; c <= 13; c++) {
        const cell = sheet.getRow(r).getCell(c);
        if (c === 13) {
          setCell(cell, { bg: 'FFFFFF', bold: true, align: 'center', size: 9 });
        } else if (r === 1 && c === 8) {
          setCell(cell, { bg: 'E2E8F0', bold: true, align: 'center', size: 9.5 });
        } else {
          setCell(cell, { bg: 'E2E8F0', bold: true, align: 'center', size: 8.5 });
        }
      }
    }

    // ── 1. INJECT OFFICIAL LOGO IMAGE (Cell M1:M2) ─────────────────────────────
    try {
      const logoUrl = s.ba_logo_url || ('/storage/settings/' + (s.ba_logo || ''));
      const logoData = await getImageBlobAsBase64(logoUrl);
      if (logoData && logoData.base64) {
        const cleanLogoB64 = toCleanBase64(logoData.base64);
        if (cleanLogoB64) {
          const logoDims = await getImageDimensions(logoData.base64);
          const logoHeight = 44;
          const logoWidth = Math.min(150, Math.round(logoHeight * (logoDims.width / logoDims.height)));
          const colOffset = 12 + Math.max(0.02, (160 - logoWidth) / 340);

          const logoId = workbook.addImage({
            base64: cleanLogoB64,
            extension: logoData.extension || 'png',
          });
          sheet.addImage(logoId, {
            tl: { col: colOffset, row: 0.08 },
            ext: { width: logoWidth, height: logoHeight },
            editAs: 'oneCell'
          });
        }
      }
    } catch (e) {
      console.warn('Logo injection notice:', e);
    }

    // ── ROW 3: Blank ──────────────────────────────────────────────────────────
    const r3 = sheet.addRow([]);
    r3.height = 6;

    // ── ROW 4-6: Metadata Block ───────────────────────────────────────────────
    const r4 = sheet.addRow(['Nomor Surat', `: ${f.nomorSurat || '-'}`]);
    r4.height = 18;
    sheet.mergeCells('B4:G4');
    setCell(sheet.getCell('A4'), { bold: true, border: false });
    setCell(sheet.getCell('B4'), { bold: true, border: false });

    const r5 = sheet.addRow(['Nama Perangkat', `: ${f.namaPerangkat || '-'}`]);
    r5.height = 18;
    sheet.mergeCells('B5:G5');
    setCell(sheet.getCell('A5'), { bold: true, border: false });
    setCell(sheet.getCell('B5'), { bold: true, border: false });

    const r6 = sheet.addRow([
      'Kode', `: ${f.kode || '-'}`,
      '', '', '', '', '',
      'Kubikal : ' + (f.kubikal || '-'),
      '', '',
      'Periode : ' + getYear(d.created_at),
      '',
      'Bulan : ' + getMonthName(d.created_at)
    ]);
    r6.height = 18;
    sheet.mergeCells('B6:F6');
    sheet.mergeCells('H6:I6');
    sheet.mergeCells('K6:L6');
    setCell(sheet.getCell('A6'), { bold: true, border: false });
    setCell(sheet.getCell('B6'), { bold: true, border: false });
    setCell(sheet.getCell('H6'), { bold: true, border: false, align: 'right' });
    setCell(sheet.getCell('K6'), { bold: true, border: false, align: 'right' });
    setCell(sheet.getCell('M6'), { bold: true, border: false, align: 'right' });

    // ── ROW 7: Blank ──────────────────────────────────────────────────────────
    const r7 = sheet.addRow([]);
    r7.height = 6;

    // ── ROW 8: Main Table Header (#5B9BD5 PLN Blue) ───────────────────────────
    const headerRow = sheet.addRow([
      'No',
      'Hari/Tanggal',
      'Start Downtime\n(h:mm:ss)',
      'End Downtime\n(h:mm:ss)',
      'Nama Agent',
      'Ext / IP',
      'ID Task SIP',
      'Penyebab Permasalahan',
      'Penyelesaian Masalah',
      'Impact',
      'Durasi Downtime',
      'Petugas TS (Shift ... )',
      'Analisa'
    ]);
    headerRow.height = 36;
    headerRow.eachCell((cell) => {
      setCell(cell, { bg: '5B9BD5', bold: true, align: 'center', size: 8 });
    });

    // ── Data Rows ─────────────────────────────────────────────────────────────
    rows.value.forEach((r) => {
      const row = sheet.addRow([
        r.no,
        r.hariTanggal,
        r.startDt,
        r.endDt,
        r.namaAgent,
        r.extIp,
        r.idTaskSip,
        r.penyebab,
        r.penyelesaian,
        r.impact,
        r.durasi,
        r.petugasTs,
        r.analisa
      ]);
      row.height = 56;
      row.eachCell((cell, colNumber) => {
        let align = 'center';
        if (colNumber === 8 || colNumber === 9 || colNumber === 13) {
          align = 'left';
        }
        setCell(cell, { align, size: 8 });
      });
    });

    // ── Total Downtime Row (Columns 8 to 11 in Blue) ──────────────────────────
    const totalRowIndex = sheet.rowCount + 1;
    sheet.addRow([
      '', '', '', '', '', '', '',
      'TOTAL DOWN TIME :', '', '',
      totalDurasi.value,
      '', ''
    ]);
    sheet.getRow(totalRowIndex).height = 24;

    sheet.mergeCells(`H${totalRowIndex}:J${totalRowIndex}`);
    setCell(sheet.getCell(`H${totalRowIndex}`), { bg: '5B9BD5', bold: true, align: 'right', size: 8.5 });
    setCell(sheet.getCell(`I${totalRowIndex}`), { bg: '5B9BD5', bold: true });
    setCell(sheet.getCell(`J${totalRowIndex}`), { bg: '5B9BD5', bold: true });
    setCell(sheet.getCell(`K${totalRowIndex}`), { bg: '5B9BD5', bold: true, align: 'center', size: 8.5 });

    // ── Signatures Section ────────────────────────────────────────────────────
    const s1 = sheet.addRow([]);
    s1.height = 10;

    const s2 = sheet.addRow(['', '', '', '', '', '', '', '', '', '', '', `${f.lokasi || 'SEMARANG'}, ${fmtDateFull(d.created_at || new Date()).toUpperCase()}`, '']);
    s2.height = 18;
    sheet.mergeCells(`L${s2.number}:M${s2.number}`);
    setCell(sheet.getCell(`L${s2.number}`), { bold: true, align: 'center', border: false, size: 8 });

    const s3 = sheet.addRow(['', '', '', '', '', '', '', 'KOORD OPS', '', '', 'TEAM SUPPORT', '', '']);
    s3.height = 18;
    sheet.mergeCells(`H${s3.number}:J${s3.number}`);
    sheet.mergeCells(`K${s3.number}:M${s3.number}`);
    setCell(sheet.getCell(`H${s3.number}`), { bold: true, align: 'center', border: false, color: '000000', size: 8 });
    setCell(sheet.getCell(`K${s3.number}`), { bold: true, align: 'center', border: false, color: '000000', size: 8 });

    // 2 Rows for signature space (Height 20 each = 40px total height)
    const sigRowStart = sheet.rowCount;
    const sigRow1 = sheet.addRow(['', '', '', '', '', '', '', '', '', '', '', '', '']); sigRow1.height = 20;
    const sigRow2 = sheet.addRow(['', '', '', '', '', '', '', '', '', '', '', '', '']); sigRow2.height = 20;
    const sigRowEnd = sheet.rowCount;

    // Merge signature cells for clean layout
    sheet.mergeCells(`H${sigRowStart + 1}:J${sigRowEnd}`);
    sheet.mergeCells(`K${sigRowStart + 1}:M${sigRowEnd}`);

    // ── 2. INJECT OFFICIAL SIGNATURE IMAGES (Koord & TS) ───────────────────────
    try {
      // A. Koordinator Signature
      const koordUrl = s.ba_koord_signature_url || ('/storage/signatures/' + (s.ba_koord_signature || ''));
      const koordData = await getImageBlobAsBase64(koordUrl);
      if (koordData && koordData.base64) {
        const cleanKoordB64 = toCleanBase64(koordData.base64);
        if (cleanKoordB64) {
          const koordDims = await getImageDimensions(koordData.base64);
          const koordHeight = 34;
          const koordWidth = Math.min(95, Math.round(koordHeight * (koordDims.width / koordDims.height)));
          const colOffset = 8.1 + Math.max(0, (95 - koordWidth) / 240);

          const sigId = workbook.addImage({
            base64: cleanKoordB64,
            extension: koordData.extension || 'png',
          });
          sheet.addImage(sigId, {
            tl: { col: colOffset, row: sigRowStart + 0.1 },
            ext: { width: koordWidth, height: koordHeight },
            editAs: 'oneCell'
          });
        }
      }

      // B. Technical Support Signature
      const tsUrl = d.assignee?.signature_url || d.assignee_signature_url || ('/storage/' + (d.assignee?.signature || d.assignee?.signature_path || ''));
      const tsData = await getImageBlobAsBase64(tsUrl);
      if (tsData && tsData.base64) {
        const cleanTsB64 = toCleanBase64(tsData.base64);
        if (cleanTsB64) {
          const tsDims = await getImageDimensions(tsData.base64);
          const tsHeight = 34;
          const tsWidth = Math.min(65, Math.round(tsHeight * (tsDims.width / tsDims.height)));
          const colOffset = 11.4 + Math.max(0, (65 - tsWidth) / 240);

          const tsId = workbook.addImage({
            base64: cleanTsB64,
            extension: tsData.extension || 'png',
          });
          sheet.addImage(tsId, {
            tl: { col: colOffset, row: sigRowStart + 0.1 },
            ext: { width: tsWidth, height: tsHeight },
            editAs: 'oneCell'
          });
        }
      }
    } catch (e) {
      console.warn('Signature injection notice:', e);
    }

    const s4 = sheet.addRow(['', '', '', '', '', '', '', `${s.ba_koord_title || 'KOORDINATOR'}`, '', '', `${s.ba_ts_title || 'TECHNICAL SUPPORT'}`, '', '']);
    s4.height = 16;
    sheet.mergeCells(`H${s4.number}:J${s4.number}`);
    sheet.mergeCells(`K${s4.number}:M${s4.number}`);
    setCell(sheet.getCell(`H${s4.number}`), { bold: true, align: 'center', border: false, size: 8 });
    setCell(sheet.getCell(`K${s4.number}`), { bold: true, align: 'center', border: false, size: 8 });

    const s5 = sheet.addRow(['', '', '', '', '', '', '', `[ ${f.koordName || 'AHMAD ZAENAL ARIFIN'} ]`, '', '', `[ ${f.tsName || 'Technical Support'} ]`, '', '']);
    s5.height = 16;
    sheet.mergeCells(`H${s5.number}:J${s5.number}`);
    sheet.mergeCells(`K${s5.number}:M${s5.number}`);
    setCell(sheet.getCell(`H${s5.number}`), { align: 'center', border: false, size: 8 });
    setCell(sheet.getCell(`K${s5.number}`), { align: 'center', border: false, size: 8 });

    // ── 3. INJECT EVIDENCE SECTION (Dynamically based on ba_show_evidence and actual evidences) ──
    const shouldShowEv = s.ba_show_evidence !== 'false' && s.ba_show_evidence !== false && s.ba_show_evidence !== '0' && s.ba_show_evidence !== 0;

    if (shouldShowEv && d.evidences && d.evidences.length > 0) {
      for (const ev of d.evidences) {
        const sp = sheet.addRow([]); sp.height = 14;
        
        const evHeader = sheet.addRow(['EVIDENCE', '', '', '', '', '', '', '', '', '', '', '', '']);
        evHeader.height = 24;
        sheet.mergeCells(`A${evHeader.number}:M${evHeader.number}`);
        setCell(sheet.getCell(`A${evHeader.number}`), { bg: '5B9BD5', bold: true, align: 'center', size: 9, color: '000000' });

        // Add 18 container rows of height 20 (Total height ~360px)
        const evStartRow = sheet.rowCount + 1;
        for (let i = 0; i < 18; i++) {
          const r = sheet.addRow(['', '', '', '', '', '', '', '', '', '', '', '', '']);
          r.height = 20;
          r.eachCell((c) => setCell(c, { bg: 'FFFFFF' }));
        }
        const evEndRow = sheet.rowCount;

        // Merge container area A to H for Image, I to M for caption
        sheet.mergeCells(`A${evStartRow}:H${evEndRow}`);
        setCell(sheet.getCell(`A${evStartRow}`), { bg: 'FFFFFF' });

        sheet.mergeCells(`I${evStartRow}:M${evEndRow}`);
        const captionCell = sheet.getCell(`I${evStartRow}`);
        captionCell.value = ev.caption || ev.description || ev.filename || 'STATUS AGENT SEDANG AUX';
        setCell(captionCell, { bg: 'FFFFFF', bold: true, size: 9.5, align: 'center' });

        // Fetch and inject this specific gangguan's evidence image
        const evUrl = resolveEvidenceUrl(ev);
        const evImgData = await getImageBlobAsBase64(evUrl);
        if (evImgData && evImgData.base64) {
          const cleanEvB64 = toCleanBase64(evImgData.base64);
          if (cleanEvB64) {
            try {
              const evImgId = workbook.addImage({
                base64: cleanEvB64,
                extension: evImgData.extension || 'png',
              });
              sheet.addImage(evImgId, {
                tl: { col: 0.15, row: evStartRow - 1 + 0.15 },
                br: { col: 7.85, row: evEndRow - 0.15 },
                editAs: 'oneCell'
              });
            } catch(e) {
              console.warn('Evidence injection notice:', e);
            }
          }
        }
      }
    }

    // Generate buffer & download
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `Berita_Acara_${d.ticket_number || 'Gangguan'}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);

    exportSuccess.value = 'Dokumen Excel (.xlsx) berhasil diunduh lengkap dengan gambar!';
    setTimeout(() => { exportSuccess.value = ''; }, 4000);
  } catch (err) {
    console.error('Excel export error:', err);
    alert('Gagal mengekspor file Excel: ' + (err.message || 'Terjadi kesalahan'));
  } finally {
    exporting.value = false;
  }
}
</script>

<template>
  <div class="spr-overlay" @click.self="emit('close')">
    <div class="spr-modal">

      <!-- ── Header Bar ─────────────────────────────────────────────────────── -->
      <div class="spr-header">
        <div class="spr-header-left">
          <div class="spr-icon-box">📊</div>
          <div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <h2 class="spr-title">Spreadsheet Berita Acara</h2>
              <span class="spr-badge" v-if="item.id_task_sip && item.id_task_sip !== '-'">{{ item.id_task_sip }}</span>
            </div>
            <p class="spr-subtitle">
              {{ baSettings.ba_brand_name || 'CONTACT CENTER ICONNET' }} &bull; {{ baSettings.ba_departemen || 'Divisi Perencanaan Ops Ritel' }}
            </p>
          </div>
        </div>

        <div class="spr-header-right">
          <!-- Zoom Controls -->
          <div class="spr-zoom-group">
            <button class="spr-zoom-btn" :class="{ active: zoomLevel === 75 }" @click="zoomLevel = 75">75%</button>
            <button class="spr-zoom-btn" :class="{ active: zoomLevel === 90 }" @click="zoomLevel = 90">90%</button>
            <button class="spr-zoom-btn" :class="{ active: zoomLevel === 100 }" @click="zoomLevel = 100">100%</button>
            <button class="spr-zoom-btn" :class="{ active: zoomLevel === 110 }" @click="zoomLevel = 110">110%</button>
            <button class="spr-zoom-btn" :class="{ active: zoomLevel === 125 }" @click="zoomLevel = 125">125%</button>
          </div>

          <button class="spr-close-btn" @click="emit('close')" title="Tutup Modal">✕</button>
        </div>
      </div>

      <!-- ── Quick Controls Bar ──────────────────────────────────────────────── -->
      <div class="spr-action-bar">
        <div style="display:flex;align-items:center;gap:8px;font-size:0.8rem;color:#334155;">
          <span style="font-weight:700;">💡 Tips:</span>
          <span>Semua teks di dalam form dan tabel dapat diedit langsung sebelum di-export ke Excel. Durasi otomatis terhitung jika Start/End time diubah.</span>
        </div>
        <div style="display:flex;gap:8px;">
          <button type="button" class="spr-sub-btn" @click="addRow">➕ Tambah Baris Gangguan</button>
          <button type="button" class="spr-sub-btn danger" :disabled="rows.length <= 1" @click="removeRow(rows.length - 1)">➖ Hapus Baris</button>
        </div>
      </div>

      <!-- ── Interactive Spreadsheet Document Container ─────────────────────── -->
      <div class="spr-body">
        <div class="spr-document-viewport" :style="`zoom: ${zoomLevel}%;`">
          <div class="spr-document-page">

            <!-- ── 1. KOP TABLE ── -->
            <table class="doc-kop-table">
              <tbody>
                <tr>
                  <td style="width:4%;font-weight:bold;">Halaman</td>
                  <td style="width:7%;font-weight:bold;">Tanggal Berlaku</td>
                  <td style="width:28%;font-weight:bold;">Departemen:</td>
                  <td style="width:47%;font-weight:bold;font-size:9.5pt;">{{ baSettings.ba_brand_name || 'CONTACT CENTER ICONNET' }}</td>
                  <td rowspan="2" style="width:14%;background:#fff;text-align:center;padding:4px;">
                    <img
                      v-if="baSettings.ba_logo_url || baSettings.ba_logo"
                      :src="resolveStorageUrl(baSettings.ba_logo_url || ('settings/' + baSettings.ba_logo))"
                      alt="Logo Kop"
                      style="max-height:50px;max-width:130px;object-fit:contain;display:inline-block;"
                      @error="(e) => { e.target.src = '/storage/settings/UleypQxj4bu6Yq4msTTksQNR6nZBCRLKmy7GLYjy.png'; }"
                    />
                  </td>
                </tr>
                <tr>
                  <td style="font-weight:bold;">1</td>
                  <td style="font-weight:bold;">{{ fmtDateShort(item.created_at) }}</td>
                  <td style="font-weight:bold;">{{ baSettings.ba_departemen || 'Divisi Perencanaan Ops Ritel' }}</td>
                  <td style="font-weight:bold;">{{ baSettings.ba_title || 'BERITA ACARA KRONOLOGIS GANGGUAN APLIKASI/JARINGAN' }}</td>
                </tr>
              </tbody>
            </table>

            <!-- ── 2. METADATA BLOCK (Editable in-place) ── -->
            <div class="doc-meta-block">
              <div class="doc-meta-row">
                <span class="doc-meta-label">Nomor Surat</span>
                <span class="doc-meta-colon">:</span>
                <input v-model="form.nomorSurat" class="doc-meta-input" :placeholder="item.nomor_surat || 'SMG03.1053/ICONNET/DA.TEK/0-26/0'" />
              </div>
              <div class="doc-meta-row">
                <span class="doc-meta-label">Nama Perangkat</span>
                <span class="doc-meta-colon">:</span>
                <input v-model="form.namaPerangkat" class="doc-meta-input" :placeholder="item.kategori || 'LOCAL NETWORK'" />
              </div>
              <div class="doc-meta-row split">
                <div style="display:flex;align-items:center;flex:1;">
                  <span class="doc-meta-label">Kode</span>
                  <span class="doc-meta-colon">:</span>
                  <input v-model="form.kode" class="doc-meta-input small" :placeholder="item.kode || '-'" />
                </div>
                <div style="display:flex;gap:20px;font-size:8pt;font-weight:bold;">
                  <div>Kubikal : <input v-model="form.kubikal" class="doc-inline-edit" style="width:60px;" :placeholder="item.cubicle_name || '-'" /></div>
                  <div>Periode : {{ getYear(item.created_at) }}</div>
                  <div>Bulan : {{ getMonthName(item.created_at) }}</div>
                </div>
              </div>
            </div>

            <!-- ── 3. MAIN SPREADSHEET TABLE ── -->
            <table class="doc-main-table">
              <thead>
                <tr>
                  <th style="width:3%;">No</th>
                  <th style="width:8%;">Hari/Tanggal</th>
                  <th style="width:7.5%;">Start Downtime<br/><span style="font-weight:normal;font-size:6.5pt;">(h:mm:ss)</span></th>
                  <th style="width:7.5%;">End Downtime<br/><span style="font-weight:normal;font-size:6.5pt;">(h:mm:ss)</span></th>
                  <th style="width:9%;">Nama Agent</th>
                  <th style="width:7.5%;">Ext / IP</th>
                  <th style="width:11%;">ID Task SIP</th>
                  <th style="width:11%;">Penyebab Permasalahan</th>
                  <th style="width:10%;">Penyelesaian Masalah</th>
                  <th style="width:9%;">Impact</th>
                  <th style="width:7.5%;">Durasi Downtime</th>
                  <th style="width:8%;">Petugas TS (Shift ... )</th>
                  <th style="width:8%;">Analisa</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(r, idx) in rows" :key="idx" class="doc-data-row">
                  <td style="text-align:center;font-weight:bold;">{{ r.no }}</td>
                  <td style="text-align:center;"><input v-model="r.hariTanggal" class="doc-cell-input center" /></td>
                  <td><input v-model="r.startDt" @input="onTimeChange(r)" class="doc-cell-input center" /></td>
                  <td><input v-model="r.endDt" @input="onTimeChange(r)" class="doc-cell-input center" /></td>
                  <td><input v-model="r.namaAgent" class="doc-cell-input center" /></td>
                  <td><input v-model="r.extIp" class="doc-cell-input center" /></td>
                  <td><input v-model="r.idTaskSip" class="doc-cell-input center" /></td>
                  <td><textarea v-model="r.penyebab" class="doc-cell-textarea"></textarea></td>
                  <td><textarea v-model="r.penyelesaian" class="doc-cell-textarea"></textarea></td>
                  <td><input v-model="r.impact" class="doc-cell-input center" /></td>
                  <td style="text-align:center;font-weight:bold;background:#f8fafc;">
                    <input v-model="r.durasi" class="doc-cell-input center bold" />
                  </td>
                  <td><input v-model="r.petugasTs" class="doc-cell-input center" /></td>
                  <td><textarea v-model="r.analisa" class="doc-cell-textarea"></textarea></td>
                </tr>
                <!-- Total Row -->
                <tr class="doc-total-row">
                  <td colspan="7" style="border:none;background:transparent;"></td>
                  <td colspan="3" class="doc-total-cell" style="text-align:right;">TOTAL DOWN TIME :</td>
                  <td class="doc-total-cell" style="text-align:center;">{{ totalDurasi }}</td>
                  <td colspan="2" style="border:none;background:transparent;"></td>
                </tr>
              </tbody>
            </table>

            <!-- ── 4. SIGNATURES BOX ── -->
            <div class="doc-sig-box">
              <div style="display:flex;justify-content:flex-end;margin-bottom:8px;">
                <div style="min-width:220px;text-align:center;font-size:8.5pt;font-weight:bold;">
                  <input v-model="form.lokasi" class="doc-inline-edit" style="width:90px;text-align:right;" />, {{ fmtDateFull(item.created_at || new Date()).toUpperCase() }}
                </div>
              </div>

              <div style="display:flex;justify-content:flex-end;gap:50px;text-align:center;">
                <!-- Koordinator -->
                <div style="min-width:180px;display:flex;flex-direction:column;align-items:center;">
                  <div style="font-size:8pt;font-weight:700;margin-bottom:4px;">KOORD OPS</div>
                  <div style="height:46px;display:flex;align-items:center;justify-content:center;margin-bottom:4px;">
                    <img
                      v-if="baSettings.ba_koord_signature_url || baSettings.ba_koord_signature"
                      :src="resolveStorageUrl(baSettings.ba_koord_signature_url || ('signatures/' + baSettings.ba_koord_signature))"
                      alt="Paraf Koordinator"
                      style="max-height:42px;max-width:105px;object-fit:contain;display:inline-block;"
                      @error="(e) => { e.target.src = '/storage/signatures/BE23nftxGKGEys8EleZS64BEaPYykuSJuzV9rzay.png'; }"
                    />
                  </div>
                  <div style="font-size:7.5pt;font-weight:800;margin-bottom:2px;">{{ baSettings.ba_koord_title || 'KOORDINATOR' }}</div>
                  <div style="font-size:7.5pt;font-weight:600;">[ <input v-model="form.koordName" class="doc-inline-edit" style="width:130px;" /> ]</div>
                </div>

                <!-- Technical Support -->
                <div style="min-width:180px;display:flex;flex-direction:column;align-items:center;">
                  <div style="font-size:8pt;font-weight:700;margin-bottom:4px;">TEAM SUPPORT</div>
                  <div style="height:46px;display:flex;align-items:center;justify-content:center;margin-bottom:4px;">
                    <img
                      v-if="item.assignee?.signature_url || item.assignee?.signature_path || item.assignee?.signature || baSettings.ba_ts_signature"
                      :src="resolveStorageUrl(item.assignee?.signature_url || item.assignee?.signature_path || item.assignee?.signature || baSettings.ba_ts_signature)"
                      alt="Paraf TS"
                      style="max-height:42px;max-width:85px;object-fit:contain;display:inline-block;"
                      @error="(e) => { e.target.src = '/storage/signatures/EJmqDWmhwAKzpNrzaEKVDp6jZDJtmzoVxM23cLgY.png'; }"
                    />
                  </div>
                  <div style="font-size:7.5pt;font-weight:800;margin-bottom:2px;">{{ baSettings.ba_ts_title || 'TECHNICAL SUPPORT' }}</div>
                  <div style="font-size:7.5pt;font-weight:600;">[ <input v-model="form.tsName" class="doc-inline-edit" style="width:130px;" /> ]</div>
                </div>
              </div>
            </div>

            <!-- ── 5. EVIDENCE BOX (Dynamically driven by baSettings and item.evidences) ── -->
            <div class="doc-evidence-box" v-if="showEvidence && item.evidences && item.evidences.length > 0">
              <div v-for="ev in item.evidences" :key="ev.id" class="doc-ev-card">
                <div class="doc-ev-header">EVIDENCE</div>
                <div class="doc-ev-grid">
                  <div class="doc-ev-left">
                    <img
                      :src="resolveEvidenceUrl(ev)"
                      :alt="ev.filename || 'Evidence Gangguan'"
                      style="max-height:280px;max-width:100%;object-fit:contain;"
                    />
                  </div>
                  <div class="doc-ev-right">
                    {{ ev.caption || ev.description || ev.filename || 'STATUS AGENT SEDANG AUX' }}
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- ── Footer Action Bar ───────────────────────────────────────────────── -->
      <div class="spr-footer">
        <div class="spr-footer-left">
          <span v-if="exportSuccess" class="spr-success-badge">✅ {{ exportSuccess }}</span>
          <span v-else class="spr-hint">
            💡 <strong>Download Excel (.xlsx)</strong> mencetak data riil tiket <strong>{{ item.ticket_number }}</strong>.
          </span>
        </div>

        <div class="spr-footer-right">
          <button type="button" class="spr-btn spr-btn-secondary" @click="emit('close')">
            Batal
          </button>
          <button
            type="button"
            class="spr-btn spr-btn-primary"
            :disabled="exporting"
            @click="exportExcelXlsx"
            title="Download file Excel resmi berformat (.xlsx) lengkap dengan gambar & tanda tangan"
          >
            <span v-if="exporting" class="spr-spinner-sm"></span>
            {{ exporting ? 'Menyematkan Gambar & Menyiapkan...' : '⬇️ Download Excel (.xlsx)' }}
          </button>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
/* ── Overlay ─────────────────────────────────────────────────────────────────── */
.spr-overlay {
  position: fixed; inset: 0;
  background: rgba(15, 23, 42, 0.8);
  backdrop-filter: blur(6px);
  z-index: 99999;
  display: flex; align-items: center; justify-content: center;
  padding: 10px;
  animation: sprFadeIn 0.2s ease;
}
@keyframes sprFadeIn { from { opacity: 0; } to { opacity: 1; } }

/* ── Modal ───────────────────────────────────────────────────────────────────── */
.spr-modal {
  background: #f1f5f9;
  width: 98vw;
  max-width: 1600px;
  height: 96vh;
  border-radius: 12px;
  box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.45);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid #cbd5e1;
}

/* ── Header Bar ──────────────────────────────────────────────────────────────── */
.spr-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 20px;
  background: #0f172a;
  color: #fff;
  flex-shrink: 0;
  border-bottom: 1px solid #1e293b;
}
.spr-header-left { display: flex; align-items: center; gap: 12px; }
.spr-icon-box {
  font-size: 1.4rem;
  background: rgba(255,255,255,0.1);
  border-radius: 8px;
  width: 38px; height: 38px;
  display: flex; align-items: center; justify-content: center;
}
.spr-title { margin: 0; font-size: 1.1rem; font-weight: 800; color: #fff; letter-spacing: -0.01em; }
.spr-badge {
  background: #2563eb; color: #fff;
  font-size: 0.72rem; font-weight: 700;
  padding: 2px 8px; border-radius: 999px;
}
.spr-subtitle { margin: 2px 0 0; font-size: 0.78rem; color: #94a3b8; }

.spr-header-right { display: flex; align-items: center; gap: 12px; }
.spr-zoom-group {
  display: flex;
  background: #1e293b;
  padding: 2px;
  border-radius: 6px;
}
.spr-zoom-btn {
  padding: 4px 10px;
  background: transparent;
  border: none;
  border-radius: 4px;
  color: #94a3b8;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
}
.spr-zoom-btn.active {
  background: #2563eb;
  color: #fff;
}
.spr-close-btn {
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.2);
  color: #fff;
  width: 30px; height: 30px;
  border-radius: 50%;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: all 0.15s;
}
.spr-close-btn:hover { background: rgba(239,68,68,0.8); }

/* ── Action / Quick Bar ──────────────────────────────────────────────────────── */
.spr-action-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 20px;
  background: #fff;
  border-bottom: 1px solid #cbd5e1;
  flex-shrink: 0;
  flex-wrap: wrap;
  gap: 8px;
}
.spr-sub-btn {
  padding: 5px 12px;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 0.78rem;
  font-weight: 700;
  color: #334155;
  cursor: pointer;
  transition: all 0.15s;
}
.spr-sub-btn:hover:not(:disabled) { background: #e2e8f0; }
.spr-sub-btn.danger { color: #dc2626; }
.spr-sub-btn.danger:hover:not(:disabled) { background: #fee2e2; border-color: #fca5a5; }
.spr-sub-btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── Body Document Area ──────────────────────────────────────────────────────── */
.spr-body {
  flex: 1;
  background: #cbd5e1;
  padding: 24px;
  overflow: auto;
  min-height: 0;
  display: flex;
  justify-content: center;
}
.spr-document-viewport {
  transition: zoom 0.15s ease;
  width: 100%;
  display: flex;
  justify-content: center;
}
.spr-document-page {
  background: #fff;
  width: 100%;
  max-width: 1460px;
  min-width: 1100px;
  padding: 28px 36px 48px;
  border: 1px solid #94a3b8;
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
  font-family: Calibri, Arial, sans-serif;
  font-size: 8pt;
  color: #000;
  box-sizing: border-box;
}

/* ── 1. KOP TABLE ── */
.doc-kop-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10px;
  border: 1px solid #000;
  background-color: #e2e8f0;
  text-align: center;
  font-size: 8.5pt;
}
.doc-kop-table td {
  border: 1px solid #000;
  padding: 5px 6px;
  vertical-align: middle;
}

/* ── 2. METADATA BLOCK ── */
.doc-meta-block {
  font-size: 8.5pt;
  font-weight: bold;
  margin-bottom: 12px;
  line-height: 1.6;
}
.doc-meta-row {
  display: flex;
  align-items: center;
  margin-bottom: 2px;
}
.doc-meta-row.split {
  justify-content: space-between;
}
.doc-meta-label { width: 110px; flex-shrink: 0; }
.doc-meta-colon { width: 14px; flex-shrink: 0; }
.doc-meta-sublabel { margin-left: 16px; margin-right: 8px; flex-shrink: 0; }
.doc-meta-input {
  border: 1px solid transparent;
  padding: 2px 6px;
  font-family: inherit;
  font-size: 8.5pt;
  font-weight: bold;
  background: transparent;
  border-radius: 4px;
  flex: 1;
  max-width: 320px;
}
.doc-meta-input.small { max-width: 180px; }
.doc-meta-input:hover { border-color: #cbd5e1; background: #f8fafc; }
.doc-meta-input:focus { border-color: #2563eb; background: #fff; outline: none; }

/* ── 3. MAIN TABLE ── */
.doc-main-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 7.5pt;
  margin-bottom: 12px;
  table-layout: fixed;
}
.doc-main-table th {
  background-color: #5b9bd5 !important;
  color: #000 !important;
  border: 1px solid #000 !important;
  padding: 6px 4px;
  font-weight: bold;
  text-align: center;
  font-size: 7pt;
  overflow-wrap: break-word;
}
.doc-main-table td {
  border: 1px solid #000 !important;
  padding: 5px 4px;
  vertical-align: middle;
  overflow-wrap: break-word;
}
.doc-data-row td { min-height: 52px; }

.doc-cell-input {
  width: 100%;
  border: 1px solid transparent;
  padding: 3px 4px;
  font-size: 7.5pt;
  font-family: inherit;
  background: transparent;
  box-sizing: border-box;
  border-radius: 3px;
}
.doc-cell-input:hover { border-color: #cbd5e1; background: #fff; }
.doc-cell-input:focus { border-color: #2563eb; background: #fff; outline: none; }
.doc-cell-input.center { text-align: center; }
.doc-cell-input.bold { font-weight: bold; }

.doc-cell-textarea {
  width: 100%;
  border: 1px solid transparent;
  padding: 3px 4px;
  font-size: 7.5pt;
  font-family: inherit;
  background: transparent;
  resize: vertical;
  min-height: 42px;
  box-sizing: border-box;
  border-radius: 3px;
}
.doc-cell-textarea:hover { border-color: #cbd5e1; background: #fff; }
.doc-cell-textarea:focus { border-color: #2563eb; background: #fff; outline: none; }

.doc-total-row td { border: none; }
.doc-total-cell {
  background-color: #5b9bd5 !important;
  color: #000 !important;
  font-weight: bold;
  border: 1px solid #000 !important;
  padding: 6px 8px;
}

/* ── 4. SIGNATURES ── */
.doc-sig-box {
  margin-top: 18px;
  margin-bottom: 24px;
}
.doc-inline-edit {
  border: 1px solid transparent;
  padding: 1px 4px;
  font-family: inherit;
  font-size: inherit;
  font-weight: inherit;
  background: transparent;
  text-align: center;
  border-radius: 3px;
}
.doc-inline-edit:hover { border-color: #cbd5e1; background: #f8fafc; }
.doc-inline-edit:focus { border-color: #2563eb; background: #fff; outline: none; }

/* ── 5. EVIDENCE ── */
.doc-evidence-box { margin-top: 24px; }
.doc-ev-card { border: 1px solid #000; margin-bottom: 20px; background: #fff; }
.doc-ev-header {
  background-color: #5b9bd5;
  color: #000;
  font-weight: bold;
  text-align: center;
  padding: 8px;
  font-size: 10pt;
  letter-spacing: 0.05em;
  border-bottom: 1px solid #000;
}
.doc-ev-grid {
  display: grid;
  grid-template-columns: 65% 35%;
  min-height: 380px;
}
.doc-ev-left {
  border-right: 1px solid #000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 18px;
  background: #f8fafc;
}
.doc-ev-left img {
  max-height: 520px;
  width: 100%;
  object-fit: contain;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #fff;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.doc-ev-right {
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 11pt;
  color: #0f172a;
  padding: 24px;
  text-align: center;
  line-height: 1.5;
}

/* ── Footer Action Bar ───────────────────────────────────────────────── */
.spr-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 20px;
  background: #fff;
  border-top: 1px solid #cbd5e1;
  gap: 12px;
  flex-wrap: wrap;
  flex-shrink: 0;
}
.spr-footer-left { display: flex; align-items: center; }
.spr-footer-right { display: flex; gap: 8px; flex-wrap: wrap; }
.spr-hint { font-size: 0.8rem; color: #475569; }
.spr-success-badge {
  font-size: 0.82rem; font-weight: 700; color: #16a34a;
  background: #dcfce7; padding: 4px 12px; border-radius: 999px;
}

/* ── Buttons ─────────────────────────────────────────────────────────────────── */
.spr-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 18px; border-radius: 8px;
  font-size: 0.85rem; font-weight: 700; cursor: pointer; border: none;
  transition: all 0.15s;
}
.spr-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.spr-btn-secondary { background: #fff; border: 1px solid #cbd5e1; color: #475569; }
.spr-btn-secondary:hover:not(:disabled) { background: #f8fafc; }
.spr-btn-primary {
  background: linear-gradient(135deg, #16a34a, #15803d);
  color: #fff;
  box-shadow: 0 4px 12px rgba(22,163,74,0.3);
}
.spr-btn-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(22,163,74,0.4);
}

.spr-spinner-sm {
  width: 14px; height: 14px;
  border: 2px solid rgba(255,255,255,0.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
