<?php
/**
 * Salin fail ini kepada `config.local.php` dan isi ID serta kata laluan Oracle 10g anda.
 * Jangan muat naik `config.local.php` ke repositori awam.
 */

return [
    // Maklumat pelayan ORCLVM55
    'oracle_host'    => '172.16.33.55',
    'oracle_port'    => 1521,
    'oracle_service' => 'orclvm53',

    // Kredensial utama lalai (contoh: mtkn atau akaun dengan capaian baca mtkn/spbt)
    'oracle_user'    => '',
    'oracle_pass'    => '',

    // Set aksara Oracle (AL32UTF8 atau WE8ISO8859P1)
    'oracle_charset' => 'AL32UTF8',

    // Tukar kepada true jika mahu mula dalam Mod Demo tanpa sambungan Oracle
    'demo_mode'      => false,

    // Profil skema Oracle mengikut modul ePBT (digunakan oleh laporan dalam modules/reports/)
    'oracle_profiles' => [
        'mktn' => ['label' => 'Modul Kutipan / Kaunter',     'username' => '', 'password' => ''],
        'mpbg' => ['label' => 'Modul Pelbagai',              'username' => '', 'password' => ''],
        'mpln' => ['label' => 'Modul Pelesenan',             'username' => '', 'password' => ''],
        'mpnl' => ['label' => 'Modul Penilaian',             'username' => '', 'password' => ''],
        'mswn' => ['label' => 'Modul Sewaan',                'username' => '', 'password' => ''],
        'mtkn' => ['label' => 'Modul Taksiran',              'username' => '', 'password' => ''],
        'sakb' => ['label' => 'Sistem Akaun Kena Bayar',     'username' => '', 'password' => ''],
        'spbt' => ['label' => 'Modul SPBT',                  'username' => '', 'password' => ''],
    ],

    // Tetapan akaun mock UI (tab Tetapan)
    'mock_admin_id'       => 'admin',
    'mock_admin_password' => 'admin123',
    'mock_admin_name'     => 'Pentadbir Hasil',
];
