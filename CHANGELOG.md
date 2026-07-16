# Changelog

## 1.0.0 — 2026-07-16

- Full submission-to-archive pipeline: `SubmissionListener` queues enabled
  Gravity Forms submissions; `QueueWorker` (WP-Cron, every 5 minutes)
  processes them via `ArchiveProcessor` — parsing the entry, generating a
  JSON document and a PDF, and uploading both to S3.
- Automatic retry with a five-attempt backoff schedule (15m / 1h / 6h / 12h),
  then a single non-sensitive failure email via `FailureNotifier`.
- Source Gravity Forms entries are permanently deleted once fully archived;
  entries that never successfully archive are left alone.
- Admin dashboard: configuration/status overview, S3 connection test,
  per-form enablement list, and a filterable, paginated queue view with
  manual retry.
- Daily cleanup (`QueueCleanup`) of old completed/failed queue metadata rows.
- Composer-based PSR-4 autoloading; official AWS SDK and TCPDF dependencies.
- Full automated test suite (PHPUnit) covering entry parsing, JSON/PDF
  generation, object key generation, the queue repository, retry policy, and
  the background processor.
- Minimum requirements raised to PHP 8.4, WordPress 7.0, Gravity Forms 2.10.

## 0.1.0 — 2026-07-16

- Initial plugin scaffold.
- Configuration validation using `KODR_GF_ARCHIVE`.
- Gravity Forms per-form enablement setting.
- Operational queue database table.
- Admin status page and S3 test upload.
- Write-only AWS Signature Version 4 S3 client.
