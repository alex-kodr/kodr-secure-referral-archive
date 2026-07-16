# Database schema

## `wp_kodr_sra_queue`

Operational metadata only. **Never** stores submitted field values, generated
JSON, or PDF content.

| Column             | Type                   | Notes                                   |
|--------------------|------------------------|------------------------------------------|
| `id`               | `bigint unsigned`      | Primary key, auto increment              |
| `reference`        | `varchar(64)`          | Unique human-readable reference (e.g. `REF-20260716-A82F19`) |
| `form_id`          | `bigint unsigned`      |                                           |
| `entry_id`         | `bigint unsigned`      |                                           |
| `status`           | `varchar(20)`          | See statuses below                       |
| `attempts`         | `smallint unsigned`    | Number of processing attempts so far     |
| `next_attempt_at`  | `datetime` nullable    | GMT; when the worker may next try        |
| `last_attempt_at`  | `datetime` nullable    | GMT                                      |
| `json_key`         | `varchar(1024)` nullable | S3 object key for the JSON archive     |
| `pdf_key`          | `varchar(1024)` nullable | S3 object key for the PDF archive      |
| `last_error_code`  | `varchar(100)` nullable | Sanitized machine-readable error code  |
| `last_error_message` | `text` nullable      | Sanitized human-readable error message   |
| `created_at`       | `datetime`             | GMT                                       |
| `updated_at`       | `datetime`             | GMT                                       |
| `completed_at`     | `datetime` nullable    | GMT                                       |

### Statuses

- `pending` — queued, not yet picked up
- `processing` — currently being worked on by the queue worker (locked)
- `retry` — a previous attempt failed and a retry is scheduled
- `completed` — both JSON and PDF uploaded successfully
- `failed` — all retry attempts exhausted (terminal)

### Indexes

- `PRIMARY KEY (id)`
- `UNIQUE (form_id, entry_id)` — prevents duplicate queue jobs for the same
  Gravity Forms entry
- `UNIQUE (reference)`
- `KEY (status, next_attempt_at)` — supports efficient due-item lookups

### Retention

- `completed` rows: retained 90 days, then pruned
- `failed` rows: retained until manually resolved, then 90 days
- The plugin never deletes the underlying S3 objects, and never deletes
  Gravity Forms entries — only rows in this operational table.

## Notes

Earlier drafts of this document referred to a `wp_kodr_archive_queue` table
with a smaller column set. That table name/shape is superseded by
`wp_kodr_sra_queue` as described above.
