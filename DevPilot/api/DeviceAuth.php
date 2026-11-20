<?php
declare(strict_types=1);

namespace DevPilot\Api;

use DevPilot\Config;
use DevPilot\Logger;

final class DeviceAuth
{
    private const DEVICES_FILE = 'devpilot_devices.json';
    private const TOKEN_EXPIRY_SECONDS = 300;

    public static function listRegisteredDevices(): array
    {
        $filePath = self::getDevicesFilePath();
        if (!is_readable($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    public static function registerDevice(string $name, string $publicKey): string
    {
        $devices = self::listRegisteredDevices();
        $deviceId = self::generateDeviceId($name);
        $devices[$deviceId] = [
            'name' => $name,
            'publicKey' => $publicKey,
            'registeredAt' => date('c'),
            'lastSeen' => null,
            'revoked' => false,
        ];

        self::saveDevices($devices);

        Logger::info('Device registered', ['deviceId' => $deviceId, 'name' => $name]);

        return $deviceId;
    }

    public static function revokeDevice(string $deviceId): bool
    {
        $devices = self::listRegisteredDevices();
        if (!isset($devices[$deviceId])) {
            return false;
        }

        if (isset($devices[$deviceId])) {
            $devices[$deviceId]['revoked'] = true;
            $devices[$deviceId]['revokedAt'] = date('c');
            self::saveDevices($devices);
            return true;
        }

        return false;
    }

    public static function updateLastSeen(string $deviceId): void
    {
        $devices = self::listRegisteredDevices();
        if (isset($devices[$deviceId])) {
            $devices[$deviceId]['lastSeen'] = date('c');
            $devices[$deviceId]['online'] = true;
            self::saveDevices($devices);
        }
    }

    public static function authenticate(string $token): ?array
    {
        $payload = self::decodeToken($token);
        if ($payload === null) {
            return null;
        }

        $deviceId = $payload['deviceId'] ?? null;
        if ($deviceId === null) {
            return null;
        }

        $devices = self::listRegisteredDevices();
        if (!isset($devices[$deviceId])) {
            return null;
        }

        $device = $devices[$deviceId];
        if (!empty($device['revoked'])) {
            return null;
        }

        if (self::verifySignature($token, $device['publicKey'] ?? '')) {
            self::updateLastSeen($deviceId);
            return $device;
        }

        return null;
    }

    public static function generateToken(string $deviceId): string
    {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'deviceId' => $deviceId,
            'iat' => time(),
            'exp' => time() + self::TOKEN_EXPIRY_SECONDS,
            'nonce' => bin2hex(random_bytes(16)),
        ]));

        $signature = hash_hmac('sha256', $header . '.' . $payload, $deviceId, true);
        $sigBase64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $header . '.' . $payload . '.' . $sigBase64;
    }

    public static function getDevice(string $deviceId): ?array
    {
        $devices = self::listRegisteredDevices();
        return $devices[$deviceId] ?? null;
    }

    public static function isRevoked(string $deviceId): bool
    {
        $device = self::getDevice($deviceId);
        return $device['revoked'] ?? false;
    }

    private static function generateDeviceId(string $name): string
    {
        return 'dev_' . substr(hash('sha256', $name . microtime(true)), 0, 16);
    }

    private static function decodeToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payload === false) {
            return null;
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return null;
        }

        if (($data['exp'] ?? 0) < time()) {
            return null;
        }

        return $data;
    }

    private static function verifySignature(string $token, string $deviceId): bool
    {
        if ($deviceId === '') {
            return false;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        $header = $parts[0];
        $payload = $parts[1];
        $signature = $parts[2];

        $dataToSign = $header . '.' . $payload;
        $expectedSig = hash_hmac('sha256', $dataToSign, $deviceId, true);

        $sigDecoded = base64_decode(strtr($signature, '-_', '+/'), true);
        if ($sigDecoded === false) {
            return false;
        }

        return hash_equals($expectedSig, $sigDecoded);
    }

    private static function getDevicesFilePath(): string
    {
        $configDir = dirname(Config::getRoot());
        return $configDir . '/' . self::DEVICES_FILE;
    }

    private static function saveDevices(array $devices): void
    {
        $filePath = self::getDevicesFilePath();
        $dir = dirname($filePath);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0700, true)) {
                throw new \RuntimeException('Failed to create devices directory');
            }
        }

        try {
            $json = json_encode(array_values($devices), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to encode devices data');
        }

        if ($json === false) {
            throw new \RuntimeException('Failed to encode devices data');
        }

        $tempFile = $filePath . '.tmp.' . uniqid('', true);
        if (file_put_contents($tempFile, $json) === false) {
            throw new \RuntimeException('Failed to write devices file');
        }

        chmod($tempFile, 0600);
        if (!rename($tempFile, $filePath)) {
            unlink($tempFile);
            throw new \RuntimeException('Failed to atomically replace devices file');
        }
    }
}