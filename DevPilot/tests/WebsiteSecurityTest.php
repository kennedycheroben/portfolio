<?php
declare(strict_types=1);

namespace DevPilot\Tests;

use DevPilot\Website\CredentialVault;
use DevPilot\Website\VaultKeyring;
use DevPilot\Website\WebsiteScopePolicy;
use DevPilot\Website\WebsiteUrlPolicy;
use InvalidArgumentException;
use RuntimeException;

final class WebsiteSecurityTest
{
    public function testWebsiteUrlPolicyIsSyntaxOnlyAndRejectsUnsafeTargets(): void
    {
        $policy = new WebsiteUrlPolicy();
        $site = $policy->normalize('https://Admin.Example.org/console');
        if ($site['admin_url'] !== 'https://admin.example.org/console' || $site['origin_port'] !== 443) {
            throw new RuntimeException('HTTPS URL was not normalized as expected.');
        }
        foreach ([
            'http://admin.example.org/',
            'https://user:pass@admin.example.org/',
            'https://127.0.0.1/admin',
            'https://localhost/admin',
            'https://dashboard.internal/admin',
            'https://admin.example.org:8443/admin',
            'https://admin.example.org/admin?token=secret',
            'https://admin.example.org/admin#fragment',
            'https://not-a-domain/',
        ] as $unsafeUrl) {
            try {
                $policy->normalize($unsafeUrl);
                throw new RuntimeException('Unsafe URL was accepted.');
            } catch (InvalidArgumentException) {
                // Expected. normalize() performs no DNS or HTTP lookup.
            }
        }

        $source = file_get_contents(dirname(__DIR__) . '/src/Website/WebsiteUrlPolicy.php');
        if (!is_string($source) || preg_match('/\b(?:curl_exec|curl_init|stream_socket_client|gethostbyname|dns_get_record)\s*\(/i', $source)) {
            throw new RuntimeException('URL validation contains an outbound network call.');
        }
    }

    public function testWebsiteScopeIsDefaultDenyAndRejectsUnknownPermissions(): void
    {
        $policy = new WebsiteScopePolicy();
        if ($policy->allows([], 'create_demo_accounts')) {
            throw new RuntimeException('Missing scope was not denied.');
        }
        $grants = [['permission_code' => 'create_demo_accounts', 'revoked_at' => null]];
        if (!$policy->allows($grants, 'create_demo_accounts')) {
            throw new RuntimeException('Active scope was not accepted.');
        }
        $grants[0]['revoked_at'] = '2026-01-01 00:00:00';
        if ($policy->allows($grants, 'create_demo_accounts')) {
            throw new RuntimeException('Revoked scope was not denied.');
        }
        try {
            $policy->normalize(['administer_users']);
            throw new RuntimeException('Unsupported permission was accepted.');
        } catch (InvalidArgumentException) {
            // Expected.
        }
    }

    public function testCredentialEncryptionAndEnvelopeDecryption(): void
    {
        $master = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $vault = new CredentialVault(new VaultKeyring('master-v1', ['master-v1' => $master]));
        $uuid = random_bytes(16);
        $encrypted = $vault->encrypt(52, $uuid, ['username' => 'demo-user', 'password' => 'fictional-secret']);
        $record = $encrypted + ['status' => 'active'];
        $plain = $vault->decrypt(52, $record);
        if ($plain !== ['username' => 'demo-user', 'password' => 'fictional-secret']) {
            throw new RuntimeException('Credential round trip failed.');
        }
        if (str_contains($encrypted['payload_ciphertext'], 'fictional-secret') || strlen($encrypted['payload_nonce']) !== 24 || strlen($encrypted['wrapped_dek_nonce']) !== 24) {
            throw new RuntimeException('Credential payload or nonce validation failed.');
        }
        $second = $vault->encrypt(52, random_bytes(16), ['username' => 'demo-user', 'password' => 'fictional-secret']);
        if ($encrypted['payload_nonce'] === $second['payload_nonce'] || $encrypted['wrapped_dek_nonce'] === $second['wrapped_dek_nonce']) {
            throw new RuntimeException('Encryption nonces were reused.');
        }
    }

    public function testCredentialTamperingWrongContextAndRevocationFailClosed(): void
    {
        $key = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $vault = new CredentialVault(new VaultKeyring('master-v1', ['master-v1' => $key]));
        $record = $vault->encrypt(8, random_bytes(16), ['username' => 'name', 'password' => 'secret']) + ['status' => 'active'];
        $tampered = $record;
        $tampered['payload_ciphertext'][0] = chr(ord($tampered['payload_ciphertext'][0]) ^ 1);
        $this->expectFailure(fn() => $vault->decrypt(8, $tampered));
        $this->expectFailure(fn() => $vault->decrypt(9, $record));
        $revoked = $record;
        $revoked['status'] = 'revoked';
        $this->expectFailure(fn() => $vault->decrypt(8, $revoked));
    }

    public function testMasterKeyVersionRewrapAndInvalidKeyHandling(): void
    {
        $v1 = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $v2 = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $oldVault = new CredentialVault(new VaultKeyring('master-v1', ['master-v1' => $v1]));
        $record = $oldVault->encrypt(11, random_bytes(16), ['username' => 'demo', 'password' => 'secret']) + ['status' => 'active'];
        $rotatingVault = new CredentialVault(new VaultKeyring('master-v2', ['master-v1' => $v1, 'master-v2' => $v2]));
        $rewrapped = array_merge($record, $rotatingVault->rewrap(11, $record));
        $plain = $rotatingVault->decrypt(11, $rewrapped);
        if ($plain['password'] !== 'secret') {
            throw new RuntimeException('Rewrapped credential could not be decrypted with the new key version.');
        }
        $this->expectFailure(fn() => new VaultKeyring('master-v1', ['master-v1' => 'short']));
        $this->expectFailure(fn() => $oldVault->decrypt(11, $rewrapped));
    }

    private function expectFailure(callable $operation): void
    {
        try {
            $operation();
        } catch (RuntimeException) {
            return;
        }
        throw new RuntimeException('An invalid or revoked credential operation did not fail closed.');
    }
}
