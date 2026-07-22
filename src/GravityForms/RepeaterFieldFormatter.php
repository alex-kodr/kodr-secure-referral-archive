<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\GravityForms;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Expands the serialized-PHP-array values produced by third-party "repeater"
 * field add-ons (e.g. Repeater for Gravity Forms) into human-readable text.
 *
 * These add-ons store each repeated group as PHP `serialize()`d data keyed
 * like `input_{fieldId}__{rowId}`. Detection here is based purely on the
 * value's shape (a safely-unserializable PHP array), not on any specific GF
 * field type constant, so this works regardless of which repeater add-on
 * produced it. Each `input_{fieldId}__` key is translated back into a human
 * label using the sibling fields already defined elsewhere on the same form.
 */
final class RepeaterFieldFormatter
{
    /**
     * @param array<int,string> $fieldLabelsById
     */
    public function format(string $value, array $fieldLabelsById): ?string
    {
        $rows = $this->unserializeRows($value);
        if ($rows === null || $rows === []) {
            return null;
        }

        $blocks = [];
        $rowNumber = 0;
        $multipleRows = count($rows) > 1;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowNumber++;
            $lines = [];

            foreach ($row as $inputKey => $inputValue) {
                if (!is_string($inputKey) || !preg_match('/^input_(\d+)__/', $inputKey, $matches)) {
                    continue;
                }

                $fieldId = (int) $matches[1];
                $label = trim($fieldLabelsById[$fieldId] ?? ('Field ' . $fieldId));
                $lines[] = $label . ' ' . trim((string) $inputValue);
            }

            if ($lines !== []) {
                $blocks[] = ($multipleRows ? "Entry {$rowNumber}:\n" : '') . implode("\n", $lines);
            }
        }

        return $blocks === [] ? null : implode("\n\n", $blocks);
    }

    /** @return array<int|string,mixed>|null */
    private function unserializeRows(string $value): ?array
    {
        $value = trim($value);

        if (!str_starts_with($value, 'a:') || !str_ends_with($value, '}')) {
            return null;
        }

        // Never allow object instantiation from this untrusted serialized
        // string — this neutralises PHP Object Injection even if it somehow
        // contained an "O:" object marker. Any embedded object becomes an
        // inert __PHP_Incomplete_Class instead of a real, callable object.
        $decoded = @unserialize($value, ['allowed_classes' => false]);

        return is_array($decoded) ? $decoded : null;
    }
}
