import axios from 'axios';
import { useAuthStore } from '../stores/auth';

export const API_BASE_URL = import.meta.env.VITE_API_URL || 'https://apits.gentz.me/api';

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
