# AWS S3 setup

## Bucket

1. Create a private S3 bucket (e.g. `your-organisation-referrals`) in your
   chosen region. Block all public access.
2. Enable default encryption with SSE-S3 (`AES256`).
3. No bucket policy is required to make objects public — this plugin never
   requires public read access.

## IAM user (write-only)

Create an IAM user dedicated to this plugin with only `PutObject` permission,
scoped to the bucket/prefix in use:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": ["s3:PutObject"],
            "Resource": "arn:aws:s3:::your-organisation-referrals/*"
        }
    ]
}
```

Do not grant `s3:GetObject`, `s3:ListBucket` or `s3:DeleteObject` — the plugin
never needs them, and omitting them limits the impact of leaked credentials.

## wp-config.php

Add this above the `/* That's all, stop editing! */` line:

```php
define('KODR_GF_ARCHIVE', [
    'region'        => 'us-east-1',
    'bucket'        => 'your-organisation-referrals',
    'prefix'        => '',
    'access_key_id' => 'YOUR_ACCESS_KEY_ID',
    'secret_key'    => 'YOUR_SECRET_ACCESS_KEY',
    'alert_email'   => 'alerts@example.com',
]);
```

Never commit real credentials to source control. Use placeholder values in any
example or fixture file.
