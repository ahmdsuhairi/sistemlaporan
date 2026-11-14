<?php
declare(strict_types=1);

/**
 * Laporan: Penyata Akaun Taksiran (httl11a / SPHB)
 * Rujukan fail dalam penyataepbtlama: httl11a2015.rep / httl11a2015.RDF
 * Pointer: httl11a
 */
return [
    'id' => 'httl11a-penyata-taksiran',
    'module_id' => 'mtkn',
    'oracle_profile' => 'mtkn',
    'title' => 'Penyata Akaun Taksiran (httl11a)',
    'description' => 'Penyata akaun cukai taksiran / pegangan mengikut nombor akaun atau jalan (Format Penyata SPHB httl11a).',
    'supports_jlkod' => true,
    'supports_carian' => true,
    'carian_label' => 'No. Akaun Pegangan',
    'default_sort' => 'PEG_AKAUN',
    'cache_key' => 'mtkn_httl11a_penyata',
    'pointer_report' => 'httl11a',
    'parameters' => [
        ['name' => 'akaun1', 'label' => 'Dari Akaun', 'type' => 'text', 'placeholder' => 'Cth: 17017001'],
        ['name' => 'akaun2', 'label' => 'Hingga Akaun', 'type' => 'text', 'placeholder' => 'Cth: 17017050'],
    ],
    'sql' => "
        SELECT
            c.peg_akaun AS PEG_AKAUN,
            c.peg_jlkod AS PEG_JLKOD,
            e.jln_jnama AS JLN_JNAMA,
            d.pyt_kdrjk AS KOD_TRANSAKSI,
            d.pyt_amaun AS AMAUN_TRANSAKSI,
            d.pyt_thn   AS TAHUN,
            d.pyt_peng  AS PENGGAL,
            c.peg_statf AS STATUS_PEGANGAN
        FROM mtkn.hvnduk2 c, mtkn.hlpyt1 d, spbt.fjalan e
        WHERE c.peg_akaun = d.pyt_akaun
          AND c.peg_jlkod = e.jln_jlkod
          AND c.peg_statf != 'H'
          /*FILTER_JLKOD*/
          /*FILTER_CARIAN*/
        ORDER BY c.peg_akaun, d.pyt_thn DESC
    ",
    'filter_jlkod_clause' => 'AND c.peg_jlkod = :jlkod',
    'filter_carian_clause' => 'AND (TO_CHAR(c.peg_akaun) LIKE :carian)',
    'columns' => [
        ['key' => 'PEG_AKAUN', 'label' => 'NO. AKAUN', 'type' => 'text'],
        ['key' => 'PEG_JLKOD', 'label' => 'KOD JALAN', 'type' => 'text'],
        ['key' => 'JLN_JNAMA', 'label' => 'NAMA JALAN', 'type' => 'text'],
        ['key' => 'KOD_TRANSAKSI', 'label' => 'KOD TRANSAKSI', 'type' => 'text'],
        ['key' => 'AMAUN_TRANSAKSI', 'label' => 'AMAUN (RM)', 'type' => 'currency'],
        ['key' => 'TAHUN', 'label' => 'TAHUN', 'type' => 'text'],
        ['key' => 'PENGGAL', 'label' => 'PENGGAL', 'type' => 'text'],
        ['key' => 'STATUS_PEGANGAN', 'label' => 'STATUS', 'type' => 'text'],
    ],
    'demo_provider' => static function (): array {
        return [
            ['PEG_AKAUN' => '17017001', 'PEG_JLKOD' => '142', 'JLN_JNAMA' => 'FLAT SIMPANG KUALA 2 & 3', 'KOD_TRANSAKSI' => '11010 - Cukai Taksiran Semasa', 'AMAUN_TRANSAKSI' => 120.00, 'TAHUN' => '2026', 'PENGGAL' => '1', 'STATUS_PEGANGAN' => 'AKTIF'],
            ['PEG_AKAUN' => '17017001', 'PEG_JLKOD' => '142', 'JLN_JNAMA' => 'FLAT SIMPANG KUALA 2 & 3', 'KOD_TRANSAKSI' => '21010 - Bayaran Resit Semasa', 'AMAUN_TRANSAKSI' => 120.00, 'TAHUN' => '2026', 'PENGGAL' => '1', 'STATUS_PEGANGAN' => 'AKTIF'],
            ['PEG_AKAUN' => '17017002', 'PEG_JLKOD' => '142', 'JLN_JNAMA' => 'FLAT SIMPANG KUALA 2 & 3', 'KOD_TRANSAKSI' => '12010 - Tunggakan Cukai Taksiran', 'AMAUN_TRANSAKSI' => 240.00, 'TAHUN' => '2025', 'PENGGAL' => '2', 'STATUS_PEGANGAN' => 'AKTIF'],
            ['PEG_AKAUN' => '17017003', 'PEG_JLKOD' => '142', 'JLN_JNAMA' => 'FLAT SIMPANG KUALA 2 & 3', 'KOD_TRANSAKSI' => '11010 - Cukai Taksiran Semasa', 'AMAUN_TRANSAKSI' => 150.00, 'TAHUN' => '2026', 'PENGGAL' => '1', 'STATUS_PEGANGAN' => 'AKTIF'],
            ['PEG_AKAUN' => '17017004', 'PEG_JLKOD' => '142', 'JLN_JNAMA' => 'FLAT SIMPANG KUALA 2 & 3', 'KOD_TRANSAKSI' => '12010 - Tunggakan Cukai Taksiran', 'AMAUN_TRANSAKSI' => 380.00, 'TAHUN' => '2025', 'PENGGAL' => '2', 'STATUS_PEGANGAN' => 'AKTIF'],
        ];
    },
];

