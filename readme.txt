=== Kodr Secure Referral Archive ===
Contributors: kodr
Tags: gravity forms, s3, archive, referrals
Requires at least: 7.0
Requires PHP: 8.4
Requires Plugins: gravityforms
Stable tag: 1.0.0
License: Proprietary

Securely archives selected Gravity Forms submissions to a private Amazon S3 bucket, off the WordPress database.

== Description ==

For each enabled Gravity Form, submissions are queued and processed in the
background: parsed into a deterministic JSON document and a PDF, both
uploaded to a private, write-only S3 bucket. Once both files are confirmed
uploaded, the source Gravity Forms entry is permanently deleted — sensitive
referral data should spend as little time as possible in the WordPress
database.

Key points:

* Per-form enablement, disabled by default.
* AWS credentials read only from `KODR_GF_ARCHIVE` in wp-config.php — never
  stored in the database.
* Write-only S3 access (PutObject only); the plugin cannot read, list or
  delete objects, and never generates public URLs.
* Failed archives retry automatically (15m / 1h / 6h / 12h) before a single
  failure alert email is sent; admins can also trigger a manual retry.
* Entries that never successfully archive are left alone and fall back to
  your Gravity Forms retention policy.

== Configuration ==

Add this above the "That's all" line in wp-config.php:

    define('KODR_GF_ARCHIVE', [
        'region'        => 'eu-west-2',
        'bucket'        => 'kodr-gf-referrals',
        'prefix'        => '',
        'access_key_id' => 'YOUR_ACCESS_KEY_ID',
        'secret_key'    => 'YOUR_SECRET_ACCESS_KEY',
        'alert_email'   => 'alerts@example.com',
    ]);

Do not commit credentials to source control.

Enable archiving per form under Forms → (your form) → Settings → Kodr Secure
Referral Archive.

== Security notes ==

See `docs/security.md` in the plugin repository for the full security model.
In summary: the IAM user backing this plugin should only be granted
`s3:PutObject` on the target bucket/prefix — no read, list, or delete
permissions are ever required.

== Changelog ==

= 1.0.0 =
* Full submission-to-archive pipeline: Gravity Forms submissions are queued,
  parsed, converted to JSON + PDF, and uploaded to S3 in the background via
  WP-Cron.
* Automatic retry with a five-attempt backoff schedule, then a single
  non-sensitive failure email.
* Source Gravity Forms entries are permanently deleted once fully archived.
* Admin dashboard: configuration/status overview, S3 connection test,
  per-form enablement list, and a filterable, paginated queue view with
  manual retry.
* Daily cleanup of old completed/failed queue metadata rows.
* Full automated test suite covering entry parsing, JSON/PDF generation, the
  queue repository, retry policy, and the background processor.

= 0.1.0 =
* Adds a status page under Forms > Secure Referral Archive.
* Reads AWS configuration only from KODR_GF_ARCHIVE in wp-config.php.
* Adds per-form enablement under Gravity Forms form settings.
* Creates a private operational queue table.
* Provides a write-only S3 connection/upload test.
* Does not yet queue or archive live form submissions.

