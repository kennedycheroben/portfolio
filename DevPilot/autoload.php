<?php
declare(strict_types=1);

/** Lightweight PSR-4 loader for the DevPilot runtime; Composer is optional. */
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'DevPilot\\Adapters\\' => __DIR__ . '/adapters/',
        'DevPilot\\Api\\' => __DIR__ . '/api/',
        'DevPilot\\' => __DIR__ . '/src/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $directory . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_file($file) && is_readable($file)) {
            require_once $file;
        }
        return;
    }
});
