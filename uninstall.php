<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Queue data is retained by default. To remove it during uninstall, define:
// define('KODR_SRA_DELETE_DATA_ON_UNINSTALL', true);
if (!defined('KODR_SRA_DELETE_DATA_ON_UNINSTALL') || KODR_SRA_DELETE_DATA_ON_UNINSTALL !== true) {
    return;
}

global $wpdb;
$table = $wpdb->prefix . 'kodr_sra_queue';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query("DROP TABLE IF EXISTS {$table}");
delete_option('kodr_sra_db_version');
