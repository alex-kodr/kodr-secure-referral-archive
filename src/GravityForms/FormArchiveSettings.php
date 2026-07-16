<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\GravityForms;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The single source of truth for whether S3 archiving is enabled for a given
 * Gravity Form. Disabled by default — nothing is archived unless explicitly
 * enabled here.
 */
final class FormArchiveSettings
{
    private const META_KEY = 'kodr_sra_enabled';

    public function isEnabledForFormId(int $formId): bool
    {
        if (!class_exists('GFAPI')) {
            return false;
        }

        $form = \GFAPI::get_form($formId);

        return is_array($form) && $this->isEnabledForForm($form);
    }

    /** @param array<string,mixed> $form */
    public function isEnabledForForm(array $form): bool
    {
        return !empty($form[self::META_KEY]);
    }

    /**
     * Reads the submitted checkbox value from $_POST and returns the form
     * array with the setting updated. Boolean, defaults to false (disabled)
     * whenever the checkbox is not present in the request.
     *
     * @param array<string,mixed> $form
     * @return array<string,mixed>
     */
    public function withEnabledFromRequest(array $form): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Gravity Forms verifies the form settings nonce before invoking gform_pre_form_settings_save.
        $form[self::META_KEY] = isset($_POST[self::META_KEY]) && wp_unslash($_POST[self::META_KEY]) === '1';

        return $form;
    }

    public function metaKey(): string
    {
        return self::META_KEY;
    }
}
