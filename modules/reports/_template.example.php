<?php
declare(strict_types=1);

/**
 * TEMPLAT LAPORAN MODUL BAHARU
 *
 * Cara tambah laporan SQL baharu:
 * 1. Salin fail ini menjadi fail baharu tanpa tanda garis bawah di depan,
 *    contoh: `modules/reports/ringkasan_kutipan_harian.php`.
 * 2. Isi `id`, `module_id` (rujuk `modules/registry.php`), `title`, `sql`, dan `columns`.
 * 3. Laporan akan muncul secara automatik di dalam Portal Modul & tab "Laporan Modul (SQL)"
 *    lengkap dengan penapis, pagination Oracle 10g (ROWNUM), dan Eksport CSV.
 */
return [
    'id' => 'contoh-laporan-baharu',
    'module_id' => 'mswn',
    // Pilih profil skema Oracle daripada config.local.php: mktn, mpbg, mpln, mpnl, mswn, mtkn, sakb, spbt (atau default)
    'oracle_profile' => 'mswn',
    'title' => 'Tajuk Laporan Baharu Anda',
    'description' => 'Penerangan ringkas laporan ini.',
    'supports_jlkod' => false,
    'supports_carian' => true,
    'carian_label' => 'Carian Kata Kunci / No. Akaun',
    'default_sort' => 'KOD',
    'sql' => "
        SELECT
            '1001' AS KOD,
            'CONTOH REKOD' AS KETERANGAN,
            0 AS AMAUN
        FROM DUAL
        WHERE 1 = 1
          /*FILTER_CARIAN*/
    ",
    'filter_carian_clause' => "AND UPPER('CONTOH') LIKE UPPER(:carian)",
    'columns' => [
        ['key' => 'KOD', 'label' => 'KOD', 'type' => 'text'],
        ['key' => 'KETERANGAN', 'label' => 'KETERANGAN', 'type' => 'text'],
        ['key' => 'AMAUN', 'label' => 'AMAUN (RM)', 'type' => 'currency'],
    ],
];

