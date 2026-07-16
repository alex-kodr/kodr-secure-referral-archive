<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Archive;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Thrown when processing a queued archive job fails. The message is always
 * sanitized before this exception is constructed — safe to store as
 * QueueRepository's last_error_message, or display in wp-admin.
 */
final class ArchiveProcessingException extends \RuntimeException
{
}
