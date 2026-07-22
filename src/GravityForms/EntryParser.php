<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\GravityForms;

use Kodr\SecureReferralArchive\Archive\ArchiveData;
use Kodr\SecureReferralArchive\Archive\ArchiveField;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Converts a raw Gravity Forms form + entry into an ArchiveData value
 * object.
 *
 * Excludes display-only fields (page breaks, HTML blocks, section breaks) —
 * they carry no submitted value. Entry metadata such as IP address, user
 * agent, source URL and payment details is never included, because it is
 * never read from $entry in the first place; only values belonging to
 * actual form fields are extracted.
 *
 * Field order is preserved, empty fields are kept (not skipped), and fields
 * with multiple inputs (e.g. Name, Address, Checkboxes) are combined into a
 * single ArchiveField rather than one row per sub-input.
 *
 * Values produced by third-party "repeater" field add-ons (stored as
 * serialized PHP, e.g. `a:1:{i:8510;a:8:{s:16:"input_1034__8510";...}}`) are
 * expanded into readable text via RepeaterFieldFormatter, rather than being
 * dumped verbatim into the archive.
 *
 * Field objects are duck-typed (checked with is_object()/is_array()) rather
 * than type-hinted against Gravity Forms' GF_Field, so this class can be
 * exercised in tests with plain array fixtures and has no hard dependency on
 * Gravity Forms being loaded.
 */
final class EntryParser
{
    private const EXCLUDED_FIELD_TYPES = ['page', 'html', 'section'];

    public function __construct(
        private readonly RepeaterFieldFormatter $repeaterFormatter = new RepeaterFieldFormatter()
    ) {
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $entry
     */
    public function parse(array $form, array $entry, string $reference): ArchiveData
    {
        $fieldLabelsById = $this->buildFieldLabelMap($form);
        $fields = [];

        foreach (($form['fields'] ?? []) as $field) {
            if (!$this->isArchivable($field)) {
                continue;
            }

            $value = $this->fieldValue($field, $entry);
            $expanded = $this->repeaterFormatter->format($value, $fieldLabelsById);

            $fields[] = new ArchiveField(
                $this->fieldId($field),
                $this->fieldLabel($field),
                $expanded ?? $value
            );
        }

        return new ArchiveData(
            $reference,
            (int) ($form['id'] ?? 0),
            (string) ($form['title'] ?? ''),
            (int) ($entry['id'] ?? 0),
            $this->parseSubmittedAt($entry),
            $fields
        );
    }

    /**
     * @param array<string,mixed> $form
     * @return array<int,string>
     */
    private function buildFieldLabelMap(array $form): array
    {
        $map = [];

        foreach (($form['fields'] ?? []) as $field) {
            $id = $this->fieldId($field);
            if ($id === '') {
                continue;
            }

            $map[(int) $id] = $this->fieldLabel($field);
        }

        return $map;
    }

    private function isArchivable(mixed $field): bool
    {
        $type = $this->fieldType($field);

        return $type !== '' && !in_array($type, self::EXCLUDED_FIELD_TYPES, true);
    }

    private function fieldType(mixed $field): string
    {
        $type = is_object($field) ? ($field->type ?? null) : (is_array($field) ? ($field['type'] ?? null) : null);

        return is_string($type) ? $type : '';
    }

    private function fieldId(mixed $field): string
    {
        $id = is_object($field) ? ($field->id ?? null) : (is_array($field) ? ($field['id'] ?? null) : null);

        return $id === null ? '' : (string) $id;
    }

    private function fieldLabel(mixed $field): string
    {
        $label = is_object($field) ? ($field->label ?? null) : (is_array($field) ? ($field['label'] ?? null) : null);

        if (is_string($label) && $label !== '') {
            return $label;
        }

        return 'Field ' . $this->fieldId($field);
    }

    /** @param array<string,mixed> $entry */
    private function fieldValue(mixed $field, array $entry): string
    {
        $inputs = $this->fieldInputs($field);

        if ($inputs === []) {
            return trim((string) ($entry[$this->fieldId($field)] ?? ''));
        }

        // Checkboxes are a list of independent selections, so join them with
        // a separator; other multi-input fields (name, address, etc.) read
        // more naturally joined with a space.
        $glue = $this->fieldType($field) === 'checkbox' ? ', ' : ' ';
        $values = [];

        foreach ($inputs as $input) {
            $inputId = is_array($input) ? (string) ($input['id'] ?? '') : (is_object($input) ? (string) ($input->id ?? '') : '');
            if ($inputId === '') {
                continue;
            }

            $value = trim((string) ($entry[$inputId] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return implode($glue, $values);
    }

    /** @return array<int,mixed> */
    private function fieldInputs(mixed $field): array
    {
        $inputs = is_object($field) ? ($field->inputs ?? null) : (is_array($field) ? ($field['inputs'] ?? null) : null);

        return is_array($inputs) ? $inputs : [];
    }

    /** @param array<string,mixed> $entry */
    private function parseSubmittedAt(array $entry): \DateTimeImmutable
    {
        $dateCreated = (string) ($entry['date_created'] ?? '');
        if ($dateCreated === '') {
            return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }

        try {
            return new \DateTimeImmutable($dateCreated, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }
    }
}
