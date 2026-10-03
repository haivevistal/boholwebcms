<?php

namespace App\Cms\Install;

use PDO;
use PDOException;
use Throwable;

/**
 * Talks to the database server with plain PDO during installation — before
 * Laravel's connection is configured — to test credentials, report the
 * server version and (optionally) create the database.
 */
class DatabaseProbe
{
    public const DRIVERS = [
        'mysql' => ['label' => 'MySQL', 'pdo' => 'pdo_mysql', 'port' => 3306, 'min' => '5.7'],
        'mariadb' => ['label' => 'MariaDB', 'pdo' => 'pdo_mysql', 'port' => 3306, 'min' => '10.3'],
        'pgsql' => ['label' => 'PostgreSQL', 'pdo' => 'pdo_pgsql', 'port' => 5432, 'min' => '10'],
        'sqlsrv' => ['label' => 'SQL Server', 'pdo' => 'pdo_sqlsrv', 'port' => 1433, 'min' => '2017'],
        'sqlite' => ['label' => 'SQLite', 'pdo' => 'pdo_sqlite', 'port' => null, 'min' => '3.26'],
    ];

    /**
     * @param  array{driver:string,host?:string,port?:int|string,database:string,username?:string,password?:string,prefix?:string}  $c
     * @return array{ok:bool,message:string,version:?string,database_exists:bool,can_create:bool,existing_tables:int,warning:?string}
     */
    public function test(array $c): array
    {
        $result = ['ok' => false, 'message' => '', 'version' => null, 'database_exists' => false, 'can_create' => false, 'existing_tables' => 0, 'warning' => null];
        $driver = $c['driver'] ?? '';

        if (! isset(self::DRIVERS[$driver])) {
            return ['message' => 'Unsupported database type.'] + $result;
        }
        if (! extension_loaded(self::DRIVERS[$driver]['pdo'])) {
            return ['message' => 'The PHP extension '.self::DRIVERS[$driver]['pdo'].' is not installed on this server, so '.self::DRIVERS[$driver]['label'].' can’t be used.'] + $result;
        }

        if ($driver === 'sqlite') {
            return $this->testSqlite($c, $result);
        }

        if (! static::validName($c['database'] ?? '')) {
            return ['message' => 'Database names may only contain letters, numbers, underscores and dashes.'] + $result;
        }

        try {
            $pdo = $this->connect($c, withDatabase: true);
            $result['database_exists'] = true;
        } catch (PDOException $e) {
            if ($this->isUnknownDatabase($driver, $e)) {
                // Server reachable, database missing → offer to create it.
                try {
                    $pdo = $this->connect($c, withDatabase: false);
                    $result['version'] = $this->version($pdo, $driver);
                    $result['can_create'] = true;

                    return ['message' => 'Connected to the server, but the database “'.$c['database'].'” does not exist yet. The installer can create it for you.'] + $result;
                } catch (PDOException $inner) {
                    return ['message' => $this->friendly($inner)] + $result;
                }
            }

            return ['message' => $this->friendly($e)] + $result;
        }

        $result['version'] = $this->version($pdo, $driver);
        $result['existing_tables'] = $this->countPrefixedTables($pdo, $driver, $c['prefix'] ?? '');
        $result['ok'] = true;
        $result['message'] = 'Connected to '.self::DRIVERS[$driver]['label'].($result['version'] ? ' '.$result['version'] : '').' successfully.';

        if ($result['version'] && $this->tooOld($driver, $result['version'])) {
            $result['warning'] = self::DRIVERS[$driver]['label'].' '.self::DRIVERS[$driver]['min'].' or newer is recommended.';
        }
        if ($result['existing_tables'] > 0) {
            $result['warning'] = trim(($result['warning'] ?? '').' This database already contains '.$result['existing_tables'].' table(s) with the prefix “'.($c['prefix'] ?: '(none)').'”. Existing BoholwebCMS tables will be reused; use a different table prefix for a separate site.');
        }

        return $result;
    }

    public function createDatabase(array $c): void
    {
        $driver = $c['driver'];
        if (! static::validName($c['database'] ?? '')) {
            throw new \RuntimeException('Invalid database name.');
        }
        $pdo = $this->connect($c, withDatabase: false);
        $name = $c['database'];

        match ($driver) {
            'mysql', 'mariadb' => $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'),
            'pgsql' => $this->createPg($pdo, $name),
            'sqlsrv' => $pdo->exec("IF DB_ID(N'{$name}') IS NULL CREATE DATABASE [{$name}]"),
            default => null,
        };
    }

    public static function validName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $name);
    }

    /* ------------------------------------------------------------------ */

    protected function testSqlite(array $c, array $result): array
    {
        $path = $c['database'] ?? '';
        if ($path === '') {
            return ['message' => 'Enter a path for the SQLite database file.'] + $result;
        }
        $dir = dirname($path);
        if (! is_dir($dir)) {
            return ['message' => "The folder {$dir} does not exist."] + $result;
        }
        if (! is_file($path) && ! is_writable($dir)) {
            return ['message' => "The folder {$dir} is not writable, so the database file can’t be created."] + $result;
        }
        if (is_file($path) && ! is_writable($path)) {
            return ['message' => 'The SQLite file exists but is not writable.'] + $result;
        }

        try {
            $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $result['version'] = (string) $pdo->query('select sqlite_version()')->fetchColumn();
            $result['existing_tables'] = $this->countPrefixedTables($pdo, 'sqlite', $c['prefix'] ?? '');
        } catch (Throwable $e) {
            return ['message' => 'Could not open the SQLite database: '.$e->getMessage()] + $result;
        }

        $result['ok'] = true;
        $result['database_exists'] = true;
        $result['message'] = 'SQLite '.$result['version'].' is ready'.(is_file($path) ? '.' : ' (the file was created).');
        if ($result['existing_tables'] > 0) {
            $result['warning'] = 'This SQLite file already contains '.$result['existing_tables'].' table(s); existing BoholwebCMS tables will be reused.';
        }

        return $result;
    }

    protected function connect(array $c, bool $withDatabase): PDO
    {
        $driver = $c['driver'];
        $host = trim($c['host'] ?? '') ?: '127.0.0.1';
        $port = (int) ($c['port'] ?? 0) ?: self::DRIVERS[$driver]['port'];
        $db = $c['database'] ?? '';

        $dsn = match ($driver) {
            'mysql', 'mariadb' => "mysql:host={$host};port={$port}".($withDatabase ? ";dbname={$db}" : '').';charset=utf8mb4',
            'pgsql' => "pgsql:host={$host};port={$port};dbname=".($withDatabase ? $db : 'postgres'),
            'sqlsrv' => "sqlsrv:Server={$host},{$port}".($withDatabase ? ";Database={$db}" : '').';TrustServerCertificate=1',
        };

        return new PDO($dsn, $c['username'] ?? '', $c['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }

    protected function version(PDO $pdo, string $driver): ?string
    {
        try {
            $v = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

            return preg_replace('/^5\.5\.5-/', '', $v); // MariaDB's legacy prefix
        } catch (Throwable) {
            return null;
        }
    }

    protected function tooOld(string $driver, string $version): bool
    {
        preg_match('/\d+(\.\d+)?/', $version, $m);

        return isset($m[0]) && version_compare($m[0], self::DRIVERS[$driver]['min'], '<') && $driver !== 'sqlsrv';
    }

    protected function countPrefixedTables(PDO $pdo, string $driver, string $prefix): int
    {
        try {
            $sql = match ($driver) {
                'mysql', 'mariadb' => "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE ? ESCAPE '!'",
                'pgsql' => "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name LIKE ? ESCAPE '!'",
                'sqlsrv' => "SELECT COUNT(*) FROM information_schema.tables WHERE table_type = 'BASE TABLE' AND table_name LIKE ? ESCAPE '!'",
                'sqlite' => "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name LIKE ? ESCAPE '!'",
            };
            $stmt = $pdo->prepare($sql);
            $like = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $prefix);
            $stmt->execute([$like.'options']);
            $hasOptions = (int) $stmt->fetchColumn() > 0;
            if (! $hasOptions) {
                return 0;
            }
            $stmt->execute([$like.'%']);

            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    protected function isUnknownDatabase(string $driver, PDOException $e): bool
    {
        $msg = $e->getMessage();

        return match ($driver) {
            'mysql', 'mariadb' => str_contains($msg, '1049') || stripos($msg, 'Unknown database') !== false,
            'pgsql' => stripos($msg, 'does not exist') !== false && stripos($msg, 'database') !== false,
            'sqlsrv' => stripos($msg, 'Cannot open database') !== false,
            default => false,
        };
    }

    protected function friendly(PDOException $e): string
    {
        $msg = $e->getMessage();

        return match (true) {
            str_contains($msg, '1045') || stripos($msg, 'Access denied') !== false || stripos($msg, 'password authentication failed') !== false || stripos($msg, 'Login failed') !== false
                => 'The username or password was rejected by the database server. Double-check them (your host’s control panel lists them).',
            str_contains($msg, '2002') || stripos($msg, 'Connection refused') !== false || stripos($msg, 'could not connect') !== false || stripos($msg, 'getaddrinfo') !== false || stripos($msg, 'timed out') !== false
                => 'Can’t reach the database server. Check the host and port — on shared hosting the host is usually “localhost”.',
            str_contains($msg, '1044') => 'The user doesn’t have access to that database. Grant it privileges in your hosting panel.',
            default => 'Database error: '.$msg,
        };
    }

    protected function createPg(PDO $pdo, string $name): void
    {
        $exists = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
        $exists->execute([$name]);
        if (! $exists->fetchColumn()) {
            $pdo->exec('CREATE DATABASE "'.$name.'"');
        }
    }
}
