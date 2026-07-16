<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Queue;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Data access for the wp_kodr_sra_queue table. Stores operational metadata
 * only — never submitted field values, generated JSON, or PDF content.
 */
final class QueueRepository
{
    private const TABLE_SUFFIX = 'kodr_sra_queue';

    public static function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::tableName();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            reference varchar(64) NOT NULL,
            form_id bigint(20) unsigned NOT NULL,
            entry_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempts smallint(5) unsigned NOT NULL DEFAULT 0,
            next_attempt_at datetime NULL,
            last_attempt_at datetime NULL,
            json_key varchar(1024) NULL,
            pdf_key varchar(1024) NULL,
            last_error_code varchar(100) NULL,
            last_error_message text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            completed_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY reference (reference),
            UNIQUE KEY form_entry (form_id, entry_id),
            KEY status_next (status, next_attempt_at)
        ) {$charset};";

        dbDelta($sql);
        update_option('kodr_sra_db_version', defined('KODR_SRA_VERSION') ? KODR_SRA_VERSION : '', false);
    }

    /**
     * Idempotent: returns the existing item if this form/entry pair is
     * already queued, instead of creating a duplicate row.
     */
    public function enqueue(int $formId, int $entryId): QueueItem
    {
        $existing = $this->findByFormAndEntry($formId, $entryId);
        if ($existing !== null) {
            return $existing;
        }

        global $wpdb;
        $table = self::tableName();
        $now = current_time('mysql', true);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (reference, form_id, entry_id, status, attempts, next_attempt_at, created_at, updated_at) VALUES (%s, %d, %d, %s, %d, %s, %s, %s)",
            self::generateReference(),
            $formId,
            $entryId,
            QueueStatus::Pending->value,
            0,
            $now,
            $now,
            $now
        ));

        if ($inserted === false) {
            // Most likely a race on the unique (form_id, entry_id) key —
            // another request enqueued this entry first.
            $existing = $this->findByFormAndEntry($formId, $entryId);
            if ($existing !== null) {
                return $existing;
            }
        }

        $item = $this->findById((int) $wpdb->insert_id);
        if ($item === null) {
            // Extremely unlikely, but keep the return type non-nullable for callers.
            throw new \RuntimeException('Failed to enqueue archive job.');
        }

        return $item;
    }

    /** @return QueueItem[] */
    public function findDueItems(int $limit): array
    {
        global $wpdb;
        $table = self::tableName();
        $now = current_time('mysql', true);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status IN (%s, %s) AND (next_attempt_at IS NULL OR next_attempt_at <= %s) ORDER BY next_attempt_at ASC, id ASC LIMIT %d",
            QueueStatus::Pending->value,
            QueueStatus::Retry->value,
            $now,
            $limit
        ), ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn (array $row): QueueItem => QueueItem::fromRow($row), $rows);
    }

    public function findById(int $id): ?QueueItem
    {
        global $wpdb;
        $table = self::tableName();

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);

        return is_array($row) ? QueueItem::fromRow($row) : null;
    }

    public function findByFormAndEntry(int $formId, int $entryId): ?QueueItem
    {
        global $wpdb;
        $table = self::tableName();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE form_id = %d AND entry_id = %d",
            $formId,
            $entryId
        ), ARRAY_A);

        return is_array($row) ? QueueItem::fromRow($row) : null;
    }

    /**
     * Atomically claims a due item for processing. Returns false if the item
     * was not in a claimable state (e.g. another worker already claimed it),
     * so callers must not assume the item is now theirs unless this returns
     * true.
     */
    public function markProcessing(int $id): bool
    {
        global $wpdb;
        $table = self::tableName();
        $now = current_time('mysql', true);

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = %s, attempts = attempts + 1, last_attempt_at = %s, updated_at = %s WHERE id = %d AND status IN (%s, %s)",
            QueueStatus::Processing->value,
            $now,
            $now,
            $id,
            QueueStatus::Pending->value,
            QueueStatus::Retry->value
        ));

        return is_int($updated) && $updated > 0;
    }

    public function markCompleted(int $id, string $jsonKey, string $pdfKey): bool
    {
        global $wpdb;
        $table = self::tableName();
        $now = current_time('mysql', true);

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = %s, json_key = %s, pdf_key = %s, completed_at = %s, updated_at = %s WHERE id = %d",
            QueueStatus::Completed->value,
            $jsonKey,
            $pdfKey,
            $now,
            $now,
            $id
        ));

        return is_int($updated) && $updated > 0;
    }

    public function scheduleRetry(
        int $id,
        \DateTimeImmutable $nextAttemptAt,
        string $errorCode,
        string $errorMessage
    ): bool {
        global $wpdb;
        $table = self::tableName();
        $now = current_time('mysql', true);

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = %s, next_attempt_at = %s, last_error_code = %s, last_error_message = %s, updated_at = %s WHERE id = %d",
            QueueStatus::Retry->value,
            $nextAttemptAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            $errorCode,
            $errorMessage,
            $now,
            $id
        ));

        return is_int($updated) && $updated > 0;
    }

    public function markFailed(int $id, string $errorCode, string $errorMessage): bool
    {
        global $wpdb;
        $table = self::tableName();
        $now = current_time('mysql', true);

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = %s, last_error_code = %s, last_error_message = %s, updated_at = %s WHERE id = %d",
            QueueStatus::Failed->value,
            $errorCode,
            $errorMessage,
            $now,
            $id
        ));

        return is_int($updated) && $updated > 0;
    }

    /** @return array<string,int> */
    public function countByStatus(): array
    {
        global $wpdb;
        $table = self::tableName();
        $counts = array_fill_keys(array_map(static fn (QueueStatus $status): string => $status->value, QueueStatus::cases()), 0);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A);
        if (!is_array($rows)) {
            return $counts;
        }

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row['total'];
            }
        }

        return $counts;
    }

    public function lastCompletedAt(): ?string
    {
        global $wpdb;
        $table = self::tableName();

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT completed_at FROM {$table} WHERE status = %s ORDER BY completed_at DESC LIMIT 1",
            QueueStatus::Completed->value
        ));

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array{items: QueueItem[], total: int}
     */
    public function paginate(?QueueStatus $status, int $page, int $perPage): array
    {
        global $wpdb;
        $table = self::tableName();
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        if ($status !== null) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = %s",
                $status->value
            ));

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
                $status->value,
                $perPage,
                $offset
            ), ARRAY_A);
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
                $perPage,
                $offset
            ), ARRAY_A);
        }

        $items = is_array($rows) ? array_map(static fn (array $row): QueueItem => QueueItem::fromRow($row), $rows) : [];

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Manually retries a failed or in-retry item immediately: resets it to
     * pending with a clean attempt count, so it gets a fresh full retry
     * schedule (an admin retrying by hand has presumably fixed whatever
     * caused the failure, e.g. AWS credentials).
     */
    public function retryNow(int $id): bool
    {
        global $wpdb;
        $table = self::tableName();
        $now = current_time('mysql', true);

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = %s, attempts = 0, next_attempt_at = NULL, last_error_code = NULL, last_error_message = NULL, updated_at = %s WHERE id = %d AND status IN (%s, %s)",
            QueueStatus::Pending->value,
            $now,
            $id,
            QueueStatus::Retry->value,
            QueueStatus::Failed->value
        ));

        return is_int($updated) && $updated > 0;
    }

    private static function generateReference(): string
    {
        return sprintf('REF-%s-%s', gmdate('Ymd'), strtoupper(bin2hex(random_bytes(3))));
    }
}
