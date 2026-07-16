<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Kodr_SRA_Gravity_Forms
{
    public static function hooks(): void
    {
        add_filter('gform_form_settings', [self::class, 'add_form_settings'], 20, 2);
        add_filter('gform_pre_form_settings_save', [self::class, 'save_form_settings']);
    }

    /** @param array<string,mixed> $settings @param array<string,mixed> $form
     *  @return array<string,mixed>
     */
    public static function add_form_settings(array $settings, array $form): array
    {
        $enabled = !empty($form['kodr_sra_enabled']);
        $settings['Kodr Secure Referral Archive'] = [
            'kodr_sra_enabled' => sprintf(
                '<tr><th><label for="kodr_sra_enabled">%s</label></th><td><input type="checkbox" id="kodr_sra_enabled" name="kodr_sra_enabled" value="1" %s> <label for="kodr_sra_enabled">%s</label><p class="description">%s</p></td></tr>',
                esc_html__('S3 archive', 'kodr-secure-referral-archive'),
                checked($enabled, true, false),
                esc_html__('Enable secure S3 archiving for this form', 'kodr-secure-referral-archive'),
                esc_html__('Version 0.1 stores this setting only. Submission queuing is added in the next release.', 'kodr-secure-referral-archive')
            ),
        ];

        return $settings;
    }

    /** @param array<string,mixed> $form @return array<string,mixed> */
    public static function save_form_settings(array $form): array
    {
        $form['kodr_sra_enabled'] = isset($_POST['kodr_sra_enabled']) && wp_unslash($_POST['kodr_sra_enabled']) === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return $form;
    }

    /** @return array<int,array{id:int,title:string,enabled:bool}> */
    public static function forms(): array
    {
        if (!class_exists('GFAPI')) {
            return [];
        }

        $forms = GFAPI::get_forms(false, false, 'title', 'ASC');
        if (!is_array($forms)) {
            return [];
        }

        return array_map(static function (array $form): array {
            return [
                'id'      => (int) ($form['id'] ?? 0),
                'title'   => (string) ($form['title'] ?? ''),
                'enabled' => !empty($form['kodr_sra_enabled']),
            ];
        }, $forms);
    }
}
