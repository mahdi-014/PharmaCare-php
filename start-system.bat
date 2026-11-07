@echo off
title PharmaCare - Pharmacy Management System
echo ========================================================
echo        Starting PharmaCare Pharmacy Management System
echo ========================================================
echo.

:: Ensure PHP and MariaDB are in PATH for this session
set "PATH=C:\Users\TheFool\mariadb\bin;C:\Users\TheFool\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe;%PATH%"

:: 1. Check if MariaDB is already running
tasklist /fi "imagename eq mariadbd.exe" | findstr /i "mariadbd.exe" >nul
if %ERRORLEVEL% equ 0 (
    echo [OK] MariaDB database service is already running.
) else (
    echo [*] Starting MariaDB server...
    start "" /B mariadbd.exe --defaults-file="C:\Users\TheFool\mariadb\data\my.ini"
    timeout /t 2 /nobreak >nul
    echo [OK] MariaDB server started.
)

:: 2. Check if PHP server is running on port 8000
echo [*] Starting PharmaCare web application on http://localhost:8000 ...
start http://localhost:8000

echo.
echo Application running at: http://localhost:8000
echo Demo Accounts:
echo   - Admin: admin / admin123
echo   - Staff: staff / staff123
echo.
echo Press Ctrl+C in this window to stop the PHP web server.
echo ========================================================
php.exe -S 127.0.0.1:8000 -t "%~dp0."

