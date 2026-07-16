<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Archive;

use Kodr\SecureReferralArchive\Config\Configuration;
use Kodr\SecureReferralArchive\GravityForms\EntryParser;
use Kodr\SecureReferralArchive\Pdf\PdfGenerator;
use Kodr\SecureReferralArchive\Queue\QueueItem;
use Kodr\SecureReferralArchive\Queue\QueueRepository;
use Kodr\SecureReferralArchive\Storage\StorageInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Coordinates turning one queued Gravity Forms submission into a JSON
 * archive and a PDF, uploading both to S3, and marking the queue item
 * completed. Never persists submitted field values, JSON, or PDF content
 * anywhere except the two uploaded S3 objects — the queue table only ever
 * records the resulting S3 keys and operational metadata.
 *
 * Idempotent: object keys are deterministic for a given queue item, so
 * re-processing after a retry re-uploads to the same keys instead of
 * creating duplicates.
 *
 * Only the happy path is handled here; this throws on any failure, and it is
 * the caller's (the queue worker's) responsibility to decide whether to
 * retry or give up permanently.
 */
final class ArchiveProcessor
{
    public function __construct(
        private readonly QueueRepository $queue,
        private readonly Configuration $config,
        private readonly StorageInterface $storage,
        private readonly EntryParser $parser = new EntryParser(),
        private readonly JsonGenerator $jsonGenerator = new JsonGenerator(),
        private readonly PdfGenerator $pdfGenerator = new PdfGenerator(),
        private readonly ObjectKeyFactory $keyFactory = new ObjectKeyFactory(),
        private readonly string $organisationName = ''
    ) {
    }

    /**
     * @return bool true if this call claimed and processed the item, false
     *              if it was not in a claimable state (e.g. already being
     *              processed elsewhere) — nothing to do in that case.
     *
     * @throws ArchiveProcessingException on any failure after the item was claimed.
     */
    public function process(QueueItem $item): bool
    {
        if (!$this->queue->markProcessing($item->id())) {
            return false;
        }

        try {
            [$form, $entry] = $this->fetchFormAndEntry($item);

            $data = $this->parser->parse($form, $entry, $item->reference());

            $json = $this->jsonGenerator->generate($data);
            $pdf = $this->pdfGenerator->generate($data, $this->siteName());

            $jsonKey = $this->config->objectKey($this->keyFactory->jsonKey($data));
            $pdfKey = $this->config->objectKey($this->keyFactory->pdfKey($data));

            $this->storage->put($jsonKey, $json, 'application/json; charset=utf-8');
            $this->storage->put($pdfKey, $pdf, 'application/pdf');

            $this->queue->markCompleted($item->id(), $jsonKey, $pdfKey);

            return true;
        } catch (\Throwable $exception) {
            throw new ArchiveProcessingException(self::sanitize($exception), 0, $exception);
        }
    }

    /** @return array{0: array<string,mixed>, 1: array<string,mixed>} */
    private function fetchFormAndEntry(QueueItem $item): array
    {
        if (!class_exists('GFAPI')) {
            throw new \RuntimeException('Gravity Forms is not available.');
        }

        $form = \GFAPI::get_form($item->formId());
        if (!is_array($form)) {
            throw new \RuntimeException('Gravity Forms form ' . $item->formId() . ' could not be loaded.');
        }

        $entry = \GFAPI::get_entry($item->entryId());
        if (!is_array($entry) || (function_exists('is_wp_error') && is_wp_error($entry))) {
            throw new \RuntimeException('Gravity Forms entry ' . $item->entryId() . ' could not be loaded.');
        }

        return [$form, $entry];
    }

    private function siteName(): string
    {
        if ($this->organisationName !== '') {
            return $this->organisationName;
        }

        return function_exists('get_bloginfo') ? (string) get_bloginfo('name') : '';
    }

    private static function sanitize(\Throwable $exception): string
    {
        $message = $exception->getMessage();
        $message = preg_replace('/AKIA[A-Z0-9]{12,}/', '[access-key-redacted]', $message) ?? $message;
        $message = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($message) : strip_tags($message);

        return mb_substr($message, 0, 700);
    }
}
