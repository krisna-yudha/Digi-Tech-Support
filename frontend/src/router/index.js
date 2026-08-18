import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import LoginPage from '../pages/LoginPage.vue';
import DashboardPage from '../pages/DashboardPage.vue';
import AgentDashboardPage from '../pages/AgentDashboardPage.vue';
import AgentLaporanDetailPage from '../pages/AgentLaporanDetailPage.vue';
import GangguanListPage from '../pages/GangguanListPage.vue';
import GangguanCreatePage from '../pages/GangguanCreatePage.vue';
import GangguanDetailPage from '../pages/GangguanDetailPage.vue';
import UploadEvidencePage from '../pages/UploadEvidencePage.vue';
import SummaryPage from '../pages/SummaryPage.vue';
import BackupPage from '../pages/BackupPage.vue';
import UserManagementPage from '../pages/UserManagementPage.vue';
import SettingsPage from '../pages/SettingsPage.vue';

const routes = [
  { path: '/', redirect: '/dashboard' },
  { path: '/login', name: 'login', component: LoginPage, meta: { guest: true } },
  { path: '/dashboard', name: 'dashboard', component: DashboardPage, meta: { roles: ['Admin', 'TS'] } },
  { path: '/agent-dashboard', name: 'agent-dashboard', component: AgentDashboardPage, meta: { roles: ['Agent'] } },
  { path: '/agent/laporan/:id', name: 'agent-laporan-detail', component: AgentLaporanDetailPage, meta: { roles: ['Agent'] } },
  { path: '/gangguan', name: 'gangguan-list', component: GangguanListPage, meta: { roles: ['Admin', 'TS'] } },
  { path: '/gangguan/create', name: 'gangguan-create', component: GangguanCreatePage, meta: { roles: ['Agent', 'TS', 'Admin'] } },
  { path: '/gangguan/:id', name: 'gangguan-detail', component: GangguanDetailPage, meta: { roles: ['Admin', 'TS'] } },
  { path: '/gangguan/:id/upload', name: 'upload-evidence', component: UploadEvidencePage, meta: { roles: ['TS'] } },
  { path: '/summary', name: 'summary', component: SummaryPage, meta: { roles: ['Admin'] } },
  { path: '/backup', name: 'backup', component: BackupPage, meta: { roles: ['Admin'] } },
  { path: '/users', name: 'users', component: UserManagementPage, meta: { roles: ['Admin'] } },
  { path: '/settings', name: 'settings', component: SettingsPage, meta: { roles: ['Admin'] } }
];

const router = createRouter({
  history: createWebHistory(),
  routes
});

router.beforeEach((to) => {
  const auth = useAuthStore();

  if (!auth.isAuthenticated && !to.meta.guest) {
    return { name: 'login' };
  }

  if (auth.isAuthenticated && to.meta.guest) {
    if (auth.hasRole('Agent')) return { name: 'agent-dashboard' };
    if (auth.hasRole('TS')) return { name: 'dashboard' };
    return { name: 'dashboard' };
  }

  const allowedRoles = to.meta.roles;

  if (Array.isArray(allowedRoles) && allowedRoles.length > 0) {
    const canAccess = allowedRoles.some((role) => auth.hasRole(role));

    if (!canAccess) {
      if (auth.hasRole('Agent')) {
        return { name: 'agent-dashboard' };
      }

      if (auth.hasRole('TS')) {
        return { name: 'gangguan-list' };
      }

      return { name: 'dashboard' };
    }
  }

  return true;
});

export default router;
