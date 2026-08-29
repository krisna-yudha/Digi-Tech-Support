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
  <div style="display: flex; flex-direction: column; gap: 20px; width: 100%;">
    <!-- Page Header & Tab Navigation -->
    <div style="display: flex; flex-direction: column; gap: 14px;">
      <div>
        <h2 class="page-title" style="margin: 0;">Settings</h2>
        <p style="margin: 4px 0 0; color: var(--muted); font-size: 0.88rem;">Kelola pengguna, cubicles, dan konfigurasi template Berita Acara.</p>
      </div>

      <!-- Tab Buttons -->
      <div class="tab-nav">
        <button 
          class="tab-btn" 
          :class="{ active: activeTab === 'users' }" 
          @click="setTab('users')"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
          User Management
        </button>

        <button 
          class="tab-btn" 
          :class="{ active: activeTab === 'cubicles' }" 
          @click="setTab('cubicles')"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
            <line x1="8" y1="21" x2="16" y2="21"/>
            <line x1="12" y1="17" x2="12" y2="21"/>
          </svg>
          Cubicles Management
        </button>

        <button 
          class="tab-btn" 
          :class="{ active: activeTab === 'template_ba' }" 
          @click="setTab('template_ba')"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
          Template Berita Acara
        </button>
      </div>
    </div>

    <!-- Active Tab Content -->
    <div>
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
.tab-nav {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 0;
  width: fit-content;
  flex-wrap: wrap;
}

.tab-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border: none;
  background: transparent;
  color: #64748b;
  font-weight: 600;
  font-size: 0.9rem;
  border-radius: 8px 8px 0 0;
  cursor: pointer;
  transition: all 0.15s ease;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
}

.tab-btn:hover {
  color: #2563eb;
  background: #f8fafc;
}

.tab-btn.active {
  color: #2563eb;
  background: #fff;
  border-bottom: 2px solid #2563eb;
  font-weight: 700;
}
</style>
