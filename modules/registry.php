<?php
declare(strict_types=1);

/**
 * Daftar 10 Modul Teras ePBT mengikut format rujukan Sistem Laporan MBAS / SPHB.
 * Rujukan fail: tutorial php menu laporan sphb.doc (Step 1 - Step 6)
 */
return [
    [
        'id' => 'kutipan',
        'code' => 'KUTIPAN',
        'oracle_profile' => 'mktn',
        'title' => 'KUTIPAN',
        'full_title' => 'MODUL KUTIPAN / KAUNTER (MKTN)',
        'subtitle' => 'Kutipan harian, terimaan resit, dan saluran bayaran kaunter',
        'color' => '#149e84',
        'icon' => 'grid',
        'target_tab' => 'menu-epbt',
        'status' => 'active',
        'menu_categories' => [
            [
                'title' => 'Cetakan Laporan Kutipan Harian',
                'items' => [
                    ['id' => 'lap-kutipan-harian', 'title' => 'Laporan Terimaan Mengikut Kaunter & Pegawai', 'report_id' => 'detail-tgkn-terimaan-jalan'],
                    ['id' => 'lap-resit-batal', 'title' => 'Senarai Resit Batal / Pindaan', 'report_id' => 'detail-tgkn-terimaan-jalan'],
                    ['id' => 'lap-penyata-bank', 'title' => 'Penyata Penyesuaian Kutipan Bank', 'report_id' => 'detail-tgkn-terimaan-jalan'],
                ],
            ],
        ],
    ],
    [
        'id' => 'penilaian',
        'code' => 'PENILAIAN',
        'oracle_profile' => 'mpnl',
        'title' => 'PENILAIAN',
        'full_title' => 'MODUL PENILAIAN (MPNL)',
        'subtitle' => 'Senarai pegangan harta, nilai tahunan dan kadaran cukai',
        'color' => '#f59e0b',
        'icon' => 'home',
        'target_tab' => 'menu-epbt',
        'status' => 'active',
        'menu_categories' => [
            [
                'title' => 'Laporan Nilaian & Pegangan',
                'items' => [
                    ['id' => 'lap-nilaian-tahunan', 'title' => 'Senarai Nilai Tahunan Mengikut Jalan', 'report_id' => 'detail-tgkn-terimaan-jalan'],
                    ['id' => 'lap-pindaan-nilaian', 'title' => 'Laporan Pindaan Nilai Tahunan (Kenyataan A)', 'report_id' => 'detail-tgkn-terimaan-jalan'],
                ],
            ],
        ],
    ],
    [
        'id' => 'taksiran',
        'code' => 'TAKSIRAN',
        'oracle_profile' => 'mtkn',
        'title' => 'TAKSIRAN',
        'full_title' => 'MODUL TAKSIRAN (MTKN)',
        'subtitle' => 'Dashboard Tunggakan & Terimaan Ikut Jalan, Penyata Akaun dan Lejar',
        'color' => '#2574b8',
        'icon' => 'document',
        'target_tab' => 'dashboard',
        'status' => 'active',
        'menu_categories' => [
            [
                'title' => 'Laporan Tunggakan & Terimaan Mengikut Jalan (Oracle 10g)',
                'items' => [
                    ['id' => 'detail-tgkn-terimaan-jalan', 'title' => 'Detail Tunggakan & Terimaan Ikut Jalan (Live / DETAIL_TGKN_TERIMAAN_IKUT_JALAN.sql)', 'report_id' => 'detail-tgkn-terimaan-jalan', 'default' => true],
                    ['id' => 'ringkasan-tgkn-jalan', 'title' => 'Ringkasan Tunggakan Mengikut Jalan', 'report_id' => 'ringkasan-tgkn-jalan'],
                    ['id' => 'httl11a-penyata-taksiran', 'title' => 'Penyata Akaun Taksiran (httl11a / SPHB)', 'report_id' => 'httl11a-penyata-taksiran'],
                    ['id' => 'dash-taksiran', 'title' => 'Paparan Dashboard Utama & Prestasi Garis (KPI)', 'view' => 'dashboard'],
                    ['id' => 'analisis-eis-taksiran', 'title' => 'Analisis Jalan EIS & Top 5 Tunggakan Tertinggi', 'view' => 'analisis'],
                    ['id' => 'senarai-akaun-taksiran', 'title' => 'Senarai Lengkap Akaun Pegangan (Paginasi & Carian)', 'view' => 'senarai'],
                ],
            ],
        ],
    ],
    [
        'id' => 'pelesenan',
        'code' => 'PELESENAN',
        'oracle_profile' => 'mpln',
        'title' => 'PELESENAN',
        'full_title' => 'MODUL PELESENAN (MPLN)',
        'subtitle' => 'Daftar lesen, cetakan bil tahunan/bulanan, dan penguatkuasaan lesen',
        'color' => '#e04f3e',
        'icon' => 'id-card',
        'target_tab' => 'menu-epbt',
        'status' => 'active',
        'menu_categories' => [
            [
                'title' => 'Cetakan Bil Lesen Tahunan (Rujukan Tutorial)',
                'items' => [
                    ['id' => 'biltahunakaunA4', 'title' => 'Bil Lesen Tahunan Mengikut Akaun (A4)', 'report_id' => 'biltahunakaun'],
                    ['id' => 'biltahunakaunPP', 'title' => 'Bil Lesen Tahunan Mengikut Akaun (Pre-Printed)', 'report_id' => 'biltahunakaun'],
                    ['id' => 'biltahunkodniagaA4', 'title' => 'Bil Lesen Tahunan Mengikut Kod Perniagaan (A4)', 'report_id' => 'biltahunakaun'],
                    ['id' => 'biltahunkodniagaPP', 'title' => 'Bil Lesen Tahunan Mengikut Kod Perniagaan (Pre-Printed)', 'report_id' => 'biltahunakaun'],
                    ['id' => 'biltaskforce', 'title' => 'Bil Taskforce Pelesenan', 'report_id' => 'biltahunakaun'],
                    ['id' => 'biltahunakaun', 'title' => 'Bil Lesen Tahunan Mengikut Akaun', 'report_id' => 'biltahunakaun', 'highlight' => true],
                ],
            ],
            [
                'title' => 'Cetakan Bil Lesen Bulanan & Awal',
                'items' => [
                    ['id' => 'bilawalakaun', 'title' => 'Bil Lesen Awal Mengikut Akaun', 'report_id' => 'biltahunakaun'],
                    ['id' => 'bilbulanakaun', 'title' => 'Bil Lesen Bulanan Mengikut Akaun', 'report_id' => 'biltahunakaun'],
                    ['id' => 'notisperingatan', 'title' => 'Notis Peringatan Tunggakan Lesen', 'report_id' => 'biltahunakaun'],
                    ['id' => 'penyataakaunlesen', 'title' => 'Penyata Akaun Pelesenan', 'report_id' => 'biltahunakaun'],
                ],
            ],
        ],
    ],
    [
        'id' => 'sewaan',
        'code' => 'SEWAAN',
        'oracle_profile' => 'mswn',
        'title' => 'SEWAAN',
        'full_title' => 'MODUL SEWAAN (MSWN)',
        'subtitle' => 'Penyata sewa gerai, pasar, premis MBAS dan tunggakan sewaan',
        'color' => '#8e44ad',
        'icon' => 'briefcase',
        'target_tab' => 'menu-epbt',
        'status' => 'active',
        'menu_categories' => [
            [
                'title' => 'Laporan Sewaan Premis & Gerai',
                'items' => [
                    ['id' => 'lap-tunggakan-sewa', 'title' => 'Penyata Tunggakan Sewaan Mengikut Pasar/Gerai', 'report_id' => 'detail-tgkn-terimaan-jalan'],
                    ['id' => 'lap-senarai-penyewa', 'title' => 'Senarai Penyewa Aktif & Perjanjian Sewa', 'report_id' => 'detail-tgkn-terimaan-jalan'],
                ],
            ],
        ],
    ],
    [
        'id' => 'pelbagai',
        'code' => 'PELBAGAI',
        'oracle_profile' => 'mpbg',
        'title' => 'PELBAGAI',
        'full_title' => 'MODUL PELBAGAI (MPBG)',
        'subtitle' => 'Bil pelbagai, caj perkhidmatan, dan hasil bukan cukai',
        'color' => '#34495e',
        'icon' => 'edit',
        'target_tab' => 'menu-epbt',
        'status' => 'active',
        'menu_categories' => [
            [
                'title' => 'Laporan Bil Pelbagai',
                'items' => [
                    ['id' => 'lap-bil-pelbagai', 'title' => 'Senarai Bil Pelbagai Mengikut Akaun', 'report_id' => 'detail-tgkn-terimaan-jalan'],
                    ['id' => 'lap-terimaan-pelbagai', 'title' => 'Ringkasan Terimaan Hasil Bukan Cukai', 'report_id' => 'detail-tgkn-terimaan-jalan'],
                ],
            ],
        ],
    ],
    [
        'id' => 'kompaun',
        'code' => 'KOMPAUN',
        'oracle_profile' => 'mpbg',
        'title' => 'KOMPAUN',
        'full_title' => 'MODUL KOMPAUN (KOMPAUN)',
        'subtitle' => 'Kompaun letak kereta, lalu lintas, dan penguatkuasaan undang-undang kecil',
        'color' => '#dc2626',
        'icon' => 'alert-circle',
        'target_tab' => 'menu-epbt',
        'status' => 'active',
        'menu_categories' => [
            [
                'title' => 'Laporan Kompaun & Tindakan',
                'items' => [
                    ['id' => 'lap-kompaun-tertunggak', 'title' => 'Senarai Kompaun Tertunggak Mengikut No Kenderaan', 'report_id' => 'detail-tgkn-terimaan-jalan'],
                    ['id' => 'lap-kutipan-kompaun', 'title' => 'Laporan Kutipan Kompaun Bulanan', 'report_id' => 'detail-tgkn-terimaan-jalan'],
                ],
            ],
        ],
    ],
    [
        'id' => 'jernal',
        'code' => 'JERNAL',
        'oracle_profile' => 'sakb',
        'title' => 'JERNAL',
        'full_title' => 'MODUL JERNAL & LEJAR AM (SAKB)',
        'subtitle' => 'Baucar jernal, pelarasan akaun, imbangan duga dan buku tunai',
        'color' => '#0284c7',
        'icon' => 'book',
        'target_tab' => 'menu-epbt',
        'status' => 'active',
        'menu_categories' => [
            [
                'title' => 'Laporan Jernal & Pelarasan',
                'items' => [
                    ['id' => 'lap-baucar-jernal', 'title' => 'Senarai Baucar Jernal Mengikut Tarikh', 'report_id' => 'detail-tgkn-terimaan-jalan'],
                    ['id' => 'lap-pelarasan-lejar', 'title' => 'Laporan Pelarasan Lejar Am', 'report_id' => 'detail-tgkn-terimaan-jalan'],
                ],
            ],
        ],
    ],
    [
        'id' => 'kutipan_luar_talian',
        'code' => 'KUTIPAN LUAR TALIAN',
        'oracle_profile' => 'mktn',
        'title' => 'KUTIPAN LUAR TALIAN',
        'full_title' => 'MODUL KUTIPAN LUAR TALIAN',
        'subtitle' => 'Penyelarasan resit kutipan offline, semakan kelompok dan muat naik',
        'color' => '#7c3aed',
        'icon' => 'wifi-off',
        'target_tab' => 'menu-epbt',
        'status' => 'active',
        'menu_categories' => [
            [
                'title' => 'Pengurusan Kutipan Offline',
                'items' => [
                    ['id' => 'lap-status-offline', 'title' => 'Status Muat Naik & Penyelarasan Kelompok Offline', 'report_id' => 'detail-tgkn-terimaan-jalan'],
                    ['id' => 'lap-log-offline', 'title' => 'Log Transaksi Kutipan Luar Talian', 'report_id' => 'detail-tgkn-terimaan-jalan'],
                ],
            ],
        ],
    ],
    [
        'id' => 'penyelenggaraan',
        'code' => 'PENYELENGGARAAN',
        'oracle_profile' => 'default',
        'title' => 'PENYELENGGARAAN',
        'full_title' => 'PENYELENGGARAAN & DIAGNOSTIK ORACLE',
        'subtitle' => 'Uji sambungan ORCLVM55 (172.16.33.55:1521/orclvm53), semak skema & tetapan',
        'color' => '#475569',
        'icon' => 'gears',
        'target_tab' => 'pentadbiran',
        'status' => 'active',
        'menu_categories' => [
            [
                'title' => 'Diagnostik Pelayan & Pangkalan Data',
                'items' => [
                    ['id' => 'diag-oracle', 'title' => 'Uji Sambungan Oracle 10g (ORCLVM55)', 'view' => 'pentadbiran', 'default' => true],
                    ['id' => 'diag-sql', 'title' => 'Rujukan SQL Teras & Skema (mtkn, mktn, mpln, spbt)', 'view' => 'pentadbiran'],
                    ['id' => 'diag-tetapan', 'title' => 'Tetapan Sistem, Kata Laluan & Mod Demo', 'view' => 'tetapan'],
                ],
            ],
        ],
    ],
];
