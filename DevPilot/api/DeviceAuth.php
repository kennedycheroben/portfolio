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
        if (!is_array($data)) {
            return [];
        }

        $devices = [];
        foreach ($data as $item) {
            if (is_array($item) && isset($item['id'])) {
                $devices[$item['id']] = $item;
            }
        }

        return $devices;
    }

    public static function registerDevice(string $name, string $publicKey = '', string $secret = null): array
    {
        $devices = self::listRegisteredDevices();
        $deviceId = self::generateDeviceId($name);
        $secret = $secret ?? bin2hex(random_bytes(32));

        foreach ($devices as $existingId => $existingDevice) {
            if ($existingDevice['name'] === $name) {
                unset($devices[$existingId]);
            }
        }

        $devices[$deviceId] = [
            'id' => $deviceId,
            'name' => $name,
            'publicKey' => $publicKey,
            'secret' => $secret,
            'registeredAt' => date('c'),
            'lastSeen' => null,
            'revoked' => false,
        ];

        self::saveDevices($devices);

        Logger::info('Device registered', ['deviceId' => $deviceId, 'name' => $name]);

        return [
            'deviceId' => $deviceId,
            'secret' => $secret,
        ];
    }

    public static function revokeDevice(string $deviceId): bool
    {
        $devices = self::listRegisteredDevices();
        if (!isset($devices[$deviceId])) {
            return false;
        }

        $devices[$deviceId]['revoked'] = true;
        $devices[$deviceId]['revokedAt'] = date('c');
        self::saveDevices($devices);
        return true;
    }

    public static function updateLastSeen(string $deviceId): void
    {
        $devices = self::listRegisteredDevices();
        if (isset($devices[$deviceId]) && !$devices[$deviceId]['revoked']) {
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

        $nonce = $payload['nonce'] ?? '';
        $tokenNonce = $payload['tokenNonce'] ?? null;
        if ($tokenNonce === null || !hash_equals($tokenNonce, $nonce)) {
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

        $deviceSecret = $device['secret'] ?? '';
        if (empty($deviceSecret)) {
            return null;
        }

        if (self::verifySignature($token, $deviceSecret)) {
            self::updateLastSeen($deviceId);
            $result = $device;
            unset($result['secret']);
            return $result;
        }

        return null;
    }

    public static function generateToken(string $deviceId, string $secret): string
    {
        if ($secret === '') {
            throw new \RuntimeException('Device has no secret');
        }

        $nonce = bin2hex(random_bytes(16));

        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'deviceId' => $deviceId,
            'iat' => time(),
            'exp' => time() + self::TOKEN_EXPIRY_SECONDS,
            'nonce' => $nonce,
            'tokenNonce' => $nonce,
        ]));

        $signature = hash_hmac('sha256', $header . '.' . $payload, $secret, true);
        $sigBase64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $header . '.' . $payload . '.' . $sigBase64;
    }

    public static function getDevice(string $deviceId): ?array
    {
        $devices = self::listRegisteredDevices();
        if (!isset($devices[$deviceId])) {
            return null;
        }
        $device = $devices[$deviceId];
        unset($device['secret']);
        return $device;
    }

    public static function isRevoked(string $deviceId): bool
    {
        $device = self::getDevice($deviceId);
        return $device['revoked'] ?? false;
    }

    private static function generateDeviceId(string $name): string
    {
        return 'dev_' . substr(hash('sha256', $name . ':' . bin2hex(random_bytes(16)) . ':' . microtime(true)), 0, 32);
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

        $iat = $data['iat'] ?? 0;
        $now = time();
        if (abs($now - $iat) > 3600) {
            return null;
        }

        return $data;
    }

    private static function verifySignature(string $token, string $secret): bool
    {
        if ($secret === '') {
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
        $expectedSig = hash_hmac('sha256', $dataToSign, $secret, true);

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

        $fileHandle = fopen($filePath, 'c+');
        if ($fileHandle === false) {
            throw new \RuntimeException('Failed to open devices file');
        }

        if (!flock($fileHandle, LOCK_EX)) {
            fclose($fileHandle);
            throw new \RuntimeException('Failed to acquire lock on devices file');
        }

        try {
            $json = json_encode(array_values($devices), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new \RuntimeException('Failed to encode devices data');
            }

            ftruncate($fileHandle, 0);
            rewind($fileHandle);
            fwrite($fileHandle, $json);
            fflush($fileHandle);

            chmod($filePath, 0600);
        } finally {
            flock($fileHandle, LOCK_UN);
            fclose($fileHandle);
        }
    }
}