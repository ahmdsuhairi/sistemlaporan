<?php
declare(strict_types=1);

/**
 * Storan tembolok jadual berindeks setempat (rows.dat + offsets.bin + sort_*.bin).
 * Mengelakkan kuiri ulangan berat ke Oracle 10g semasa mengisih (sort), menapis,
 * menukar halaman ("Seterusnya"), dan mengeksport CSV.
 */
final class IndexedTableCache
{
    private string $tablesDir;
    private int $defaultTtl;

    public function __construct(string $cacheDir, int $defaultTtl = 3600)
    {
        $this->tablesDir = rtrim($cacheDir, '/\\') . '/tables';
        $this->defaultTtl = max(60, $defaultTtl);
    }

    public function sanitizeKey(string $key): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $clean !== null && $clean !== '' ? $clean : 'default';
    }

    public function clearAll(): void
    {
        if (!is_dir($this->tablesDir)) {
            return;
        }
        $this->removeDirectoryContents($this->tablesDir);
    }

    public function hasFresh(string $datasetKey, ?int $ttl = null): bool
    {
        $dir = $this->datasetDir($datasetKey);
        $meta = $this->readMeta($dir);
        if ($meta === null) {
            return false;
        }
        if (!file_exists($dir . '/rows.dat') || !file_exists($dir . '/offsets.bin')) {
            return false;
        }
        $maxAge = $ttl ?? $this->defaultTtl;
        $builtAt = (int) ($meta['built_at'] ?? 0);
        return $builtAt > 0 && (time() - $builtAt) <= $maxAge;
    }

    /**
     * Memastikan set data tempatan dibina (sekali bagi setiap tempoh TTL) menggunakan strim Oracle.
     * Menggunakan kunci fail eksklusif supaya permintaan serentak tidak membina secara berganda.
     *
     * @param array<int, string> $columns
     * @param callable(callable(array<string, mixed>): void): void $streamProducer
     * @return bool True jika dibina baharu daripada sumber, False jika sedia ada dalam cache.
     */
    public function ensureBuilt(
        string $datasetKey,
        array $columns,
        callable $streamProducer,
        bool $forceRefresh = false,
        ?int $ttl = null
    ): bool {
        $columns = array_values(array_map(static fn(string $c): string => strtoupper($c), $columns));
        $dir = $this->datasetDir($datasetKey);

        if (!$forceRefresh && $this->hasFresh($datasetKey, $ttl)) {
            $meta = $this->readMeta($dir);
            if ($meta !== null && ($meta['columns'] ?? []) === $columns) {
                return false;
            }
        }

        if (!is_dir($this->tablesDir)) {
            @mkdir($this->tablesDir, 0775, true);
        }

        $lockFp = $this->acquireLock($datasetKey, LOCK_EX);
        try {
            if (!$forceRefresh && $this->hasFresh($datasetKey, $ttl)) {
                $meta = $this->readMeta($dir);
                if ($meta !== null && ($meta['columns'] ?? []) === $columns) {
                    return false;
                }
            }

            $tmpDir = $dir . '.__build_' . getmypid() . '_' . mt_rand(1000, 9999);
            if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0775, true)) {
                throw new RuntimeException('Gagal mencipta direktori sementara cache jadual.');
            }

            $fpDat = @fopen($tmpDir . '/rows.dat', 'wb');
            $fpOff = @fopen($tmpDir . '/offsets.bin', 'wb');
            if ($fpDat === false || $fpOff === false) {
                if (is_resource($fpDat)) {
                    fclose($fpDat);
                }
                if (is_resource($fpOff)) {
                    fclose($fpOff);
                }
                $this->removeDirectory($tmpDir);
                throw new RuntimeException('Gagal membuka fail cache jadual untuk penulisan.');
            }

            $pos = 0;
            $count = 0;
            try {
                $streamProducer(static function (array $row) use ($columns, $fpDat, $fpOff, &$pos, &$count): void {
                    $upper = array_change_key_case($row, CASE_UPPER);
                    $vals = [];
                    foreach ($columns as $col) {
                        $vals[] = $upper[$col] ?? null;
                    }
                    $line = json_encode($vals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                    $len = strlen($line);
                    fwrite($fpDat, $line);
                    fwrite($fpOff, pack('VV', $pos, $len));
                    $pos += $len;
                    $count++;
                });

                fflush($fpDat);
                fflush($fpOff);
                fclose($fpDat);
                fclose($fpOff);

                $metaPayload = [
                    'version' => 1,
                    'built_at' => time(),
                    'total_rows' => $count,
                    'columns' => $columns,
                ];
                file_put_contents(
                    $tmpDir . '/meta.json',
                    json_encode($metaPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );

                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                } else {
                    $this->removeDirectoryContents($dir);
                }

                @rename($tmpDir . '/rows.dat', $dir . '/rows.dat');
                @rename($tmpDir . '/offsets.bin', $dir . '/offsets.bin');
                @rename($tmpDir . '/meta.json', $dir . '/meta.json');
                $this->removeDirectory($tmpDir);
            } catch (Throwable $e) {
                if (is_resource($fpDat)) {
                    fclose($fpDat);
                }
                if (is_resource($fpOff)) {
                    fclose($fpOff);
                }
                $this->removeDirectory($tmpDir);
                throw $e;
            }

            return true;
        } finally {
            $this->releaseLock($lockFp);
        }
    }

    /**
     * Mengambil satu halaman rekod dengan tapisan dan isihan pantas daripada cache berindeks.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function queryPage(string $datasetKey, array $options): array
    {
        $lockFp = $this->acquireLock($datasetKey, LOCK_SH);
        try {
            $dir = $this->datasetDir($datasetKey);
            $meta = $this->readMeta($dir);
            if ($meta === null) {
                throw new RuntimeException('Cache jadual tidak ditemui: ' . $datasetKey);
            }

            $columns = $meta['columns'];
            $totalRows = (int) ($meta['total_rows'] ?? 0);
            $page = max(1, (int) ($options['page'] ?? 1));
            $perPage = max(5, min(200, (int) ($options['per_page'] ?? 25)));

            if ($totalRows === 0) {
                return [
                    'rows' => [],
                    'total' => 0,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => 1,
                ];
            }

            $defaultSort = strtoupper((string) ($options['default_sort'] ?? ($columns[0] ?? '')));
            $sortBy = strtoupper((string) ($options['sort_by'] ?? $defaultSort));
            $sortDir = strtoupper((string) ($options['sort_dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
            $colTypes = $options['col_types'] ?? [];
            $virtualCols = $options['virtual_cols'] ?? [];
            $filterKey = $this->sanitizeKey((string) ($options['filter_key'] ?? 'all'));
            $filterPredicate = $options['filter_predicate'] ?? null;

            $metaMtime = (int) (@filemtime($dir . '/meta.json') ?: time());
            $matchedIds = $this->resolveFilteredIds($dir, $meta, $metaMtime, $filterKey, $filterPredicate);

            if ($matchedIds !== null && empty($matchedIds)) {
                return [
                    'rows' => [],
                    'total' => 0,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => 1,
                ];
            }

            $totalMatched = $matchedIds !== null ? count($matchedIds) : $totalRows;
            $totalPages = max(1, (int) ceil($totalMatched / $perPage));
            $offset = ($page - 1) * $perPage;

            $sortFile = $this->ensureSortIndex(
                $dir,
                $meta,
                $metaMtime,
                $filterKey,
                $matchedIds,
                $sortBy,
                $sortDir,
                $defaultSort,
                $colTypes,
                $virtualCols
            );

            $pageIds = $this->readIdSlice($sortFile, $offset, $perPage, $totalMatched);
            $rows = $this->fetchRowsByIds($dir, $columns, $pageIds, $offset);

            return [
                'rows' => $rows,
                'total' => $totalMatched,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
            ];
        } finally {
            $this->releaseLock($lockFp);
        }
    }

    /**
     * Menstrim semua baris yang telah ditapis & diisih untuk eksport CSV pantas tanpa kuiri Oracle.
     *
     * @param array<string, mixed> $options
     */
    public function streamSortedRows(string $datasetKey, array $options, callable $onRow): void
    {
        $lockFp = $this->acquireLock($datasetKey, LOCK_SH);
        try {
            $dir = $this->datasetDir($datasetKey);
            $meta = $this->readMeta($dir);
            if ($meta === null) {
                throw new RuntimeException('Cache jadual tidak ditemui: ' . $datasetKey);
            }

            $columns = $meta['columns'];
            $totalRows = (int) ($meta['total_rows'] ?? 0);
            if ($totalRows === 0) {
                return;
            }

            $defaultSort = strtoupper((string) ($options['default_sort'] ?? ($columns[0] ?? '')));
            $sortBy = strtoupper((string) ($options['sort_by'] ?? $defaultSort));
            $sortDir = strtoupper((string) ($options['sort_dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
            $colTypes = $options['col_types'] ?? [];
            $virtualCols = $options['virtual_cols'] ?? [];
            $filterKey = $this->sanitizeKey((string) ($options['filter_key'] ?? 'all'));
            $filterPredicate = $options['filter_predicate'] ?? null;

            $metaMtime = (int) (@filemtime($dir . '/meta.json') ?: time());
            $matchedIds = $this->resolveFilteredIds($dir, $meta, $metaMtime, $filterKey, $filterPredicate);
            if ($matchedIds !== null && empty($matchedIds)) {
                return;
            }

            $totalMatched = $matchedIds !== null ? count($matchedIds) : $totalRows;
            $sortFile = $this->ensureSortIndex(
                $dir,
                $meta,
                $metaMtime,
                $filterKey,
                $matchedIds,
                $sortBy,
                $sortDir,
                $defaultSort,
                $colTypes,
                $virtualCols
            );

            $chunkSize = 2000;
            for ($offset = 0; $offset < $totalMatched; $offset += $chunkSize) {
                $ids = $this->readIdSlice($sortFile, $offset, $chunkSize, $totalMatched);
                $rows = $this->fetchRowsByIds($dir, $columns, $ids, $offset);
                foreach ($rows as $row) {
                    $onRow($row);
                }
            }
        } finally {
            $this->releaseLock($lockFp);
        }
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<int, int>|null Null bermaksud semua baris tanpa tapisan.
     */
    private function resolveFilteredIds(
        string $dir,
        array $meta,
        int $metaMtime,
        string $filterKey,
        ?callable $filterPredicate
    ): ?array {
        if ($filterPredicate === null || $filterKey === 'all') {
            return null;
        }

        $filterFile = $dir . '/filter_' . $filterKey . '.bin';
        if (file_exists($filterFile) && (int) @filemtime($filterFile) >= $metaMtime) {
            $size = (int) @filesize($filterFile);
            if ($size === 0) {
                return [];
            }
            $raw = @file_get_contents($filterFile);
            if ($raw !== false && strlen($raw) >= 4) {
                return array_values(unpack('V*', $raw));
            }
        }

        $columns = $meta['columns'];
        $colCount = count($columns);
        $ids = [];
        $fp = @fopen($dir . '/rows.dat', 'rb');
        if ($fp === false) {
            throw new RuntimeException('Gagal membaca fail baris cache.');
        }
        $idx = 0;
        while (($line = fgets($fp)) !== false) {
            $vals = json_decode($line, true);
            if (is_array($vals) && count($vals) === $colCount) {
                $assoc = array_combine($columns, $vals);
                if ($filterPredicate($assoc)) {
                    $ids[] = $idx;
                }
            }
            $idx++;
        }
        fclose($fp);

        $packed = empty($ids) ? '' : pack('V*', ...$ids);
        $this->atomicWrite($filterFile, $packed);
        return $ids;
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<int, int>|null $matchedIds
     * @param array<string, string> $colTypes
     * @param array<string, callable> $virtualCols
     */
    private function ensureSortIndex(
        string $dir,
        array $meta,
        int $metaMtime,
        string $filterKey,
        ?array $matchedIds,
        string $sortBy,
        string $sortDir,
        string $defaultSort,
        array $colTypes,
        array $virtualCols
    ): string {
        $sortFile = $dir . '/sort_' . $filterKey . '_' . $this->sanitizeKey($sortBy) . '_' . $sortDir . '.bin';
        if (file_exists($sortFile) && (int) @filemtime($sortFile) >= $metaMtime) {
            return $sortFile;
        }

        $totalRows = (int) ($meta['total_rows'] ?? 0);
        $columns = $meta['columns'];
        $ids = $matchedIds ?? range(0, $totalRows - 1);

        $primaryType = ($colTypes[$sortBy] ?? 'string') === 'numeric' ? SORT_NUMERIC : (SORT_STRING | SORT_FLAG_CASE);
        $primaryFlag = $sortDir === 'DESC' ? SORT_DESC : SORT_ASC;
        $hasTie = ($defaultSort !== '' && $defaultSort !== $sortBy);
        $tieType = ($colTypes[$defaultSort] ?? 'string') === 'numeric' ? SORT_NUMERIC : (SORT_STRING | SORT_FLAG_CASE);

        // Laluan pantas sub-set kecil (cth. penapis 1 jalan ~520 rekod): baca terus baris daripada offsets.bin
        if (count($ids) <= 5000) {
            $rows = $this->fetchRowsByIds($dir, $columns, $ids, 0);
            $primaryVals = [];
            $tieVals = [];
            $isNum = $primaryType === SORT_NUMERIC;
            $isTieNum = $tieType === SORT_NUMERIC;

            foreach ($rows as $row) {
                $val = $this->extractValueFromRow($row, $sortBy, $virtualCols);
                $primaryVals[] = $isNum ? (float) ($val ?? 0) : (string) ($val ?? '');
                if ($hasTie) {
                    $tVal = $this->extractValueFromRow($row, $defaultSort, $virtualCols);
                    $tieVals[] = $isTieNum ? (float) ($tVal ?? 0) : (string) ($tVal ?? '');
                }
            }

            if ($hasTie) {
                array_multisort(
                    $primaryVals,
                    $primaryFlag,
                    $primaryType,
                    $tieVals,
                    SORT_ASC,
                    $tieType,
                    $ids,
                    SORT_ASC,
                    SORT_NUMERIC
                );
            } else {
                array_multisort(
                    $primaryVals,
                    $primaryFlag,
                    $primaryType,
                    $ids,
                    SORT_ASC,
                    SORT_NUMERIC
                );
            }

            $this->atomicWrite($sortFile, empty($ids) ? '' : pack('V*', ...$ids));
            return $sortFile;
        }

        // Laluan set penuh / besar: guna cache kolum tunggal bagi meminimumkan RAM & I/O
        $fullPrimary = $this->getColumnValues($dir, $meta, $metaMtime, $sortBy, $colTypes, $virtualCols);
        if ($matchedIds === null) {
            $primaryVals = $fullPrimary;
        } else {
            $primaryVals = [];
            foreach ($ids as $id) {
                $primaryVals[] = $fullPrimary[$id] ?? null;
            }
        }
        unset($fullPrimary);

        if ($hasTie) {
            $fullTie = $this->getColumnValues($dir, $meta, $metaMtime, $defaultSort, $colTypes, $virtualCols);
            if ($matchedIds === null) {
                $tieVals = $fullTie;
            } else {
                $tieVals = [];
                foreach ($ids as $id) {
                    $tieVals[] = $fullTie[$id] ?? null;
                }
            }
            unset($fullTie);

            array_multisort(
                $primaryVals,
                $primaryFlag,
                $primaryType,
                $tieVals,
                SORT_ASC,
                $tieType,
                $ids,
                SORT_ASC,
                SORT_NUMERIC
            );
            unset($tieVals);
        } else {
            array_multisort(
                $primaryVals,
                $primaryFlag,
                $primaryType,
                $ids,
                SORT_ASC,
                SORT_NUMERIC
            );
        }
        unset($primaryVals);

        $this->atomicWrite($sortFile, empty($ids) ? '' : pack('V*', ...$ids));
        return $sortFile;
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, string> $colTypes
     * @param array<string, callable> $virtualCols
     * @return array<int, float|string>
     */
    private function getColumnValues(
        string $dir,
        array $meta,
        int $metaMtime,
        string $colKey,
        array $colTypes,
        array $virtualCols
    ): array {
        $colFile = $dir . '/col_' . $this->sanitizeKey($colKey) . '.ser';
        if (file_exists($colFile) && (int) @filemtime($colFile) >= $metaMtime) {
            $raw = @file_get_contents($colFile);
            if ($raw !== false) {
                $decoded = @unserialize($raw, ['allowed_classes' => false]);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        $columns = $meta['columns'];
        $colCount = count($columns);
        $colPos = array_search($colKey, $columns, true);
        $isNum = ($colTypes[$colKey] ?? 'string') === 'numeric';
        $virtualFn = $virtualCols[$colKey] ?? null;

        $values = [];
        $fp = @fopen($dir . '/rows.dat', 'rb');
        if ($fp === false) {
            throw new RuntimeException('Gagal membaca fail baris cache untuk kolum ' . $colKey);
        }

        while (($line = fgets($fp)) !== false) {
            $arr = json_decode($line, true);
            if (!is_array($arr) || count($arr) !== $colCount) {
                $values[] = $isNum ? 0.0 : '';
                continue;
            }
            if ($colPos !== false) {
                $rawVal = $arr[$colPos] ?? null;
            } elseif (is_callable($virtualFn)) {
                $assoc = array_combine($columns, $arr);
                $rawVal = $virtualFn($assoc);
            } else {
                $rawVal = '';
            }
            $values[] = $isNum ? (float) ($rawVal ?? 0) : (string) ($rawVal ?? '');
        }
        fclose($fp);

        $this->atomicWrite($colFile, serialize($values));
        return $values;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, callable> $virtualCols
     */
    private function extractValueFromRow(array $row, string $colKey, array $virtualCols): mixed
    {
        if (array_key_exists($colKey, $row)) {
            return $row[$colKey];
        }
        if (isset($virtualCols[$colKey]) && is_callable($virtualCols[$colKey])) {
            return $virtualCols[$colKey]($row);
        }
        return '';
    }

    /**
     * @return array<int, int>
     */
    private function readIdSlice(string $sortFile, int $offset, int $limit, int $totalMatched): array
    {
        if ($offset >= $totalMatched || $limit <= 0) {
            return [];
        }
        $take = min($limit, $totalMatched - $offset);
        $fp = @fopen($sortFile, 'rb');
        if ($fp === false) {
            return [];
        }
        fseek($fp, $offset * 4);
        $chunk = fread($fp, $take * 4);
        fclose($fp);
        if ($chunk === false || $chunk === '') {
            return [];
        }
        $count = intdiv(strlen($chunk), 4);
        if ($count <= 0) {
            return [];
        }
        return array_values(unpack('V' . $count, $chunk));
    }

    /**
     * @param array<int, string> $columns
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function fetchRowsByIds(string $dir, array $columns, array $ids, int $startRowNum): array
    {
        if (empty($ids)) {
            return [];
        }
        $fpOff = @fopen($dir . '/offsets.bin', 'rb');
        $fpDat = @fopen($dir . '/rows.dat', 'rb');
        if ($fpOff === false || $fpDat === false) {
            if (is_resource($fpOff)) {
                fclose($fpOff);
            }
            if (is_resource($fpDat)) {
                fclose($fpDat);
            }
            throw new RuntimeException('Gagal membaca fail data berindeks.');
        }

        $colCount = count($columns);
        $rows = [];
        foreach ($ids as $idx => $id) {
            fseek($fpOff, $id * 8);
            $metaRaw = fread($fpOff, 8);
            if ($metaRaw === false || strlen($metaRaw) < 8) {
                continue;
            }
            $u = unpack('Vo/Vl', $metaRaw);
            fseek($fpDat, (int) $u['o']);
            $line = fread($fpDat, (int) $u['l']);
            if ($line === false) {
                continue;
            }
            $vals = json_decode($line, true);
            if (!is_array($vals) || count($vals) !== $colCount) {
                continue;
            }
            $assoc = array_combine($columns, $vals);
            $assoc['ROW_NUM'] = $startRowNum + $idx + 1;
            $rows[] = $assoc;
        }

        fclose($fpOff);
        fclose($fpDat);
        return $rows;
    }

    private function datasetDir(string $datasetKey): string
    {
        return $this->tablesDir . '/' . $this->sanitizeKey($datasetKey);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readMeta(string $dir): ?array
    {
        $path = $dir . '/meta.json';
        if (!file_exists($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $meta = json_decode($raw, true);
        if (!is_array($meta) || !isset($meta['columns']) || !is_array($meta['columns'])) {
            return null;
        }
        return $meta;
    }

    /**
     * @return resource|false
     */
    private function acquireLock(string $datasetKey, int $mode)
    {
        if (!is_dir($this->tablesDir)) {
            @mkdir($this->tablesDir, 0775, true);
        }
        $lockPath = $this->tablesDir . '/' . $this->sanitizeKey($datasetKey) . '.lock';
        $fp = @fopen($lockPath, 'c+');
        if ($fp !== false) {
            @flock($fp, $mode);
        }
        return $fp;
    }

    /**
     * @param resource|false $fp
     */
    private function releaseLock($fp): void
    {
        if (is_resource($fp)) {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }

    private function atomicWrite(string $targetPath, string $content): void
    {
        $tmp = $targetPath . '.tmp_' . getmypid() . '_' . mt_rand(100, 999);
        if (@file_put_contents($tmp, $content) !== false) {
            @rename($tmp, $targetPath);
        }
    }

    private function removeDirectoryContents(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $dir . '/' . $entry;
            if (is_dir($full)) {
                $this->removeDirectory($full);
            } else {
                @unlink($full);
            }
        }
    }

    private function removeDirectory(string $dir): void
    {
        $this->removeDirectoryContents($dir);
        @rmdir($dir);
    }
}

