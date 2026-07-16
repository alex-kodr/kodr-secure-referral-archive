<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Queue;

if (!defined('ABSPATH')) {
    exit;
}

enum QueueStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Retry = 'retry';
    case Completed = 'completed';
    case Failed = 'failed';
}
