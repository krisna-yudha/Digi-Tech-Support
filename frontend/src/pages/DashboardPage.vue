<script setup>
import { onMounted, ref, watch, nextTick } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';

const auth = useAuthStore();

const summary = ref(null);
const loading = ref(false);

const startDate = ref('');
const endDate = ref('');

async function fetchSummary() {
  loading.value = true;
  try {
    const params = {};
    if (startDate.value) params.start_date = startDate.value;
    if (endDate.value) params.end_date = endDate.value;

    const { data } = await api.get('/summary', { params });
    summary.value = data;
  } finally {
    loading.value = false;
  }
}

function formatDurasi(minutes) {
  if (!minutes) return '0j 0m';
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  if (h > 0) return `${h}j ${m}m`;
  return `${m}m`;
}

let statusChartInstance = null;
let categoryChartInstance = null;

watch(summary, async (newVal) => {
  if (!newVal) return;
  await nextTick();

  // Render Status Chart
  const ctxStatus = document.getElementById('statusChart');
  if (ctxStatus) {
    if (statusChartInstance) statusChartInstance.destroy();
    statusChartInstance = new window.Chart(ctxStatus, {
      type: 'doughnut',
      data: {
        labels: ['Open', 'In Progress', 'Closed'],
        datasets: [{
          data: [newVal.open || 0, newVal.in_progress || 0, newVal.closed || 0],
          backgroundColor: ['#ef4444', '#f59e0b', '#10b981'],
          borderWidth: 0
        }]
      },
      options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { position: 'bottom' } } }
    });
  }

  // Render Category Chart
  const ctxCategory = document.getElementById('categoryChart');
  if (ctxCategory) {
    if (categoryChartInstance) categoryChartInstance.destroy();
    // Convert object to array of [key, value], sort descending by value, and extract back
    const sortedKategori = Object.entries(newVal.by_kategori || {})
      .sort((a, b) => b[1] - a[1]);
    
    const catLabels = sortedKategori.map(item => item[0]);
    const catData = sortedKategori.map(item => item[1]);
    
    categoryChartInstance = new window.Chart(ctxCategory, {
      type: 'bar',
      data: {
        labels: catLabels.length ? catLabels : ['Tanpa Kategori'],
        datasets: [{
          label: 'Jumlah Tiket',
          data: catData.length ? catData : [0],
          backgroundColor: '#3b82f6',
          borderRadius: 4
        }]
      },
      options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { display: false } } }
    });
  }
});

onMounted(fetchSummary);
</script>

<template>
  <section class="dashboard-page-wrap">

    <!-- Header -->
    <div class="dash-header-card">
      <div class="dash-header-inner">
        <div class="dash-icon-badge">📊</div>
        <div>
          <h2 class="dash-title">Dashboard Overview</h2>
          <p class="dash-subtitle">Selamat Datang, <b>{{ auth.user?.name || 'User' }}</b> 👋 &bull; Ringkasan status sistem.</p>
        </div>
      </div>
    </div>

    <!-- Status Stats -->
    <div class="dash-stats-grid">
      <template v-if="loading">
        <div v-for="i in 6" :key="i" class="dash-stat-skeleton"></div>
      </template>

      <template v-else-if="summary">
        <!-- Total Tiket -->
        <div class="corp-stat-card">
          <div class="corp-stat-icon" style="background: #eff6ff; color: #3b82f6;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4V6h16v12zm-9-1h2v-2h-2v2zm0-4h2V7h-2v6z"/></svg>
          </div>
          <div class="corp-stat-content">
            <p class="corp-stat-label">Total Tiket</p>
            <h3 class="corp-stat-value">{{ summary.total ?? 0 }}</h3>
          </div>
        </div>

        <!-- Open -->
        <div class="corp-stat-card">
          <div class="corp-stat-icon" style="background: #fef2f2; color: #ef4444;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
          </div>
          <div class="corp-stat-content">
            <p class="corp-stat-label">Open</p>
            <h3 class="corp-stat-value">{{ summary.open ?? 0 }}</h3>
          </div>
        </div>

        <!-- In Progress -->
        <div class="corp-stat-card">
          <div class="corp-stat-icon" style="background: #fffbeb; color: #f59e0b;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
          </div>
          <div class="corp-stat-content">
            <p class="corp-stat-label">In Progress</p>
            <h3 class="corp-stat-value">{{ summary.in_progress ?? 0 }}</h3>
          </div>
        </div>

        <!-- Closed -->
        <div class="corp-stat-card">
          <div class="corp-stat-icon" style="background: #ecfdf5; color: #10b981;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
          </div>
          <div class="corp-stat-content">
            <p class="corp-stat-label">Closed</p>
            <h3 class="corp-stat-value">{{ summary.closed ?? 0 }}</h3>
          </div>
        </div>

        <!-- Waktu Kendala -->
        <div class="corp-stat-card">
          <div class="corp-stat-icon" style="background: #fdf4ff; color: #d946ef;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm1-13h-2v5l4.28 2.54.72-1.21-3-1.78z"/></svg>
          </div>
          <div class="corp-stat-content">
            <p class="corp-stat-label">Total Waktu</p>
            <h3 class="corp-stat-value">{{ formatDurasi(summary.total_downtime) }}</h3>
          </div>
        </div>

        <!-- Agent Terdampak -->
        <div class="corp-stat-card">
          <div class="corp-stat-icon" style="background: #eef2ff; color: #6366f1;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
          </div>
          <div class="corp-stat-content">
            <p class="corp-stat-label">Agent Terdampak</p>
            <h3 class="corp-stat-value">{{ summary.total_agent_terdampak ?? 0 }}</h3>
          </div>
        </div>
      </template>
    </div>

    <!-- Informative Data -->
    <div class="dash-charts-grid" v-if="summary && !loading">
      
      <!-- By Status -->
      <div class="corp-chart-card">
        <h3 class="corp-chart-title">Statistik Status Tiket</h3>
        <div style="position: relative; height: 260px; margin-top: 16px;">
          <canvas id="statusChart"></canvas>
        </div>
      </div>

      <!-- By Kategori -->
      <div class="corp-chart-card" style="grid-column: span 2;">
        <h3 class="corp-chart-title">Tiket Berdasarkan Kategori</h3>
        <div style="position: relative; height: 260px; margin-top: 16px;">
          <canvas id="categoryChart"></canvas>
        </div>
      </div>

      <!-- By Priority -->
      <div class="corp-chart-card">
        <h3 class="corp-chart-title">Tiket Berdasarkan Prioritas</h3>
        <div v-if="Object.keys(summary.by_priority || {}).length === 0" style="color: #94a3b8; font-size: 0.95rem; text-align: center; margin-top: 40px;">
          Tidak ada data.
        </div>
        <div v-else style="display:flex; flex-direction:column; gap:16px; margin-top: 20px;">
          <div v-for="(count, priority) in summary.by_priority" :key="priority" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #f1f5f9;">
            <span style="font-size: 1rem; font-weight: 600; color: #334155; text-transform: capitalize;">{{ priority || 'Tanpa Prioritas' }}</span>
            <span style="background: #e0e7ff; color: #4338ca; padding: 4px 16px; border-radius: 99px; font-weight: 700; font-size: 0.9rem;">{{ count }}</span>
          </div>
        </div>
      </div>

    </div>

  </section>
</template>

<style scoped>
.corp-stat-card {
  background: white; 
  padding: 22px; 
  border-radius: 16px; 
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); 
  border: 1px solid #f1f5f9; 
  display: flex; 
  align-items: center; 
  gap: 16px;
  transition: transform 0.2s, box-shadow 0.2s;
}
.corp-stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.025);
}
.corp-stat-icon {
  width: 54px; 
  height: 54px; 
  border-radius: 14px; 
  display: flex; 
  align-items: center; 
  justify-content: center; 
  flex-shrink: 0;
}
.corp-stat-label {
  margin: 0; 
  font-size: 0.82rem; 
  font-weight: 700; 
  color: #64748b; 
  text-transform: uppercase; 
  letter-spacing: 0.05em;
}
.corp-stat-value {
  margin: 4px 0 0; 
  font-size: 1.75rem; 
  font-weight: 800; 
  color: #0f172a;
}

.dash-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 20px;
}

.dash-stat-skeleton {
  background: #fff;
  border-radius: 12px;
  height: 100px;
  animation: pulse 1.2s infinite alternate;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
  border: 1px solid #f1f5f9;
}

.dash-charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 24px;
}

.corp-chart-card {
  background: white; 
  padding: 24px; 
  border-radius: 16px; 
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); 
  border: 1px solid #f1f5f9;
}
.corp-chart-title {
  font-size: 1.1rem; 
  font-weight: 800; 
  color: #0f172a; 
  margin: 0; 
  border-bottom: 1px solid #f1f5f9; 
  padding-bottom: 12px;
}

.dashboard-page-wrap {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.dash-header-card {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 16px;
}

.dash-header-inner {
  display: flex;
  align-items: center;
  gap: 12px;
}

.dash-icon-badge {
  display: none;
}

.dash-title {
  margin: 0 0 4px;
  font-size: 1.75rem;
  font-weight: 800;
  color: #0f172a;
}

.dash-subtitle {
  margin: 0;
  font-size: 0.95rem;
  color: #64748b;
}

@media (max-width: 768px) {
  .dash-header-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 14px 16px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.03);
    margin-bottom: 0;
  }
  .dash-icon-badge {
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
  .dash-title {
    font-size: 1.15rem !important;
    margin: 0 !important;
  }
  .dash-subtitle {
    font-size: 0.76rem !important;
    margin-top: 2px !important;
  }
  .dash-stats-grid {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 10px !important;
  }
  .corp-stat-card {
    padding: 12px 10px;
    border-radius: 12px;
    gap: 8px;
    flex-direction: column;
    align-items: flex-start;
  }
  .corp-stat-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
  }
  .corp-stat-icon svg {
    width: 18px;
    height: 18px;
  }
  .corp-stat-label {
    font-size: 0.68rem;
    letter-spacing: 0.02em;
  }
  .corp-stat-value {
    font-size: 1.25rem;
    margin-top: 2px;
  }
  .dash-charts-grid {
    grid-template-columns: 1fr !important;
    gap: 14px !important;
  }
  .corp-chart-card {
    padding: 14px 16px;
    grid-column: span 1 !important;
    border-radius: 14px;
  }
}
</style>
