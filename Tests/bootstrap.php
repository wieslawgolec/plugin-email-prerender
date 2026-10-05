<?php

declare(strict_types=1);

$vendorAutoload = dirname(__DIR__).'/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    require_once __DIR__.'/autoload_simple.php';
}

// Always load stubs (idempotent via class_exists checks)
require_once __DIR__.'/Stub/MauticStubs.php';
