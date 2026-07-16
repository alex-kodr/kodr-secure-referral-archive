<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Immutable AWS/S3 configuration read from the KODR_GF_ARCHIVE constant in
 * wp-config.php. This is the only supported way to configure credentials —
 * there is no options-page or database-backed alternative.
 */
final class Configuration
{
    private function __construct(
        private readonly string $region,
        private readonly string $bucket,
        private readonly string $prefix,
        private readonly string $accessKeyId,
        private readonly string $secretKey,
        private readonly string $alertEmail
    ) {
    }

    public static function fromConstant(): self
    {
        $raw = self::rawValue();

        $defaultAlertEmail = function_exists('get_option') ? (string) get_option('admin_email') : '';

        return new self(
            trim((string) ($raw['region'] ?? '')),
            trim((string) ($raw['bucket'] ?? '')),
            trim((string) ($raw['prefix'] ?? ''), '/'),
            trim((string) ($raw['access_key_id'] ?? '')),
            trim((string) ($raw['secret_key'] ?? '')),
            trim((string) ($raw['alert_email'] ?? $defaultAlertEmail)) ?: $defaultAlertEmail
        );
    }

    /** @return array<string,mixed> */
    private static function rawValue(): array
    {
        if (!defined('KODR_GF_ARCHIVE')) {
            return [];
        }

        $value = constant('KODR_GF_ARCHIVE');

        return is_array($value) ? $value : [];
    }

    /**
     * @return string[] human-readable validation errors; empty when the
     *                   configuration is complete and usable.
     */
    public function validationErrors(): array
    {
        $errors = [];

        if ($this->region === '') {
            $errors[] = 'AWS region is missing.';
        }
        if ($this->bucket === '') {
            $errors[] = 'S3 bucket name is missing.';
        }
        if ($this->accessKeyId === '') {
            $errors[] = 'AWS access key ID is missing.';
        }
        if ($this->secretKey === '') {
            $errors[] = 'AWS secret access key is missing.';
        }
        if ($this->alertEmail !== '' && !str_contains($this->alertEmail, '@')) {
            $errors[] = 'Alert email address is not valid.';
        }

        return $errors;
    }

    public function isValid(): bool
    {
        return $this->validationErrors() === [];
    }

    public function region(): string
    {
        return $this->region;
    }

    public function bucket(): string
    {
        return $this->bucket;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function accessKeyId(): string
    {
        return $this->accessKeyId;
    }

    public function alertEmail(): string
    {
        return $this->alertEmail;
    }

    /**
     * @internal for use by the storage layer only, to sign/authenticate AWS
     *           requests. Never echo, log, or otherwise display this value.
     */
    public function secretKey(): string
    {
        return $this->secretKey;
    }

    public function objectKey(string $relative): string
    {
        $relative = ltrim($relative, '/');

        return $this->prefix === '' ? $relative : $this->prefix . '/' . $relative;
    }

    /**
     * Safe for admin display or logging: the secret key is always redacted
     * and the access key ID is truncated.
     *
     * @return array<string,string>
     */
    public function toSafeArray(): array
    {
        return [
            'region'        => $this->region,
            'bucket'        => $this->bucket,
            'prefix'        => $this->prefix,
            'access_key_id' => $this->maskAccessKey(),
            'secret_key'    => $this->secretKey === '' ? '' : '••••••••',
            'alert_email'   => $this->alertEmail,
        ];
    }

    private function maskAccessKey(): string
    {
        if ($this->accessKeyId === '') {
            return '';
        }

        $visible = substr($this->accessKeyId, 0, 4);

        return $visible . str_repeat('•', max(strlen($this->accessKeyId) - 4, 0));
    }
}
