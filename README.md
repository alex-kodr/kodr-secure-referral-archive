# Kodr Secure Referral Archive

A WordPress plugin that securely archives selected Gravity Forms submissions to a
private Amazon S3 bucket, off the WordPress database, for organisations that need
durable, confidential storage of referral data.

See `readme.txt` for the WordPress-facing plugin readme (installation, changelog
summary). This file is the developer-facing entry point.

## Documentation

- [docs/architecture.md](docs/architecture.md) — component overview and data flow
- [docs/security.md](docs/security.md) — security model and guarantees
- [docs/database.md](docs/database.md) — queue table schema
- [docs/roadmap.md](docs/roadmap.md) — build phases and current status
- [docs/aws-setup.md](docs/aws-setup.md) — S3 bucket and IAM setup

## Key decisions

- PHP 8.4+, WordPress 7.0+, Gravity Forms 2.10+
- AWS credentials are read only from the `KODR_GF_ARCHIVE` constant in
  `wp-config.php` — never stored in the database
- Each submission is archived as JSON and a PDF (via TCPDF)
- Archiving is queue-based and processed in the background via WP-Cron; nothing
  is uploaded during the visitor's request
- No submitted form data ever appears in logs or alert emails
- Archiving is enabled per Gravity Form, and disabled by default
- File uploads attached to forms are out of scope for version 1
- Gravity Forms entries are left in place under Gravity Forms' own retention
  policy — this plugin does not delete GF entries

## Development

```bash
composer install
```

No local WordPress environment is bundled with this repository. Test changes on
a development or staging WordPress site with Gravity Forms active.
