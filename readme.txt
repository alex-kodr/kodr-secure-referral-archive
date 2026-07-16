=== Kodr Secure Referral Archive ===
Contributors: kodr
Tags: gravity forms, s3, archive, referrals
Requires at least: 6.6
Tested up to: 7.0.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: Proprietary

Secure off-site archiving foundation for selected Gravity Forms submissions.

== Version 0.1.0 ==

* Adds a status page under Forms > Secure Referral Archive.
* Reads AWS configuration only from KODR_GF_ARCHIVE in wp-config.php.
* Adds per-form enablement under Gravity Forms form settings.
* Creates a private operational queue table.
* Provides a write-only S3 connection/upload test.
* Does not yet queue or archive live form submissions.

== Configuration ==

Add this above the "That's all" line in wp-config.php:

    define('KODR_GF_ARCHIVE', [
        'region'        => 'eu-west-2',
        'bucket'        => 'kodr-gf-referrals',
        'prefix'        => '',
        'access_key_id' => 'YOUR_ACCESS_KEY_ID',
        'secret_key'    => 'YOUR_SECRET_ACCESS_KEY',
        'alert_email'   => 'alex@kodr.io',
    ]);

Do not commit credentials to source control.

== Security notes ==

The connection test uses an AWS Signature Version 4 PUT request. It does not request read or delete permissions. Test objects contain only the site URL and test timestamp.
