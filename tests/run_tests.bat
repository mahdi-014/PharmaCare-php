@echo off
title PharmaCare Test Suite
echo ========================================================
echo               PharmaCare Test Runner
echo ========================================================
echo.

set "PATH=C:\Users\TheFool\mariadb\bin;C:\Users\TheFool\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe;%PATH%"

echo [1/10] Running Smoke Tests (Business Logic & Algorithms)...
php "%~dp0smoke.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo [2/10] Running Database & Relational Integrity Tests...
php "%~dp0database_test.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo [3/10] Running Customer Authentication & RBAC Tests...
php "%~dp0auth_test.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo [4/10] Running Shopping Cart & Stock Clamping Tests...
php "%~dp0cart_test.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo [5/10] Running Order Placement & Concurrency Tests...
php "%~dp0order_test.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo [6/10] Running Order Lifecycle & Sales Channel Bridge Tests...
php "%~dp0order_lifecycle_test.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo [7/10] Running Asynchronous RESTful JSON API Tests...
php "%~dp0api_test.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo [8/10] Running Layered MVC Architecture Tests...
php "%~dp0mvc_test.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo [9/10] Running Comprehensive Security Hardening Tests...
php "%~dp0security_test.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo [10/10] Running Unified Dashboard Analytics & Reporting Tests...
php "%~dp0dashboard_test.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo ========================================================
echo       ALL 10 TEST SUITES (382 ASSERTIONS) PASSED!
echo ========================================================
pause
exit /b 0

:failed
echo.
echo [ERROR] Test suite failed with exit code %ERRORLEVEL%.
pause
exit /b %ERRORLEVEL%
