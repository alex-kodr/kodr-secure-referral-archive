<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Storage;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The result of successfully storing an object. Deliberately contains no
 * public URL — objects are always private.
 */
final class StoredObject
{
    public function __construct(
        private readonly string $bucket,
        private readonly string $key,
        private readonly string $etag,
        private readonly \DateTimeImmutable $storedAt
    ) {
    }

    public function bucket(): string
    {
        return $this->bucket;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function etag(): string
    {
        return $this->etag;
    }

    public function storedAt(): \DateTimeImmutable
    {
        return $this->storedAt;
    }
}
