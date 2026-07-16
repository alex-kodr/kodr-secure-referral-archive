<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Tests\Support;

/**
 * A minimal $wpdb double backed by an in-memory SQLite database, translating
 * enough of wpdb's API (prepare/query/get_row/get_results/get_var/insert_id)
 * for QueueRepository to be exercised in unit tests without a real
 * WordPress/MySQL environment.
 */
final class InMemoryWpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
        $this->pdo->exec('CREATE TABLE wp_kodr_sra_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reference TEXT NOT NULL UNIQUE,
            form_id INTEGER NOT NULL,
            entry_id INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT \'pending\',
            attempts INTEGER NOT NULL DEFAULT 0,
            next_attempt_at TEXT NULL,
            last_attempt_at TEXT NULL,
            json_key TEXT NULL,
            pdf_key TEXT NULL,
            last_error_code TEXT NULL,
            last_error_message TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            completed_at TEXT NULL,
            UNIQUE(form_id, entry_id)
        )');
    }

    public function prepare(string $query, mixed ...$args): string
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        $i = 0;

        return preg_replace_callback('/%[sdf]/', static function (array $matches) use (&$i, $args): string {
            $value = $args[$i++];

            return match ($matches[0]) {
                '%s' => "'" . str_replace("'", "''", (string) $value) . "'",
                '%d' => (string) (int) $value,
                '%f' => (string) (float) $value,
            };
        }, $query) ?? $query;
    }

    public function query(string $sql): int|false
    {
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute();
        if (!$ok) {
            return false;
        }

        if (stripos(ltrim($sql), 'insert') === 0) {
            $this->insert_id = (int) $this->pdo->lastInsertId();
        }

        return $stmt->rowCount();
    }

    /** @return array<int,array<string,mixed>> */
    public function get_results(string $sql, $output = null): array
    {
        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string,mixed>|null */
    public function get_row(string $sql, $output = null): ?array
    {
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function get_var(string $sql): mixed
    {
        $stmt = $this->pdo->query($sql);
        $value = $stmt->fetchColumn();

        return $value === false ? null : $value;
    }

    public function get_charset_collate(): string
    {
        return '';
    }
}
