<?php

declare(strict_types=1);

/**
 * Plaud PHP SDK Standalone PSR-4 Autoloader
 *
 * Allows using the package without Composer if desired.
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'Plaud\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Automatically load .env if present in package directory or project root
(function (): void {
    $candidates = [
        __DIR__ . '/.env',
        __DIR__ . '/../.env',
    ];

    foreach ($candidates as $envFile) {
        if (!file_exists($envFile) || !is_readable($envFile)) {
            continue;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            continue;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1], " \t\n\r\0\x0B\"'");

                if (getenv($key) === false) {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
        break;
    }
})();
