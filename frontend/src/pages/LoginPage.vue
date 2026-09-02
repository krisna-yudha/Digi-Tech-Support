<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';

const router  = useRouter();
const auth    = useAuthStore();
const loading = ref(false);
const error   = ref('');
const showPassword = ref(false);

const form = reactive({ email: '', password: '', remember: false });

async function submit() {
  loading.value = true;
  error.value   = '';
  try {
    const { data } = await api.post('/login', { email: form.email, password: form.password });
    auth.setAuth(data, form.remember);
    const roles = data.user.roles || [];
    const roleNames = roles.map(r => typeof r === 'object' ? r.name : r);
    if (roleNames.includes('Agent')) { router.push({ name: 'agent-dashboard' }); return; }
    if (roleNames.includes('TS'))    { router.push({ name: 'gangguan-list'   }); return; }
    router.push({ name: 'dashboard' });
  } catch (err) {
    error.value = err.response?.data?.message || 'Login gagal. Periksa email dan password Anda.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="login-wrapper">
    <div class="login-card">
      <!-- Ambient Glow Decorator -->
      <div class="card-glow-bg"></div>

      <!-- Logo & Header Area -->
      <div class="login-header">
        <div class="logo-container">
          <img
            :src="'/logo.png'"
            alt="Logo"
            class="login-logo-img"
            @error="$event.target.style.display='none'; $event.target.nextElementSibling.style.display='inline-flex';"
          />
          <div class="logo-fallback">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
            </svg>
          </div>
        </div>

        <h2 class="login-title">Masuk ke TS Monitoring</h2>
        <div class="login-badge-wrap">
          <span class="login-sub-badge">IS Call Center &bull; Monitoring Gangguan</span>
        </div>
      </div>

      <!-- Login Form -->
      <form class="login-form" @submit.prevent="submit">
        <div class="form-group">
          <label for="email" class="form-label">Email / Account</label>
          <div class="input-container">
            <input
              id="email"
              v-model="form.email"
              type="text"
              class="form-control"
              placeholder="Masukan account terdaftar"
              required
              autocomplete="username"
            />
          </div>
        </div>

        <div class="form-group">
          <label for="password" class="form-label">Password</label>
          <div class="input-container password-container">
            <input
              id="password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              class="form-control"
              placeholder="••••••••"
              required
              autocomplete="current-password"
            />
            <button
              type="button"
              class="toggle-pwd-btn"
              @click="showPassword = !showPassword"
              :title="showPassword ? 'Sembunyikan Password' : 'Lihat Password'"
            >
              <!-- Eye Open (when visible) -->
              <svg v-if="showPassword" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <!-- Eye Slashed (when hidden) -->
              <svg v-else width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Ingat Saya -->
        <div class="remember-row">
          <label class="custom-checkbox">
            <input
              id="remember"
              v-model="form.remember"
              type="checkbox"
            />
            <span class="checkbox-box"></span>
            <span class="remember-text">
              Ingat Saya <span class="remember-hint">(sesi tersimpan 24 jam)</span>
            </span>
          </label>
        </div>

        <!-- Error message -->
        <div v-if="error" class="login-alert">{{ error }}</div>

        <!-- Submit Button -->
        <button class="login-submit-btn" type="submit" :disabled="loading">
          <span v-if="!loading">Masuk ke Sistem</span>
          <span v-else class="btn-loading">
            <svg class="spin-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <circle cx="12" cy="12" r="10" stroke-opacity="0.25" />
              <path d="M12 2a10 10 0 0 1 10 10" />
            </svg>
            Memproses...
          </span>
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.login-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: calc(100vh - 120px);
  padding: 24px 16px;
}

.login-card {
  position: relative;
  width: 100%;
  max-width: 440px;
  background: #ffffff;
  border-radius: 24px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(241, 245, 249, 0.8);
  padding: 38px 36px 32px;
  box-sizing: border-box;
}

.card-glow-bg {
  position: absolute;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 240px;
  height: 120px;
  background: radial-gradient(circle, rgba(37, 99, 235, 0.08) 0%, rgba(255, 255, 255, 0) 70%);
  pointer-events: none;
  border-top-left-radius: 24px;
  border-top-right-radius: 24px;
}

.login-header {
  text-align: center;
  margin-bottom: 28px;
}

.logo-container {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 12px;
}

.login-logo-img {
  width: 110px;
  height: 110px;
  object-fit: contain;
  filter: drop-shadow(0 8px 16px rgba(37, 99, 235, 0.2));
  transition: transform 0.25s ease;
}
.login-logo-img:hover {
  transform: scale(1.04);
}

.logo-fallback {
  width: 68px;
  height: 68px;
  border-radius: 18px;
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  display: none;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
}

.login-title {
  font-size: 1.4rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.02em;
  margin: 0 0 6px;
}

.login-badge-wrap {
  display: flex;
  justify-content: center;
}

.login-sub-badge {
  font-size: 0.74rem;
  font-weight: 700;
  color: #475569;
  background: #f1f5f9;
  padding: 3px 10px;
  border-radius: 999px;
  letter-spacing: 0.02em;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-label {
  font-size: 0.75rem;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 7px;
}

.input-container {
  position: relative;
  display: flex;
  align-items: center;
}

.form-control {
  width: 100%;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  padding: 11px 16px;
  font-size: 0.92rem;
  color: #0f172a;
  font-family: inherit;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.form-control:hover {
  border-color: #cbd5e1;
  background: #fff;
}

.form-control:focus {
  background: #ffffff;
  border-color: #2563eb;
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
  outline: none;
}

.password-container .form-control {
  padding-right: 46px;
}

.toggle-pwd-btn {
  position: absolute;
  right: 8px;
  width: 34px;
  height: 34px;
  border: none;
  background: transparent;
  color: #64748b;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}

.toggle-pwd-btn:hover {
  background: #e2e8f0;
  color: #1d4ed8;
}

.remember-row {
  display: flex;
  align-items: center;
}

.custom-checkbox {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  user-select: none;
}

.custom-checkbox input {
  width: 16px;
  height: 16px;
  accent-color: #2563eb;
  cursor: pointer;
}

.remember-text {
  font-size: 0.82rem;
  font-weight: 600;
  color: #334155;
}

.remember-hint {
  color: #94a3b8;
  font-weight: 400;
}

.login-alert {
  padding: 10px 14px;
  border-radius: 10px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
  font-size: 0.82rem;
  font-weight: 600;
  line-height: 1.4;
}

.login-submit-btn {
  width: 100%;
  padding: 12px 20px;
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  color: #ffffff;
  border: none;
  border-radius: 12px;
  font-size: 0.92rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 10px 22px -4px rgba(37, 99, 235, 0.38);
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: inherit;
}

.login-submit-btn:hover:not(:disabled) {
  transform: translateY(-1.5px);
  box-shadow: 0 14px 26px -4px rgba(37, 99, 235, 0.45);
  filter: brightness(1.05);
}

.login-submit-btn:active:not(:disabled) {
  transform: translateY(0);
}

.login-submit-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.btn-loading {
  display: flex;
  align-items: center;
  gap: 8px;
}

.spin-icon {
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
