<?php

declare(strict_types=1);

// Deliberately global namespace: production code calls the real Gravity
// Forms API as \GFAPI::... — this fake must live in the same (global) scope
// to be substitutable in tests without a real Gravity Forms install.

if (!class_exists('GFAPI')) {
    final class GFAPI
    {
        /** @var array<int,array<string,mixed>> */
        public static array $forms = [];

        /** @var array<int,array<string,mixed>> */
        public static array $entries = [];

        /** @var int[] entry IDs passed to delete_entry(), in call order */
        public static array $deletedEntryIds = [];

        /** @return array<string,mixed>|false */
        public static function get_form(int $formId): array|false
        {
            return self::$forms[$formId] ?? false;
        }

        /** @return array<string,mixed>|\WP_Error */
        public static function get_entry(int $entryId): array|WP_Error
        {
            return self::$entries[$entryId] ?? new WP_Error();
        }

        public static function delete_entry(int $entryId): bool
        {
            self::$deletedEntryIds[] = $entryId;
            unset(self::$entries[$entryId]);

            return true;
        }
    }
}
