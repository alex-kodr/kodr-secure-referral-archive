<?php

declare(strict_types=1);

// Minimal WordPress stand-ins so unit tests can load plugin classes without a
// full WordPress install. Only what the classes under test actually touch.

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string|false
    {
        return json_encode($data, $options, $depth);
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';
