<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Kodr_SRA_Queue
{
    public const TABLE_SUFFIX = 'kodr_sra_queue';

    public static function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table_name();
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
        update_option('kodr_sra_db_version', KODR_SRA_VERSION, false);
    }

    /** @return array<string,int> */
    public static function counts(): array
    {
        global $wpdb;
        $table = self::table_name();
        $statuses = ['pending', 'processing', 'retry', 'completed', 'failed'];
        $counts = array_fill_keys($statuses, 0);

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

    public static function last_completed_at(): ?string
    {
        global $wpdb;
        $table = self::table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $value = $wpdb->get_var("SELECT completed_at FROM {$table} WHERE status = 'completed' ORDER BY completed_at DESC LIMIT 1");
        return is_string($value) && $value !== '' ? $value : null;
    }
}
