<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Storage;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Thrown when a storage provider fails to store an object. The message must
 * already be sanitized (no credentials, no raw provider exception detail)
 * before this exception is constructed — callers may log or display it
 * directly.
 */
final class StorageException extends \RuntimeException
{
}
