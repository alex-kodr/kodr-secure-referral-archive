<?php

declare(strict_types=1);

use Kodr\SecureReferralArchive\Config\Configuration;
use Kodr\SecureReferralArchive\Queue\QueueRepository;
use Kodr\SecureReferralArchive\Queue\QueueStatus;
use Kodr\SecureReferralArchive\Storage\S3Storage;
use Kodr\SecureReferralArchive\Storage\StorageException;

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
        add_action('admin_post_kodr_sra_retry_item', [self::class, 'retry_item']);
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

        $config = Configuration::fromConstant();
        $safeConfig = $config->toSafeArray();
        $missing = $config->validationErrors();
        $queue = new QueueRepository();
        $counts = $queue->countByStatus();
        $forms = Kodr_SRA_Gravity_Forms::forms();
        $last = $queue->lastCompletedAt();
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
                <tr><th>AWS configuration</th><td><?php echo empty($missing) ? '<span style="color:#008a20">Complete</span>' : '<span style="color:#b32d2e">' . esc_html(implode(' ', $missing)) . '</span>'; ?></td></tr>
                <tr><th>Bucket</th><td><code><?php echo esc_html($safeConfig['bucket']); ?></code></td></tr>
                <tr><th>Region</th><td><code><?php echo esc_html($safeConfig['region']); ?></code></td></tr>
                <tr><th>Prefix</th><td><code><?php echo esc_html($safeConfig['prefix'] ?: '(none)'); ?></code></td></tr>
                <tr><th>Alert email</th><td><?php echo esc_html($safeConfig['alert_email']); ?></td></tr>
                <tr><th>Last successful archive</th><td><?php echo $last ? esc_html(get_date_from_gmt($last, 'j M Y H:i:s')) : '—'; ?></td></tr>
                <tr><th>Queue</th><td><?php echo esc_html(sprintf('Pending: %d · Retry: %d · Failed: %d · Completed: %d', $counts['pending'], $counts['retry'], $counts['failed'], $counts['completed'])); ?></td></tr>
                </tbody>
            </table>

            <h2><?php esc_html_e('Connection test', 'kodr-secure-referral-archive'); ?></h2>
            <p><?php esc_html_e('Uploads a harmless text object beneath system-tests/. It does not contain form or referral data.', 'kodr-secure-referral-archive'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="kodr_sra_test_connection">
                <?php wp_nonce_field('kodr_sra_test_connection'); ?>
                <?php
                // submit_button() renders any key in $other_attributes as an
                // attribute regardless of its value (e.g. disabled="")  —
                // HTML treats the mere presence of "disabled" as disabling
                // the control, so the key must be omitted entirely when the
                // button should be enabled.
                $buttonAttributes = empty($missing) ? [] : ['disabled' => 'disabled'];
                submit_button(__('Test S3 upload', 'kodr-secure-referral-archive'), 'primary', 'submit', false, $buttonAttributes);
                ?>
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

            <h2><?php esc_html_e('Recent jobs', 'kodr-secure-referral-archive'); ?></h2>
            <?php self::render_queue_table($queue); ?>
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

        $config = Configuration::fromConstant();

        if (!$config->isValid()) {
            wp_safe_redirect(add_query_arg([
                'kodr_sra_notice'  => 'error',
                'kodr_sra_message' => 'AWS configuration is incomplete.',
            ], $redirect));
            exit;
        }

        try {
            $storage = new S3Storage($config);
            $key = $config->objectKey('system-tests/connection-test-' . gmdate('Ymd-His') . '-' . wp_generate_password(6, false, false) . '.txt');
            $body = "Kodr Secure Referral Archive connection test\nGenerated: " . gmdate('c') . "\nSite: " . home_url('/') . "\n";
            $result = $storage->put($key, $body, 'text/plain; charset=utf-8');
            $message = sprintf('S3 test upload completed successfully: %s (ETag %s).', $result->key(), $result->etag());
            $notice = 'success';
        } catch (StorageException $exception) {
            $message = 'S3 test upload failed: ' . self::safe_error($exception->getMessage());
            $notice = 'error';
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

    public static function retry_item(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'kodr-secure-referral-archive'));
        }

        $id = isset($_POST['kodr_sra_item_id']) ? absint($_POST['kodr_sra_item_id']) : 0;
        check_admin_referer('kodr_sra_retry_item_' . $id);

        $redirect = admin_url('admin.php?page=' . self::PAGE_SLUG);

        $retried = $id > 0 && (new QueueRepository())->retryNow($id);

        wp_safe_redirect(add_query_arg([
            'kodr_sra_notice'  => $retried ? 'success' : 'error',
            'kodr_sra_message' => $retried
                ? 'Queue item scheduled for immediate retry.'
                : 'Unable to retry that item — it may already be processing or completed.',
        ], $redirect));
        exit;
    }

    /**
     * Lists queue jobs only by their operational metadata — reference,
     * form, status, timestamps, sanitized error. Never displays generated
     * JSON/PDF content; there is deliberately no "view referral contents"
     * feature in wp-admin.
     */
    private static function render_queue_table(QueueRepository $queue): void
    {
        $statusFilter = isset($_GET['kodr_sra_status']) ? sanitize_key(wp_unslash($_GET['kodr_sra_status'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $status = QueueStatus::tryFrom($statusFilter);
        $page = isset($_GET['kodr_sra_paged']) ? max(1, absint($_GET['kodr_sra_paged'])) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $perPage = 20;

        $result = $queue->paginate($status, $page, $perPage);
        $items = $result['items'];
        $totalPages = (int) ceil($result['total'] / $perPage);

        $formTitles = [];
        foreach (Kodr_SRA_Gravity_Forms::forms() as $form) {
            $formTitles[$form['id']] = $form['title'];
        }

        $baseUrl = admin_url('admin.php?page=' . self::PAGE_SLUG);
        $statusLabels = ['' => 'All'];
        foreach (QueueStatus::cases() as $case) {
            $statusLabels[$case->value] = ucfirst($case->value);
        }

        $navLinks = [];
        foreach ($statusLabels as $value => $label) {
            $url = $value === '' ? $baseUrl : add_query_arg(['kodr_sra_status' => $value], $baseUrl);
            $navLinks[] = sprintf(
                '<a href="%s"%s>%s</a>',
                esc_url($url),
                $statusFilter === $value ? ' style="font-weight:bold"' : '',
                esc_html($label)
            );
        }
        echo '<p>' . implode(' | ', $navLinks) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        if ($items === []) {
            echo '<p>' . esc_html__('No queue jobs match this filter.', 'kodr-secure-referral-archive') . '</p>';

            return;
        }
        ?>
        <table class="widefat striped" style="max-width:1200px">
            <thead>
            <tr>
                <th>Reference</th><th>Form</th><th>Status</th><th>Attempts</th>
                <th>Created</th><th>Last attempt</th><th>Next attempt</th><th>Error</th><th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item) : ?>
                <tr>
                    <td><code><?php echo esc_html($item->reference()); ?></code></td>
                    <td><?php echo esc_html($formTitles[$item->formId()] ?? ('Form #' . $item->formId())); ?></td>
                    <td><?php echo esc_html(ucfirst($item->status()->value)); ?></td>
                    <td><?php echo esc_html((string) $item->attempts()); ?></td>
                    <td><?php echo esc_html(get_date_from_gmt($item->createdAt()->format('Y-m-d H:i:s'), 'j M Y H:i')); ?></td>
                    <td><?php echo $item->lastAttemptAt() ? esc_html(get_date_from_gmt($item->lastAttemptAt()->format('Y-m-d H:i:s'), 'j M Y H:i')) : '—'; ?></td>
                    <td><?php echo $item->nextAttemptAt() ? esc_html(get_date_from_gmt($item->nextAttemptAt()->format('Y-m-d H:i:s'), 'j M Y H:i')) : '—'; ?></td>
                    <td><?php echo $item->lastErrorMessage() ? esc_html(mb_substr($item->lastErrorMessage(), 0, 120)) : '—'; ?></td>
                    <td>
                        <?php if (in_array($item->status(), [QueueStatus::Retry, QueueStatus::Failed], true)) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0">
                                <input type="hidden" name="action" value="kodr_sra_retry_item">
                                <input type="hidden" name="kodr_sra_item_id" value="<?php echo esc_attr((string) $item->id()); ?>">
                                <?php wp_nonce_field('kodr_sra_retry_item_' . $item->id()); ?>
                                <?php submit_button(__('Retry now', 'kodr-secure-referral-archive'), 'secondary', 'submit', false); ?>
                            </form>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1) : ?>
            <p>
                <?php for ($p = 1; $p <= $totalPages; $p++) : ?>
                    <?php
                    $pageArgs = $statusFilter !== '' ? ['kodr_sra_status' => $statusFilter, 'kodr_sra_paged' => $p] : ['kodr_sra_paged' => $p];
                    $pageUrl = add_query_arg($pageArgs, $baseUrl);
                    ?>
                    <?php if ($p === $page) : ?>
                        <strong><?php echo esc_html((string) $p); ?></strong>
                    <?php else : ?>
                        <a href="<?php echo esc_url($pageUrl); ?>"><?php echo esc_html((string) $p); ?></a>
                    <?php endif; ?>
                    <?php if ($p < $totalPages) : ?> · <?php endif; ?>
                <?php endfor; ?>
            </p>
        <?php endif;
    }

    private static function safe_error(string $message): string
    {
        $message = preg_replace('/AKIA[A-Z0-9]{12,}/', '[access-key-redacted]', $message) ?: $message;
        return mb_substr(wp_strip_all_tags($message), 0, 700);
    }
}
