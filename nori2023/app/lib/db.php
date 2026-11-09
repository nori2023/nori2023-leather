<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config()['db'] ?? null;
        if (!$c) {
            throw new RuntimeException('DB is not configured');
        }
        $pdo = db_connect($c['host'], $c['name'], $c['user'], $c['pass'], (int) ($c['port'] ?? 3306));
    }
    return $pdo;
}

function db_connect(string $host, string $name, string $user, string $pass, int $port = 3306): PDO
{
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );
    $pdo->exec("SET time_zone = '+09:00'");
    return $pdo;
}

function db_all(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function db_value(string $sql, array $params = []): mixed
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

function db_exec(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/** IN (?, ?, ...) 用のプレースホルダー */
function db_in(array $values): string
{
    return implode(',', array_fill(0, max(1, count($values)), '?'));
}

/**
 * 未適用のスキーマ更新（app/migrations/*.sql）を番号順に適用する。
 * 管理画面を開いたときに自動で実行される。
 */
function db_migrate(?PDO $pdo = null): array
{
    $pdo ??= db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(100) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $done = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $applied = [];
    $files = glob(APP_DIR . '/migrations/*.sql') ?: [];
    sort($files, SORT_STRING);
    foreach ($files as $file) {
        $version = basename($file, '.sql');
        if (in_array($version, $done, true)) {
            continue;
        }
        foreach (sql_statements((string) file_get_contents($file)) as $sql) {
            $pdo->exec($sql);
        }
        $st = $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, NOW())');
        $st->execute([$version]);
        $applied[] = $version;
    }
    return $applied;
}

/** SQLファイルを文単位に分割（-- コメント行を除去。文中にセミコロンを含めない前提） */
function sql_statements(string $sql): array
{
    $lines = array_filter(
        explode("\n", str_replace("\r\n", "\n", $sql)),
        fn ($l) => !str_starts_with(ltrim($l), '--'),
    );
    $parts = array_map('trim', explode(';', implode("\n", $lines)));
    return array_values(array_filter($parts, fn ($p) => $p !== ''));
}

function audit(string $action, ?string $target = null, mixed $detail = null): void
{
    db_exec(
        'INSERT INTO audit_logs (actor, action, target, detail, created_at) VALUES (?, ?, ?, ?, NOW())',
        [current_admin()['email'] ?? 'system', $action, $target, $detail === null ? null : json_encode($detail, JSON_UNESCAPED_UNICODE)],
    );
}
