<?php
declare(strict_types=1);

namespace DevPilot\Website;

use InvalidArgumentException;

/** Syntax-only URL validation. This class deliberately performs no DNS or HTTP requests. */
final class WebsiteUrlPolicy
{
    /** @return array{admin_url:string,origin_host:string,origin_port:int,base_path:string,origin_fingerprint:string} */
    public function normalize(string $url): array
    {
        if ($url === '' || strlen($url) > 2048 || trim($url) !== $url || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            throw new InvalidArgumentException('Enter a valid HTTPS administrator URL.');
        }

        $parts = parse_url($url);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || !isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Use an HTTPS URL without credentials, query parameters, or a fragment.');
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $port = isset($parts['port']) ? (int) $parts['port'] : 443;
        if ($port !== 443 || $host === '' || strlen($host) > 253 || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            throw new InvalidArgumentException('Use a public DNS hostname on the standard HTTPS port.');
        }

        // Browser URL parsers treat a numeric final label as an IPv4 address and
        // accept decimal, octal, hexadecimal, shortened, and integer forms. Reject
        // those spellings before storing a hostname. This remains syntax-only.
        if (preg_match('/^[0-9.]+$/', $host)
            || preg_match('/(?:^|\.)(?:0x[0-9a-f]*|[0-9]+)$/i', $host)
            || !str_contains($host, '.')
            || preg_match('/\.(?:localhost|local|internal|test|invalid|example)$/', $host)) {
            throw new InvalidArgumentException('Use a public DNS hostname. Local and reserved hostnames are not allowed.');
        }

        foreach (explode('.', $host) as $label) {
            if (strlen($label) < 1 || strlen($label) > 63
                || !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label)) {
                throw new InvalidArgumentException('Enter a valid ASCII DNS hostname (international names must use punycode).');
            }
        }

        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }
        if (!str_starts_with($path, '/') || strlen($path) > 1024) {
            throw new InvalidArgumentException('The administrator URL path is invalid.');
        }

        $normalized = 'https://' . $host . ($path === '/' ? '' : $path);
        $origin = 'https://' . $host . ':443';
        return [
            'admin_url' => $normalized,
            'origin_host' => $host,
            'origin_port' => 443,
            'base_path' => $path,
            'origin_fingerprint' => hash('sha256', $origin, true),
        ];
    }
}
