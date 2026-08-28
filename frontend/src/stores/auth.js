import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: sessionStorage.getItem('token') || '',
    user: JSON.parse(sessionStorage.getItem('user') || 'null')
  }),
  getters: {
    isAuthenticated: (state) => Boolean(state.token),
    roles: (state) => state.user?.roles || [],
    primaryRole: (state) => state.user?.roles?.[0] || ''
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
      return this.roles.includes(roleName);
    }
  }
});
