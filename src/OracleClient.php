<?php
declare(strict_types=1);

/**
 * Klien pangkalan data Oracle 10g dengan sokongan dwi-pemacu (OCI8 dan PDO_OCI).
 * Menggunakan pembolehubah terikat (bind parameters) untuk semua input pengguna.
 */
final class OracleClient
{
    private array $config;
    /** @var resource|\PDO|null */
    private $connection = null;
    private ?string $activeDriver = null;

    public function __construct(array $oracleConfig)
    {
        $this->config = $oracleConfig;
    }

    public function getAvailableDriver(): ?string
    {
        if (function_exists('oci_connect')) {
            return 'oci8';
        }
        if (extension_loaded('pdo_oci') && class_exists('PDO')) {
            return 'pdo_oci';
        }
        return null;
    }

    public function isConfigured(): bool
    {
        return trim((string) ($this->config['username'] ?? '')) !== ''
            && trim((string) ($this->config['password'] ?? '')) !== '';
    }

    /**
     * Membuka sambungan ke Oracle 10g jika belum wujud.
     *
     * @throws RuntimeException
     */
    public function connect(): void
    {
        if ($this->connection !== null) {
            return;
        }

        $driver = $this->getAvailableDriver();
        if ($driver === null) {
            throw new RuntimeException(
                'Pemacu PHP Oracle (extension=oci8 atau extension=pdo_oci) belum diaktifkan di dalam php.ini.'
            );
        }

        if (!$this->isConfigured()) {
            throw new RuntimeException(
                'ID pengguna atau kata laluan Oracle belum ditetapkan di dalam fail config.local.php.'
            );
        }

        $user = (string) $this->config['username'];
        $pass = (string) $this->config['password'];
        $tns = (string) $this->config['tns_descriptor'];
        $charset = (string) ($this->config['charset'] ?? 'AL32UTF8');

        if ($driver === 'oci8') {
            $conn = @oci_connect($user, $pass, $tns, $charset);
            if ($conn === false) {
                $err = oci_error();
                $msg = is_array($err) && isset($err['message']) ? $err['message'] : 'Gagal menyambung melalui OCI8.';
                throw new RuntimeException($msg);
            }
            $this->connection = $conn;
            $this->activeDriver = 'oci8';
            return;
        }

        try {
            $dsn = sprintf('oci:dbname=%s;charset=%s', $tns, $charset);
            $pdoOptions = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_CASE => PDO::CASE_UPPER,
            ];
            if (defined('PDO::ATTR_PREFETCH')) {
                $pdoOptions[PDO::ATTR_PREFETCH] = 1000;
            }
            $pdo = new PDO($dsn, $user, $pass, $pdoOptions);
            $this->connection = $pdo;
            $this->activeDriver = 'pdo_oci';
        } catch (PDOException $e) {
            throw new RuntimeException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Melaksanakan kuiri SELECT dan mengembalikan semua baris sebagai tatasusunan bersekutu.
     *
     * @return array<int, array<string, mixed>>
     */
    public function query(string $sql, array $params = []): array
    {
        $rows = [];
        $this->streamQuery($sql, $params, static function (array $row) use (&$rows): void {
            $rows[] = $row;
        });
        return $rows;
    }

    /**
     * Melaksanakan kuiri SELECT dan mengembalikan baris pertama sahaja atau null.
     *
     * @return array<string, mixed>|null
     */
    public function queryOne(string $sql, array $params = []): ?array
    {
        $rows = $this->query($sql, $params);
        return $rows[0] ?? null;
    }

    /**
     * Menstrim hasil kuiri baris demi baris untuk kegunaan jadual atau eksport CSV berskala besar.
     */
    public function streamQuery(string $sql, array $params, callable $onRow): void
    {
        $this->connect();

        if ($this->activeDriver === 'oci8') {
            $stmt = @oci_parse($this->connection, $sql);
            if ($stmt === false) {
                $err = oci_error($this->connection);
                throw new RuntimeException($err['message'] ?? 'Ralat oci_parse.');
            }

            foreach ($params as $key => $value) {
                $paramName = str_starts_with((string) $key, ':') ? (string) $key : ':' . $key;
                $boundVal = $value;
                @oci_bind_by_name($stmt, $paramName, $boundVal, -1);
            }

            if (function_exists('oci_set_prefetch')) {
                @oci_set_prefetch($stmt, 1000);
            }

            if (@oci_execute($stmt) === false) {
                $err = oci_error($stmt);
                @oci_free_statement($stmt);
                throw new RuntimeException($err['message'] ?? 'Ralat oci_execute.');
            }

            while (($row = oci_fetch_assoc($stmt)) !== false) {
                $onRow(array_change_key_case($row, CASE_UPPER));
            }
            oci_free_statement($stmt);
            return;
        }

        /** @var PDO $pdo */
        $pdo = $this->connection;
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $paramName = str_starts_with((string) $key, ':') ? (string) $key : ':' . $key;
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($paramName, $value, $type);
        }
        $stmt->execute();

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $onRow(array_change_key_case($row, CASE_UPPER));
        }
        $stmt->closeCursor();
    }

    /**
     * Menguji sambungan ke pelayan Oracle 10g dan mengembalikan maklumat diagnostik.
     *
     * @return array<string, mixed>
     */
    public function testConnection(): array
    {
        $driver = $this->getAvailableDriver();
        $result = [
            'ok' => false,
            'driver' => $driver ?? 'tiada',
            'configured' => $this->isConfigured(),
            'tns_alias' => (string) ($this->config['tns_alias'] ?? 'ORCLVM55'),
            'host' => (string) ($this->config['host'] ?? ''),
            'port' => (int) ($this->config['port'] ?? 1521),
            'service_name' => (string) ($this->config['service_name'] ?? ''),
            'server_version' => null,
            'latency_ms' => null,
            'message' => '',
        ];

        $startedAt = microtime(true);
        try {
            $this->connect();
            $version = null;

            if ($this->activeDriver === 'oci8' && function_exists('oci_server_version')) {
                $ver = @oci_server_version($this->connection);
                if (is_string($ver) && $ver !== '') {
                    $version = $ver;
                }
            }

            if ($version === null) {
                try {
                    $row = $this->queryOne('SELECT BANNER FROM V$VERSION WHERE ROWNUM = 1');
                    $version = $row['BANNER'] ?? null;
                } catch (Throwable $ignored) {
                    $this->queryOne('SELECT 1 AS OK FROM DUAL');
                    $version = 'Oracle Database (V$VERSION terhad, DUAL berjaya)';
                }
            }

            $result['ok'] = true;
            $result['driver'] = $this->activeDriver;
            $result['server_version'] = $version;
            $result['latency_ms'] = round((microtime(true) - $startedAt) * 1000, 1);
            $result['message'] = 'Sambungan ke Oracle 10g berjaya disahkan.';
        } catch (Throwable $e) {
            $result['latency_ms'] = round((microtime(true) - $startedAt) * 1000, 1);
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function close(): void
    {
        if ($this->connection !== null && $this->activeDriver === 'oci8') {
            @oci_close($this->connection);
        }
        $this->connection = null;
        $this->activeDriver = null;
    }
}

