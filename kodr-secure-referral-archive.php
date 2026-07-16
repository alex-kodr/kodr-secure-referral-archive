<?php
/**
 * Plugin Name:       Kodr Secure Referral Archive
 * Plugin URI:        https://kodr.io/
 * Description:       Secure off-site archiving foundation for selected Gravity Forms submissions.
 * Version:           0.1.0
 * Author:            Kodr Digital Ltd
 * Author URI:        https://kodr.io/
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Text Domain:       kodr-secure-referral-archive
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('KODR_SRA_VERSION', '0.1.0');
define('KODR_SRA_FILE', __FILE__);
define('KODR_SRA_DIR', plugin_dir_path(__FILE__));
define('KODR_SRA_URL', plugin_dir_url(__FILE__));

if (!file_exists(KODR_SRA_DIR . 'vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p><strong>Kodr Secure Referral Archive:</strong> Composer dependencies are missing. Run <code>composer install</code> in the plugin directory.</p></div>';
    });
    return;
}

require_once KODR_SRA_DIR . 'vendor/autoload.php';

require_once KODR_SRA_DIR . 'includes/class-kodr-sra-admin.php';
require_once KODR_SRA_DIR . 'includes/class-kodr-sra-gravity-forms.php';
require_once KODR_SRA_DIR . 'includes/class-kodr-sra-plugin.php';

register_activation_hook(__FILE__, ['Kodr_SRA_Plugin', 'activate']);

add_action('plugins_loaded', static function (): void {
    Kodr_SRA_Plugin::instance()->boot();
});
