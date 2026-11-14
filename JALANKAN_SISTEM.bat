@echo off
title Pelancar Sistem Laporan MBAS (Oracle 10g)
color 0b

echo =====================================================================
echo       SISTEM DASHBOARD TUNGGAKAN & TERIMAAN MBAS (ORACLE 10G)
echo =====================================================================
echo.

set "PATH=E:\laragon\bin\instantclient_12_1;E:\laragon\bin\php\php-8.3.36-Win32-vs16-x64;%PATH%"

set "PHP_BIN=E:\laragon\bin\php\php-8.3.36-Win32-vs16-x64\php.exe"
if not exist "%PHP_BIN%" (
    set "PHP_BIN=php"
)

echo [OK] PHP dikesan: %PHP_BIN%
"%PHP_BIN%" -v | findstr /i "PHP 8 PHP 7"
echo.
echo Memulakan pelayan web tempatan di http://localhost:8088 ...
echo Pelayan Oracle: 172.16.33.55:1521 (ORCLVM55)
echo.
echo Sila biarkan tetingkap ini terbuka semasa menggunakan sistem.
echo Tekan Ctrl+C untuk menutup pelayan.
echo =====================================================================
echo.

start "" "http://localhost:8088"
"%PHP_BIN%" -S 127.0.0.1:8088 -t "%~dp0"
