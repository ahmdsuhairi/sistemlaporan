# Sistem Dashboard Tunggakan & Terimaan Ikut Jalan (PHP + Oracle 10g)

Aplikasi web PHP + HTML/CSS/JS Vanilla (PWA-ready) untuk menarik dan menganalisis data tunggakan serta terimaan mengikut jalan daripada pangkalan data **Oracle 10g (`ORCLVM55`)**.

## Struktur Fail
- `PROMPT_PHP_ORACLE10G.md` — Prompt adaptasi daripada PDF asal kepada PHP + Oracle 10g.
- `config.php` & `config.local.example.php` — Konfigurasi TNS `ORCLVM55` (`172.16.33.55:1521/orclvm53`) dan akaun mock.
- `src/OracleClient.php` — Lapisan sambungan dwi-pemacu (`OCI8` / `PDO_OCI`) dengan kuiri berparameter.
- `src/TunggakanRepository.php` — Kuiri Oracle 10g (menggunakan `ROWNUM` untuk *pagination* & Top 5) berasaskan `DETAIL_TGKN_TERIMAAN_IKUT_JALAN.sql`.
- `src/ReportEngine.php` & `modules/` — Seni bina modular gaya portal ePBT (`modules/registry.php`, `modules/reports/*.php`, `modules/README_TAMBAH_MODUL.md`) untuk menambah laporan SQL baharu secara automatik.
- `src/DemoDataset.php` — Set data contoh berasaskan output sebenar Jalan 142 untuk Mod Demo UI.
- `api.php` & `index.php` — API JSON, eksport CSV, dan antara muka PWA (Laman Utama Modul, Dashboard Utama, Analisis Jalan, Senarai Akaun, Laporan Modul, Pentadbiran & Oracle, Tetapan).

## Panduan Pemasangan & Sambungan Oracle 10g (Laragon Windows)

1. **Pemacu Oracle PHP (`pdo_oci`)**:
   - Pada persekitaran Windows + Laragon PHP 8.3 (64-bit) dengan klien Oracle 11.2.0 (`C:\oraclexe\app\oracle\product\11.2.0\server\bin`), `php_oci8_19.dll` memerlukan Instant Client 19c (yang tidak menyokong Oracle 10g), manakala **`extension=pdo_oci`** berfungsi terus dengan klien 11.2.0 sedia ada untuk menyambung ke Oracle 10g.
   - `extension=pdo_oci` telah diaktifkan dalam `C:\laragon\bin\php\php-8.3.10-Win32-vs16-x64\php.ini`. Jika Apache dalam Laragon sedang berjalan, klik **Reload / Start All** dalam aplikasi Laragon.

2. **Tetapkan Kredensial Oracle**:
   - Salin `config.local.example.php` kepada `config.local.php`.
   - Masukkan `oracle_user` dan `oracle_pass` bagi pelayan `172.16.33.55:1521/orclvm53`.

3. **Jalankan Projek**:
   - Projek telah dipautkan terus ke `C:\laragon\www\sistem-tunggakan-terimaan`. Setelah Laragon Apache dimulakan (**Start All**), layari `http://localhost/sistem-tunggakan-terimaan/` (atau jalankan pelayan PHP tempatan: `php -S 127.0.0.1:8088 -t C:\Users\10027\Documents\sistem-tunggakan-terimaan`).
   - Buka tab **Pentadbiran & Oracle** -> klik **Uji Sambungan Oracle**.
   - Untuk menambah modul atau laporan SQL baharu, lihat panduan dalam `modules/README_TAMBAH_MODUL.md`.

