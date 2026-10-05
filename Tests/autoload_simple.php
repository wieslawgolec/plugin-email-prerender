<?php
declare(strict_types=1);

spl_autoload_register(function ($class) {
    $prefix = 'MauticPlugin\\MauticEmailPreRenderBundle\\';
    $baseDir = dirname(__DIR__) . '/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

spl_autoload_register(function ($class) {
    $prefix = 'MauticPlugin\\MauticEmailPreRenderBundle\\Tests\\';
    $baseDir = dirname(__DIR__) . '/Tests/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

require_once __DIR__ . '/Stub/MauticStubs.php';
