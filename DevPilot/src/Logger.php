<?php
declare(strict_types=1);

namespace DevPilot;

use DateTimeImmutable;

final class Logger
{
    private static ?string $logFile = null;

    private static array $sensitiveKeys = [
        'password',
        'passwd',
        'secret',
        'token',
        'api_key',
        'api-key',
        'authorization',
        'access_key',
        'private_key',
        'credential',
        'db_pass',
        'db_password',
    ];

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
        $redactedMessage = self::redactMessage($message);
        $redactedContext = self::redactContext($context);

        $entry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $redactedMessage,
            'context' => $redactedContext,
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

    private static function redactMessage(string $message): string
    {
        foreach (self::$sensitiveKeys as $key) {
            $pattern = '/' . preg_quote($key, '/') . '["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i';
            $message = preg_replace($pattern, $key . '="***REDACTED***"', $message);
        }

        return $message;
    }

    private static function redactContext(array $context): array
    {
        $redacted = [];

        foreach ($context as $key => $value) {
            $lowerKey = strtolower($key);
            $isSensitive = false;

            foreach (self::$sensitiveKeys as $sensitiveKey) {
                if (str_contains($lowerKey, $sensitiveKey)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $redacted[$key] = '***REDACTED***';
            } elseif (is_string($value)) {
                $redacted[$key] = self::redactMessage($value);
            } elseif (is_array($value)) {
                $redacted[$key] = self::redactContext($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
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