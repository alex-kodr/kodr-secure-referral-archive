<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Storage;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A write-only remote storage provider. Implementations must never require
 * or perform read/list/delete operations against the backing store.
 */
interface StorageInterface
{
    /**
     * @param array<string,string> $metadata Optional key/value metadata to
     *                                        attach to the stored object.
     *
     * @throws StorageException on any failure to store the object.
     */
    public function put(
        string $key,
        string $contents,
        string $contentType,
        array $metadata = []
    ): StoredObject;
}
