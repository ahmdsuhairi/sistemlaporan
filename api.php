<?php
declare(strict_types=1);

require_once __DIR__ . '/src/Bootstrap.php';

@set_time_limit(180);

$ctx = Bootstrap::init();
/** @var OracleClient $client */
$client = $ctx['client'];
/** @var TunggakanRepository $repo */
$repo = $ctx['repo'];
/** @var ReportEngine $reportEngine */
$reportEngine = $ctx['report_engine'];

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'export_report_csv') {
    $reportId = (string) ($_GET['report_id'] ?? '');
    $sortBy = (string) ($_GET['sort_by'] ?? '');
    $sortDir = (string) ($_GET['sort_dir'] ?? 'ASC');
    $filters = [
        'jlkod' => $_GET['jlkod'] ?? '',
        'carian' => $_GET['carian'] ?? ($_GET['akaun'] ?? ''),
    ];

    try {
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $reportId) . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");

        $headerWritten = false;
        $colKeys = [];
        $reportEngine->streamReportCsv($reportId, $filters, static function (array $row) use ($out, &$headerWritten, &$colKeys): void {
            if (!$headerWritten) {
                $colKeys = array_keys($row);
                fputcsv($out, $colKeys);
                $headerWritten = true;
            }
            $line = [];
            foreach ($colKeys as $k) {
                $line[] = $row[$k] ?? '';
            }
            fputcsv($out, $line);
        }, $sortBy, $sortDir);
        fclose($out);
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Ralat Eksport Laporan CSV: ' . $e->getMessage();
    }
    exit;
}

if ($action === 'export_csv') {
    $sortBy = (string) ($_GET['sort_by'] ?? 'PEG_AKAUN');
    $sortDir = (string) ($_GET['sort_dir'] ?? 'ASC');
    $filters = [
        'jlkod' => $_GET['jlkod'] ?? '',
        'akaun' => $_GET['akaun'] ?? '',
        'status' => $_GET['status'] ?? 'all',
    ];

    try {
        $filename = 'tunggakan_terimaan_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'wb');
        // UTF-8 BOM supaya aksara & format kemas apabila dibuka dalam Microsoft Excel
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'ROW_NUM',
            'PEG_AKAUN',
            'PEG_JLKOD',
            'JLN_JNAMA',
            'BIL_TUNGGAKAN',
            'TERIMA_TUNGGAKAN',
            'BAKI_TUNGGAKAN',
            'BIL_SEMASA',
            'TERIMA_SEMASA',
            'BAKI_SEMASA',
            'JUMLAH_BAKI',
            'STATUS',
        ]);

        $repo->streamCsvExport($filters, static function (array $row) use ($out): void {
            fputcsv($out, [
                $row['ROW_NUM'],
                $row['PEG_AKAUN'],
                $row['PEG_JLKOD'],
                $row['JLN_JNAMA'],
                number_format((float) $row['BIL_TUNGGAKAN'], 2, '.', ''),
                number_format((float) $row['TERIMA_TUNGGAKAN'], 2, '.', ''),
                number_format((float) $row['BAKI_TUNGGAKAN'], 2, '.', ''),
                number_format((float) $row['BIL_SEMASA'], 2, '.', ''),
                number_format((float) $row['TERIMA_SEMASA'], 2, '.', ''),
                number_format((float) $row['BAKI_SEMASA'], 2, '.', ''),
                number_format((float) $row['JUMLAH_BAKI'], 2, '.', ''),
                $row['STATUS'],
            ]);
        }, $sortBy, $sortDir);
        fclose($out);
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Ralat Eksport CSV: ' . $e->getMessage();
    }
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

$sendJson = static function (array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

$filters = [
    'jlkod' => $_GET['jlkod'] ?? '',
    'akaun' => $_GET['akaun'] ?? '',
    'status' => $_GET['status'] ?? 'all',
];

$forceRefresh = ($_GET['refresh'] ?? '') === '1';
if ($forceRefresh) {
    $repo->clearCache();
    $reportEngine->clearCache();
}

try {
    switch ($action) {
        case 'jalan_list':
            $sendJson([
                'ok' => true,
                'demo_mode' => $repo->isDemoMode(),
                'data' => $repo->getJalanList($forceRefresh),
            ]);
            break;

        case 'modules':
            $sendJson([
                'ok' => true,
                'demo_mode' => $repo->isDemoMode(),
                'data' => $reportEngine->getModules(),
            ]);
            break;

        case 'reports_list':
            $moduleId = isset($_GET['module_id']) ? (string) $_GET['module_id'] : null;
            $sendJson([
                'ok' => true,
                'demo_mode' => $repo->isDemoMode(),
                'data' => $reportEngine->getReportsMeta($moduleId),
            ]);
            break;

        case 'run_report':
            $reportId = (string) ($_GET['report_id'] ?? '');
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = max(5, min(200, (int) ($_GET['per_page'] ?? 25)));
            $sortBy = (string) ($_GET['sort_by'] ?? '');
            $sortDir = (string) ($_GET['sort_dir'] ?? 'ASC');
            $reportFilters = [
                'jlkod' => $_GET['jlkod'] ?? '',
                'carian' => $_GET['carian'] ?? ($_GET['akaun'] ?? ''),
            ];
            $sendJson([
                'ok' => true,
                'demo_mode' => $repo->isDemoMode(),
                'data' => $reportEngine->runReport($reportId, $reportFilters, $page, $perPage, $sortBy, $sortDir, $forceRefresh),
            ]);
            break;

        case 'dashboard':
            $sendJson([
                'ok' => true,
                'demo_mode' => $repo->isDemoMode(),
                'data' => $repo->getDashboardData($filters, $forceRefresh),
            ]);
            break;

        case 'analisis':
            $sendJson([
                'ok' => true,
                'demo_mode' => $repo->isDemoMode(),
                'data' => $repo->getAnalisisData($filters, $forceRefresh),
            ]);
            break;

        case 'senarai':
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = max(5, min(200, (int) ($_GET['per_page'] ?? 25)));
            $sortBy = (string) ($_GET['sort_by'] ?? 'PEG_AKAUN');
            $sortDir = (string) ($_GET['sort_dir'] ?? 'ASC');
            $sendJson([
                'ok' => true,
                'demo_mode' => $repo->isDemoMode(),
                'data' => $repo->getSenaraiAkaun($filters, $page, $perPage, $sortBy, $sortDir, $forceRefresh),
            ]);
            break;

        case 'test_connection':
            $sendJson([
                'ok' => true,
                'demo_mode' => $repo->isDemoMode(),
                'data' => $client->testConnection(),
            ]);
            break;

        case 'test_profiles':
            $results = [];
            foreach (Bootstrap::getPublicProfileList() as $prof) {
                $profClient = Bootstrap::getClientForProfile($prof['key']);
                $res = $profClient->testConnection();
                $res['profile'] = $prof['key'];
                $res['label'] = $prof['label'];
                $profClient->close();
                $results[] = $res;
            }
            $sendJson([
                'ok' => true,
                'demo_mode' => $repo->isDemoMode(),
                'data' => $results,
            ]);
            break;

        case 'clear_cache':
            $repo->clearCache();
            $reportEngine->clearCache();
            $sendJson([
                'ok' => true,
                'demo_mode' => $repo->isDemoMode(),
                'message' => 'Cache berjaya dikosongkan.',
            ]);
            break;

        case 'set_mode':
            $raw = json_decode((string) file_get_contents('php://input'), true);
            $mode = is_array($raw) ? ($raw['demo_mode'] ?? null) : ($_POST['demo_mode'] ?? null);
            $_SESSION['demo_mode'] = filter_var($mode, FILTER_VALIDATE_BOOLEAN);
            $sendJson([
                'ok' => true,
                'demo_mode' => $_SESSION['demo_mode'],
            ]);
            break;

        case 'auth':
            $raw = json_decode((string) file_get_contents('php://input'), true);
            $input = is_array($raw) ? $raw : $_POST;
            $sub = (string) ($input['op'] ?? 'status');

            if ($sub === 'login') {
                $id = trim((string) ($input['id'] ?? ''));
                $pass = (string) ($input['password'] ?? '');
                $expectedId = (string) ($_SESSION['mock_auth']['id'] ?? 'admin');
                $expectedPass = (string) ($_SESSION['mock_auth']['password'] ?? 'admin123');

                if ($id === $expectedId && hash_equals($expectedPass, $pass)) {
                    $_SESSION['mock_auth']['logged_in'] = true;
                    $sendJson([
                        'ok' => true,
                        'message' => 'Log masuk mock berjaya.',
                        'auth' => Bootstrap::getPublicAuthState(),
                    ]);
                }
                $sendJson([
                    'ok' => false,
                    'message' => 'ID atau Kata Laluan tidak sepadan.',
                    'auth' => Bootstrap::getPublicAuthState(),
                ], 401);
            } elseif ($sub === 'logout') {
                $_SESSION['mock_auth']['logged_in'] = false;
                $sendJson([
                    'ok' => true,
                    'message' => 'Log keluar berjaya.',
                    'auth' => Bootstrap::getPublicAuthState(),
                ]);
            } elseif ($sub === 'update_mock') {
                $newId = trim((string) ($input['new_id'] ?? ''));
                $newPass = (string) ($input['new_password'] ?? '');
                $newName = trim((string) ($input['new_name'] ?? ''));

                if ($newId === '') {
                    $sendJson(['ok' => false, 'message' => 'ID tidak boleh kosong.'], 422);
                }
                $_SESSION['mock_auth']['id'] = $newId;
                if ($newPass !== '') {
                    $_SESSION['mock_auth']['password'] = $newPass;
                }
                if ($newName !== '') {
                    $_SESSION['mock_auth']['name'] = $newName;
                }
                $sendJson([
                    'ok' => true,
                    'message' => 'Tetapan ID & Kata Laluan mock dikemaskini untuk sesi ini.',
                    'auth' => Bootstrap::getPublicAuthState(),
                ]);
            } else {
                $sendJson([
                    'ok' => true,
                    'auth' => Bootstrap::getPublicAuthState(),
                ]);
            }
            break;

        default:
            $sendJson([
                'ok' => false,
                'message' => 'Tindakan API tidak dikenali.',
            ], 400);
    }
} catch (Throwable $e) {
    $sendJson([
        'ok' => false,
        'demo_mode' => $repo->isDemoMode(),
        'message' => $e->getMessage(),
    ], 500);
}

