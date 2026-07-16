<?php

declare(strict_types=1);

// Minimal WordPress stand-ins so unit tests can load plugin classes without a
// full WordPress install. Only what the classes under test actually touch.

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require dirname(__DIR__) . '/vendor/autoload.php';
