<?php
declare(strict_types=1);

/**
 * Laporan 2 — Modul Taksiran (mtkn)
 * Ringkasan Tunggakan Cukai Taksiran Ikut Jalan / Taman / Kampung.
 *
 * Reka bentuk pantas (High-Performance Aggregation):
 *   - disable_cache = true & fetch_all = true (data dipaparkan terus dari Oracle)
 *   - Penapisan kod jalan (:jlkod) menggunakan INDEX HLPNYT_01 & HLPGNG_03:
 *     Larian pantas ~1-2 saat bagi setiap jalan terpilih.
 *   - Carian nama jalan / taman / kawasan (:carian): ~3-5 saat.
 *   - add_grand_total = true: Baris JUMLAH BESAR dikira secara automatik oleh PHP.
 */
return [
    'id'                   => 'ringkasan-tgkn-jalan',
    'module_id'            => 'mtkn',
    'oracle_profile'       => 'mtkn',
    'title'                => 'Ringkasan Tunggakan Ikut Jalan / Taman / Kampung',
    'description'          => 'Ringkasan bilangan rumah, tunggakan, peratus tunggak & jumlah tunggakan mengikut kawasan dan jalan/taman/kampung (RINGKASAN_TUNGGAKAN_JALAN.sql).',
    'supports_jlkod'       => true,
    'supports_carian'      => true,
    'carian_label'         => 'Kawasan / Nama Jalan',
    'default_sort'         => 'KAWASAN',
    'disable_cache'        => true,
    'fetch_all'            => true,
    'add_grand_total'      => true,
    'jlkod_column'         => 'KOD_JALAN',
    'carian_column'        => 'JALAN_TAMAN_KAMPUNG',

    'sql' => "
        SELECT
            NVL(j.jln_knama, '(Tiada Kawasan)')                     AS KAWASAN,
            p.peg_jlkod                                             AS KOD_JALAN,
            NVL(j.jln_jnama, '(Tiada Nama Jalan)')                   AS JALAN_TAMAN_KAMPUNG,
            COUNT(DISTINCT p.peg_akaun)                             AS BIL_RUMAH,
            SUM(CASE WHEN baki_tbl.baki > 0 THEN 1 ELSE 0 END)     AS BIL_TUNGGAK,
            SUM(CASE WHEN baki_tbl.baki <= 0 THEN 1 ELSE 0 END)    AS BIL_SELESAI,
            SUM(CASE WHEN baki_tbl.baki IS NULL THEN 1 ELSE 0 END) AS BIL_TIADA_PENYATA,
            ROUND(100 * SUM(CASE WHEN baki_tbl.baki > 0 THEN 1 ELSE 0 END) / NULLIF(COUNT(DISTINCT p.peg_akaun), 0), 2) AS PERATUS_TUNGGAK,
            ROUND(100 * SUM(CASE WHEN baki_tbl.baki <= 0 THEN 1 ELSE 0 END) / NULLIF(COUNT(DISTINCT p.peg_akaun), 0), 2) AS PERATUS_SELESAI,
            NVL(SUM(CASE WHEN baki_tbl.baki > 0 THEN baki_tbl.baki ELSE 0 END), 0) AS JUMLAH_TUNGGAKAN
        FROM mtkn.hlpgng p
        INNER JOIN mtkn.vjalan2 j ON j.jln_jlkod = p.peg_jlkod
        LEFT JOIN (
            SELECT /*+ INDEX(n HLPNYT_01) */
                   n.pyt_akaun,
                   SUM(CASE WHEN n.pyt_kdrjk LIKE '1%' THEN n.pyt_amaun
                            WHEN n.pyt_kdrjk LIKE '2%' THEN -n.pyt_amaun
                            ELSE 0 END) AS baki
            FROM mtkn.hlpnyt n
            WHERE n.pyt_akaun IN (
                SELECT p2.peg_akaun FROM mtkn.hlpgng p2
                JOIN mtkn.vjalan2 j2 ON j2.jln_jlkod = p2.peg_jlkod
                WHERE p2.peg_statf <> 'H'
                /*FILTER_JLKOD_SUB*/
                /*FILTER_CARIAN_SUB*/
            )
            GROUP BY n.pyt_akaun
        ) baki_tbl ON baki_tbl.pyt_akaun = p.peg_akaun
        WHERE p.peg_statf <> 'H'
        /*FILTER_JLKOD*/
        /*FILTER_CARIAN*/
        GROUP BY p.peg_jlkod, j.jln_knama, j.jln_jnama
        ORDER BY j.jln_knama, j.jln_jnama
    ",

    'filter_jlkod_clause'       => 'AND p.peg_jlkod = :jlkod',
    'filter_jlkod_sub_clause'   => 'AND p2.peg_jlkod = :jlkod',
    'filter_carian_clause'      => 'AND (UPPER(j.jln_knama) LIKE UPPER(:carian) OR UPPER(j.jln_jnama) LIKE UPPER(:carian))',
    'filter_carian_sub_clause'  => 'AND (UPPER(j2.jln_knama) LIKE UPPER(:carian) OR UPPER(j2.jln_jnama) LIKE UPPER(:carian))',

    'columns' => [
        ['key' => 'KAWASAN',            'label' => 'KAWASAN',                 'type' => 'text'],
        ['key' => 'KOD_JALAN',          'label' => 'KOD JALAN',               'type' => 'text', 'sort_type' => 'numeric'],
        ['key' => 'JALAN_TAMAN_KAMPUNG','label' => 'JALAN / TAMAN / KAMPUNG', 'type' => 'text'],
        ['key' => 'BIL_RUMAH',          'label' => 'BIL. RUMAH',              'type' => 'integer'],
        ['key' => 'BIL_TUNGGAK',        'label' => 'BIL. TUNGGAK',            'type' => 'integer'],
        ['key' => 'BIL_SELESAI',        'label' => 'BIL. SELESAI',            'type' => 'integer'],
        ['key' => 'BIL_TIADA_PENYATA',  'label' => 'TIADA PENYATA',           'type' => 'integer'],
        ['key' => 'PERATUS_TUNGGAK',    'label' => '% TUNGGAK',               'type' => 'percent'],
        ['key' => 'PERATUS_SELESAI',    'label' => '% SELESAI',               'type' => 'percent'],
        ['key' => 'JUMLAH_TUNGGAKAN',   'label' => 'JUMLAH TUNGGAKAN (RM)',    'type' => 'currency'],
    ],

    // Data demo jika sambungan Oracle belum dikonfigur
    'demo_provider' => static fn(): array => [
        ['KAWASAN' => 'AMPANG',   'KOD_JALAN' => '101', 'JALAN_TAMAN_KAMPUNG' => 'JALAN AMPANG 1',      'BIL_RUMAH' => 320,  'BIL_TUNGGAK' => 85,  'BIL_SELESAI' => 230, 'BIL_TIADA_PENYATA' => 5, 'PERATUS_TUNGGAK' => 26.56, 'PERATUS_SELESAI' => 71.88, 'JUMLAH_TUNGGAKAN' => 128400.00],
        ['KAWASAN' => 'AMPANG',   'KOD_JALAN' => '102', 'JALAN_TAMAN_KAMPUNG' => 'JALAN AMPANG 2',      'BIL_RUMAH' => 210,  'BIL_TUNGGAK' => 40,  'BIL_SELESAI' => 165, 'BIL_TIADA_PENYATA' => 5, 'PERATUS_TUNGGAK' => 19.05, 'PERATUS_SELESAI' => 78.57, 'JUMLAH_TUNGGAKAN' =>  60200.00],
        ['KAWASAN' => 'BERANANG', 'KOD_JALAN' => '201', 'JALAN_TAMAN_KAMPUNG' => 'TAMAN SRI BERANANG',  'BIL_RUMAH' => 450,  'BIL_TUNGGAK' => 120, 'BIL_SELESAI' => 325, 'BIL_TIADA_PENYATA' => 5, 'PERATUS_TUNGGAK' => 26.67, 'PERATUS_SELESAI' => 72.22, 'JUMLAH_TUNGGAKAN' => 215600.00],
        ['KAWASAN' => 'CHERAS',   'KOD_JALAN' => '301', 'JALAN_TAMAN_KAMPUNG' => 'KAMPUNG CHERAS BARU', 'BIL_RUMAH' => 180,  'BIL_TUNGGAK' => 60,  'BIL_SELESAI' => 118, 'BIL_TIADA_PENYATA' => 2, 'PERATUS_TUNGGAK' => 33.33, 'PERATUS_SELESAI' => 65.56, 'JUMLAH_TUNGGAKAN' =>  97300.00],
        ['KAWASAN' => 'DENGKIL',  'KOD_JALAN' => '401', 'JALAN_TAMAN_KAMPUNG' => 'TAMAN DENGKIL INDAH', 'BIL_RUMAH' => 275,  'BIL_TUNGGAK' => 55,  'BIL_SELESAI' => 215, 'BIL_TIADA_PENYATA' => 5, 'PERATUS_TUNGGAK' => 20.00, 'PERATUS_SELESAI' => 78.18, 'JUMLAH_TUNGGAKAN' =>  83250.00],
    ],
];
