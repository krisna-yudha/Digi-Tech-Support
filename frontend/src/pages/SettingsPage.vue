<script setup>
import { ref, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import UserManagementPage from './UserManagementPage.vue';
import CubiclesPage from './CubiclesPage.vue';
import BaTemplateSettingsPage from './BaTemplateSettingsPage.vue';

const route  = useRoute();
const router = useRouter();

const activeTab = ref('users');

onMounted(() => {
  if (route.query.tab && ['users', 'cubicles', 'template_ba'].includes(route.query.tab)) {
    activeTab.value = route.query.tab;
  } else {
    activeTab.value = 'users';
  }
});

watch(() => route.query.tab, (newTab) => {
  if (newTab && ['users', 'cubicles', 'template_ba'].includes(newTab)) {
    activeTab.value = newTab;
  } else {
    activeTab.value = 'users';
  }
});

function setTab(tab) {
  activeTab.value = tab;
  router.push({ query: { ...route.query, tab } });
}
</script>

<template>
  <div class="settings-page-wrap">
    <!-- Page Header & Segmented Tab Navigation -->
    <div class="settings-header-card">
      <div class="settings-title-group">
        <div class="settings-icon-badge">⚙️</div>
        <div>
          <h2 class="settings-main-title">Settings</h2>
          <p class="settings-main-subtitle">Kelola pengguna, cubicles, dan konfigurasi template Berita Acara.</p>
        </div>
      </div>

      <!-- Segmented Tab Bar (Effective & Responsive) -->
      <div class="settings-segmented-nav">
        <button 
          class="seg-tab-btn" 
          :class="{ active: activeTab === 'users' }" 
          @click="setTab('users')"
        >
          <svg class="seg-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
          <span class="desktop-tab-label">User Management</span>
          <span class="mobile-tab-label">Users</span>
        </button>

        <button 
          class="seg-tab-btn" 
          :class="{ active: activeTab === 'cubicles' }" 
          @click="setTab('cubicles')"
        >
          <svg class="seg-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
            <line x1="8" y1="21" x2="16" y2="21"/>
            <line x1="12" y1="17" x2="12" y2="21"/>
          </svg>
          <span class="desktop-tab-label">Cubicles Management</span>
          <span class="mobile-tab-label">Cubicles</span>
        </button>

        <button 
          class="seg-tab-btn" 
          :class="{ active: activeTab === 'template_ba' }" 
          @click="setTab('template_ba')"
        >
          <svg class="seg-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
          <span class="desktop-tab-label">Template Berita Acara</span>
          <span class="mobile-tab-label">Template BA</span>
        </button>
      </div>
    </div>

    <!-- Active Tab Content -->
    <div class="settings-tab-content">
      <div v-if="activeTab === 'users'">
        <UserManagementPage />
      </div>

      <div v-else-if="activeTab === 'cubicles'">
        <CubiclesPage />
      </div>

      <div v-else-if="activeTab === 'template_ba'">
        <BaTemplateSettingsPage />
      </div>
    </div>
  </div>
</template>

<style scoped>
.settings-page-wrap {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
}

.settings-header-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.settings-title-group {
  display: flex;
  align-items: center;
  gap: 12px;
}

.settings-icon-badge {
  display: none;
}

.settings-main-title {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 800;
  color: #0f172a;
}

.settings-main-subtitle {
  margin: 3px 0 0;
  color: var(--muted, #64748b);
  font-size: 0.88rem;
}

/* ─── Desktop Segmented Tab Bar ─── */
.settings-segmented-nav {
  display: flex;
  gap: 6px;
  background: #f1f5f9;
  padding: 5px;
  border-radius: 12px;
  width: fit-content;
  border: 1px solid #e2e8f0;
}

.seg-tab-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 18px;
  border: none;
  background: transparent;
  color: #64748b;
  font-weight: 600;
  font-size: 0.88rem;
  border-radius: 9px;
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
}

.seg-tab-btn:hover {
  color: #1e293b;
  background: rgba(255, 255, 255, 0.6);
}

.seg-tab-btn.active {
  color: #2563eb;
  background: #ffffff;
  font-weight: 700;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}

.mobile-tab-label {
  display: none;
}

/* ─── Mobile Layout ─── */
@media (max-width: 768px) {
  .settings-header-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 14px 14px;
    gap: 12px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
  }

  .settings-icon-badge {
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

  .settings-main-title {
    font-size: 1.15rem;
  }

  .settings-main-subtitle {
    font-size: 0.76rem;
    color: #64748b;
  }

  .settings-segmented-nav {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    width: 100%;
    padding: 3px;
    gap: 3px;
    border-radius: 11px;
    background: #f1f5f9;
    box-sizing: border-box;
  }

  .seg-tab-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    padding: 7px 2px;
    font-size: 0.74rem;
    border-radius: 8px;
    text-align: center;
  }

  .seg-tab-btn:active {
    transform: scale(0.96);
  }

  .desktop-tab-label {
    display: none;
  }

  .mobile-tab-label {
    display: inline-block;
    font-size: 0.72rem;
    line-height: 1.1;
    font-weight: 700;
    white-space: nowrap;
  }

  .seg-icon {
    width: 17px;
    height: 17px;
  }
}
</style>
