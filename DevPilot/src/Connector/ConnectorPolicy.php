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
        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])) {
            throw new InvalidArgumentException('Connector origin must be a public HTTPS origin.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new InvalidArgumentException('Private and literal IP connector origins are not allowed.');
        }
        foreach (explode('.', $host) as $label) {
            if ($label === '' || strlen($label) > 63 || !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label)) throw new InvalidArgumentException('Connector origin hostname is invalid.');
        }
        $port = (int) ($parts['port'] ?? 443);
        if ($port !== 443) throw new InvalidArgumentException('Connector origins must use HTTPS port 443.');
        return 'https://' . $host;
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
