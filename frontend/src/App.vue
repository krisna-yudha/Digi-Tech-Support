<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from './stores/auth';

const route = useRoute();
const auth  = useAuthStore();
const showNav = computed(() => route.name !== 'login');

const navItems = computed(() => {
  if (auth.hasRole('Admin')) return [
    { to: '/dashboard', label: 'Dashboard' },
    { to: '/gangguan',  label: 'Gangguan'  },
    { to: '/gangguan/create', label: 'Input Gangguan' },
    { to: '/summary',   label: 'Summary'   },
    { to: '/backup',    label: 'Backup'    },
    { to: '/users',     label: 'Users'     },
    { to: '/settings',  label: 'Settings'  },
  ];
  if (auth.hasRole('TS')) return [
    { to: '/dashboard', label: 'Dashboard' },
    { to: '/gangguan',  label: 'Gangguan'  },
    { to: '/gangguan/create', label: 'Input Gangguan' },
  ];
  if (auth.hasRole('Agent')) return [
    { to: '/agent-dashboard',  label: 'Dashboard'       },
    { to: '/gangguan/create',  label: 'Tambah Gangguan' },
  ];
  return [];
});

const roleLabel = computed(() => {
  if (!auth.user) return '';
  const roles = auth.user.roles ?? [];
  return roles[0] ?? '';
});
</script>

<template>
  <div>
    <!-- ── Top Bar ── -->
    <header v-if="showNav" class="top-bar">
      <div class="container header-content" style="display:flex; align-items:center; gap:32px; padding: 14px 20px;">
        <!-- Logo & Title -->
        <div style="display:flex; align-items:center; gap:12px;">
          <div style="width:36px; height:36px; border-radius:10px; background:var(--primary); display:flex; align-items:center; justify-content:center; box-shadow:0 4px 10px rgba(38,149,239,0.2);">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
            </svg>
          </div>
          <div>
            <h1 style="font-size:1.15rem; font-weight:800; line-height:1.2; letter-spacing:-0.02em;">TS Monitoring</h1>
            <p style="font-size:0.75rem; color:var(--muted); letter-spacing:0.02em;">IS Call Center</p>
          </div>
        </div>

        <!-- Navigation -->
        <nav v-if="showNav && navItems.length" class="nav" style="flex:1;">
          <RouterLink v-for="item in navItems" :key="item.to" :to="item.to">
            {{ item.label }}
          </RouterLink>
        </nav>

        <!-- User Info & Logout -->
        <div v-if="auth.isAuthenticated" class="header-actions" style="display:flex; align-items:center; gap:16px; margin-left:auto;">
          <div style="text-align:right;">
            <p style="font-size:0.85rem; font-weight:700;">{{ auth.user?.name }}</p>
            <p style="font-size:0.72rem; color:var(--muted); text-transform:uppercase; letter-spacing:0.04em; font-weight:600;">{{ roleLabel }}</p>
          </div>
          <div style="width:1px; height:24px; background:var(--border);"></div>
          <button class="btn-ghost" style="padding:8px 16px; font-size:0.85rem; border-radius:8px;" @click="auth.logout()">Logout</button>
        </div>
      </div>
    </header>

    <!-- ── Content ── -->
    <main class="main-content container">
      <RouterView />
    </main>

    <!-- ── Footer ── -->
    <footer style="text-align:center;padding:20px 0 30px;font-size:0.8rem;color:var(--muted);">
      IS Call Center &mdash; TS Monitoring System
    </footer>
  </div>
</template>
