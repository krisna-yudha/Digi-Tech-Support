# 04. API

## Auth
- POST /login
- POST /logout
- GET /me

## Gangguan
- GET /gangguan
- POST /gangguan
- GET /gangguan/{id}
- PUT /gangguan/{id}
- DELETE /gangguan/{id}

## Evidence
- POST /gangguan/{id}/upload
- DELETE /evidence/{id}

## Summary
- GET /summary
- GET /summary/daily
- GET /summary/weekly
- GET /summary/monthly

## Backup
- GET /backups
- POST /backups/download
- POST /backups/restore
