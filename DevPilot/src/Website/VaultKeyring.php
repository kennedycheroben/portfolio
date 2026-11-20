<?php
declare(strict_types=1);

namespace DevPilot\Website;

use RuntimeException;

/** Reads a separate, operator-provisioned JSON keyring. It never creates keys. */
final class VaultKeyring
{
    /** @param array<string,string> $keys Raw 32-byte keys keyed by version. */
    public function __construct(private readonly string $activeVersion, private readonly array $keys)
    {
        if (!extension_loaded('sodium') || !defined('SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES')) {
            throw new RuntimeException('Secure credential storage is unavailable.');
        }
        if (!preg_match('/^[A-Za-z0-9._-]{1,32}$/', $activeVersion)
            || !isset($keys[$activeVersion])) {
            throw new RuntimeException('Vault keyring configuration is invalid.');
        }
        foreach ($keys as $version => $key) {
            if (!is_string($version) || !preg_match('/^[A-Za-z0-9._-]{1,32}$/', $version)
                || !is_string($key) || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
                throw new RuntimeException('Vault keyring configuration is invalid.');
            }
        }
    }

    public static function fromEnvironment(): self
    {
        if (!extension_loaded('sodium') || !function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            throw new RuntimeException('Secure credential storage is unavailable.');
        }

        $configuredPath = getenv('DEVPILOT_VAULT_KEYRING_FILE');
        if (!is_string($configuredPath) || $configuredPath === '' || !str_starts_with($configuredPath, '/')) {
            throw new RuntimeException('Secure credential storage is unavailable.');
        }
        if (is_link($configuredPath)) {
            throw new RuntimeException('Secure credential storage is unavailable.');
        }
        $realPath = realpath($configuredPath);
        if ($realPath === false || !is_file($realPath) || !is_readable($realPath)) {
            throw new RuntimeException('Secure credential storage is unavailable.');
        }

        $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($documentRoot !== false && ($realPath === $documentRoot || str_starts_with($realPath, rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Secure credential storage is unavailable.');
        }

        $permissions = fileperms($realPath);
        if ($permissions === false || (($permissions & 0077) !== 0)) {
            throw new RuntimeException('Secure credential storage is unavailable.');
        }

        $contents = @file_get_contents($realPath);
        $document = is_string($contents) ? json_decode($contents, true) : null;
        if (!is_array($document)
            || !is_string($document['active_version'] ?? null)
            || !is_array($document['keys'] ?? null)) {
            throw new RuntimeException('Secure credential storage is unavailable.');
        }
        $keys = [];
        foreach ($document['keys'] as $version => $encodedKey) {
            if (!is_string($version) || !is_string($encodedKey)) {
                throw new RuntimeException('Secure credential storage is unavailable.');
            }
            $decoded = base64_decode($encodedKey, true);
            if (!is_string($decoded)) {
                throw new RuntimeException('Secure credential storage is unavailable.');
            }
            $keys[$version] = $decoded;
        }
        return new self($document['active_version'], $keys);
    }

    public function activeVersion(): string
    {
        return $this->activeVersion;
    }

    public function key(string $version): string
    {
        if (!isset($this->keys[$version])) {
            throw new RuntimeException('Credential key version is unavailable.');
        }
        return $this->keys[$version];
    }
}
