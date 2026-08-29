import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: sessionStorage.getItem('token') || '',
    user: JSON.parse(sessionStorage.getItem('user') || 'null')
  }),
  getters: {
    isAuthenticated: (state) => Boolean(state.token),
    roles: (state) => state.user?.roles || [],
    // Ambil nama role: bisa string atau object {id, name}
    roleNames: (state) => {
      const roles = state.user?.roles || [];
      return roles.map(r => typeof r === 'object' ? r.name : r);
    },
    primaryRole: (state) => {
      const roles = state.user?.roles || [];
      if (!roles.length) return '';
      const first = roles[0];
      return typeof first === 'object' ? first.name : first;
    }
  },
  actions: {
    setAuth(payload) {
      this.token = payload.token;
      this.user = payload.user;
      sessionStorage.setItem('token', payload.token);
      sessionStorage.setItem('user', JSON.stringify(payload.user));
    },
    logout() {
      this.token = '';
      this.user = null;
      sessionStorage.removeItem('token');
      sessionStorage.removeItem('user');
      window.location.href = '/login';
    },
    hasRole(roleName) {
      // Handle both array of strings and array of objects {id, name, ...}
      return this.roleNames.includes(roleName);
    }
  }
});
