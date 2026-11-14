<?php
declare(strict_types=1);

require_once __DIR__ . '/OracleClient.php';
require_once __DIR__ . '/DemoDataset.php';
require_once __DIR__ . '/IndexedTableCache.php';
require_once __DIR__ . '/TunggakanRepository.php';
require_once __DIR__ . '/ReportEngine.php';

/**
 * Inisialisasi bersama untuk `index.php` dan `api.php` (konfigurasi, sesi, profil skema, mod data, auth mock).
 */
final class Bootstrap
{
    private static ?array $config = null;
    /** @var array<string, OracleClient> */
    private static array $clients = [];

    public static function init(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            $sessPath = dirname(__DIR__) . '/cache/sessions';
            if (!is_dir($sessPath)) {
                @mkdir($sessPath, 0777, true);
            }
            if (is_dir($sessPath) && is_writable($sessPath)) {
                @session_save_path($sessPath);
            }
            @session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
            ]);
        }

        if (self::$config === null) {
            self::$config = require dirname(__DIR__) . '/config.php';
        }

        if (!isset($_SESSION['mock_auth'])) {
            $_SESSION['mock_auth'] = [
                'logged_in' => false,
                'id' => self::$config['mock_auth']['default_id'],
                'password' => self::$config['mock_auth']['default_password'],
                'name' => self::$config['mock_auth']['display_name'],
            ];
        }

        if (isset($_GET['demo'])) {
            $_SESSION['demo_mode'] = ($_GET['demo'] === '1' || $_GET['demo'] === 'true');
        }

        $demoMode = isset($_SESSION['demo_mode'])
            ? (bool) $_SESSION['demo_mode']
            : (bool) (self::$config['oracle']['demo_mode'] ?? false);

        $cacheDir = dirname(__DIR__) . '/cache';
        $cacheTtl = (int) (self::$config['app']['cache_ttl_seconds'] ?? 600);

        $client = self::getClientForProfile('default');
        $repo = new TunggakanRepository($client, $demoMode, $cacheDir, $cacheTtl);
        $reportEngine = new ReportEngine(
            static fn(?string $profile = null): OracleClient => self::getClientForProfile($profile),
            $demoMode,
            null,
            $cacheDir,
            $cacheTtl
        );

        return [
            'config' => self::$config,
            'client' => $client,
            'repo' => $repo,
            'report_engine' => $reportEngine,
            'demo_mode' => $demoMode,
        ];
    }

    public static function getClientForProfile(?string $profile = null): OracleClient
    {
        if (self::$config === null) {
            self::$config = require dirname(__DIR__) . '/config.php';
        }
        $key = ($profile === null || $profile === '') ? 'default' : $profile;
        if (isset(self::$clients[$key])) {
            return self::$clients[$key];
        }

        $profiles = self::$config['oracle_profiles'] ?? [];
        $cfg = $profiles[$key] ?? self::$config['oracle'];
        self::$clients[$key] = new OracleClient($cfg);
        return self::$clients[$key];
    }

    /**
     * Senarai nama profil skema Oracle tanpa mendedahkan kata laluan.
     *
     * @return array<int, array{key: string, label: string, configured: bool}>
     */
    public static function getPublicProfileList(): array
    {
        if (self::$config === null) {
            self::$config = require dirname(__DIR__) . '/config.php';
        }
        $out = [];
        foreach ((self::$config['oracle_profiles'] ?? []) as $key => $cfg) {
            if ($key === 'default') {
                continue;
            }
            $out[] = [
                'key' => (string) $key,
                'label' => (string) ($cfg['label'] ?? strtoupper((string) $key)),
                'configured' => trim((string) ($cfg['username'] ?? '')) !== ''
                    && trim((string) ($cfg['password'] ?? '')) !== '',
            ];
        }
        return $out;
    }

    public static function getPublicAuthState(): array
    {
        $auth = $_SESSION['mock_auth'] ?? [];
        return [
            'logged_in' => (bool) ($auth['logged_in'] ?? false),
            'id' => (string) ($auth['id'] ?? 'admin'),
            'name' => (string) ($auth['name'] ?? 'Pentadbir Hasil'),
        ];
    }

    public static function getSqlReference(): string
    {
        return <<<'SQL'
SELECT
  c.peg_akaun AS PEG_AKAUN,
  c.peg_jlkod AS PEG_JLKOD,
  e.jln_jnama AS JLN_JNAMA,
  (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'12010',d.pyt_amaun,0))
    + SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0))) AS BIL_TUNGGAKAN,
  (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'22010',d.pyt_amaun,0))
    - SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0))) AS TERIMA_TUNGGAKAN,
  (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'12010',d.pyt_amaun,0))
    + SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0)))
    - (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'22010',d.pyt_amaun,0))
    - SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0))) AS BAKI_TUNGGAKAN,
  (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11010',d.pyt_amaun,0))
    + SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0))) AS BIL_SEMASA,
  (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'21010',d.pyt_amaun,0))
    - SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0))) AS TERIMA_SEMASA,
  (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11010',d.pyt_amaun,0))
    + SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0)))
    - (SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'21010',d.pyt_amaun,0))
    - SUM(DECODE(SUBSTR(d.pyt_kdrjk,1,5),'11090',d.pyt_amaun,0))) AS BAKI_SEMASA
FROM mtkn.hvnduk2 c, mtkn.hlpyt1 d, spbt.fjalan e
WHERE c.jpk_itkod = '11010'
  AND c.peg_akaun = d.pyt_akaun
  AND c.peg_jlkod = e.jln_jlkod
  AND c.peg_statf != 'H'
  -- Tapisan dinamik mesra indeks Oracle:
  -- AND c.peg_jlkod = :jlkod
  -- AND TO_CHAR(c.peg_akaun) LIKE :akaun
GROUP BY c.peg_akaun, c.peg_jlkod, e.jln_jnama
ORDER BY c.peg_akaun;
SQL;
    }
}
