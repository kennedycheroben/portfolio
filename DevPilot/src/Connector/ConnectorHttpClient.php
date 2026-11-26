<?php
declare(strict_types=1);

namespace DevPilot\Connector;

use RuntimeException;

/** HTTPS-only connector transport. DNS answers are pinned for the TLS request and redirects fail closed. */
final class ConnectorHttpClient
{
    public function post(string $origin, string $path, ?string $bearer = null, array $payload = []): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('Secure connector transport is unavailable.');
        $origin = ConnectorPolicy::origin($origin);
        if (!in_array($path, ['/api/devpilot-connector.php?action=authorize', '/api/devpilot-connector.php?action=inspect', '/api/devpilot-connector.php?action=revoke', '/api/devpilot-hub-connector.php?action=request'], true)) {
            throw new RuntimeException('Connector operation is not supported.');
        }
        $host = (string) parse_url($origin, PHP_URL_HOST);
        $port = (int) (parse_url($origin, PHP_URL_PORT) ?? 443);
        $ips = ConnectorPolicy::publicAddresses($host);
        $url = $origin . $path;
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $responseBody = '';
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($bearer !== null) {
            if (!preg_match('/^dvp_[A-Za-z0-9_-]{40,}$/', $bearer)) throw new RuntimeException('Connector authentication failed.');
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }
        $resolve = [];
        foreach ($ips as $ip) $resolve[] = $host . ':' . $port . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip);
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => $resolve,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$responseBody): int {
                if (strlen($responseBody) + strlen($chunk) > 1048576) return 0;
                $responseBody .= $chunk;
                return strlen($chunk);
            },
        ]);
        if (getenv('DEVPILOT_CONNECTOR_TEST_MODE') === '1' && getenv('APP_ENV') !== 'production') {
            $testCa = getenv('DEVPILOT_CONNECTOR_TEST_CA');
            if (is_string($testCa) && $testCa !== '' && is_file($testCa) && is_readable($testCa)) curl_setopt($handle, CURLOPT_CAINFO, $testCa);
        }
        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($ok === false || $status < 200 || $status >= 300) throw new RuntimeException('Connector request failed.');
        try { $decoded = json_decode($responseBody, true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException $exception) { throw new RuntimeException('Connector returned an invalid response.', 0, $exception); }
        if (!is_array($decoded) || ($decoded['success'] ?? false) !== true) throw new RuntimeException('Connector request was not authorized.');
        return $decoded;
    }
}
