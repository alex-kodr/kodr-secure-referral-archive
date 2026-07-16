<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Archive;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders an ArchiveData value object as deterministic, pretty-printed JSON.
 * Contains only what ArchiveData carries — never IP address, user agent,
 * source URL, payment metadata, or any other Gravity Forms entry internals.
 */
final class JsonGenerator
{
    private const SCHEMA_VERSION = '1.0';

    public function generate(ArchiveData $data): string
    {
        $payload = [
            'schema_version' => self::SCHEMA_VERSION,
            'reference'      => $data->reference(),
            'form'           => [
                'id'    => $data->formId(),
                'title' => $data->formTitle(),
            ],
            'entry' => [
                'id'           => $data->entryId(),
                'submitted_at' => $data->submittedAt()->format(\DateTimeInterface::ATOM),
            ],
            'fields' => array_map(
                static fn (ArchiveField $field): array => $field->toArray(),
                $data->fields()
            ),
        ];

        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            throw new \RuntimeException('Failed to encode archive JSON.');
        }

        return $json;
    }
}
