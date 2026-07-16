<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\GravityForms;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Thin wrapper around the Gravity Forms API. Only call this once Gravity
 * Forms has initialised — check class_exists('GFAPI') (or call from
 * plugins_loaded/later) before using it, otherwise GFAPI is undefined.
 */
final class FormsRepository
{
    /**
     * @return array<int,array{id:int,title:string,enabled:bool}>
     */
    public function all(): array
    {
        if (!class_exists('GFAPI')) {
            return [];
        }

        $settings = new FormArchiveSettings();

        // GFAPI::get_forms() only returns forms matching the given "active"
        // state — there is no single call that returns every form regardless
        // of status. Fetching only one state is what caused the admin screen
        // to report "no forms found" whenever the relevant forms were the
        // other status. Fetch both and merge.
        $active = \GFAPI::get_forms(true, false, 'title', 'ASC');
        $inactive = \GFAPI::get_forms(false, false, 'title', 'ASC');

        $forms = array_merge(
            is_array($active) ? $active : [],
            is_array($inactive) ? $inactive : []
        );

        usort(
            $forms,
            static fn (array $a, array $b): int => strcasecmp(
                (string) ($a['title'] ?? ''),
                (string) ($b['title'] ?? '')
            )
        );

        return array_map(static function (array $form) use ($settings): array {
            return [
                'id'      => (int) ($form['id'] ?? 0),
                'title'   => (string) ($form['title'] ?? ''),
                'enabled' => $settings->isEnabledForForm($form),
            ];
        }, $forms);
    }
}
