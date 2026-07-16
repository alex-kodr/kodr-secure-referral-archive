# Security model

## Credentials

- AWS credentials are read only from the `KODR_GF_ARCHIVE` constant in
  `wp-config.php`. They are never written to the WordPress database, options
  table, or any file the plugin controls.
- The IAM user must be scoped to least privilege: `s3:PutObject` on the target
  bucket/prefix only. No `GetObject`, `ListBucket` or `DeleteObject`
  permissions are required or used.
- The S3 bucket must be private with no public ACLs. Objects are uploaded with
  private access and SSE-S3 server-side encryption.

## Referral data handling

- Submitted form values exist only in memory for the duration of a single
  queue-processing run. They are never written to `error_log`, `var_dump`,
  `print_r`, the queue table, or alert emails.
- The queue table (`wp_kodr_sra_queue`) stores operational metadata only:
  status, timestamps, attempt counts, S3 object keys and sanitized error
  codes/messages. It never stores field values, generated JSON, or PDF
  contents.
- Failure notification emails contain only: site, form ID/title, entry ID,
  reference, attempt count, a sanitized error message, and a link to the admin
  queue screen. They never include field values.
- AWS SDK exception messages are sanitized (access keys and any embedded
  request/response bodies stripped) before being logged or stored.

## Access control

- Every admin action (viewing the dashboard, running the connection test,
  manually retrying a queue item) requires the `manage_options` capability.
- Every state-changing admin action is protected by a WordPress nonce.
- All admin-rendered output is escaped (`esc_html`, `esc_attr`, `esc_url`).
- All variable SQL uses `$wpdb->prepare()`.

## Data retention

- Once a submission is fully archived (both JSON and PDF confirmed uploaded to
  S3), the plugin permanently deletes the source Gravity Forms entry via
  `GFAPI::delete_entry()`. Sensitive referral data should spend as little
  time as possible in the WordPress database, so this happens automatically
  and immediately — it is not optional or per-form configurable.
- If a submission permanently fails to archive (all retry attempts
  exhausted), its Gravity Forms entry is deliberately left alone — S3 would
  otherwise be the only remaining copy of that data. It falls back to
  Gravity Forms' own entry retention policy, and the admin failure email
  (see [architecture.md](architecture.md)) is the mechanism for follow-up.
- Completed queue rows are retained for a bounded period (see
  [database.md](database.md)) and then pruned — the S3 objects themselves are
  never deleted by the plugin.
- No live/real referral data is ever committed to source control, used in
  fixtures, or included in the plugin's Git history.

## Security review log

Reviewed 2026-07-16 against this document's checklist:

- Searched the codebase for `error_log`, `print_r`, `var_dump`, `var_export`,
  hardcoded secrets, and direct `$_POST`/`$_GET` use — every request
  parameter is sanitized (`absint`, `sanitize_key`, `sanitize_text_field`,
  `wp_unslash`) before use.
- Confirmed every admin action (`test_connection`, `retry_item`) checks
  `current_user_can('manage_options')` and `check_admin_referer()` before
  making any change, in that order.
- Confirmed all queue table SQL goes through `$wpdb->prepare()`.
- Confirmed `S3Storage` never calls `GetObject`/`HeadObject`/`DeleteObject`
  and never sets a public ACL.
- Searched the full Git history for AWS access key patterns — none found
  outside obviously-fake test fixtures (`AKIATESTTESTTESTTEST` etc.).
- Removed an orphaned legacy `Kodr_SRA_Queue` class file that had reappeared
  on disk after being deleted in an earlier phase (dead code, not referenced
  anywhere, no functional impact — cleaned up for hygiene).

