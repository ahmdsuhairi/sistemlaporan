<?php
declare(strict_types=1);

/**
 * Repositori data Tunggakan & Terimaan Ikut Jalan.
 * Dioptimumkan untuk Oracle Database 10g (ROWNUM, ROW_NUMBER() OVER, carian mesra indeks)
 * serta tembolok (cache) ringkas bagi agregat "Semua Jalan" (~90k akaun).
 */
final class TunggakanRepository
{
    private OracleClient $client;
    private bool $demoMode;
    private string $cacheDir;
    private int $cacheTtl;
    private IndexedTableCache $tableCache;

    private const MTKN_BASE_COLUMNS = [
        'PEG_AKAUN',
        'PEG_JLKOD',
        'JLN_JNAMA',
        'BIL_TUNGGAKAN',
        'TERIMA_TUNGGAKAN',
        'BAKI_TUNGGAKAN',
        'BIL_SEMASA',
        'TERIMA_SEMASA',
        'BAKI_SEMASA',
    ];

    private const ALLOWED_SORT_COLUMNS = [
        'PEG_AKAUN' => 'q.PEG_AKAUN',
        'PEG_JLKOD' => 'q.PEG_JLKOD',
        'JLN_JNAMA' => 'q.JLN_JNAMA',
        'BIL_TUNGGAKAN' => 'q.BIL_TUNGGAKAN',
        'TERIMA_TUNGGAKAN' => 'q.TERIMA_TUNGGAKAN',
        'BAKI_TUNGGAKAN' => 'q.BAKI_TUNGGAKAN',
        'BIL_SEMASA' => 'q.BIL_SEMASA',
        'TERIMA_SEMASA' => 'q.TERIMA_SEMASA',
        'BAKI_SEMASA' => 'q.BAKI_SEMASA',
        'JUMLAH_BAKI' => '(q.BAKI_TUNGGAKAN + q.BAKI_SEMASA)',
        'STATUS' => '(q.BAKI_TUNGGAKAN + q.BAKI_SEMASA)',
    ];

    private const CACHE_COL_TYPES = [
        'PEG_AKAUN' => 'numeric',
        'PEG_JLKOD' => 'numeric',
        'JLN_JNAMA' => 'string',
        'BIL_TUNGGAKAN' => 'numeric',
        'TERIMA_TUNGGAKAN' => 'numeric',
        'BAKI_TUNGGAKAN' => 'numeric',
        'BIL_SEMASA' => 'numeric',
        'TERIMA_SEMASA' => 'numeric',
        'BAKI_SEMASA' => 'numeric',
        'JUMLAH_BAKI' => 'numeric',
        'STATUS' => 'numeric',
    ];

    public function __construct(
        OracleClient $client,
        bool $demoMode = false,
        ?string $cacheDir = null,
        int $cacheTtl = 3600
    ) {
        $this->client = $client;
        $this->demoMode = $demoMode;
        $this->cacheDir = $cacheDir ?? (dirname(__DIR__) . '/cache');
        $this->cacheTtl = max(60, $cacheTtl);
        $this->tableCache = new IndexedTableCache($this->cacheDir, $this->cacheTtl);
    }

    public function isDemoMode(): bool
    {
        return $this->demoMode;
    }

    public function clearCache(): void
    {
        if (is_dir($this->cacheDir)) {
            foreach (glob($this->cacheDir . '/*.json') ?: [] as $file) {
                @unlink($file);
            }
        }
        $this->tableCache->clearAll();
    }

    /**
     * Senarai Jalan unik terus daripada `spbt.fjalan` (~0.3s untuk >1,000 jalan)
     * dengan tembolok 1 jam bagi kelajuan muatan antara muka.
     *
     * @return array<int, array{PEG_JLKOD: string, JLN_JNAMA: string}>
     */
    public function getJalanList(bool $forceRefresh = false): array
    {
        if ($this->demoMode) {
            $map = [];
            foreach (DemoDataset::getRows() as $row) {
                $map[$row['PEG_JLKOD']] = [
                    'PEG_JLKOD' => (string) $row['PEG_JLKOD'],
                    'JLN_JNAMA' => (string) $row['JLN_JNAMA'],
                ];
            }
            ksort($map, SORT_NATURAL);
            return array_values($map);
        }

        if (!$forceRefresh) {
            $cached = $this->readCache('jalan_list', 3600);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $sql = "
            SELECT
                TO_CHAR(e.jln_jlkod) AS PEG_JLKOD,
                e.jln_jnama AS JLN_JNAMA
            FROM spbt.fjalan e
            ORDER BY e.jln_jnama, e.jln_jlkod
        ";

        $rows = $this->client->query($sql);
        $list = array_map(static fn(array $r): array => [
            'PEG_JLKOD' => (string) ($r['PEG_JLKOD'] ?? ''),
            'JLN_JNAMA' => (string) ($r['JLN_JNAMA'] ?? ''),
        ], $rows);

        $this->writeCache('jalan_list', $list);
        return $list;
    }

    /**
     * Data lengkap Dashboard Utama (KPI, siri carta prestasi, dan profil akaun jika dicari).
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(array $filters, bool $forceRefresh = false): array
    {
        $clean = $this->normalizeFilters($filters);

        if ($this->demoMode) {
            $rows = $this->filterDemoRows($clean);
            $summary = $this->computeSummaryFromRows($rows);
            $series = $this->buildDemoPrestasiSeries($rows, $clean);
            $accountProfile = $clean['akaun'] !== '' ? ($rows[0] ?? null) : null;

            return [
                'summary' => $summary,
                'series' => $series,
                'account_profile' => $accountProfile,
                'matched_accounts' => $clean['akaun'] !== '' ? array_slice($rows, 0, 5) : [],
            ];
        }

        // Laluan pantas 1-imbasan + cache bagi "Semua Jalan"
        if ($clean['jlkod'] === '' && $clean['akaun'] === '') {
            $jalanAgg = $this->getAllJalanAggregated($clean['status'], $forceRefresh);
            $summary = $this->computeSummaryFromJalanAgg($jalanAgg);
            $top8 = array_slice($jalanAgg, 0, 8);

            return [
                'summary' => $summary,
                'series' => $this->formatSeriesRows($top8, 'jalan'),
                'account_profile' => null,
                'matched_accounts' => [],
            ];
        }

        // Laluan 1-imbasan bagi 1 Jalan khusus tanpa carian akaun (~2s untuk Jalan 142)
        if ($clean['jlkod'] !== '' && $clean['akaun'] === '') {
            $rows = $this->fetchSingleJalanRows($clean);
            $summary = $this->computeSummaryFromRows($rows);
            $seriesRows = array_slice($rows, 0, 10);

            return [
                'summary' => $summary,
                'series' => $this->formatSeriesRows($seriesRows, 'akaun'),
                'account_profile' => null,
                'matched_accounts' => [],
            ];
        }

        // Carian khusus No. Akaun (PEG_AKAUN)
        $params = [];
        $baseSql = $this->buildFilteredAccountSubquery($clean, $params);
        $matched = array_map(
            [$this, 'normalizeAccountRow'],
            $this->client->query("SELECT * FROM ( {$baseSql} ORDER BY c.peg_akaun ASC ) WHERE ROWNUM <= 25", $params)
        );

        return [
            'summary' => $this->computeSummaryFromRows($matched),
            'series' => $this->formatSeriesRows(array_slice($matched, 0, 10), 'akaun'),
            'account_profile' => $matched[0] ?? null,
            'matched_accounts' => array_slice($matched, 0, 5),
        ];
    }

    /**
     * Data tab Analisis Jalan: 4 KPI, carta bar taburan, carta donat, dan Top 5 Akaun.
     *
     * @return array<string, mixed>
     */
    public function getAnalisisData(array $filters, bool $forceRefresh = false): array
    {
        $clean = $this->normalizeFilters($filters);

        if ($this->demoMode) {
            $rows = $this->filterDemoRows($clean);
            return $this->buildAnalisisFromAccountRows($rows);
        }

        // Laluan pantas + cache bagi "Semua Jalan"
        if ($clean['jlkod'] === '' && $clean['akaun'] === '') {
            $jalanAgg = $this->getAllJalanAggregated($clean['status'], $forceRefresh);
            $summary = $this->computeSummaryFromJalanAgg($jalanAgg);
            $buckets = $this->computeBucketsFromJalanAgg($jalanAgg);
            $top5 = $this->getAllJalanTop5($clean['status'], $forceRefresh);

            return [
                'summary' => $summary,
                'buckets' => $buckets,
                'top_tunggakan' => $top5['top_tunggakan'],
                'top_kutipan' => $top5['top_kutipan'],
            ];
        }

        // Laluan 1-imbasan bagi 1 Jalan khusus
        if ($clean['jlkod'] !== '' && $clean['akaun'] === '') {
            $rows = $this->fetchSingleJalanRows($clean);
            return $this->buildAnalisisFromAccountRows($rows);
        }

        // Carian No. Akaun
        $params = [];
        $baseSql = $this->buildFilteredAccountSubquery($clean, $params);
        $rows = array_map(
            [$this, 'normalizeAccountRow'],
            $this->client->query("SELECT * FROM ( {$baseSql} ORDER BY c.peg_akaun ASC ) WHERE ROWNUM <= 200", $params)
        );
        return $this->buildAnalisisFromAccountRows($rows);
    }

    /**
     * Senarai Akaun berhalaman menggunakan tembolok jadual berindeks setempat
     * supaya penukaran halaman ("Seterusnya") dan pengisihan semua tajuk berjalan pantas.
     *
     * @return array<string, mixed>
     */
    public function getSenaraiAkaun(
        array $filters,
        int $page = 1,
        int $perPage = 25,
        string $sortBy = 'PEG_AKAUN',
        string $sortDir = 'ASC',
        bool $forceRefresh = false
    ): array {
        $startedAt = microtime(true);
        $clean = $this->normalizeFilters($filters);
        $page = max(1, $page);
        $perPage = max(5, min(200, $perPage));
        $reqSortKey = strtoupper(trim($sortBy));
        $sortKey = array_key_exists($reqSortKey, self::ALLOWED_SORT_COLUMNS) ? $reqSortKey : 'PEG_AKAUN';
        $direction = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        if ($this->demoMode) {
            $rows = $this->filterDemoRows($clean);
            $this->sortDemoAccountRows($rows, $sortKey, $direction);

            $total = count($rows);
            $offset = ($page - 1) * $perPage;
            $slice = array_slice($rows, $offset, $perPage);
            $numbered = [];
            foreach ($slice as $idx => $row) {
                $row['ROW_NUM'] = $offset + $idx + 1;
                $numbered[] = $this->normalizeAccountRow($row);
            }

            return [
                'rows' => $numbered,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
                'sort_by' => $sortKey,
                'sort_dir' => $direction,
                'cache_hit' => true,
                'elapsed_ms' => round((microtime(true) - $startedAt) * 1000, 1),
            ];
        }

        [$datasetKey, $filterKey, $predicate, $rebuilt] = $this->prepareAccountTableCache($clean, $forceRefresh);
        $result = $this->tableCache->queryPage($datasetKey, [
            'page' => $page,
            'per_page' => $perPage,
            'sort_by' => $sortKey,
            'sort_dir' => $direction,
            'default_sort' => 'PEG_AKAUN',
            'col_types' => self::CACHE_COL_TYPES,
            'virtual_cols' => $this->getVirtualAccountColumns(),
            'filter_key' => $filterKey,
            'filter_predicate' => $predicate,
        ]);

        $result['rows'] = array_map([$this, 'normalizeAccountRow'], $result['rows']);
        $result['sort_by'] = $sortKey;
        $result['sort_dir'] = $direction;
        $result['cache_hit'] = !$rebuilt;
        $result['elapsed_ms'] = round((microtime(true) - $startedAt) * 1000, 1);
        return $result;
    }

    /**
     * Menstrim eksport CSV terus ke output mengikut susunan kolum semasa.
     */
    public function streamCsvExport(
        array $filters,
        callable $writeRow,
        string $sortBy = 'PEG_AKAUN',
        string $sortDir = 'ASC'
    ): void {
        $clean = $this->normalizeFilters($filters);
        $reqSortKey = strtoupper(trim($sortBy));
        $sortKey = array_key_exists($reqSortKey, self::ALLOWED_SORT_COLUMNS) ? $reqSortKey : 'PEG_AKAUN';
        $direction = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        if ($this->demoMode) {
            $rows = $this->filterDemoRows($clean);
            $this->sortDemoAccountRows($rows, $sortKey, $direction);
            foreach ($rows as $idx => $row) {
                $norm = $this->normalizeAccountRow($row);
                $norm['ROW_NUM'] = $idx + 1;
                $writeRow($norm);
            }
            return;
        }

        [$datasetKey, $filterKey, $predicate] = $this->prepareAccountTableCache($clean, false);
        $this->tableCache->streamSortedRows($datasetKey, [
            'sort_by' => $sortKey,
            'sort_dir' => $direction,
            'default_sort' => 'PEG_AKAUN',
            'col_types' => self::CACHE_COL_TYPES,
            'virtual_cols' => $this->getVirtualAccountColumns(),
            'filter_key' => $filterKey,
            'filter_predicate' => $predicate,
        ], function (array $row) use ($writeRow): void {
            $writeRow($this->normalizeAccountRow($row));
        });
    }

    /**
     * Memilih & membina cache berindeks yang paling pantas berdasarkan penapis aktif.
     *
     * @return array{0: string, 1: string, 2: ?callable, 3: bool}
     */
    private function prepareAccountTableCache(array $clean, bool $forceRefresh): array
    {
        $baseKey = 'mtkn_detail_tgkn';
        $fullDatasetKey = $baseKey . '_all';
        $hasNarrowFilter = ($clean['jlkod'] !== '' || $clean['akaun'] !== '');

        if ((!$forceRefresh && $this->tableCache->hasFresh($fullDatasetKey)) || !$hasNarrowFilter) {
            $rebuilt = $this->tableCache->ensureBuilt(
                $fullDatasetKey,
                self::MTKN_BASE_COLUMNS,
                function (callable $emit): void {
                    $params = [];
                    $sql = $this->buildFilteredAccountSubquery([
                        'jlkod' => '',
                        'akaun' => '',
                        'status' => 'all',
                    ], $params);
                    $this->client->streamQuery($sql, $params, $emit);
                },
                $forceRefresh
            );

            $filterKey = (!$hasNarrowFilter && $clean['status'] === 'all')
                ? 'all'
                : md5(json_encode($clean));

            $predicate = $filterKey === 'all' ? null : $this->buildAccountFilterPredicate($clean);
            return [$fullDatasetKey, $filterKey, $predicate, $rebuilt];
        }

        // Laluan pantas penapis khusus apabila set penuh belum dibina: imbas hanya jalan/akaun berkenaan (~2s)
        $narrowKey = $baseKey . '_' . md5($clean['jlkod'] . '|' . $clean['akaun']);
        $rebuilt = $this->tableCache->ensureBuilt(
            $narrowKey,
            self::MTKN_BASE_COLUMNS,
            function (callable $emit) use ($clean): void {
                $params = [];
                $sql = $this->buildFilteredAccountSubquery([
                    'jlkod' => $clean['jlkod'],
                    'akaun' => $clean['akaun'],
                    'status' => 'all',
                ], $params);
                $this->client->streamQuery($sql, $params, $emit);
            },
            $forceRefresh
        );

        $filterKey = $clean['status'] === 'all' ? 'all' : ('st_' . $clean['status']);
        $predicate = $filterKey === 'all'
            ? null
            : $this->buildAccountFilterPredicate([
                'jlkod' => '',
                'akaun' => '',
                'status' => $clean['status'],
            ]);

        return [$narrowKey, $filterKey, $predicate, $rebuilt];
    }

    private function buildAccountFilterPredicate(array $clean): callable
    {
        $jlkodTarget = $clean['jlkod'];
        $akaunTarget = $clean['akaun'];
        $statusTarget = $clean['status'];

        return static function (array $row) use ($jlkodTarget, $akaunTarget, $statusTarget): bool {
            if ($jlkodTarget !== '' && trim((string) ($row['PEG_JLKOD'] ?? '')) !== $jlkodTarget) {
                return false;
            }
            if ($akaunTarget !== '' && stripos((string) ($row['PEG_AKAUN'] ?? ''), $akaunTarget) === false) {
                return false;
            }
            if ($statusTarget !== 'all') {
                $net = round((float) ($row['BAKI_TUNGGAKAN'] ?? 0) + (float) ($row['BAKI_SEMASA'] ?? 0), 2);
                if ($statusTarget === 'tertunggak' && $net <= 0.0) {
                    return false;
                }
                if ($statusTarget === 'selesai' && $net != 0.0) {
                    return false;
                }
                if ($statusTarget === 'lebih_bayar' && $net >= 0.0) {
                    return false;
                }
            }
            return true;
        };
    }

    /**
     * @return array<string, callable>
     */
    private function getVirtualAccountColumns(): array
    {
        $netCalc = static fn(array $r): float =>
            (float) ($r['BAKI_TUNGGAKAN'] ?? 0) + (float) ($r['BAKI_SEMASA'] ?? 0);

        return [
            'JUMLAH_BAKI' => $netCalc,
            'STATUS' => $netCalc,
        ];
    }

    private function sortDemoAccountRows(array &$rows, string $sortBy, string $direction): void
    {
        $key = strtoupper($sortBy);
        usort($rows, static function (array $a, array $b) use ($key, $direction): int {
            $isNet = ($key === 'JUMLAH_BAKI' || $key === 'STATUS');
            $valA = $isNet ? ($a['BAKI_TUNGGAKAN'] + $a['BAKI_SEMASA']) : ($a[$key] ?? $a['PEG_AKAUN']);
            $valB = $isNet ? ($b['BAKI_TUNGGAKAN'] + $b['BAKI_SEMASA']) : ($b[$key] ?? $b['PEG_AKAUN']);
            $cmp = is_numeric($valA) && is_numeric($valB)
                ? ((float) $valA <=> (float) $valB)
                : strnatcasecmp((string) $valA, (string) $valB);
            return $direction === 'DESC' ? -$cmp : $cmp;
        });
    }

    private function fetchSingleJalanRows(array $clean): array
    {
        $params = [];
        $baseSql = $this->buildFilteredAccountSubquery($clean, $params);
        $sql = "SELECT q.* FROM ( {$baseSql} ) q ORDER BY q.PEG_AKAUN ASC";
        return array_map([$this, 'normalizeAccountRow'], $this->client->query($sql, $params));
    }

    /**
     * Satu imbasan Oracle mengikut Jalan (~1,080 baris maks) + cache 10 minit.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getAllJalanAggregated(string $status, bool $forceRefresh): array
    {
        $cacheKey = 'all_jalan_' . $status;
        if (!$forceRefresh) {
            $cached = $this->readCache($cacheKey, $this->cacheTtl);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $params = [];
        $baseSql = $this->buildFilteredAccountSubquery([
            'jlkod' => '',
            'akaun' => '',
            'status' => $status,
        ], $params);

        $sql = "
            SELECT
                TO_CHAR(s.PEG_JLKOD) AS LABEL_KOD,
                s.JLN_JNAMA AS LABEL_NAMA,
                COUNT(*) AS TOTAL_AKAUN,
                NVL(SUM(s.BIL_TUNGGAKAN), 0) AS BIL_TUNGGAKAN,
                NVL(SUM(s.TERIMA_TUNGGAKAN), 0) AS TERIMA_TUNGGAKAN,
                NVL(SUM(s.BAKI_TUNGGAKAN), 0) AS BAKI_TUNGGAKAN,
                NVL(SUM(s.BIL_SEMASA), 0) AS BIL_SEMASA,
                NVL(SUM(s.TERIMA_SEMASA), 0) AS TERIMA_SEMASA,
                NVL(SUM(s.BAKI_SEMASA), 0) AS BAKI_SEMASA,
                NVL(SUM(CASE WHEN (s.BAKI_TUNGGAKAN + s.BAKI_SEMASA) <= 0 THEN 1 ELSE 0 END), 0) AS AKAUN_BERSIH,
                NVL(SUM(CASE WHEN (s.BAKI_TUNGGAKAN + s.BAKI_SEMASA) > 0 THEN 1 ELSE 0 END), 0) AS AKAUN_TERTUNGGAK,
                NVL(SUM(CASE WHEN (s.BAKI_TUNGGAKAN + s.BAKI_SEMASA) < 0 THEN 1 ELSE 0 END), 0) AS AKAUN_LEBIH_BAYAR,
                NVL(SUM(CASE WHEN (s.BAKI_TUNGGAKAN + s.BAKI_SEMASA) < 0 THEN 1 ELSE 0 END), 0) AS BKT_LEBIH,
                NVL(SUM(CASE WHEN (s.BAKI_TUNGGAKAN + s.BAKI_SEMASA) = 0 THEN 1 ELSE 0 END), 0) AS BKT_SIFAR,
                NVL(SUM(CASE WHEN (s.BAKI_TUNGGAKAN + s.BAKI_SEMASA) > 0 AND (s.BAKI_TUNGGAKAN + s.BAKI_SEMASA) <= 100 THEN 1 ELSE 0 END), 0) AS BKT_100,
                NVL(SUM(CASE WHEN (s.BAKI_TUNGGAKAN + s.BAKI_SEMASA) > 100 AND (s.BAKI_TUNGGAKAN + s.BAKI_SEMASA) <= 300 THEN 1 ELSE 0 END), 0) AS BKT_300,
                NVL(SUM(CASE WHEN (s.BAKI_TUNGGAKAN + s.BAKI_SEMASA) > 300 AND (s.BAKI_TUNGGAKAN + s.BAKI_SEMASA) <= 500 THEN 1 ELSE 0 END), 0) AS BKT_500,
                NVL(SUM(CASE WHEN (s.BAKI_TUNGGAKAN + s.BAKI_SEMASA) > 500 THEN 1 ELSE 0 END), 0) AS BKT_ATAS500
            FROM ( {$baseSql} ) s
            GROUP BY s.PEG_JLKOD, s.JLN_JNAMA
            ORDER BY (NVL(SUM(s.BIL_TUNGGAKAN), 0) + NVL(SUM(s.BIL_SEMASA), 0)) DESC
        ";

        $rows = $this->client->query($sql, $params);
        $this->writeCache($cacheKey, $rows);
        return $rows;
    }

    /**
     * Satu imbasan Oracle menggunakan ROW_NUMBER() OVER untuk Top 5 Tunggakan + Top 5 Kutipan serentak.
     *
     * @return array{top_tunggakan: array, top_kutipan: array}
     */
    private function getAllJalanTop5(string $status, bool $forceRefresh): array
    {
        $cacheKey = 'top5_all_' . $status;
        if (!$forceRefresh) {
            $cached = $this->readCache($cacheKey, $this->cacheTtl);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $params = [];
        $baseSql = $this->buildFilteredAccountSubquery([
            'jlkod' => '',
            'akaun' => '',
            'status' => $status,
        ], $params);

        $topTgkSql = "
            SELECT * FROM (
                SELECT q.* FROM ( {$baseSql} ) q
                ORDER BY q.BAKI_TUNGGAKAN DESC, (q.BAKI_TUNGGAKAN + q.BAKI_SEMASA) DESC, q.PEG_AKAUN ASC
            ) WHERE ROWNUM <= 5
        ";
        $topTgk = $this->client->query($topTgkSql, $params);

        $topKutSql = "
            SELECT * FROM (
                SELECT q.* FROM ( {$baseSql} ) q
                ORDER BY (q.TERIMA_TUNGGAKAN + q.TERIMA_SEMASA) DESC, q.PEG_AKAUN ASC
            ) WHERE ROWNUM <= 5
        ";
        $topKut = $this->client->query($topKutSql, $params);

        $result = [
            'top_tunggakan' => array_map([$this, 'normalizeAccountRow'], $topTgk),
            'top_kutipan' => array_map([$this, 'normalizeAccountRow'], $topKut),
        ];

        $this->writeCache($cacheKey, $result);
        return $result;
    }

    private function buildAnalisisFromAccountRows(array $rows): array
    {
        $summary = $this->computeSummaryFromRows($rows);
        $buckets = $this->computeBucketsFromRows($rows);

        $topTunggakan = $rows;
        usort($topTunggakan, static function (array $a, array $b): int {
            $diff = ($b['BAKI_TUNGGAKAN'] <=> $a['BAKI_TUNGGAKAN']);
            if ($diff !== 0) {
                return $diff;
            }
            return ($b['BAKI_TUNGGAKAN'] + $b['BAKI_SEMASA']) <=> ($a['BAKI_TUNGGAKAN'] + $a['BAKI_SEMASA']);
        });

        $topKutipan = $rows;
        usort($topKutipan, static function (array $a, array $b): int {
            $sumA = $a['TERIMA_TUNGGAKAN'] + $a['TERIMA_SEMASA'];
            $sumB = $b['TERIMA_TUNGGAKAN'] + $b['TERIMA_SEMASA'];
            return $sumB <=> $sumA;
        });

        return [
            'summary' => $summary,
            'buckets' => $buckets,
            'top_tunggakan' => array_slice($topTunggakan, 0, 5),
            'top_kutipan' => array_slice($topKutipan, 0, 5),
        ];
    }

    private function computeSummaryFromJalanAgg(array $jalanRows): array
    {
        $raw = [
            'TOTAL_AKAUN' => 0,
            'BIL_TUNGGAKAN' => 0,
            'TERIMA_TUNGGAKAN' => 0,
            'BAKI_TUNGGAKAN' => 0,
            'BIL_SEMASA' => 0,
            'TERIMA_SEMASA' => 0,
            'BAKI_SEMASA' => 0,
            'AKAUN_BERSIH' => 0,
            'AKAUN_TERTUNGGAK' => 0,
            'AKAUN_LEBIH_BAYAR' => 0,
        ];

        foreach ($jalanRows as $r) {
            $raw['TOTAL_AKAUN'] += (int) ($r['TOTAL_AKAUN'] ?? 0);
            $raw['BIL_TUNGGAKAN'] += (float) ($r['BIL_TUNGGAKAN'] ?? 0);
            $raw['TERIMA_TUNGGAKAN'] += (float) ($r['TERIMA_TUNGGAKAN'] ?? 0);
            $raw['BAKI_TUNGGAKAN'] += (float) ($r['BAKI_TUNGGAKAN'] ?? 0);
            $raw['BIL_SEMASA'] += (float) ($r['BIL_SEMASA'] ?? 0);
            $raw['TERIMA_SEMASA'] += (float) ($r['TERIMA_SEMASA'] ?? 0);
            $raw['BAKI_SEMASA'] += (float) ($r['BAKI_SEMASA'] ?? 0);
            $raw['AKAUN_BERSIH'] += (int) ($r['AKAUN_BERSIH'] ?? 0);
            $raw['AKAUN_TERTUNGGAK'] += (int) ($r['AKAUN_TERTUNGGAK'] ?? 0);
            $raw['AKAUN_LEBIH_BAYAR'] += (int) ($r['AKAUN_LEBIH_BAYAR'] ?? 0);
        }

        return $this->formatSummaryRecord($raw);
    }

    private function computeBucketsFromJalanAgg(array $jalanRows): array
    {
        $sum = ['lebih' => 0, 'sifar' => 0, 'b100' => 0, 'b300' => 0, 'b500' => 0, 'atas500' => 0];
        foreach ($jalanRows as $r) {
            $sum['lebih'] += (int) ($r['BKT_LEBIH'] ?? 0);
            $sum['sifar'] += (int) ($r['BKT_SIFAR'] ?? 0);
            $sum['b100'] += (int) ($r['BKT_100'] ?? 0);
            $sum['b300'] += (int) ($r['BKT_300'] ?? 0);
            $sum['b500'] += (int) ($r['BKT_500'] ?? 0);
            $sum['atas500'] += (int) ($r['BKT_ATAS500'] ?? 0);
        }

        return [
            ['label' => 'Lebih Bayar', 'count' => $sum['lebih'], 'color' => '#06B6D4'],
            ['label' => 'Selesai (RM0)', 'count' => $sum['sifar'], 'color' => '#10B981'],
            ['label' => 'RM1 - RM100', 'count' => $sum['b100'], 'color' => '#3B82F6'],
            ['label' => 'RM101 - RM300', 'count' => $sum['b300'], 'color' => '#F59E0B'],
            ['label' => 'RM301 - RM500', 'count' => $sum['b500'], 'color' => '#F97316'],
            ['label' => '> RM500', 'count' => $sum['atas500'], 'color' => '#EF4444'],
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        $status = (string) ($filters['status'] ?? 'all');
        if (!in_array($status, ['all', 'tertunggak', 'selesai', 'lebih_bayar'], true)) {
            $status = 'all';
        }

        return [
            'jlkod' => trim((string) ($filters['jlkod'] ?? '')),
            'akaun' => trim((string) ($filters['akaun'] ?? '')),
            'status' => $status,
        ];
    }

    /**
     * Kuiri berparameter mesra indeks Oracle berdasarkan `DETAIL_TGKN_TERIMAAN_IKUT_JALAN.sql`.
     */
    private function buildFilteredAccountSubquery(array $clean, array &$params): string
    {
        $whereExtra = '';
        if ($clean['jlkod'] !== '') {
            $whereExtra .= " AND c.peg_jlkod = :jlkod ";
            $params['jlkod'] = ctype_digit($clean['jlkod']) ? (int) $clean['jlkod'] : $clean['jlkod'];
        }
        if ($clean['akaun'] !== '') {
            $whereExtra .= " AND UPPER(TO_CHAR(c.peg_akaun)) LIKE UPPER(:akaun) ";
            $params['akaun'] = '%' . $clean['akaun'] . '%';
        }

        $groupedSql = "
            SELECT
                ag.PEG_AKAUN,
                ag.PEG_JLKOD,
                e.jln_jnama AS JLN_JNAMA,
                ag.BIL_TUNGGAKAN,
                ag.TERIMA_TUNGGAKAN,
                (ag.BIL_TUNGGAKAN - ag.TERIMA_TUNGGAKAN) AS BAKI_TUNGGAKAN,
                ag.BIL_SEMASA,
                ag.TERIMA_SEMASA,
                (ag.BIL_SEMASA - ag.TERIMA_SEMASA) AS BAKI_SEMASA
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
                  {$whereExtra}
                GROUP BY c.peg_akaun, c.peg_jlkod
            ) ag, spbt.fjalan e
            WHERE ag.PEG_JLKOD = e.jln_jlkod
        ";

        if ($clean['status'] === 'all') {
            return $groupedSql;
        }

        $statusWhere = match ($clean['status']) {
            'tertunggak' => '(g.BAKI_TUNGGAKAN + g.BAKI_SEMASA) > 0',
            'selesai' => '(g.BAKI_TUNGGAKAN + g.BAKI_SEMASA) = 0',
            'lebih_bayar' => '(g.BAKI_TUNGGAKAN + g.BAKI_SEMASA) < 0',
            default => '1=1',
        };

        return "SELECT g.* FROM ( {$groupedSql} ) g WHERE {$statusWhere}";
    }

    private function formatSummaryRecord(array $raw): array
    {
        $bilTgk = (float) ($raw['BIL_TUNGGAKAN'] ?? 0);
        $terimaTgk = (float) ($raw['TERIMA_TUNGGAKAN'] ?? 0);
        $bakiTgk = (float) ($raw['BAKI_TUNGGAKAN'] ?? 0);
        $bilSem = (float) ($raw['BIL_SEMASA'] ?? 0);
        $terimaSem = (float) ($raw['TERIMA_SEMASA'] ?? 0);
        $bakiSem = (float) ($raw['BAKI_SEMASA'] ?? 0);

        $jumlahBil = $bilTgk + $bilSem;
        $jumlahTerima = $terimaTgk + $terimaSem;
        $jumlahBaki = $bakiTgk + $bakiSem;

        return [
            'total_akaun' => (int) ($raw['TOTAL_AKAUN'] ?? 0),
            'bil_tunggakan' => round($bilTgk, 2),
            'terima_tunggakan' => round($terimaTgk, 2),
            'baki_tunggakan' => round($bakiTgk, 2),
            'bil_semasa' => round($bilSem, 2),
            'terima_semasa' => round($terimaSem, 2),
            'baki_semasa' => round($bakiSem, 2),
            'jumlah_bil' => round($jumlahBil, 2),
            'jumlah_terima' => round($jumlahTerima, 2),
            'jumlah_baki' => round($jumlahBaki, 2),
            'kadar_kutipan_tunggakan' => $this->safePercent($terimaTgk, $bilTgk),
            'kadar_kutipan_semasa' => $this->safePercent($terimaSem, $bilSem),
            'kadar_kutipan_keseluruhan' => $this->safePercent($jumlahTerima, $jumlahBil),
            'akaun_bersih' => (int) ($raw['AKAUN_BERSIH'] ?? 0),
            'akaun_tertunggak' => (int) ($raw['AKAUN_TERTUNGGAK'] ?? 0),
            'akaun_lebih_bayar' => (int) ($raw['AKAUN_LEBIH_BAYAR'] ?? 0),
        ];
    }

    private function safePercent(float $received, float $billed): float
    {
        if ($billed <= 0.0) {
            return $received > 0 ? 100.0 : 0.0;
        }
        return round(($received / $billed) * 100, 1);
    }

    private function normalizeAccountRow(array $row): array
    {
        $bilTgk = round((float) ($row['BIL_TUNGGAKAN'] ?? 0), 2);
        $terimaTgk = round((float) ($row['TERIMA_TUNGGAKAN'] ?? 0), 2);
        $bakiTgk = round((float) ($row['BAKI_TUNGGAKAN'] ?? 0), 2);
        $bilSem = round((float) ($row['BIL_SEMASA'] ?? 0), 2);
        $terimaSem = round((float) ($row['TERIMA_SEMASA'] ?? 0), 2);
        $bakiSem = round((float) ($row['BAKI_SEMASA'] ?? 0), 2);
        $jumlahBaki = round($bakiTgk + $bakiSem, 2);

        $status = 'Selesai';
        if ($jumlahBaki > 0) {
            $status = 'Tertunggak';
        } elseif ($jumlahBaki < 0) {
            $status = 'Lebih Bayar';
        }

        return [
            'ROW_NUM' => isset($row['ROW_NUM']) ? (int) $row['ROW_NUM'] : null,
            'PEG_AKAUN' => (string) ($row['PEG_AKAUN'] ?? ''),
            'PEG_JLKOD' => (string) ($row['PEG_JLKOD'] ?? ''),
            'JLN_JNAMA' => (string) ($row['JLN_JNAMA'] ?? ''),
            'BIL_TUNGGAKAN' => $bilTgk,
            'TERIMA_TUNGGAKAN' => $terimaTgk,
            'BAKI_TUNGGAKAN' => $bakiTgk,
            'BIL_SEMASA' => $bilSem,
            'TERIMA_SEMASA' => $terimaSem,
            'BAKI_SEMASA' => $bakiSem,
            'JUMLAH_BAKI' => $jumlahBaki,
            'STATUS' => $status,
        ];
    }

    private function filterDemoRows(array $clean): array
    {
        $rows = DemoDataset::getRows();
        return array_values(array_filter($rows, static function (array $row) use ($clean): bool {
            if ($clean['jlkod'] !== '' && (string) $row['PEG_JLKOD'] !== $clean['jlkod']) {
                return false;
            }
            if ($clean['akaun'] !== '' && stripos((string) $row['PEG_AKAUN'], $clean['akaun']) === false) {
                return false;
            }
            $net = (float) $row['BAKI_TUNGGAKAN'] + (float) $row['BAKI_SEMASA'];
            if ($clean['status'] === 'tertunggak' && $net <= 0) {
                return false;
            }
            if ($clean['status'] === 'selesai' && $net != 0.0) {
                return false;
            }
            if ($clean['status'] === 'lebih_bayar' && $net >= 0) {
                return false;
            }
            return true;
        }));
    }

    private function computeSummaryFromRows(array $rows): array
    {
        $raw = [
            'TOTAL_AKAUN' => count($rows),
            'BIL_TUNGGAKAN' => 0,
            'TERIMA_TUNGGAKAN' => 0,
            'BAKI_TUNGGAKAN' => 0,
            'BIL_SEMASA' => 0,
            'TERIMA_SEMASA' => 0,
            'BAKI_SEMASA' => 0,
            'AKAUN_BERSIH' => 0,
            'AKAUN_TERTUNGGAK' => 0,
            'AKAUN_LEBIH_BAYAR' => 0,
        ];

        foreach ($rows as $row) {
            $raw['BIL_TUNGGAKAN'] += (float) $row['BIL_TUNGGAKAN'];
            $raw['TERIMA_TUNGGAKAN'] += (float) $row['TERIMA_TUNGGAKAN'];
            $raw['BAKI_TUNGGAKAN'] += (float) $row['BAKI_TUNGGAKAN'];
            $raw['BIL_SEMASA'] += (float) $row['BIL_SEMASA'];
            $raw['TERIMA_SEMASA'] += (float) $row['TERIMA_SEMASA'];
            $raw['BAKI_SEMASA'] += (float) $row['BAKI_SEMASA'];

            $net = (float) $row['BAKI_TUNGGAKAN'] + (float) $row['BAKI_SEMASA'];
            if ($net <= 0) {
                $raw['AKAUN_BERSIH']++;
            } else {
                $raw['AKAUN_TERTUNGGAK']++;
            }
            if ($net < 0) {
                $raw['AKAUN_LEBIH_BAYAR']++;
            }
        }

        return $this->formatSummaryRecord($raw);
    }

    private function computeBucketsFromRows(array $rows): array
    {
        $counts = ['lebih' => 0, 'sifar' => 0, 'b100' => 0, 'b300' => 0, 'b500' => 0, 'atas500' => 0];

        foreach ($rows as $row) {
            $net = (float) $row['BAKI_TUNGGAKAN'] + (float) $row['BAKI_SEMASA'];
            if ($net < 0) {
                $counts['lebih']++;
            } elseif ($net == 0.0) {
                $counts['sifar']++;
            } elseif ($net <= 100) {
                $counts['b100']++;
            } elseif ($net <= 300) {
                $counts['b300']++;
            } elseif ($net <= 500) {
                $counts['b500']++;
            } else {
                $counts['atas500']++;
            }
        }

        return [
            ['label' => 'Lebih Bayar', 'count' => $counts['lebih'], 'color' => '#06B6D4'],
            ['label' => 'Selesai (RM0)', 'count' => $counts['sifar'], 'color' => '#10B981'],
            ['label' => 'RM1 - RM100', 'count' => $counts['b100'], 'color' => '#3B82F6'],
            ['label' => 'RM101 - RM300', 'count' => $counts['b300'], 'color' => '#F59E0B'],
            ['label' => 'RM301 - RM500', 'count' => $counts['b500'], 'color' => '#F97316'],
            ['label' => '> RM500', 'count' => $counts['atas500'], 'color' => '#EF4444'],
        ];
    }

    private function buildDemoPrestasiSeries(array $rows, array $clean): array
    {
        if ($clean['jlkod'] === '' && $clean['akaun'] === '') {
            $byJalan = [];
            foreach ($rows as $row) {
                $kod = (string) $row['PEG_JLKOD'];
                if (!isset($byJalan[$kod])) {
                    $byJalan[$kod] = [
                        'LABEL_KOD' => $kod,
                        'LABEL_NAMA' => (string) $row['JLN_JNAMA'],
                        'BIL_TUNGGAKAN' => 0,
                        'TERIMA_TUNGGAKAN' => 0,
                        'BAKI_TUNGGAKAN' => 0,
                        'BIL_SEMASA' => 0,
                        'TERIMA_SEMASA' => 0,
                        'BAKI_SEMASA' => 0,
                    ];
                }
                $byJalan[$kod]['BIL_TUNGGAKAN'] += (float) $row['BIL_TUNGGAKAN'];
                $byJalan[$kod]['TERIMA_TUNGGAKAN'] += (float) $row['TERIMA_TUNGGAKAN'];
                $byJalan[$kod]['BAKI_TUNGGAKAN'] += (float) $row['BAKI_TUNGGAKAN'];
                $byJalan[$kod]['BIL_SEMASA'] += (float) $row['BIL_SEMASA'];
                $byJalan[$kod]['TERIMA_SEMASA'] += (float) $row['TERIMA_SEMASA'];
                $byJalan[$kod]['BAKI_SEMASA'] += (float) $row['BAKI_SEMASA'];
            }
            return $this->formatSeriesRows(array_slice(array_values($byJalan), 0, 8), 'jalan');
        }

        return $this->formatSeriesRows(array_slice($rows, 0, 10), 'akaun');
    }

    private function formatSeriesRows(array $rows, string $mode): array
    {
        $items = [];
        foreach ($rows as $row) {
            $label = $mode === 'jalan'
                ? ((string) ($row['LABEL_KOD'] ?? '') . ' · ' . $this->shortenLabel((string) ($row['LABEL_NAMA'] ?? ''), 12))
                : ('Akaun ' . (string) ($row['PEG_AKAUN'] ?? ''));

            $bilTgk = round((float) ($row['BIL_TUNGGAKAN'] ?? 0), 2);
            $terimaTgk = round((float) ($row['TERIMA_TUNGGAKAN'] ?? 0), 2);
            $bakiTgk = round((float) ($row['BAKI_TUNGGAKAN'] ?? 0), 2);
            $bilSem = round((float) ($row['BIL_SEMASA'] ?? 0), 2);
            $terimaSem = round((float) ($row['TERIMA_SEMASA'] ?? 0), 2);
            $bakiSem = round((float) ($row['BAKI_SEMASA'] ?? 0), 2);

            $items[] = [
                'label' => $label,
                'full_label' => $mode === 'jalan'
                    ? ((string) ($row['LABEL_KOD'] ?? '') . ' - ' . (string) ($row['LABEL_NAMA'] ?? ''))
                    : ('Akaun ' . (string) ($row['PEG_AKAUN'] ?? '') . ' (' . (string) ($row['JLN_JNAMA'] ?? '') . ')'),
                'bil_tunggakan' => $bilTgk,
                'terima_tunggakan' => $terimaTgk,
                'baki_tunggakan' => $bakiTgk,
                'bil_semasa' => $bilSem,
                'terima_semasa' => $terimaSem,
                'baki_semasa' => $bakiSem,
                'kadar_tunggakan' => $this->safePercent($terimaTgk, $bilTgk),
                'kadar_semasa' => $this->safePercent($terimaSem, $bilSem),
            ];
        }

        return [
            'mode' => $mode,
            'items' => $items,
        ];
    }

    private function shortenLabel(string $text, int $maxLen): string
    {
        $clean = trim($text);
        if (strlen($clean) <= $maxLen) {
            return $clean;
        }
        return substr($clean, 0, $maxLen - 1) . '…';
    }

    private function readCache(string $key, int $ttlSeconds): mixed
    {
        $path = $this->cacheDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.json';
        if (!file_exists($path) || (time() - (int) filemtime($path)) > $ttlSeconds) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function writeCache(string $key, mixed $data): void
    {
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
        $path = $this->cacheDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.json';
        @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
