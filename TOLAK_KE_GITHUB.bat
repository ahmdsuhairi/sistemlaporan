@echo off
title Hantar Kod Sistem ke GitHub (ahmdsuhairi/sistemlaporan)
color 0a

echo =====================================================================
echo           HANTAR KOD SISTEM KE GITHUB
echo           Repositori: ahmdsuhairi/sistemlaporan
echo =====================================================================
echo.

set "PATH=E:\laragon\bin\git\cmd;%PATH%"

cd /d "%~dp0"

echo [1/3] Menetapkan remote origin ke https://github.com/ahmdsuhairi/sistemlaporan.git ...
git remote set-url origin https://github.com/ahmdsuhairi/sistemlaporan.git 2>nul || git remote add origin https://github.com/ahmdsuhairi/sistemlaporan.git

echo [2/3] Menyemak status fail tempatan...
git add .
git commit -m "update: kemaskini sistem laporan tunggakan terimaan MBAS" 2>nul

echo [3/3] Menolak kod ke GitHub (Branch main)...
echo Sila sahkan log masuk GitHub anda jika tetingkap pelayar/log masuk muncul.
echo.

git push -u origin main

echo.
if %ERRORLEVEL% equ 0 (
    echo =====================================================================
    echo [BERJAYA] Kod sistem berjaya dimuat naik ke GitHub!
    echo Sila semak repositori anda di:
    echo https://github.com/ahmdsuhairi/sistemlaporan
    echo =====================================================================
) else (
    echo =====================================================================
    echo [PERHATIAN] Gagal menghantar ke GitHub.
    echo Sila pastikan:
    echo 1. Anda telah mencipta repositori 'sistemlaporan' di https://github.com/new
    echo 2. Anda telah log masuk ke akaun GitHub 'ahmdsuhairi'.
    echo =====================================================================
)

echo.
pause
