<?php
declare(strict_types=1);

namespace DevPilot;

use DateTimeImmutable;

final class Logger
{
    private static ?string $logFile = null;

    public static function init(string $root): void
    {
        $logDir = $root . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        self::$logFile = $logDir . '/operations.log';
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        if (self::$logFile === null) {
            self::init(Config::getRoot());
        }

        $timestamp = (new DateTimeImmutable())->format('Y-m-d\TH:i:sP');
        $redacted = self::redact($message);

        $entry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $redacted,
            'context' => self::redactContext($context),
        ];

        $logLine = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents(self::$logFile, $logLine . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function warn(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('DEBUG', $message, $context);
    }

    private static function redact(string $message): string
    {
        $patterns = [
            '/password["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i',
            '/api[_-]?key["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i',
            '/token["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i',
            '/secret["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i',
            '/DB_PASS["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i',
        ];

        foreach ($patterns as $pattern) {
            $message = preg_replace($pattern, '$1 "***REDACTED***"', $message);
        }

        return $message;
    }

    private static function redactContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($value)) {
                $context[$key] = self::redact($value);
            } elseif (is_array($value)) {
                $context[$key] = self::redactContext($value);
            }
        }
        return $context;
    }

    public static function readLogs(int $lines = 100): array
    {
        if (self::$logFile === null || !is_readable(self::$logFile)) {
            return [];
        }

        $handle = fopen(self::$logFile, 'r');
        if ($handle === false) {
            return [];
        }

        flock($handle, LOCK_SH);
        $logs = [];
        $lineCount = 0;

        while (!feof($handle) && $lineCount < $lines) {
            $line = fgets($handle);
            if ($line !== false) {
                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    $logs[] = $decoded;
                }
            }
            $lineCount++;
        }

        flock($handle, LOCK_UN);
        fclose($handle);

        return array_reverse($logs);
    }
}