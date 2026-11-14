<?php
declare(strict_types=1);

/**
 * Laporan Contoh 1: Detail Tunggakan & Terimaan Ikut Jalan.
 * Boleh dilancarkan terus melalui tab "Laporan Modul (SQL)" atau "ePBT Report".
 */
return [
    'id' => 'detail-tgkn-terimaan-jalan',
    'module_id' => 'mtkn',
    'oracle_profile' => 'mtkn',
    'title' => 'Detail Tunggakan & Terimaan Ikut Jalan',
    'description' => 'Laporan terperinci bil, terimaan dan baki tunggakan serta semasa mengikut pegangan dan jalan (DETAIL_TGKN_TERIMAAN_IKUT_JALAN.sql).',
    'supports_jlkod' => true,
    'supports_carian' => true,
    'carian_label' => 'No. Akaun (PEG_AKAUN)',
    'default_sort' => 'PEG_AKAUN',
    'cache_key' => 'mtkn_detail_tgkn',
    'jlkod_column' => 'PEG_JLKOD',
    'carian_column' => 'PEG_AKAUN',
    'sql' => "
        SELECT
            g.PEG_AKAUN,
            g.PEG_JLKOD,
            e.jln_jnama AS JLN_JNAMA,
            g.BIL_TUNGGAKAN,
            g.TERIMA_TUNGGAKAN,
            (g.BIL_TUNGGAKAN - g.TERIMA_TUNGGAKAN) AS BAKI_TUNGGAKAN,
            g.BIL_SEMASA,
            g.TERIMA_SEMASA,
            (g.BIL_SEMASA - g.TERIMA_SEMASA) AS BAKI_SEMASA
        FROM (
            SELECT
                c.peg_akaun AS PEG_AKAUN,
                c.peg_jlkod AS PEG_JLKOD,
                (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'12010',d.pyt_amaun,0)) + SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0))) AS BIL_TUNGGAKAN,
                (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'22010',d.pyt_amaun,0)) - SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0))) AS TERIMA_TUNGGAKAN,
                (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11010',d.pyt_amaun,0)) + SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0))) AS BIL_SEMASA,
                (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'21010',d.pyt_amaun,0)) - SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0))) AS TERIMA_SEMASA
            FROM mtkn.hvnduk2 c, mtkn.hlpyt1 d
            WHERE c.jpk_itkod = '11010'
              AND c.peg_akaun = d.pyt_akaun
              AND c.peg_statf != 'H'
              /*FILTER_JLKOD*/
              /*FILTER_CARIAN*/
            GROUP BY c.peg_akaun, c.peg_jlkod
        ) g, spbt.fjalan e
        WHERE g.PEG_JLKOD = e.jln_jlkod
    ",
    'filter_jlkod_clause' => 'AND c.peg_jlkod = :jlkod',
    'filter_carian_clause' => 'AND UPPER(TO_CHAR(c.peg_akaun)) LIKE UPPER(:carian)',
    'columns' => [
        ['key' => 'PEG_AKAUN', 'label' => 'PEG_AKAUN', 'type' => 'text', 'sort_type' => 'numeric'],
        ['key' => 'PEG_JLKOD', 'label' => 'PEG_JLKOD', 'type' => 'text', 'sort_type' => 'numeric'],
        ['key' => 'JLN_JNAMA', 'label' => 'JLN_JNAMA', 'type' => 'text'],
        ['key' => 'BIL_TUNGGAKAN', 'label' => 'BIL_TUNGGAKAN', 'type' => 'currency'],
        ['key' => 'TERIMA_TUNGGAKAN', 'label' => 'TERIMA_TUNGGAKAN', 'type' => 'currency'],
        ['key' => 'BAKI_TUNGGAKAN', 'label' => 'BAKI_TUNGGAKAN', 'type' => 'currency'],
        ['key' => 'BIL_SEMASA', 'label' => 'BIL_SEMASA', 'type' => 'currency'],
        ['key' => 'TERIMA_SEMASA', 'label' => 'TERIMA_SEMASA', 'type' => 'currency'],
        ['key' => 'BAKI_SEMASA', 'label' => 'BAKI_SEMASA', 'type' => 'currency'],
    ],
    'demo_provider' => static fn(): array => DemoDataset::getRows(),
];

