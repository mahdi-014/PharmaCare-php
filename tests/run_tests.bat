@echo off
title PharmaCare Test Suite
echo ========================================================
echo               PharmaCare Test Runner
echo ========================================================
echo.

set "PATH=C:\Users\TheFool\mariadb\bin;C:\Users\TheFool\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe;%PATH%"

echo [1/2] Running Smoke Tests (Business Logic & Algorithms)...
php "%~dp0smoke.php"
if %ERRORLEVEL% neq 0 (
    echo.
    echo [ERROR] Smoke tests failed.
    pause
    exit /b %ERRORLEVEL%
)

echo.
echo [2/2] Running Database & Relational Integrity Tests...
php "%~dp0database_test.php"
if %ERRORLEVEL% neq 0 (
    echo.
    echo [ERROR] Database integrity tests failed.
    pause
    exit /b %ERRORLEVEL%
)

echo.
echo ========================================================
echo            ALL TEST SUITES PASSED SUCCESSFULLY!
echo ========================================================
pause

