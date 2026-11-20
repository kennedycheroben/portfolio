<?php
declare(strict_types=1);

namespace DevPilot;

use DateTimeImmutable;

final class Logger
{
    private static ?string $logFile = null;

    // Canonical sensitive key names (lowercase, no separators)
    private static array $canonicalSensitiveKeys = [
        'password',
        'passwd',
        'secret',
        'token',
        'apikey',
        'accesstoken',
        'refresh_token',
        'clientsecret',
        'databasepassword',
        'dbpass',
        'dbpassword',
        'authorization',
        'bearer',
    ];

    public static function init(string $root): void
    {
        $logDir = $root . '/logs';
        if (!is_dir($logDir)) {
            if (!@mkdir($logDir, 0700, true) && !is_dir($logDir)) {
                throw new \RuntimeException('DevPilot log directory could not be created');
            }
        }
        if (!is_writable($logDir)) {
            throw new \RuntimeException('DevPilot log directory is not writable');
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

    private static function normalizeKey(string $key): string
    {
        // Convert to lowercase and remove common separators
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $key));
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = self::normalizeKey($key);
        foreach (self::$canonicalSensitiveKeys as $canonical) {
            if (str_contains($normalized, $canonical) || $canonical === $normalized) {
                return true;
            }
        }
        return false;
    }

    private static function redactMessage(string $message): string
    {
        foreach (self::$canonicalSensitiveKeys as $key) {
            // Match key with various separators: key="value", key='value', key=value, key : value
            $pattern = '/' . preg_quote($key, '/') . '[_-]?["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i';
            $message = preg_replace($pattern, $key . '="***REDACTED***"', $message);
        }

        return $message;
    }

    private static function redactContext(array $context, int $depth = 0): array
    {
        $redacted = [];
        $maxDepth = 10;

        if ($depth > $maxDepth) {
            return ['***NESTING_LIMIT***'];
        }

        foreach ($context as $key => $value) {
            if (self::isSensitiveKey((string) $key)) {
                $redacted[$key] = '***REDACTED***';
            } elseif (is_string($value)) {
                $redacted[$key] = self::redactMessage($value);
            } elseif (is_array($value)) {
                $redacted[$key] = self::redactContext($value, $depth + 1);
            } elseif (is_object($value)) {
                $redacted[$key] = self::redactObject($value, $depth + 1);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    private static function redactObject(object $object, int $depth): mixed
    {
        if ($depth > 10) {
            return '***NESTING_LIMIT***';
        }

        $array = get_object_vars($object);
        if ($array === false) {
            return $object;
        }

        $redacted = [];
        foreach ($array as $key => $value) {
            if (self::isSensitiveKey((string) $key)) {
                $redacted[$key] = '***REDACTED***';
            } elseif (is_string($value)) {
                $redacted[$key] = self::redactMessage($value);
            } elseif (is_array($value)) {
                $redacted[$key] = self::redactContext($value, $depth + 1);
            } elseif (is_object($value)) {
                $redacted[$key] = self::redactObject($value, $depth + 1);
            } else {
                $redacted[$key] = $value;
            }
        }

        return (object) $redacted;
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
