@echo off
setlocal enabledelayedexpansion

set "ROOT_DIR=%~dp0"
set "BACKEND_DIR=%ROOT_DIR%backend"
set "FRONTEND_DIR=%ROOT_DIR%frontend"

where php >nul 2>nul
if errorlevel 1 (
  echo php is not installed or not in PATH
  exit /b 1
)

where composer >nul 2>nul
if errorlevel 1 (
  echo composer is not installed or not in PATH
  exit /b 1
)

where npm >nul 2>nul
if errorlevel 1 (
  echo npm is not installed or not in PATH
  exit /b 1
)

if not exist "%BACKEND_DIR%\vendor" (
  echo Installing backend dependencies...
  pushd "%BACKEND_DIR%"
  call composer install
  popd
  if errorlevel 1 exit /b 1
)

if not exist "%FRONTEND_DIR%\node_modules" (
  echo Installing frontend dependencies...
  pushd "%FRONTEND_DIR%"
  call npm install
  popd
  if errorlevel 1 exit /b 1
)

echo Starting backend API on http://127.0.0.1:8000
start "" /D "%BACKEND_DIR%" php artisan serve --host=127.0.0.1 --port=8000

echo Starting frontend on http://127.0.0.1:5173
start "" /D "%FRONTEND_DIR%" npm run dev -- --host 127.0.0.1 --port 5173

echo.
echo Development servers have been started in separate windows.
echo Close the Backend API and Frontend App windows to stop them.
endlocal