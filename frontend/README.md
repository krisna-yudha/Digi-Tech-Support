# Frontend - TS Monitoring

Lokasi frontend:
- C:/xampp/htdocs/ts/microservice-planning/frontend

## Stack
- Vue 3
- Vite
- Vue Router
- Pinia
- Axios

## Setup
1. Buka terminal di folder frontend.
2. Install dependency:
   npm install
3. Salin env:
   copy .env.example .env
4. Jalankan dev server:
   npm run dev

## Halaman yang tersedia
- /login
- /dashboard
- /gangguan
- /gangguan/create
- /gangguan/:id
- /gangguan/:id/upload
- /summary
- /backup
- /users
- /settings

## Catatan Integrasi API
- URL API diambil dari VITE_API_URL.
- Default fallback: http://localhost:8000/api
