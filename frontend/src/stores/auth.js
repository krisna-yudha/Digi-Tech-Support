import { defineStore } from 'pinia';

const TOKEN_KEY = 'auth_token';
const USER_KEY  = 'auth_user';

// ─── Cookie helpers ───────────────────────────────────────────────────────────
function setCookie(name, value, maxAgeSeconds) {
  let cookie = `${name}=${encodeURIComponent(value)}; path=/; SameSite=Lax`;
  if (maxAgeSeconds) cookie += `; max-age=${maxAgeSeconds}`;
  document.cookie = cookie;
}

function getCookie(name) {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
  return match ? decodeURIComponent(match[1]) : null;
}

function deleteCookie(name) {
  document.cookie = `${name}=; path=/; max-age=0`;
}

// ─── Read helpers ─────────────────────────────────────────────────────────────
function readToken() {
  return getCookie(TOKEN_KEY) || '';
}

function readUser() {
  const raw = getCookie(USER_KEY);
  if (!raw) return null;
  try { return JSON.parse(raw); } catch { return null; }
}

// ─── Store ────────────────────────────────────────────────────────────────────
export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: readToken(),
    user:  readUser(),
  }),
  getters: {
    isAuthenticated: (state) => Boolean(state.token),
    roles: (state) => state.user?.roles || [],
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
    /**
     * @param {{ token, user }} payload
     * @param {boolean} remember
     *   true  → simpan cookie selama 24 jam (86400 detik)
     *   false → session cookie (hilang saat browser ditutup, tapi dibagikan antar tab)
     */
    setAuth(payload, remember = false) {
      this.token = payload.token;
      this.user  = payload.user;

      const maxAge = remember ? 86400 : undefined; // 24 jam atau session
      setCookie(TOKEN_KEY, payload.token, maxAge);
      setCookie(USER_KEY,  JSON.stringify(payload.user), maxAge);
    },
    logout() {
      this.token = '';
      this.user  = null;
      deleteCookie(TOKEN_KEY);
      deleteCookie(USER_KEY);
      window.location.href = '/login';
    },
    hasRole(roleName) {
      return this.roleNames.includes(roleName);
    }
  }
});
