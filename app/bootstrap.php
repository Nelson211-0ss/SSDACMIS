<?php
/**
 * PSR-4ish autoloader so we don't need Composer (keeps deployment simple).
 * Maps "App\..." to "app/..." with PascalCase folders matching namespaces.
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;

    $relative = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($path)) {
        require $path;
    }
});

use App\Core\App;
use App\Core\ErrorHandler;

// Register error/exception plumbing FIRST so any failure inside App::boot()
// itself is caught and turned into a clean 500 + log entry instead of a
// half-rendered page that might leak credentials or DB schema.
ErrorHandler::register();
App::boot();
