@echo off
setlocal enabledelayedexpansion

echo ============================================
echo   Stopping Development Servers...
echo ============================================
echo.

REM --- Stop PHP Artisan Serve (port 8000) ---
echo [1/2] Stopping backend (php artisan serve on port 8000)...

for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":8000" ^| findstr "LISTENING"') do (
    set "PID=%%a"
    if not "!PID!"=="" (
        taskkill /PID !PID! /F >nul 2>&1
        if !errorlevel!==0 (
            echo       Backend stopped. ^(PID: !PID!^)
        ) else (
            echo       Failed to stop backend on PID !PID!.
        )
    )
)

REM --- Stop npm run dev (port 5173) ---
echo [2/2] Stopping frontend (npm run dev on port 5173)...

for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":5173" ^| findstr "LISTENING"') do (
    set "PID=%%a"
    if not "!PID!"=="" (
        taskkill /PID !PID! /F >nul 2>&1
        if !errorlevel!==0 (
            echo       Frontend stopped. ^(PID: !PID!^)
        ) else (
            echo       Failed to stop frontend on PID !PID!.
        )
    )
)

echo.
echo ============================================
echo   All development servers have been stopped.
echo ============================================
echo.
pause
endlocal
