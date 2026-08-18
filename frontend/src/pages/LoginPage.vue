<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';

const router = useRouter();
const auth   = useAuthStore();
const loading = ref(false);
const error   = ref('');

const form = reactive({ email: '', password: '' });

async function submit() {
  loading.value = true;
  error.value   = '';
  try {
    const { data } = await api.post('/login', form);
    auth.setAuth(data);
    if (data.user.roles?.includes('Agent')) { router.push({ name: 'agent-dashboard' }); return; }
    if (data.user.roles?.includes('TS'))    { router.push({ name: 'gangguan-list'   }); return; }
    router.push({ name: 'dashboard' });
  } catch (err) {
    error.value = err.response?.data?.message || 'Login gagal. Periksa email dan password Anda.';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <section style="max-width:420px;margin:48px auto;">
    <div class="card">
      <!-- Logo area -->
      <div style="text-align:center;margin-bottom:24px;">
        <div style="width:48px;height:48px;border-radius:14px;background:var(--primary);display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
          </svg>
        </div>
        <h2 style="font-size:1.25rem;font-weight:700;">Masuk ke TS Monitoring</h2>
        <p style="color:var(--muted);font-size:0.85rem;margin-top:4px;">IS Call Center — Monitoring Gangguan</p>
      </div>

      <form class="grid" style="gap:14px;" @submit.prevent="submit">
        <div>
          <label for="email">Email / Account</label>
          <input id="email" v-model="form.email" type="text" placeholder="Masukan account yang terdaftar" required autocomplete="username">
        </div>
        <div>
          <label for="password">Password</label>
          <input id="password" v-model="form.password" type="password" placeholder="••••••••" required autocomplete="current-password">
        </div>

        <div v-if="error" class="alert alert-danger">{{ error }}</div>

        <button class="btn-primary" type="submit" :disabled="loading" style="width:100%;padding:11px;">
          {{ loading ? 'Memproses...' : 'Masuk' }}
        </button>
      </form>
    </div>
  </section>
</template>
