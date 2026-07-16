<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Tests\Support;

use Kodr\SecureReferralArchive\Storage\StorageInterface;
use Kodr\SecureReferralArchive\Storage\StoredObject;

/**
 * Records every put() call in memory instead of talking to S3, so tests can
 * assert on what was "uploaded" without any network access.
 */
final class InMemoryStorage implements StorageInterface
{
    /** @var array<string,array{contents:string,contentType:string}> */
    public array $stored = [];

    /** @param array<string,string> $metadata */
    public function put(string $key, string $contents, string $contentType, array $metadata = []): StoredObject
    {
        $this->stored[$key] = [
            'contents'    => $contents,
            'contentType' => $contentType,
        ];

        return new StoredObject('test-bucket', $key, 'etag-' . md5($contents), new \DateTimeImmutable('now'));
    }
}
