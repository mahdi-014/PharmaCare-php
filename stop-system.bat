@echo off
title Stop PharmaCare System
echo Stopping PharmaCare services...

taskkill /F /IM php.exe /T 2>nul
taskkill /F /IM mariadbd.exe /T 2>nul

echo All PharmaCare processes stopped.
pause

