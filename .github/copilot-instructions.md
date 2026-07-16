# Copilot instructions — Kodr Secure Referral Archive

This is a WordPress plugin that archives selected Gravity Forms submissions to
a private Amazon S3 bucket. Read `README.md` and everything under `docs/`
before making changes — they describe the target architecture and agreed
decisions.

## Hard rules

- **Never** store submitted form field values, generated JSON, or PDF content
  in the WordPress database, options table, transients, or logs. The queue
  table (`wp_kodr_sra_queue`) holds operational metadata only.
- **Never** log, echo, or email raw AWS exception messages — sanitize them
  first (strip access keys, truncate, strip tags).
- **Never** call `s3:GetObject`, `s3:HeadObject` or `s3:DeleteObject` — the IAM
  user is write-only (`PutObject` only). Don't add code that assumes read
  access to the bucket.
- **Never** generate public S3 URLs or add a "view referral contents" feature
  in wp-admin.
- AWS credentials come only from the `KODR_GF_ARCHIVE` constant in
  `wp-config.php` (via `Kodr\SecureReferralArchive\Config\Configuration`).
  Never introduce another way to configure credentials (options page, DB,
  etc.).
- Archive generation and S3 uploads must happen in the background (WP-Cron
  queue worker), never synchronously during `gform_after_submission`.
- This plugin does not delete Gravity Forms entries. Retention of GF entries
  is left to Gravity Forms' own settings.
- File-upload fields are out of scope for version 1 — do not add handling for
  them.

## Conventions

- `declare(strict_types=1)` in every PHP file.
- New application code goes under `src/`, namespaced
  `Kodr\SecureReferralArchive\...`, PSR-4 autoloaded via Composer.
- `includes/` remains for WordPress bootstrap/hook-registration glue.
- All variable SQL uses `$wpdb->prepare()`.
- All admin-rendered output is escaped; all state-changing admin actions
  require `manage_options` + a WordPress nonce.
- Prefer small, focused classes with a single static `hooks()` entry point for
  wiring WordPress actions/filters, matching the existing `Kodr_SRA_*` classes.

## Workflow

- Work through `docs/roadmap.md` one phase/numbered item at a time.
- Do not jump ahead to implement later phases before earlier ones are in
  place, unless asked.
- Do not implement submission archiving, S3 uploads, or queue processing
  ahead of the phase that introduces them.
