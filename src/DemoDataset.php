<?php
declare(strict_types=1);

/**
 * Set data contoh rasmi untuk Mod Demo (berdasarkan output sebenar SQL
 * `DETAIL_TGKN_TERIMAAN_IKUT_JALAN.sql` bagi Jalan 142 serta beberapa jalan contoh tambahan)
 * supaya antara muka dashboard boleh diuji apabila sambungan Oracle LAN belum aktif.
 */
final class DemoDataset
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getRows(): array
    {
        return [
            // 20 rekod tepat daripada tangkapan skrin output SQL pengguna (Jalan 142)
            self::row('17013', '142', 'FLAT SIMPANG KUALA 2 & 3', 0, 0, 0, 46, 92, -46),
            self::row('17014', '142', 'FLAT SIMPANG KUALA 2 & 3', 0, 0, 0, 46, 46, 0),
            self::row('17015', '142', 'FLAT SIMPANG KUALA 2 & 3', -46, 46, -92, 0, 92, -92),
            self::row('17016', '142', 'FLAT SIMPANG KUALA 2 & 3', 0, 0, 0, 46, 46, 0),
            self::row('17017', '142', 'FLAT SIMPANG KUALA 2 & 3', 483, 0, 483, 46, 0, 46),
            self::row('17018', '142', 'FLAT SIMPANG KUALA 2 & 3', 92, 92, 0, 46, 23, 23),
            self::row('17019', '142', 'FLAT SIMPANG KUALA 2 & 3', 0, 0, 0, 46, 92, -46),
            self::row('17020', '142', 'FLAT SIMPANG KUALA 2 & 3', 0, 0, 0, 46, 92, -46),
            self::row('17021', '142', 'FLAT SIMPANG KUALA 2 & 3', 256, 160, 96, 46, 0, 46),
            self::row('17022', '142', 'FLAT SIMPANG KUALA 2 & 3', 46, 46, 0, 46, 92, -46),
            self::row('17023', '142', 'FLAT SIMPANG KUALA 2 & 3', 0, 0, 0, 46, 46, 0),
            self::row('17024', '142', 'FLAT SIMPANG KUALA 2 & 3', 0, 0, 0, 46, 46, 0),
            self::row('17025', '142', 'FLAT SIMPANG KUALA 2 & 3', 180, 0, 180, 46, 0, 46),
            self::row('17026', '142', 'FLAT SIMPANG KUALA 2 & 3', 0, 0, 0, 46, 46, 0),
            self::row('17027', '142', 'FLAT SIMPANG KUALA 2 & 3', 0, 0, 0, 46, 23, 23),
            self::row('17028', '142', 'FLAT SIMPANG KUALA 2 & 3', 0, 0, 0, 46, 46, 0),
            self::row('17029', '142', 'FLAT SIMPANG KUALA 2 & 3', 0, 0, 0, 46, 46, 0),
            self::row('17030', '142', 'FLAT SIMPANG KUALA 2 & 3', 0, 0, 0, 46, 46, 0),
            self::row('17031', '142', 'FLAT SIMPANG KUALA 2 & 3', 0, 0, 0, 46, 23, 23),
            self::row('17032', '142', 'FLAT SIMPANG KUALA 2 & 3', 46, 46, 0, 46, 46, 0),

            // Rekod contoh jalan tambahan untuk menguji paparan Semua Jalan & penapis dropdown
            self::row('11201', '105', 'TAMAN BERJAYA', 320, 120, 200, 115, 115, 0),
            self::row('11202', '105', 'TAMAN BERJAYA', 0, 0, 0, 115, 115, 0),
            self::row('11203', '105', 'TAMAN BERJAYA', 640, 0, 640, 115, 0, 115),
            self::row('11204', '105', 'TAMAN BERJAYA', 150, 150, 0, 115, 60, 55),

            self::row('20401', '210', 'JALAN LANGGAR', 980, 400, 580, 240, 0, 240),
            self::row('20402', '210', 'JALAN LANGGAR', 0, 0, 0, 240, 240, 0),
            self::row('20403', '210', 'JALAN LANGGAR', 120, 120, 0, 240, 240, 0),
            self::row('20404', '210', 'JALAN LANGGAR', 450, 150, 300, 240, 120, 120),

            self::row('30801', '308', 'PEKAN SIMPANG EMPAT', 0, 0, 0, 180, 180, 0),
            self::row('30802', '308', 'PEKAN SIMPANG EMPAT', 540, 240, 300, 180, 90, 90),
            self::row('30803', '308', 'PEKAN SIMPANG EMPAT', 720, 0, 720, 180, 0, 180),

            self::row('41501', '415', 'TAMAN MERGONG JAYA', 85, 85, 0, 95, 95, 0),
            self::row('41502', '415', 'TAMAN MERGONG JAYA', 210, 100, 110, 95, 0, 95),
            self::row('41503', '415', 'TAMAN MERGONG JAYA', 0, 0, 0, 95, 190, -95),
        ];
    }

    private static function row(
        string $akaun,
        string $jlkod,
        string $jnama,
        float $bilTgk,
        float $terimaTgk,
        float $bakiTgk,
        float $bilSem,
        float $terimaSem,
        float $bakiSem
    ): array {
        return [
            'PEG_AKAUN' => $akaun,
            'PEG_JLKOD' => $jlkod,
            'JLN_JNAMA' => $jnama,
            'BIL_TUNGGAKAN' => $bilTgk,
            'TERIMA_TUNGGAKAN' => $terimaTgk,
            'BAKI_TUNGGAKAN' => $bakiTgk,
            'BIL_SEMASA' => $bilSem,
            'TERIMA_SEMASA' => $terimaSem,
            'BAKI_SEMASA' => $bakiSem,
        ];
    }
}

