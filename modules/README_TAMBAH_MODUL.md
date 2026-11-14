# Panduan Menambah Modul & Laporan SQL Baharu (Pelbagai Skema Oracle 10g)

Sistem ini menyokong 8 profil skema Oracle 10g mengikut modul ePBT (`config.local.php` -> `oracle_profiles`):
- `mtkn` — Modul Taksiran
- `mktn` — Modul Kutipan / Kaunter
- `mpnl` — Modul Penilaian
- `mswn` — Modul Sewaan
- `mpln` — Modul Pelesenan
- `mpbg` — Modul Pelbagai
- `sakb` — Sistem Akaun Kena Bayar
- `spbt` — Modul SPBT

## 1. Tambah Laporan SQL Baharu (Automatik dalam UI)
1. Salin fail `modules/reports/_template.example.php` kepada nama fail baharu tanpa `_` di hadapan (contoh: `modules/reports/senarai_sewa_premis.php`).
2. Tetapkan:
   - `id`: pengenal unik laporan (contoh: `senarai-sewa-premis`)
   - `module_id`: pengenal modul daripada `modules/registry.php` (contoh: `mswn`, `mktn`, `mpnl`, `mpln`, `mpbg`, `sakb`, `spbt`, `mtkn`)
   - `oracle_profile`: pilih skema sambungan Oracle yang sesuai (`mswn`, `mktn`, dll. atau `default`)
   - `sql`: letakkan kuiri `SELECT` Oracle 10g anda (tanpa `;` di hujung)
   - `columns`: senarai kolum (`text`, `number`, atau `currency`)
3. Simpan fail. Modul berkenaan akan bertukar kepada status **Aktif** secara automatik dan laporan boleh dibuka terus di dalam tab **Laporan Modul (SQL)** lengkap dengan *pagination* `ROWNUM` dan **Eksport CSV**.
