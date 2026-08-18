# Backend - TS Monitoring API

Backend API untuk aplikasi monitoring gangguan sesuai planning microservice-planning.

## Stack
- Laravel 12
- Sanctum (token auth)
- Spatie Permission (role)
- Queue + Scheduler

## Endpoint utama
### Auth
- POST /api/login
- POST /api/logout
- GET /api/me

### Gangguan
- GET /api/gangguan
- POST /api/gangguan
- GET /api/gangguan/{id}
- PUT /api/gangguan/{id}
- DELETE /api/gangguan/{id}

### Evidence
- POST /api/gangguan/{id}/upload
- DELETE /api/evidence/{id}

### Summary
- GET /api/summary
- GET /api/summary/daily
- GET /api/summary/weekly
- GET /api/summary/monthly

### Backup
- GET /api/backups
- POST /api/backups/download
- POST /api/backups/restore
- POST /api/backups/trigger

## Setup
1. Copy env:
   copy .env.example .env
2. Atur database di .env (mysql/pgsql) dan queue di redis/database.
3. Install dependency:
   composer install
4. Generate key:
   php artisan key:generate
5. Migrate + seed:
   php artisan migrate --seed
6. Jalankan server:
   php artisan serve

## User default (seed)
- admin@example.com / password (Admin)
- ts@example.com / password (TS)
- agent@example.com / password (Agent)

## Scheduler
- Weekly Backup: Minggu 23:55
- Retention Cleanup: setiap hari 01:00

Jalankan scheduler worker:
- php artisan schedule:work

Jalankan queue worker:
- php artisan queue:work
