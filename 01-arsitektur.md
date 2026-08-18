# 01. Arsitektur

## Stack
- Backend: Laravel 12
- Frontend: Vue 3 + Vite
- Database: PostgreSQL/MySQL
- Queue: Redis
- Auth: Laravel Sanctum
- Permission: Spatie Permission

## Arsitektur
```text
Vue 3
   │
REST API
   │
Laravel 12
   ├── Auth
   ├── Gangguan
   ├── Report
   ├── Backup
   └── Retention
        │
 PostgreSQL
        │
 Redis Queue
        │
 Jobs & Scheduler
```
