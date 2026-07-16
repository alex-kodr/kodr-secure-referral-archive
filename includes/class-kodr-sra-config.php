<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Kodr_SRA_Config
{
    /** @return array<string,mixed> */
    public static function all(): array
    {
        $defaults = [
            'region'        => '',
            'bucket'        => '',
            'prefix'        => '',
            'access_key_id' => '',
            'secret_key'    => '',
            'alert_email'   => get_option('admin_email'),
        ];

        $value = defined('KODR_GF_ARCHIVE') ? constant('KODR_GF_ARCHIVE') : [];
        if (!is_array($value)) {
            $value = [];
        }

        $config = wp_parse_args($value, $defaults);
        $config['prefix'] = trim((string) $config['prefix'], '/');

        return $config;
    }

    /** @return string[] */
    public static function missing_keys(): array
    {
        $config = self::all();
        $required = ['region', 'bucket', 'access_key_id', 'secret_key'];
        $missing = [];

        foreach ($required as $key) {
            if (trim((string) ($config[$key] ?? '')) === '') {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    public static function is_ready(): bool
    {
        return self::missing_keys() === [];
    }

    public static function object_key(string $relative): string
    {
        $prefix = (string) (self::all()['prefix'] ?? '');
        $relative = ltrim($relative, '/');
        return $prefix === '' ? $relative : $prefix . '/' . $relative;
    }
}
