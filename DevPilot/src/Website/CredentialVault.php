<?php
declare(strict_types=1);

namespace DevPilot\Website;

use RuntimeException;
use SodiumException;

final class CredentialVault
{
    public const ALGORITHM = 'xchacha20-poly1305-ietf';
    public const DATA_KEY_VERSION = 'dek-v1';

    public function __construct(private readonly ?VaultKeyring $keyring = null)
    {
    }

    /** @return array{credential_uuid:string,cipher_algorithm:string,data_key_version:string,master_key_version:string,payload_nonce:string,payload_ciphertext:string,wrapped_dek_nonce:string,wrapped_dek:string} */
    public function encrypt(int $websiteId, string $credentialUuid, array $credentials): array
    {
        $this->requireSodium();
        $this->assertContext($websiteId, $credentialUuid);
        $username = $credentials['username'] ?? null;
        $password = $credentials['password'] ?? null;
        if (!is_string($username) || trim($username) === '' || strlen($username) > 320
            || !is_string($password) || $password === '' || strlen($password) > 4096) {
            throw new RuntimeException('Enter a valid username and password.');
        }

        $keyring = $this->keyring();
        $masterVersion = $keyring->activeVersion();
        $payload = null;
        $dek = null;
        try {
            $payload = json_encode(['username' => $username, 'password' => $password], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $dek = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
            $payloadNonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
            $wrappedNonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
            $payloadAad = $this->associatedData($websiteId, $credentialUuid, self::DATA_KEY_VERSION, 'payload');
            $wrappedAad = $this->associatedData($websiteId, $credentialUuid, $masterVersion, 'wrapped-dek:' . self::DATA_KEY_VERSION);
            try {
                $encryptedPayload = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($payload, $payloadAad, $payloadNonce, $dek);
                $wrappedDek = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($dek, $wrappedAad, $wrappedNonce, $keyring->key($masterVersion));
            } catch (SodiumException $exception) {
                throw new RuntimeException('Credential encryption failed.', 0, $exception);
            } finally {
                sodium_memzero($payload);
                sodium_memzero($dek);
            }
        } finally {
            if (is_string($payload)) {
                sodium_memzero($payload);
            }
            if (is_string($dek)) {
                sodium_memzero($dek);
            }
            if (isset($credentials['password']) && is_string($credentials['password'])) {
                sodium_memzero($credentials['password']);
            }
            if (isset($credentials['username']) && is_string($credentials['username'])) {
                sodium_memzero($credentials['username']);
            }
        }

        return [
            'credential_uuid' => $credentialUuid,
            'cipher_algorithm' => self::ALGORITHM,
            'data_key_version' => self::DATA_KEY_VERSION,
            'master_key_version' => $masterVersion,
            'payload_nonce' => $payloadNonce,
            'payload_ciphertext' => $encryptedPayload,
            'wrapped_dek_nonce' => $wrappedNonce,
            'wrapped_dek' => $wrappedDek,
        ];
    }

    /** Decryption is internal-only; callers must never serialize this value. */
    public function decrypt(int $websiteId, array $record): array
    {
        $this->requireSodium();
        $uuid = $record['credential_uuid'] ?? null;
        if (!is_string($uuid)) {
            throw new RuntimeException('Credential is unavailable.');
        }
        $this->assertContext($websiteId, $uuid);
        if (($record['status'] ?? null) !== 'active'
            || ($record['cipher_algorithm'] ?? null) !== self::ALGORITHM
            || !is_string($record['data_key_version'] ?? null)
            || !is_string($record['master_key_version'] ?? null)
            || !is_string($record['payload_nonce'] ?? null)
            || strlen($record['payload_nonce']) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES
            || !is_string($record['payload_ciphertext'] ?? null)
            || !is_string($record['wrapped_dek_nonce'] ?? null)
            || strlen($record['wrapped_dek_nonce']) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES
            || !is_string($record['wrapped_dek'] ?? null)) {
            throw new RuntimeException('Credential is unavailable.');
        }

        $keyring = $this->keyring();
        $masterVersion = $record['master_key_version'];
        $wrappedAad = $this->associatedData($websiteId, $uuid, $masterVersion, 'wrapped-dek:' . $record['data_key_version']);
        $dek = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $record['wrapped_dek'],
            $wrappedAad,
            $record['wrapped_dek_nonce'],
            $keyring->key($masterVersion)
        );
        if (!is_string($dek) || strlen($dek) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new RuntimeException('Credential integrity check failed.');
        }

        try {
            $payload = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $record['payload_ciphertext'],
                $this->associatedData($websiteId, $uuid, $record['data_key_version'], 'payload'),
                $record['payload_nonce'],
                $dek
            );
        } finally {
            sodium_memzero($dek);
        }
        if (!is_string($payload)) {
            throw new RuntimeException('Credential integrity check failed.');
        }
        try {
            $decoded = json_decode($payload, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Credential integrity check failed.', 0, $exception);
        } finally {
            sodium_memzero($payload);
        }
        if (!is_array($decoded) || !is_string($decoded['username'] ?? null) || !is_string($decoded['password'] ?? null)) {
            throw new RuntimeException('Credential integrity check failed.');
        }
        return $decoded;
    }

    /** Rewraps the per-record DEK under the keyring's active master key without re-encrypting the payload. */
    public function rewrap(int $websiteId, array $record): array
    {
        $this->requireSodium();
        $this->assertContext($websiteId, (string) ($record['credential_uuid'] ?? ''));
        if (($record['status'] ?? null) !== 'active'
            || !is_string($record['data_key_version'] ?? null)
            || !is_string($record['master_key_version'] ?? null)
            || !is_string($record['wrapped_dek_nonce'] ?? null)
            || !is_string($record['wrapped_dek'] ?? null)) {
            throw new RuntimeException('Credential is unavailable.');
        }
        $keyring = $this->keyring();
        $uuid = $record['credential_uuid'];
        $oldMasterVersion = $record['master_key_version'];
        $dek = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $record['wrapped_dek'],
            $this->associatedData($websiteId, $uuid, $oldMasterVersion, 'wrapped-dek:' . $record['data_key_version']),
            $record['wrapped_dek_nonce'],
            $keyring->key($oldMasterVersion)
        );
        if (!is_string($dek) || strlen($dek) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new RuntimeException('Credential integrity check failed.');
        }
        $newVersion = $keyring->activeVersion();
        $newNonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        try {
            $newWrappedDek = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $dek,
                $this->associatedData($websiteId, $uuid, $newVersion, 'wrapped-dek:' . $record['data_key_version']),
                $newNonce,
                $keyring->key($newVersion)
            );
        } finally {
            sodium_memzero($dek);
        }
        return ['master_key_version' => $newVersion, 'wrapped_dek_nonce' => $newNonce, 'wrapped_dek' => $newWrappedDek];
    }

    private function keyring(): VaultKeyring
    {
        return $this->keyring ?? VaultKeyring::fromEnvironment();
    }

    private function requireSodium(): void
    {
        if (!extension_loaded('sodium') || !function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            throw new RuntimeException('Secure credential storage is unavailable.');
        }
    }

    private function assertContext(int $websiteId, string $credentialUuid): void
    {
        if ($websiteId <= 0 || strlen($credentialUuid) !== 16) {
            throw new RuntimeException('Credential context is invalid.');
        }
    }

    private function associatedData(int $websiteId, string $credentialUuid, string $keyVersion, string $purpose): string
    {
        return implode('|', ['devpilot-credential-v1', (string) $websiteId, bin2hex($credentialUuid), $keyVersion, $purpose]);
    }
}
