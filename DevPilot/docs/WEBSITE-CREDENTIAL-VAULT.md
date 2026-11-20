# DevPilot website credential vault — operator setup and recovery

Phase 1 accepts only `demo` and `staging` registrations. Existing environment metadata may still contain `production` or `unknown` for compatibility, but new registrations using either value are rejected. Phase 1 will not log in to any registered website. Credential values are stored only after encryption, and there is no API or page that returns a saved value.

Website URL validation in Phase 1 is syntax-only and makes no DNS lookup or outbound request. Any future outbound connection phase must resolve and validate IP addresses on every connection, validate every redirect target, and protect against DNS rebinding before connecting.

## Prerequisites

- PHP Sodium with XChaCha20-Poly1305 available in the PHP runtime used by the website.
- A keyring file outside the public webroot and outside the database, readable by the PHP account and not readable by group/other (mode `0600`).
- `DEVPILOT_VAULT_KEYRING_FILE` set through the external application environment file (the existing `APP_ENV_FILE` mechanism can load it) or the PHP process environment.
- A separate protected backup of the keyring. Never place it in Git, the website directory, database backups, logs, or support tickets.

The local PHP CLI must confirm Sodium before deploying code:

```sh
php -r 'exit(extension_loaded("sodium") ? 0 : 1);'
```

The CLI result does not prove that PHP-FPM/cPanel uses the same PHP version or extension set. Check the cPanel PHP runtime separately before deployment.

## Create the external keyring

Create a directory outside the document root with ownership that allows the PHP process to read it. Set directory mode `0700` and file mode `0600`. Generate a 32-byte master key on the host using a trusted PHP CLI:

```sh
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
```

Place that output in the external JSON file. Example structure (the placeholder is not a usable key):

```json
{
  "active_version": "mk-2026-01",
  "keys": {
    "mk-2026-01": "REPLACE_WITH_BASE64_OF_EXACTLY_32_RANDOM_BYTES"
  }
}
```

Set the absolute file path in the external environment file:

```text
DEVPILOT_VAULT_KEYRING_FILE=/home/account/private/devpilot-vault-keyring.json
```

The application rejects missing Sodium, missing key material, invalid key lengths, symlinked key files, key files inside the document root, and group/world-readable key files. It will not create a key, choose a fallback cipher, or store plaintext.

## Encryption and data layout

Each credential gets a new random 32-byte data-encryption key (DEK). XChaCha20-Poly1305 encrypts the JSON credential payload with a fresh 24-byte nonce. The DEK is separately wrapped under the active master key with another fresh 24-byte nonce. Associated data binds the ciphertext to the website ID, random credential UUID, data-key version, and purpose; the wrapped DEK also authenticates the master-key version. Ciphertext, wrapped DEK, nonces, algorithm, and version identifiers are stored in `devpilot_website_credentials`; usernames and passwords are encrypted together.

The Sodium API requires a 256-bit key and a 24-byte XChaCha nonce, and authenticates additional data without encrypting it. The application uses those explicit lengths and rejects failed authentication during decryption. [PHP Sodium XChaCha20-Poly1305 documentation](https://www.php.net/manual/en/function.sodium-crypto-aead-xchacha20poly1305-ietf-encrypt.php)

## Key versioning and rotation

1. Back up the current keyring separately from database backups and verify that it is readable.
2. Add a newly generated 32-byte key under a new version in the `keys` map, then set that version as `active_version`. Keep old versions in the keyring. New credential records use the new master-key version.
3. Existing credentials continue to decrypt only with the master-key version stored with their wrapped DEK. Do not remove an old key while any active credential refers to it.
4. To rotate all existing wrapped DEKs, use a reviewed maintenance procedure that loads each active credential, calls `CredentialVault::rewrap()`, and updates only the wrapped DEK, wrap nonce, and master-key version in a transaction. Verify all active rows use the target key version and authenticate successfully before considering removal of an old key. Phase 1 provides the cryptographic rewrap primitive but does not include a batch rotation command.
5. Keep old master-key versions in protected recovery copies until all database backups containing DEKs wrapped by those versions have expired or are separately retired. Removing a key early makes those backup copies unrecoverable; retaining a key also means an old backup can still decrypt its historical credentials.

## Replacement, revocation, and backups

- Replacement inserts a new credential version and revokes the previous active version in one database transaction. Only the new ciphertext remains active; the previous row retains metadata but its live ciphertext, nonce, wrapped DEK, and wrap nonce are cleared.
- Revocation makes the row unusable by setting its status to revoked and clearing its live cryptographic fields. This is not a claim of deletion from database snapshots, binary logs, exports, filesystem backups, or remote backups.
- A database backup contains encrypted credential data. Protect it as confidential data. Back up the keyring separately under tighter access control; never bundle the key with the database backup.
- Restore requires both the encrypted DB state and the matching keyring versions. Restore into an isolated environment first and verify decryption without logging or displaying credential values.
- If a master key is lost, credentials wrapped by that version cannot be recovered. If a backup containing credentials may be exposed, rotate the affected website passwords at the remote websites; revoking a DevPilot credential only disables DevPilot's stored copy.

## Manual MFA

Phase 1 records whether MFA is expected but does not attempt remote authentication, collect MFA codes, or store MFA seeds or recovery codes. In a later phase, the owner must complete challenges manually in an isolated interactive session. Unsupported SSO, CAPTCHA, passkey, or hardware-key flows must stop and be reported.
