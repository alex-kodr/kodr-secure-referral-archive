# Roadmap

Tracking the 14-phase build plan. One phase (or numbered item) per commit.

- [x] **Phase 1 — Clean up prototype**: scaffold (composer.json, README.md,
      .gitignore, docs/, .github/)
- [x] **Phase 2 — Documentation**: architecture, security, database, roadmap,
      AWS setup, Copilot instructions
- [x] **Phase 3 — Foundation**: Composer autoloading, `Configuration` class,
      Gravity Forms detection fix
- [x] **Phase 3b — S3 foundation**: storage abstraction, `S3Storage`, repaired
      connection test
- [x] **Phase 4 — Per-form settings**: `FormArchiveSettings` service
- [x] **Phase 5 — Queue database**: finalised schema, `QueueRepository`
- [x] **Phase 6 — Capture submissions**: `SubmissionListener`
- [x] **Phase 7 — Parse Gravity Forms entries**: `EntryParser` + tests
- [x] **Phase 8 — Archive generation**: `JsonGenerator`, `PdfGenerator` (TCPDF)
- [x] **Phase 9 — Background processor**: `ObjectKeyFactory`, `ArchiveProcessor`
- [x] **Phase 10 — Cron and retries**: `QueueWorker`, `Scheduler`, retry schedule
- [x] **Phase 11 — Alerts and admin**: `FailureNotifier`, dashboard improvements
- [x] **Phase 12 — Retention and cleanup**: GF retention confirmation, queue
      metadata cleanup (`QueueCleanup`)
- [ ] **Phase 13 — Production testing**: full test matrix, security review
- [ ] **Phase 14 — Release**: production build, tag v1.0.0

## Agreed decisions

- PHP 8.4+, WordPress 7.0+, Gravity Forms 2.10+
- AWS S3 credentials from `wp-config.php` only (see [aws-setup.md](aws-setup.md))
- JSON and TCPDF output per referral
- Queue-based background processing (WP-Cron)
- No submitted data in logs or alert emails
- No AWS credentials in the database
- Forms enabled individually, disabled by default
- No file-upload field support in version 1
- Gravity Forms entries remain under their existing GF retention policy — this
  plugin does not delete them
