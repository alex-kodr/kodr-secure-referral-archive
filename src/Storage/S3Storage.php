<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Storage;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Kodr\SecureReferralArchive\Config\Configuration;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Write-only Amazon S3 storage provider. The IAM credentials backing this
 * plugin only grant s3:PutObject, so this class must never call GetObject,
 * HeadObject or DeleteObject.
 */
final class S3Storage implements StorageInterface
{
    private readonly S3Client $client;
    private readonly string $bucket;

    public function __construct(Configuration $config, ?S3Client $client = null)
    {
        $this->bucket = $config->bucket();
        $this->client = $client ?? new S3Client([
            'version'     => 'latest',
            'region'      => $config->region(),
            'credentials' => [
                'key'    => $config->accessKeyId(),
                'secret' => $config->secretKey(),
            ],
        ]);
    }

    /** @param array<string,string> $metadata */
    public function put(
        string $key,
        string $contents,
        string $contentType,
        array $metadata = []
    ): StoredObject {
        $key = ltrim($key, '/');
        if ($key === '') {
            throw new StorageException('The storage key cannot be empty.');
        }

        try {
            $result = $this->client->putObject([
                'Bucket'               => $this->bucket,
                'Key'                  => $key,
                'Body'                 => $contents,
                'ContentType'          => $contentType,
                'ACL'                  => 'private',
                'ServerSideEncryption' => 'AES256',
                'Metadata'             => $metadata,
            ]);
        } catch (AwsException $exception) {
            throw new StorageException(self::sanitize($exception), 0, $exception);
        }

        $etag = trim((string) ($result['ETag'] ?? ''), '"');

        return new StoredObject($this->bucket, $key, $etag, new \DateTimeImmutable('now'));
    }

    private static function sanitize(AwsException $exception): string
    {
        $message = $exception->getAwsErrorMessage() ?: $exception->getMessage();
        $message = preg_replace('/AKIA[A-Z0-9]{12,}/', '[access-key-redacted]', $message) ?? $message;
        $message = wp_strip_all_tags($message);

        $code = $exception->getAwsErrorCode();
        $prefix = $code ? "S3 error ({$code}): " : 'S3 error: ';

        return mb_substr($prefix . $message, 0, 700);
    }
}
