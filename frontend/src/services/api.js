import axios from 'axios';
import { useAuthStore } from '../stores/auth';

const isLocalHost = typeof window !== 'undefined' && 
  (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');

export const API_BASE_URL = import.meta.env.VITE_API_URL || 
  (isLocalHost ? 'http://127.0.0.1:8000/api' : 'https://apits.gentz.me/api');
export const STORAGE_BASE_URL = API_BASE_URL.replace(/\/api\/?$/, '') + '/storage';

const api = axios.create({
  baseURL: API_BASE_URL
});

api.interceptors.request.use((config) => {
  const auth = useAuthStore();
  if (auth.token) {
    config.headers.Authorization = `Bearer ${auth.token}`;
  }
  return config;
});

export default api;
