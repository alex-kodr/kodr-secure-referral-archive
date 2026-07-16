# Architecture

## Overview

The plugin listens for Gravity Forms submissions on forms that have archiving
enabled, queues them for background processing, and later converts each queued
entry into a JSON document and a PDF, uploading both to a private S3 bucket.
Nothing is uploaded synchronously during the visitor's request.

```
Gravity Forms submission
        │
        ▼
SubmissionListener (gform_after_submission)
        │  enqueue only — no parsing, no AWS calls
        ▼
wp_kodr_sra_queue (operational metadata only)
        │
        ▼
Cron\Scheduler (WP-Cron, every 5 minutes)
        │
        ▼
Queue\QueueWorker (small batch, locked)
        │
        ▼
Archive\ArchiveProcessor
        │
        ├─ GravityForms\EntryParser        → Archive\ArchiveData / ArchiveField
        ├─ Archive\JsonGenerator            → referral.json
        ├─ Pdf\PdfGenerator (TCPDF)         → referral.pdf
        ├─ Archive\ObjectKeyFactory         → deterministic S3 key
        └─ Storage\S3Storage (PutObject)    → uploads both files
        │
        ▼
QueueRepository::markCompleted() / scheduleRetry() / markFailed()
        │
        ▼ (only on markCompleted)
GFAPI::delete_entry() — source entry permanently deleted once fully archived
        │
        ▼
Notification\FailureNotifier (only on terminal failure)
```

## Namespace and layout

PSR-4 autoloaded under `Kodr\SecureReferralArchive\`, rooted at `src/`.

- `src/Config/` — `Configuration` (reads `KODR_GF_ARCHIVE` from wp-config.php)
- `src/GravityForms/` — `FormsRepository`, `FormArchiveSettings`,
  `SubmissionListener`, `EntryParser`
- `src/Archive/` — `ArchiveData`, `ArchiveField`, `JsonGenerator`,
  `ObjectKeyFactory`, `ArchiveProcessor`
- `src/Pdf/` — `PdfGenerator`
- `src/Storage/` — `StorageInterface`, `S3Storage`, `StoredObject`,
  `StorageException`
- `src/Queue/` — `QueueRepository`, `QueueItem`, `QueueStatus`, `QueueWorker`
- `src/Cron/` — `Scheduler`
- `src/Notification/` — `FailureNotifier`
- `includes/` — legacy WordPress-facing glue that boots the plugin and wires
  hooks (kept outside the PSR-4 tree since it is procedural WordPress
  bootstrap code, not application logic)

## Design principles

- **Write-only S3 access.** The IAM user backing this plugin can only
  `PutObject`. The plugin never calls `GetObject`, `HeadObject` or
  `DeleteObject`, and never generates public URLs.
- **No referral content at rest in WordPress.** The queue table stores only
  operational metadata (status, timestamps, attempts, S3 key). Submitted field
  values are held in memory only for the duration of a single processing run.
- **Idempotent processing.** Enqueueing and processing must be safe to run more
  than once for the same entry without creating duplicate S3 objects or queue
  rows.
- **Background only.** No archive generation or AWS network call happens
  during the visitor-facing form submission request.
