<?php

declare(strict_types=1);

use Kodr\SecureReferralArchive\Queue\QueueRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class Kodr_SRA_Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate(): void
    {
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            deactivate_plugins(plugin_basename(KODR_SRA_FILE));
            wp_die('Kodr Secure Referral Archive requires PHP 8.1 or later.');
        }
        QueueRepository::install();
    }

    public function boot(): void
    {
        Kodr_SRA_Admin::hooks();

        if (class_exists('GFForms')) {
            Kodr_SRA_Gravity_Forms::hooks();
        }
    }
}
