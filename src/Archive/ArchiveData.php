<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Archive;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A fully parsed, ready-to-archive referral: form/entry identity plus the
 * ordered list of question/answer fields. Never carries raw Gravity Forms
 * entry metadata (IP, user agent, source URL, payment details) — only what
 * EntryParser explicitly extracted from form fields.
 */
final class ArchiveData
{
    /** @param ArchiveField[] $fields */
    public function __construct(
        private readonly string $reference,
        private readonly int $formId,
        private readonly string $formTitle,
        private readonly int $entryId,
        private readonly \DateTimeImmutable $submittedAt,
        private readonly array $fields
    ) {
    }

    public function reference(): string
    {
        return $this->reference;
    }

    public function formId(): int
    {
        return $this->formId;
    }

    public function formTitle(): string
    {
        return $this->formTitle;
    }

    public function entryId(): int
    {
        return $this->entryId;
    }

    public function submittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    /** @return ArchiveField[] */
    public function fields(): array
    {
        return $this->fields;
    }
}
