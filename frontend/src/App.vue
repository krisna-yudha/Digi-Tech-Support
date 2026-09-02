<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from './stores/auth';

const route = useRoute();
const auth  = useAuthStore();
const showNav = computed(() => route.name !== 'login');

const navItems = computed(() => {
  if (auth.hasRole('Admin')) return [
    { to: '/dashboard', label: 'Dashboard', icon: 'dashboard' },
    { to: '/gangguan',  label: 'Gangguan',  icon: 'gangguan'  },
    { to: '/gangguan/create', label: 'Input Gangguan', icon: 'create', isPrimary: true },
    { to: '/settings',  label: 'Settings',  icon: 'settings'  },
  ];
  if (auth.hasRole('TS')) return [
    { to: '/dashboard', label: 'Dashboard', icon: 'dashboard' },
    { to: '/gangguan',  label: 'Gangguan',  icon: 'gangguan'  },
    { to: '/gangguan/create', label: 'Input Gangguan', icon: 'create', isPrimary: true },
    { to: '/profil-ts', label: 'Digi TS',   icon: 'profil'    },
  ];
  if (auth.hasRole('Agent')) return [
    { to: '/agent-dashboard',  label: 'Dashboard',       icon: 'dashboard' },
    { to: '/gangguan/create',  label: 'Tambah Gangguan', icon: 'create', isPrimary: true },
  ];
  return [];
});

const roleLabel = computed(() => auth.primaryRole);
</script>

<template>
  <div class="app-layout">
    <!-- ── Top Bar (Desktop & Mobile Header) ── -->
    <header v-if="showNav" class="top-bar">
      <div class="container header-content">
        <!-- Logo & Title -->
        <div class="brand-wrap">
          <img
            :src="'/logo.png'"
            alt="Logo"
            class="header-logo"
            @error="$event.target.style.display='none'; $event.target.nextElementSibling.style.display='flex';"
          />
          <div class="header-logo-fallback">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
            </svg>
          </div>
          <div class="brand-text">
            <h1 class="brand-title">TS Monitoring</h1>
            <p class="brand-sub">IS Call Center</p>
          </div>
        </div>

        <!-- Desktop Navigation -->
        <nav v-if="showNav && navItems.length" class="nav desktop-nav">
          <RouterLink v-for="item in navItems" :key="item.to" :to="item.to" class="desktop-nav-link">
            {{ item.label }}
          </RouterLink>
        </nav>

        <!-- User Info & Logout -->
        <div v-if="auth.isAuthenticated" class="header-actions">
          <div class="user-info-text">
            <p class="user-name">{{ auth.user?.name }}</p>
            <p class="user-role">{{ roleLabel }}</p>
          </div>
          <div class="header-divider"></div>
          <button class="btn-logout" @click="auth.logout()" title="Keluar dari akun">
            <svg class="logout-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
              <polyline points="16 17 21 12 16 7"></polyline>
              <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span class="logout-text">Logout</span>
          </button>
        </div>
      </div>
    </header>

    <!-- ── Content ── -->
    <main class="main-content container">
      <RouterView />
    </main>

    <!-- ── Mobile Bottom Navigation Bar (Native App Style) ── -->
    <nav v-if="showNav && navItems.length" class="mobile-bottom-nav">
      <RouterLink
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        class="mobile-nav-item"
      >
        <!-- Dashboard Icon -->
        <svg v-if="item.icon === 'dashboard'" class="nav-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="7" height="9"></rect>
          <rect x="14" y="3" width="7" height="5"></rect>
          <rect x="14" y="12" width="7" height="9"></rect>
          <rect x="3" y="16" width="7" height="5"></rect>
        </svg>

        <!-- Gangguan Icon -->
        <svg v-else-if="item.icon === 'gangguan'" class="nav-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="16" y1="13" x2="8" y2="13"></line>
          <line x1="16" y1="17" x2="8" y2="17"></line>
          <polyline points="10 9 9 9 8 9"></polyline>
        </svg>

        <!-- Input / Tambah Gangguan Icon (Plus Circle) -->
        <svg v-else-if="item.icon === 'create'" class="nav-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="16"></line>
          <line x1="8" y1="12" x2="16" y2="12"></line>
        </svg>

        <!-- Digi TS / Profil Icon -->
        <svg v-else-if="item.icon === 'profil'" class="nav-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>

        <!-- Settings Icon -->
        <svg v-else-if="item.icon === 'settings'" class="nav-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="3"></circle>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
        </svg>

        <span class="mobile-nav-label">{{ item.label }}</span>
      </RouterLink>
    </nav>

    <!-- ── Footer (Desktop only) ── -->
    <footer class="app-footer">
      IS Call Center &mdash; TS Monitoring System
    </footer>
  </div>
</template>

<style scoped>
.app-layout {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.brand-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-logo {
  width: 38px;
  height: 38px;
  object-fit: contain;
}

.header-logo-fallback {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: var(--primary);
  display: none;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 10px rgba(38,149,239,0.2);
}

.brand-title {
  font-size: 1.15rem;
  font-weight: 800;
  line-height: 1.2;
  letter-spacing: -0.02em;
  margin: 0;
  color: #0f172a;
}

.brand-sub {
  font-size: 0.75rem;
  color: var(--muted);
  letter-spacing: 0.02em;
  margin: 0;
}

.user-name {
  font-size: 0.85rem;
  font-weight: 700;
  line-height: 1.2;
  margin: 0;
  color: #0f172a;
}

.user-role {
  font-size: 0.72rem;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-weight: 600;
  margin: 2px 0 0;
}

.btn-logout {
  display: flex;
  align-items: center;
  gap: 6px;
}

.app-footer {
  text-align: center;
  padding: 20px 0 30px;
  font-size: 0.8rem;
  color: var(--muted);
}

/* ─── Mobile Bottom Nav (Native Mobile Style) ─── */
.mobile-bottom-nav {
  display: none;
}

@media (max-width: 768px) {
  .desktop-nav {
    display: none !important;
  }

  .header-content {
    padding: 10px 16px !important;
  }

  .brand-title {
    font-size: 1.05rem;
  }

  .user-info-text {
    display: none;
  }

  .header-divider {
    display: none;
  }

  .logout-text {
    display: none;
  }

  .btn-logout {
    padding: 8px !important;
    border-radius: 10px !important;
    background: #fef2f2 !important;
    color: #dc2626 !important;
  }

  /* Safe area for mobile content */
  .main-content {
    padding-bottom: 96px !important;
    min-height: calc(100vh - 64px);
  }

  .app-footer {
    display: none;
  }

  /* Native Bottom Nav Bar */
  .mobile-bottom-nav {
    display: flex;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-top: 1px solid #e2e8f0;
    box-shadow: 0 -4px 20px rgba(15, 23, 42, 0.05);
    z-index: 999;
    padding: 6px 6px max(6px, env(safe-area-inset-bottom, 6px)) 6px;
    justify-content: space-around;
    align-items: center;
  }

  .mobile-nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    flex: 1;
    height: 100%;
    color: #64748b;
    text-decoration: none;
    font-size: 0.72rem;
    font-weight: 600;
    transition: all 0.15s ease;
    border-radius: 12px;
    position: relative;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
    padding: 2px 0;
  }

  .mobile-nav-item:active {
    transform: scale(0.92);
  }

  .mobile-nav-item.router-link-active {
    color: #2563eb;
    font-weight: 700;
  }

  .mobile-nav-item.router-link-active .nav-svg {
    stroke: #2563eb;
  }

  .mobile-nav-label {
    font-size: 0.72rem;
    line-height: 1;
    letter-spacing: -0.01em;
  }
}
</style>
