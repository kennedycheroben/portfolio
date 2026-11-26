<?php
declare(strict_types=1);

namespace DevPilot\Connector;

use InvalidArgumentException;

final class ConnectorPolicy
{
    public const INSPECT = 'project.inspect.read';
    public const ALLOWED_SCOPES = [self::INSPECT];

    public static function origin(string $origin): string
    {
        $parts = parse_url(trim($origin));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')) {
            throw new InvalidArgumentException('Connector origin must be a public HTTPS origin.');
        }
        $localSuffixes = ['.local','.internal','.localhost','.test','.invalid','.example','.home.arpa'];
        $localOnly = $host === 'localhost';
        foreach ($localSuffixes as $suffix) if (str_ends_with($host, $suffix)) $localOnly = true;
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || !str_contains($host, '.') || $localOnly) {
            throw new InvalidArgumentException('Private and literal IP connector origins are not allowed.');
        }
        foreach (explode('.', $host) as $label) {
            if ($label === '' || strlen($label) > 63 || !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label)) throw new InvalidArgumentException('Connector origin hostname is invalid.');
        }
        $port = (int) ($parts['port'] ?? 443);
        if ($port !== 443) throw new InvalidArgumentException('Connector origins must use HTTPS port 443.');
        return 'https://' . $host;
    }

    /** Resolve once per request and reject the complete DNS answer set unless every address is public. */
    public static function publicAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $addresses = [$host];
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            $addresses = [];
            foreach (is_array($records) ? $records : [] as $record) {
                $address = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($address)) $addresses[] = $address;
            }
        }
        $addresses = array_values(array_unique($addresses));
        if ($addresses === []) throw new InvalidArgumentException('Connector host could not be resolved.');
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false || self::isProhibitedAddress($address)) {
                throw new InvalidArgumentException('Connector host resolves to a prohibited IP address.');
            }
        }
        return $addresses;
    }

    private static function isProhibitedAddress(string $address): bool
    {
        $ranges = [
            '0.0.0.0/8','10.0.0.0/8','100.64.0.0/10','127.0.0.0/8','169.254.0.0/16','172.16.0.0/12',
            '192.0.0.0/24','192.0.2.0/24','192.31.196.0/24','192.52.193.0/24','192.88.99.0/24','192.168.0.0/16','192.175.48.0/24','198.18.0.0/15','198.51.100.0/24','203.0.113.0/24','224.0.0.0/4','240.0.0.0/4',
            '::/128','::1/128','::ffff:0:0/96','64:ff9b::/96','64:ff9b:1::/48','100::/64','2001:db8::/32','2001::/23','2002::/16','fc00::/7','fe80::/10','fec0::/10','ff00::/8',
        ];
        $packed = @inet_pton($address);
        if (!is_string($packed)) return true;
        foreach ($ranges as $range) {
            [$network, $prefix] = explode('/', $range, 2);
            $networkPacked = inet_pton($network);
            if (!is_string($networkPacked) || strlen($packed) !== strlen($networkPacked)) continue;
            $bits = (int) $prefix;
            $wholeBytes = intdiv($bits, 8);
            if (substr($packed, 0, $wholeBytes) !== substr($networkPacked, 0, $wholeBytes)) continue;
            $remaining = $bits % 8;
            if ($remaining === 0 || ((ord($packed[$wholeBytes]) ^ ord($networkPacked[$wholeBytes])) & (0xff << (8 - $remaining))) === 0) return true;
        }
        return false;
    }

    public static function scopes(array $scopes): array
    {
        $result = [];
        foreach ($scopes as $scope) {
            if (!is_string($scope) || !in_array($scope, self::ALLOWED_SCOPES, true)) throw new InvalidArgumentException('Requested connector permission is not supported.');
            $result[$scope] = true;
        }
        return array_keys($result);
    }
}
