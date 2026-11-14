<?php
declare(strict_types=1);

/**
 * Konfigurasi teras Sistem Dashboard Tunggakan & Terimaan Ikut Jalan.
 * Jangan simpan kata laluan sebenar di dalam fail ini; gunakan config.local.php
 * atau pemboleh ubah persekitaran (environment variables).
 */

$localConfigPath = __DIR__ . '/config.local.php';
$localConfig = file_exists($localConfigPath) ? (require $localConfigPath) : [];
if (!is_array($localConfig)) {
    $localConfig = [];
}

$envOrLocal = static function (string $envKey, string $localKey, $default = null) use ($localConfig) {
    $envVal = getenv($envKey);
    if ($envVal !== false && $envVal !== '') {
        return $envVal;
    }
    if (array_key_exists($localKey, $localConfig) && $localConfig[$localKey] !== '' && $localConfig[$localKey] !== null) {
        return $localConfig[$localKey];
    }
    return $default;
};

$host = (string) $envOrLocal('ORACLE_HOST', 'oracle_host', '172.16.33.55');
$port = (int) $envOrLocal('ORACLE_PORT', 'oracle_port', 1521);
$serviceName = (string) $envOrLocal('ORACLE_SERVICE', 'oracle_service', 'orclvm53');
$charset = (string) $envOrLocal('ORACLE_CHARSET', 'oracle_charset', 'AL32UTF8');

$tnsDescriptor = sprintf(
    '(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=%s)(PORT=%d)))(CONNECT_DATA=(SERVICE_NAME=%s)(SERVER=DEDICATED)))',
    $host,
    $port,
    $serviceName
);

$baseOracle = [
    'tns_alias' => 'ORCLVM55',
    'host' => $host,
    'port' => $port,
    'service_name' => $serviceName,
    'tns_descriptor' => $tnsDescriptor,
    'username' => (string) $envOrLocal('ORACLE_USER', 'oracle_user', ''),
    'password' => (string) $envOrLocal('ORACLE_PASS', 'oracle_pass', ''),
    'charset' => $charset,
    'demo_mode' => filter_var($envOrLocal('APP_DEMO_MODE', 'demo_mode', false), FILTER_VALIDATE_BOOLEAN),
];

$profiles = ['default' => $baseOracle];
$rawProfiles = $localConfig['oracle_profiles'] ?? [];
if (is_array($rawProfiles)) {
    foreach ($rawProfiles as $profileKey => $profileCfg) {
        if (!is_array($profileCfg)) {
            continue;
        }
        $pHost = (string) ($profileCfg['host'] ?? $host);
        $pPort = (int) ($profileCfg['port'] ?? $port);
        $pService = (string) ($profileCfg['service_name'] ?? $serviceName);
        $profiles[(string) $profileKey] = [
            'tns_alias' => (string) ($profileCfg['tns_alias'] ?? 'ORCLVM55'),
            'host' => $pHost,
            'port' => $pPort,
            'service_name' => $pService,
            'tns_descriptor' => sprintf(
                '(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=%s)(PORT=%d)))(CONNECT_DATA=(SERVICE_NAME=%s)(SERVER=DEDICATED)))',
                $pHost,
                $pPort,
                $pService
            ),
            'username' => (string) ($profileCfg['username'] ?? ''),
            'password' => (string) ($profileCfg['password'] ?? ''),
            'charset' => (string) ($profileCfg['charset'] ?? $charset),
            'label' => (string) ($profileCfg['label'] ?? strtoupper((string) $profileKey)),
        ];
    }
}

return [
    'app' => [
        'name' => 'Portal Dashboard ePBT & Tunggakan',
        'short_name' => 'ePBT Dash',
        'subtitle' => 'Sistem Laporan Modular (Oracle 10g)',
        'version' => '1.1.0',
        'default_page_size' => 25,
        'max_page_size' => 200,
        'cache_ttl_seconds' => 3600,
    ],
    'oracle' => $baseOracle,
    'oracle_profiles' => $profiles,
    'mock_auth' => [
        'default_id' => (string) ($localConfig['mock_admin_id'] ?? 'admin'),
        'default_password' => (string) ($localConfig['mock_admin_password'] ?? 'admin123'),
        'display_name' => (string) ($localConfig['mock_admin_name'] ?? 'Pentadbir Hasil'),
    ],
];
