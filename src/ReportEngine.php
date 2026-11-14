<?php
declare(strict_types=1);

/**
 * Enjin Pelancar Laporan Modular.
 * Menyokong sambungan berlainan mengikut profil skema Oracle (`oracle_profile`)
 * bagi setiap modul ePBT (mktn, mpbg, mpln, mpnl, mswn, mtkn, sakb, spbt).
 */
final class ReportEngine
{
    /** @var callable */
    private $clientResolver;
    private bool $demoMode;
    private string $modulesDir;
    private int $cacheTtl;
    private IndexedTableCache $tableCache;

    public function __construct(
        callable $clientResolver,
        bool $demoMode,
        ?string $modulesDir = null,
        ?string $cacheDir = null,
        int $cacheTtl = 3600
    ) {
        $this->clientResolver = $clientResolver;
        $this->demoMode = $demoMode;
        $this->modulesDir = $modulesDir ?? (dirname(__DIR__) . '/modules');
        $this->cacheTtl = max(60, $cacheTtl);
        $this->tableCache = new IndexedTableCache($cacheDir ?? (dirname(__DIR__) . '/cache'), $this->cacheTtl);
    }

    public function clearCache(): void
    {
        $this->tableCache->clearAll();
    }

    /**
     * Senarai semua modul daripada `modules/registry.php` bersama kiraan laporan aktif.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getModules(): array
    {
        $registryPath = $this->modulesDir . '/registry.php';
        $modules = file_exists($registryPath) ? (require $registryPath) : [];
        if (!is_array($modules)) {
            return [];
        }

        $reports = $this->getReportsMeta();
        $counts = [];
        foreach ($reports as $rep) {
            $mid = (string) ($rep['module_id'] ?? 'epbt-report');
            $counts[$mid] = ($counts[$mid] ?? 0) + 1;
        }

        foreach ($modules as &$mod) {
            $id = (string) ($mod['id'] ?? '');
            $mod['report_count'] = $id === 'epbt-report' ? count($reports) : ($counts[$id] ?? 0);
            if ($mod['report_count'] > 0 && ($mod['target_tab'] ?? null) === null) {
                $mod['target_tab'] = 'laporan';
                $mod['status'] = 'active';
            }
        }
        unset($mod);

        return $modules;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReportsMeta(?string $moduleId = null): array
    {
        $defs = $this->loadDefinitions();
        $out = [];
        foreach ($defs as $def) {
            if ($moduleId !== null && $moduleId !== '' && $moduleId !== 'epbt-report' && $def['module_id'] !== $moduleId) {
                continue;
            }
            $out[] = [
                'id' => $def['id'],
                'module_id' => $def['module_id'],
                'oracle_profile' => (string) ($def['oracle_profile'] ?? 'default'),
                'title' => $def['title'],
                'description' => $def['description'],
                'supports_jlkod' => (bool) ($def['supports_jlkod'] ?? false),
                'supports_carian' => (bool) ($def['supports_carian'] ?? false),
                'carian_label' => (string) ($def['carian_label'] ?? 'Carian'),
                'columns' => $def['columns'],
                'pointer_report' => (string) ($def['pointer_report'] ?? ''),
                'parameters' => $def['parameters'] ?? [],
            ];
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function runReport(
        string $reportId,
        array $filters,
        int $page = 1,
        int $perPage = 25,
        string $sortBy = '',
        string $sortDir = 'ASC',
        bool $forceRefresh = false
    ): array {
        $startedAt = microtime(true);
        $def = $this->findDefinition($reportId);
        if ($def === null) {
            throw new RuntimeException('Laporan modul tidak ditemui: ' . $reportId);
        }

        $page = max(1, $page);
        $perPage = max(5, min(200, $perPage));
        $jlkod = trim((string) ($filters['jlkod'] ?? ''));
        $carian = trim((string) ($filters['carian'] ?? ''));
        [$sortKey, $direction, $defaultKey] = $this->resolveSort($def, $sortBy, $sortDir);
        $reportMeta = $this->formatReportHeaderMeta($def);

        if ($this->demoMode) {
            $provider = $def['demo_provider'] ?? null;
            $allRows = is_callable($provider) ? $provider() : [];
            $filtered = array_values(array_filter($allRows, static function (array $row) use ($jlkod, $carian, $def): bool {
                if (!empty($def['supports_jlkod']) && $jlkod !== '' && (string) ($row['PEG_JLKOD'] ?? '') !== $jlkod) {
                    return false;
                }
                if (!empty($def['supports_carian']) && $carian !== '') {
                    $haystack = implode(' ', array_map('strval', $row));
                    if (stripos($haystack, $carian) === false) {
                        return false;
                    }
                }
                return true;
            }));

            $this->sortDemoRows($filtered, $sortKey, $direction);

            $total = count($filtered);
            $offset = ($page - 1) * $perPage;
            $slice = array_slice($filtered, $offset, $perPage);
            foreach ($slice as $i => &$row) {
                $row = array_change_key_case($row, CASE_UPPER);
                $row['ROW_NUM'] = $offset + $i + 1;
            }
            unset($row);

            return [
                'report' => $reportMeta,
                'rows' => $slice,
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

        if (!empty($def['disable_cache'])) {
            /** @var OracleClient $client */
            $client = ($this->clientResolver)($def['oracle_profile'] ?? null);
            $params = [];
            $baseSql = $this->compileSql($def, $jlkod, $carian, $params);

            // Mod fetch_all: ambil semua baris sekaligus (sesuai untuk laporan agregat
            // yang menggunakan ROLLUP/CUBE — tidak boleh dibungkus dengan ROWNUM pager).
            if (!empty($def['fetch_all'])) {
                $allRows = $client->query($baseSql, $params);

                // Tambah baris JUMLAH BESAR jika diminta oleh definisi laporan
                $grandTotalRow = null;
                if (!empty($def['add_grand_total']) && count($allRows) > 0) {
                    $sumRumah = 0;
                    $sumTunggak = 0;
                    $sumSelesai = 0;
                    $sumTiada = 0;
                    $sumTgknAmaun = 0.0;
                    foreach ($allRows as $r) {
                        $sumRumah += (int) ($r['BIL_RUMAH'] ?? 0);
                        $sumTunggak += (int) ($r['BIL_TUNGGAK'] ?? 0);
                        $sumSelesai += (int) ($r['BIL_SELESAI'] ?? 0);
                        $sumTiada += (int) ($r['BIL_TIADA_PENYATA'] ?? 0);
                        $sumTgknAmaun += (float) ($r['JUMLAH_TUNGGAKAN'] ?? 0.0);
                    }
                    $pctTunggak = $sumRumah > 0 ? round(100 * $sumTunggak / $sumRumah, 2) : 0.0;
                    $pctSelesai = $sumRumah > 0 ? round(100 * $sumSelesai / $sumRumah, 2) : 0.0;

                    $grandTotalRow = [
                        'KAWASAN' => 'JUMLAH BESAR',
                        'KOD_JALAN' => '',
                        'JALAN_TAMAN_KAMPUNG' => '(Keseluruhan ' . count($allRows) . ' Jalan)',
                        'BIL_RUMAH' => $sumRumah,
                        'BIL_TUNGGAK' => $sumTunggak,
                        'BIL_SELESAI' => $sumSelesai,
                        'BIL_TIADA_PENYATA' => $sumTiada,
                        'PERATUS_TUNGGAK' => $pctTunggak,
                        'PERATUS_SELESAI' => $pctSelesai,
                        'JUMLAH_TUNGGAKAN' => $sumTgknAmaun,
                        'ROW_TYPE' => 'TOTAL'
                    ];
                }

                // Isih dalam PHP
                if ($sortKey !== '' && ($sortKey !== $defaultKey || $direction === 'DESC')) {
                    $this->sortDemoRows($allRows, $sortKey, $direction);
                }

                if ($grandTotalRow !== null) {
                    $allRows[] = $grandTotalRow;
                }

                foreach ($allRows as $i => &$row) {
                    $row['ROW_NUM'] = $i + 1;
                }
                unset($row);
                $total  = count($allRows);
                $offset = ($page - 1) * $perPage;
                $slice  = array_slice($allRows, $offset, $perPage);
                return [
                    'report'      => $reportMeta,
                    'rows'        => $slice,
                    'total'       => $total,
                    'page'        => $page,
                    'per_page'    => $perPage,
                    'total_pages' => max(1, (int) ceil($total / $perPage)),
                    'sort_by'     => $sortKey,
                    'sort_dir'    => $direction,
                    'cache_hit'   => false,
                    'elapsed_ms'  => round((microtime(true) - $startedAt) * 1000, 1),
                ];
            }

            $countRow = $client->queryOne("SELECT COUNT(*) AS TOTAL_ROWS FROM ( {$baseSql} ) q", $params);
            $total = (int) ($countRow['TOTAL_ROWS'] ?? 0);

            $orderClause = $sortKey === $defaultKey
                ? "q.{$sortKey} {$direction}"
                : "q.{$sortKey} {$direction}, q.{$defaultKey} ASC";

            $pagedParams = $params;
            $pagedParams['max_row'] = $page * $perPage;
            $pagedParams['min_row'] = ($page - 1) * $perPage;

            $pagedSql = "
                SELECT * FROM (
                    SELECT inner_q.*, ROWNUM AS ROW_NUM FROM (
                        SELECT q.* FROM ( {$baseSql} ) q
                        ORDER BY {$orderClause}
                    ) inner_q
                    WHERE ROWNUM <= :max_row
                )
                WHERE ROW_NUM > :min_row
            ";

            return [
                'report'      => $reportMeta,
                'rows'        => $client->query($pagedSql, $pagedParams),
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
                'sort_by'     => $sortKey,
                'sort_dir'    => $direction,
                'cache_hit'   => false,
                'elapsed_ms'  => round((microtime(true) - $startedAt) * 1000, 1),
            ];
        }

        [$colKeys, $colTypes] = $this->extractColumnSpecs($def);
        [$datasetKey, $filterKey, $predicate, $rebuilt] = $this->prepareReportTableCache($def, $colKeys, $jlkod, $carian, $forceRefresh);

        $result = $this->tableCache->queryPage($datasetKey, [
            'page' => $page,
            'per_page' => $perPage,
            'sort_by' => $sortKey,
            'sort_dir' => $direction,
            'default_sort' => $defaultKey,
            'col_types' => $colTypes,
            'filter_key' => $filterKey,
            'filter_predicate' => $predicate,
        ]);

        $result['report'] = $reportMeta;
        $result['sort_by'] = $sortKey;
        $result['sort_dir'] = $direction;
        $result['cache_hit'] = !$rebuilt;
        $result['elapsed_ms'] = round((microtime(true) - $startedAt) * 1000, 1);
        return $result;
    }

    public function streamReportCsv(
        string $reportId,
        array $filters,
        callable $onRow,
        string $sortBy = '',
        string $sortDir = 'ASC'
    ): array {
        $def = $this->findDefinition($reportId);
        if ($def === null) {
            throw new RuntimeException('Laporan modul tidak ditemui: ' . $reportId);
        }

        $jlkod = trim((string) ($filters['jlkod'] ?? ''));
        $carian = trim((string) ($filters['carian'] ?? ''));
        [$sortKey, $direction, $defaultKey] = $this->resolveSort($def, $sortBy, $sortDir);

        if ($this->demoMode) {
            $provider = $def['demo_provider'] ?? null;
            $allRows = is_callable($provider) ? $provider() : [];
            $filtered = [];
            foreach ($allRows as $row) {
                if (!empty($def['supports_jlkod']) && $jlkod !== '' && (string) ($row['PEG_JLKOD'] ?? '') !== $jlkod) {
                    continue;
                }
                if (!empty($def['supports_carian']) && $carian !== '' && stripos(implode(' ', array_map('strval', $row)), $carian) === false) {
                    continue;
                }
                $filtered[] = array_change_key_case($row, CASE_UPPER);
            }
            $this->sortDemoRows($filtered, $sortKey, $direction);
            foreach ($filtered as $idx => $upper) {
                $upper['ROW_NUM'] = $idx + 1;
                $onRow($upper);
            }
            return $def;
        }

        if (!empty($def['disable_cache'])) {
            /** @var OracleClient $client */
            $client = ($this->clientResolver)($def['oracle_profile'] ?? null);
            $params = [];
            $baseSql = $this->compileSql($def, $jlkod, $carian, $params);

            // fetch_all: ROLLUP/CUBE query — stream terus tanpa ORDER wrapper luar
            if (!empty($def['fetch_all'])) {
                $allRows = $client->query($baseSql, $params);
                if ($sortKey !== $defaultKey) {
                    $this->sortDemoRows($allRows, $sortKey, $direction);
                }
                foreach ($allRows as $i => $row) {
                    $row['ROW_NUM'] = $i + 1;
                    $onRow($row);
                }
                return $def;
            }

            $orderClause = $sortKey === $defaultKey
                ? "q.{$sortKey} {$direction}"
                : "q.{$sortKey} {$direction}, q.{$defaultKey} ASC";
            $sql = "SELECT ordered_q.*, ROWNUM AS ROW_NUM FROM ( SELECT q.* FROM ( {$baseSql} ) q ORDER BY {$orderClause} ) ordered_q";
            $client->streamQuery($sql, $params, $onRow);
            return $def;
        }

        [$colKeys, $colTypes] = $this->extractColumnSpecs($def);
        [$datasetKey, $filterKey, $predicate] = $this->prepareReportTableCache($def, $colKeys, $jlkod, $carian, false);
        $this->tableCache->streamSortedRows($datasetKey, [
            'sort_by' => $sortKey,
            'sort_dir' => $direction,
            'default_sort' => $defaultKey,
            'col_types' => $colTypes,
            'filter_key' => $filterKey,
            'filter_predicate' => $predicate,
        ], $onRow);

        return $def;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatReportHeaderMeta(array $def): array
    {
        return [
            'id' => $def['id'],
            'title' => $def['title'],
            'description' => $def['description'],
            'oracle_profile' => (string) ($def['oracle_profile'] ?? 'default'),
            'columns' => $def['columns'],
        ];
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, string>}
     */
    private function extractColumnSpecs(array $def): array
    {
        $keys = [];
        $types = [];
        foreach ($def['columns'] as $c) {
            $k = strtoupper((string) $c['key']);
            $keys[] = $k;
            $sortType = strtolower((string) ($c['sort_type'] ?? ''));
            if ($sortType === 'numeric' || ($sortType === '' && in_array((string) ($c['type'] ?? ''), ['currency', 'number'], true))) {
                $types[$k] = 'numeric';
            } else {
                $types[$k] = 'string';
            }
        }
        return [$keys, $types];
    }

    /**
     * Memilih atau membina cache berindeks laporan bagi mempercepatkan pengisihan & halaman seterusnya.
     *
     * @param array<int, string> $colKeys
     * @return array{0: string, 1: string, 2: ?callable, 3: bool}
     */
    private function prepareReportTableCache(
        array $def,
        array $colKeys,
        string $jlkod,
        string $carian,
        bool $forceRefresh
    ): array {
        $ttl = isset($def['cache_ttl']) ? max(60, (int) $def['cache_ttl']) : $this->cacheTtl;
        $baseKey = !empty($def['cache_key']) ? (string) $def['cache_key'] : ('report_' . (string) $def['id']);
        $fullDatasetKey = $baseKey . '_all';

        $jlkodCol = strtoupper((string) ($def['jlkod_column'] ?? ''));
        $carianCol = strtoupper((string) ($def['carian_column'] ?? ''));
        $canFilterLocally = (empty($def['supports_jlkod']) || $jlkod === '' || $jlkodCol !== '')
            && (empty($def['supports_carian']) || $carian === '' || $carianCol !== '');
        $hasFilter = (!empty($def['supports_jlkod']) && $jlkod !== '') || (!empty($def['supports_carian']) && $carian !== '');

        if ((!$forceRefresh && $canFilterLocally && $this->tableCache->hasFresh($fullDatasetKey, $ttl)) || !$hasFilter) {
            $rebuilt = $this->tableCache->ensureBuilt(
                $fullDatasetKey,
                $colKeys,
                function (callable $emit) use ($def): void {
                    /** @var OracleClient $client */
                    $client = ($this->clientResolver)($def['oracle_profile'] ?? null);
                    $params = [];
                    $sql = $this->compileSql($def, '', '', $params);
                    $client->streamQuery($sql, $params, $emit);
                },
                $forceRefresh,
                $ttl
            );

            $filterKey = !$hasFilter ? 'all' : md5($jlkod . '|' . $carian);
            $predicate = !$hasFilter ? null : static function (array $row) use ($jlkod, $carian, $jlkodCol, $carianCol, $def): bool {
                if (!empty($def['supports_jlkod']) && $jlkod !== '' && trim((string) ($row[$jlkodCol] ?? '')) !== $jlkod) {
                    return false;
                }
                if (!empty($def['supports_carian']) && $carian !== '' && stripos((string) ($row[$carianCol] ?? ''), $carian) === false) {
                    return false;
                }
                return true;
            };

            return [$fullDatasetKey, $filterKey, $predicate, $rebuilt];
        }

        // Laluan pantas penapis khusus apabila set penuh belum wujud dalam cache
        $filteredKey = $baseKey . '_' . md5($jlkod . '|' . $carian);
        $rebuilt = $this->tableCache->ensureBuilt(
            $filteredKey,
            $colKeys,
            function (callable $emit) use ($def, $jlkod, $carian): void {
                /** @var OracleClient $client */
                $client = ($this->clientResolver)($def['oracle_profile'] ?? null);
                $params = [];
                $sql = $this->compileSql($def, $jlkod, $carian, $params);
                $client->streamQuery($sql, $params, $emit);
            },
            $forceRefresh,
            $ttl
        );

        return [$filteredKey, 'all', null, $rebuilt];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveSort(array $def, string $sortBy, string $sortDir): array
    {
        $allowedKeys = array_map(static fn(array $c): string => strtoupper((string) $c['key']), $def['columns']);
        $defaultRaw = strtoupper((string) ($def['default_sort'] ?? $allowedKeys[0]));
        $defaultKey = in_array($defaultRaw, $allowedKeys, true) ? $defaultRaw : $allowedKeys[0];

        $reqKey = strtoupper(trim($sortBy));
        $sortKey = in_array($reqKey, $allowedKeys, true) ? $reqKey : $defaultKey;
        $direction = strtoupper(trim($sortDir)) === 'DESC' ? 'DESC' : 'ASC';

        return [$sortKey, $direction, $defaultKey];
    }

    private function sortDemoRows(array &$rows, string $sortKey, string $direction): void
    {
        usort($rows, static function (array $a, array $b) use ($sortKey, $direction): int {
            $ua = array_change_key_case($a, CASE_UPPER);
            $ub = array_change_key_case($b, CASE_UPPER);
            $valA = $ua[$sortKey] ?? '';
            $valB = $ub[$sortKey] ?? '';
            $cmp = (is_numeric($valA) && is_numeric($valB))
                ? ((float) $valA <=> (float) $valB)
                : strnatcasecmp((string) $valA, (string) $valB);
            return $direction === 'DESC' ? -$cmp : $cmp;
        });
    }

    private function compileSql(array $def, string $jlkod, string $carian, array &$params): string
    {
        $sql = trim((string) $def['sql']);
        $sql = rtrim($sql, "; \t\n\r\0\x0B");

        $jlkodClause = '';
        $jlkodSubClause = '';
        if (!empty($def['supports_jlkod']) && $jlkod !== '' && !empty($def['filter_jlkod_clause'])) {
            $jlkodClause = (string) $def['filter_jlkod_clause'];
            $jlkodSubClause = (string) ($def['filter_jlkod_sub_clause'] ?? $jlkodClause);
            $params['jlkod'] = ctype_digit($jlkod) ? (int) $jlkod : $jlkod;
        }

        $carianClause = '';
        $carianSubClause = '';
        if (!empty($def['supports_carian']) && $carian !== '' && !empty($def['filter_carian_clause'])) {
            $carianClause = (string) $def['filter_carian_clause'];
            $carianSubClause = (string) ($def['filter_carian_sub_clause'] ?? $carianClause);
            $params['carian'] = '%' . $carian . '%';
        }

        $sql = str_replace('/*FILTER_JLKOD*/', $jlkodClause, $sql);
        $sql = str_replace('/*FILTER_JLKOD_SUB*/', $jlkodSubClause, $sql);
        $sql = str_replace('/*FILTER_CARIAN*/', $carianClause, $sql);
        $sql = str_replace('/*FILTER_CARIAN_SUB*/', $carianSubClause, $sql);
        return $sql;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadDefinitions(): array
    {
        $files = glob($this->modulesDir . '/reports/*.php') ?: [];
        sort($files);
        $defs = [];
        foreach ($files as $file) {
            $base = basename($file);
            if (str_starts_with($base, '_')) {
                continue;
            }
            $def = require $file;
            if (is_array($def) && !empty($def['id']) && !empty($def['sql']) && !empty($def['columns'])) {
                $defs[] = $def;
            }
        }
        return $defs;
    }

    private function findDefinition(string $reportId): ?array
    {
        foreach ($this->loadDefinitions() as $def) {
            if ((string) $def['id'] === $reportId) {
                return $def;
            }
        }
        return null;
    }
}
