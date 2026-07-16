<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Archive;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A single question/answer pair extracted from a Gravity Forms entry.
 * Deliberately carries the field ID alongside the label so that repeated or
 * similar labels remain distinguishable.
 */
final class ArchiveField
{
    public function __construct(
        private readonly string $fieldId,
        private readonly string $label,
        private readonly string $value
    ) {
    }

    public function fieldId(): string
    {
        return $this->fieldId;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    /** @return array{field_id:string,label:string,value:string} */
    public function toArray(): array
    {
        return [
            'field_id' => $this->fieldId,
            'label'    => $this->label,
            'value'    => $this->value,
        ];
    }
}
