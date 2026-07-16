<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Kodr_SRA_Admin
{
    private const PAGE_SLUG = 'kodr-secure-referral-archive';

    public static function hooks(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 30);
        add_action('admin_post_kodr_sra_test_connection', [self::class, 'test_connection']);
        add_action('admin_notices', [self::class, 'dependency_notice']);
    }

    public static function menu(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (class_exists('GFForms')) {
            add_submenu_page(
                'gf_edit_forms',
                __('Secure Referral Archive', 'kodr-secure-referral-archive'),
                __('Secure Referral Archive', 'kodr-secure-referral-archive'),
                'manage_options',
                self::PAGE_SLUG,
                [self::class, 'render']
            );
        } else {
            add_management_page(
                __('Secure Referral Archive', 'kodr-secure-referral-archive'),
                __('Secure Referral Archive', 'kodr-secure-referral-archive'),
                'manage_options',
                self::PAGE_SLUG,
                [self::class, 'render']
            );
        }
    }

    public static function dependency_notice(): void
    {
        if (class_exists('GFForms') || !current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-warning"><p><strong>Kodr Secure Referral Archive:</strong> Gravity Forms must be active before form archiving can be enabled.</p></div>';
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'kodr-secure-referral-archive'));
        }

        $config = Kodr_SRA_Config::all();
        $missing = Kodr_SRA_Config::missing_keys();
        $counts = Kodr_SRA_Queue::counts();
        $forms = Kodr_SRA_Gravity_Forms::forms();
        $last = Kodr_SRA_Queue::last_uploaded_gmt();
        $notice = isset($_GET['kodr_sra_notice']) ? sanitize_key(wp_unslash($_GET['kodr_sra_notice'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $message = isset($_GET['kodr_sra_message']) ? sanitize_text_field(wp_unslash($_GET['kodr_sra_message'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Kodr Secure Referral Archive', 'kodr-secure-referral-archive'); ?></h1>
            <p><?php esc_html_e('Version 0.1 validates configuration, adds per-form enablement and tests write-only S3 uploads.', 'kodr-secure-referral-archive'); ?></p>

            <?php if ($notice === 'success') : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message ?: 'S3 test upload completed successfully.'); ?></p></div>
            <?php elseif ($notice === 'error') : ?>
                <div class="notice notice-error"><p><?php echo esc_html($message ?: 'S3 test upload failed.'); ?></p></div>
            <?php endif; ?>

            <table class="widefat striped" style="max-width:1000px">
                <tbody>
                <tr><th style="width:240px">Plugin version</th><td><?php echo esc_html(KODR_SRA_VERSION); ?></td></tr>
                <tr><th>Gravity Forms</th><td><?php echo class_exists('GFForms') ? '<span style="color:#008a20">Available</span>' : '<span style="color:#b32d2e">Not detected</span>'; ?></td></tr>
                <tr><th>AWS configuration</th><td><?php echo empty($missing) ? '<span style="color:#008a20">Complete</span>' : '<span style="color:#b32d2e">Missing: ' . esc_html(implode(', ', $missing)) . '</span>'; ?></td></tr>
                <tr><th>Bucket</th><td><code><?php echo esc_html((string) $config['bucket']); ?></code></td></tr>
                <tr><th>Region</th><td><code><?php echo esc_html((string) $config['region']); ?></code></td></tr>
                <tr><th>Prefix</th><td><code><?php echo esc_html((string) ($config['prefix'] ?: '(none)')); ?></code></td></tr>
                <tr><th>Alert email</th><td><?php echo esc_html((string) $config['alert_email']); ?></td></tr>
                <tr><th>Last successful archive</th><td><?php echo $last ? esc_html(get_date_from_gmt($last, 'j M Y H:i:s')) : '—'; ?></td></tr>
                <tr><th>Queue</th><td><?php echo esc_html(sprintf('Pending: %d · Retry: %d · Failed: %d · Uploaded: %d', $counts['pending'], $counts['retry'], $counts['failed'], $counts['uploaded'])); ?></td></tr>
                </tbody>
            </table>

            <h2><?php esc_html_e('Connection test', 'kodr-secure-referral-archive'); ?></h2>
            <p><?php esc_html_e('Uploads a harmless text object beneath system-tests/. It does not contain form or referral data.', 'kodr-secure-referral-archive'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="kodr_sra_test_connection">
                <?php wp_nonce_field('kodr_sra_test_connection'); ?>
                <?php submit_button(__('Test S3 upload', 'kodr-secure-referral-archive'), 'primary', 'submit', false, ['disabled' => empty($missing) ? false : 'disabled']); ?>
            </form>

            <h2><?php esc_html_e('Gravity Forms', 'kodr-secure-referral-archive'); ?></h2>
            <?php if ($forms === []) : ?>
                <p><?php esc_html_e('No Gravity Forms were found.', 'kodr-secure-referral-archive'); ?></p>
            <?php else : ?>
                <table class="widefat striped" style="max-width:1000px">
                    <thead><tr><th>ID</th><th>Form</th><th>Archive enabled</th></tr></thead>
                    <tbody>
                    <?php foreach ($forms as $form) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $form['id']); ?></td>
                            <td><?php echo esc_html($form['title']); ?></td>
                            <td><?php echo $form['enabled'] ? '<strong style="color:#008a20">Yes</strong>' : 'No'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description"><?php esc_html_e('Enable archiving within each form under Settings → Form Settings → Kodr Secure Referral Archive.', 'kodr-secure-referral-archive'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function test_connection(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'kodr-secure-referral-archive'));
        }
        check_admin_referer('kodr_sra_test_connection');

        $redirect = admin_url('admin.php?page=' . self::PAGE_SLUG);

        if (!Kodr_SRA_Config::is_ready()) {
            wp_safe_redirect(add_query_arg([
                'kodr_sra_notice'  => 'error',
                'kodr_sra_message' => 'AWS configuration is incomplete.',
            ], $redirect));
            exit;
        }

        try {
            $client = new Kodr_SRA_S3_Client(Kodr_SRA_Config::all());
            $key = Kodr_SRA_Config::object_key('system-tests/connection-test-' . gmdate('Ymd-His') . '-' . wp_generate_password(6, false, false) . '.txt');
            $body = "Kodr Secure Referral Archive connection test\nGenerated: " . gmdate('c') . "\nSite: " . home_url('/') . "\n";
            $result = $client->put_object($key, $body, 'text/plain; charset=utf-8');
            $message = sprintf('S3 test upload completed successfully: %s (HTTP %d).', $result['key'], $result['status']);
            $notice = 'success';
        } catch (Throwable $exception) {
            $message = 'S3 test upload failed: ' . self::safe_error($exception->getMessage());
            $notice = 'error';
        }

        wp_safe_redirect(add_query_arg([
            'kodr_sra_notice'  => $notice,
            'kodr_sra_message' => $message,
        ], $redirect));
        exit;
    }

    private static function safe_error(string $message): string
    {
        $message = preg_replace('/AKIA[A-Z0-9]{12,}/', '[access-key-redacted]', $message) ?: $message;
        return mb_substr(wp_strip_all_tags($message), 0, 700);
    }
}
