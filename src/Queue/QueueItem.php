<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Queue;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Immutable representation of a single wp_kodr_sra_queue row. Operational
 * metadata only — never carries submitted field values, JSON, or PDF
 * contents.
 */
final class QueueItem
{
    public function __construct(
        private readonly int $id,
        private readonly string $reference,
        private readonly int $formId,
        private readonly int $entryId,
        private readonly QueueStatus $status,
        private readonly int $attempts,
        private readonly ?\DateTimeImmutable $nextAttemptAt,
        private readonly ?\DateTimeImmutable $lastAttemptAt,
        private readonly ?string $jsonKey,
        private readonly ?string $pdfKey,
        private readonly ?string $lastErrorCode,
        private readonly ?string $lastErrorMessage,
        private readonly \DateTimeImmutable $createdAt,
        private readonly \DateTimeImmutable $updatedAt,
        private readonly ?\DateTimeImmutable $completedAt
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['reference'],
            (int) $row['form_id'],
            (int) $row['entry_id'],
            QueueStatus::from((string) $row['status']),
            (int) $row['attempts'],
            self::parseDate($row['next_attempt_at'] ?? null),
            self::parseDate($row['last_attempt_at'] ?? null),
            self::nullableString($row['json_key'] ?? null),
            self::nullableString($row['pdf_key'] ?? null),
            self::nullableString($row['last_error_code'] ?? null),
            self::nullableString($row['last_error_message'] ?? null),
            self::parseDate($row['created_at'] ?? null) ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            self::parseDate($row['updated_at'] ?? null) ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            self::parseDate($row['completed_at'] ?? null)
        );
    }

    private static function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function reference(): string
    {
        return $this->reference;
    }

    public function formId(): int
    {
        return $this->formId;
    }

    public function entryId(): int
    {
        return $this->entryId;
    }

    public function status(): QueueStatus
    {
        return $this->status;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function nextAttemptAt(): ?\DateTimeImmutable
    {
        return $this->nextAttemptAt;
    }

    public function lastAttemptAt(): ?\DateTimeImmutable
    {
        return $this->lastAttemptAt;
    }

    public function jsonKey(): ?string
    {
        return $this->jsonKey;
    }

    public function pdfKey(): ?string
    {
        return $this->pdfKey;
    }

    public function lastErrorCode(): ?string
    {
        return $this->lastErrorCode;
    }

    public function lastErrorMessage(): ?string
    {
        return $this->lastErrorMessage;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }
}
