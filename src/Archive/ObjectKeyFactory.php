<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Archive;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds deterministic, S3-safe object keys for a referral's JSON and PDF
 * archives. Deterministic for a given queue item so retries re-upload to the
 * exact same keys rather than creating duplicates. Never includes a
 * person's name or email — only the form ID, a slugified form title, the
 * submission year/month, and the (randomly generated, non-identifying)
 * reference.
 *
 * Does not apply the configured S3 prefix — callers combine this with
 * Configuration::objectKey().
 */
final class ObjectKeyFactory
{
    public function jsonKey(ArchiveData $data): string
    {
        return $this->baseKey($data) . '/referral.json';
    }

    public function pdfKey(ArchiveData $data): string
    {
        return $this->baseKey($data) . '/referral.pdf';
    }

    private function baseKey(ArchiveData $data): string
    {
        return sprintf(
            'form-%d-%s/%s/%s',
            $data->formId(),
            $this->slugify($data->formTitle()),
            $data->submittedAt()->format('Y/m'),
            $this->sanitizeReference($data->reference())
        );
    }

    private function slugify(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value === '' ? 'form' : $value;
    }

    private function sanitizeReference(string $reference): string
    {
        $reference = preg_replace('/[^A-Za-z0-9\-]/', '', $reference) ?? '';

        return $reference === '' ? 'unknown-reference' : $reference;
    }
}
