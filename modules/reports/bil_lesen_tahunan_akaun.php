<?php
declare(strict_types=1);

/**
 * Laporan: Bil Lesen Tahunan Mengikut Akaun (biltahunakaun.php)
 * Rujukan Tutorial: tutorial php menu laporan sphb.doc (Step 3 & 4)
 * Pointer Laporan: Itd05b
 */
return [
    'id' => 'biltahunakaun',
    'module_id' => 'mpln',
    'oracle_profile' => 'mpln',
    'title' => 'Bil Lesen Tahunan Mengikut Akaun',
    'description' => 'Cetakan dan semakan bil lesen tahunan mengikut julat no akaun (Parameter: Dari Akaun, Hingga Akaun, Pointer: Itd05b).',
    'supports_jlkod' => false,
    'supports_carian' => true,
    'carian_label' => 'No. Akaun Lesen',
    'default_sort' => 'LES_AKAUN',
    'cache_key' => 'mpln_bil_lesen_akaun',
    'pointer_report' => 'Itd05b',
    'parameters' => [
        ['name' => 'akaun1', 'label' => 'Dari Akaun', 'type' => 'text', 'placeholder' => 'Cth: L-10001'],
        ['name' => 'akaun2', 'label' => 'Hingga Akaun', 'type' => 'text', 'placeholder' => 'Cth: L-10050'],
    ],
    'sql' => "
        SELECT
            c.les_akaun AS LES_AKAUN,
            c.les_daftr AS NO_DAFTAR,
            c.les_nama  AS NAMA_PEMEGANG,
            c.les_kniag AS KOD_NIAGA,
            c.les_jnis  AS JENIS_LESEN,
            c.les_thn   AS TAHUN,
            c.les_amaun AS AMAUN_BIL,
            c.les_bayar AS AMAUN_BAYAR,
            (c.les_amaun - c.les_bayar) AS BAKI_LESEN,
            c.les_stat  AS STATUS
        FROM mpln.hlptn c
        WHERE c.les_stat != 'B'
          /*FILTER_CARIAN*/
        ORDER BY c.les_akaun
    ",
    'filter_carian_clause' => 'AND (c.les_akaun LIKE :carian OR UPPER(c.les_nama) LIKE UPPER(:carian))',
    'columns' => [
        ['key' => 'LES_AKAUN', 'label' => 'NO. AKAUN', 'type' => 'text'],
        ['key' => 'NO_DAFTAR', 'label' => 'NO. DAFTAR', 'type' => 'text'],
        ['key' => 'NAMA_PEMEGANG', 'label' => 'NAMA PEMEGANG LESEN', 'type' => 'text'],
        ['key' => 'KOD_NIAGA', 'label' => 'KOD NIAGA', 'type' => 'text'],
        ['key' => 'JENIS_LESEN', 'label' => 'JENIS LESEN', 'type' => 'text'],
        ['key' => 'TAHUN', 'label' => 'TAHUN', 'type' => 'text'],
        ['key' => 'AMAUN_BIL', 'label' => 'AMAUN BIL (RM)', 'type' => 'currency'],
        ['key' => 'AMAUN_BAYAR', 'label' => 'BAYAR (RM)', 'type' => 'currency'],
        ['key' => 'BAKI_LESEN', 'label' => 'BAKI (RM)', 'type' => 'currency'],
        ['key' => 'STATUS', 'label' => 'STATUS', 'type' => 'text'],
    ],
    'demo_provider' => static function (): array {
        return [
            ['LES_AKAUN' => 'L01-001042', 'NO_DAFTAR' => 'MBAS/PL/2026/0142', 'NAMA_PEMEGANG' => 'RESTORAN KELUARGA ALOR SETAR', 'KOD_NIAGA' => '101 - MAKANAN & MINUMAN', 'JENIS_LESEN' => 'PREMIS PERNIAGAAN', 'TAHUN' => '2026', 'AMAUN_BIL' => 450.00, 'AMAUN_BAYAR' => 450.00, 'BAKI_LESEN' => 0.00, 'STATUS' => 'AKTIF'],
            ['LES_AKAUN' => 'L01-001088', 'NO_DAFTAR' => 'MBAS/PL/2026/0188', 'NAMA_PEMEGANG' => 'PASAR RAYA BINTANG UTARA', 'KOD_NIAGA' => '102 - PASAR MINI / RUNCIT', 'JENIS_LESEN' => 'PERUNCITAN', 'TAHUN' => '2026', 'AMAUN_BIL' => 1200.00, 'AMAUN_BAYAR' => 600.00, 'BAKI_LESEN' => 600.00, 'STATUS' => 'TERTUNGGAK'],
            ['LES_AKAUN' => 'L01-001140', 'NO_DAFTAR' => 'MBAS/PL/2026/0240', 'NAMA_PEMEGANG' => 'BENGKEL KERETA WAN ENTERPRISE', 'KOD_NIAGA' => '305 - BENGKEL KENDERAAN', 'JENIS_LESEN' => 'PERINDUSTRIAN KECIL', 'TAHUN' => '2026', 'AMAUN_BIL' => 650.00, 'AMAUN_BAYAR' => 650.00, 'BAKI_LESEN' => 0.00, 'STATUS' => 'AKTIF'],
            ['LES_AKAUN' => 'L01-001205', 'NO_DAFTAR' => 'MBAS/PL/2026/0305', 'NAMA_PEMEGANG' => 'FESYEN AYU BOUTIQUE', 'KOD_NIAGA' => '201 - TEKSTIL & PAKAIAN', 'JENIS_LESEN' => 'PREMIS PERNIAGAAN', 'TAHUN' => '2026', 'AMAUN_BIL' => 380.00, 'AMAUN_BAYAR' => 0.00, 'BAKI_LESEN' => 380.00, 'STATUS' => 'BELUM BAYAR'],
            ['LES_AKAUN' => 'L01-001312', 'NO_DAFTAR' => 'MBAS/PL/2026/0412', 'NAMA_PEMEGANG' => 'KEDAI RUNCIT CHONG HUAT', 'KOD_NIAGA' => '102 - PASAR MINI / RUNCIT', 'JENIS_LESEN' => 'PERUNCITAN', 'TAHUN' => '2026', 'AMAUN_BIL' => 520.00, 'AMAUN_BAYAR' => 520.00, 'BAKI_LESEN' => 0.00, 'STATUS' => 'AKTIF'],
        ];
    },
];

