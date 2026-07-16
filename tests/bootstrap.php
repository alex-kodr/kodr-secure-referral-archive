<?php

declare(strict_types=1);

// Minimal WordPress stand-ins so unit tests can load plugin classes without a
// full WordPress install. Only what the classes under test actually touch.

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS);
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string|false
    {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type, int|bool $gmt = 0): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof \WP_Error;
    }
}

if (!class_exists('WP_Error')) {
    final class WP_Error
    {
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $text): string
    {
        return trim(strip_tags($text));
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = ''): string
    {
        return 'Test Site';
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'https://example.test' . $path;
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return 'https://example.test/wp-admin/' . $path;
    }
}

if (!function_exists('wp_mail')) {
    function wp_mail(string $to, string $subject, string $message): bool
    {
        $GLOBALS['__kodr_test_mails'][] = [
            'to'      => $to,
            'subject' => $subject,
            'message' => $message,
        ];

        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient(string $key): mixed
    {
        return $GLOBALS['__kodr_test_transients'][$key] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $key, mixed $value, int $expiration = 0): bool
    {
        $GLOBALS['__kodr_test_transients'][$key] = $value;

        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $key): bool
    {
        unset($GLOBALS['__kodr_test_transients'][$key]);

        return true;
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

// Global-namespace fakes (Gravity Forms' own classes live in the global
// namespace, so their test doubles must too) aren't PSR-4 autoloadable.
require __DIR__ . '/Support/FakeGFAPI.php';
