@echo off
title PharmaCare Test Suite
echo ========================================================
echo               PharmaCare Test Runner
echo ========================================================
echo.

set "PATH=C:\Users\TheFool\mariadb\bin;C:\Users\TheFool\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe;%PATH%"

echo [1/9] Running Smoke Tests (Business Logic & Algorithms)...
php "%~dp0smoke.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo [2/9] Running Database & Relational Integrity Tests...
php "%~dp0database_test.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo [3/9] Running Customer Authentication & RBAC Tests...
php "%~dp0auth_test.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo [4/9] Running Shopping Cart & Stock Clamping Tests...
php "%~dp0cart_test.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo [5/9] Running Order Placement & Concurrency Tests...
php "%~dp0order_test.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo [6/9] Running Order Lifecycle & Sales Channel Bridge Tests...
php "%~dp0order_lifecycle_test.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo [7/9] Running Asynchronous RESTful JSON API Tests...
php "%~dp0api_test.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo [8/9] Running Layered MVC Architecture Tests...
php "%~dp0mvc_test.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo [9/9] Running Comprehensive Security Hardening Tests...
php "%~dp0security_test.php"
if %ERRORLEVEL% neq 0 goto :failed

echo.
echo ========================================================
echo       ALL 9 TEST SUITES (334 ASSERTIONS) PASSED!
echo ========================================================
pause
exit /b 0

:failed
echo.
echo [ERROR] Test suite failed with exit code %ERRORLEVEL%.
pause
exit /b %ERRORLEVEL%
