<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    if (getenv('APP_ENV') === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = getenv('APP_ENV') === 'production' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) === '443');
    $runtimeUser = function_exists('posix_geteuid') ? (string) posix_geteuid() : substr(hash('sha256', __DIR__), 0, 12);
    $sessionDirectory = (string) (getenv('SESSION_SAVE_PATH') ?: sys_get_temp_dir() . '/kennedy-portfolio-sessions-' . $runtimeUser);
    if (!is_dir($sessionDirectory)) {
        @mkdir($sessionDirectory, 0700, true);
    }
    if (is_dir($sessionDirectory) && is_writable($sessionDirectory)) {
        session_save_path($sessionDirectory);
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_name('kennedy_portfolio');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'domain' => '', 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

$now = time();
if (isset($_SESSION['last_activity'], $_SESSION['session_started'])
    && (($now - (int) $_SESSION['last_activity']) > 1800 || ($now - (int) $_SESSION['session_started']) > 28800)) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['last_activity'] = $now;
$_SESSION['session_started'] ??= $now;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function text_excerpt(string $value, int $limit): string
{
    if (text_length($value) <= $limit) {
        return $value;
    }
    return function_exists('mb_strimwidth') ? mb_strimwidth($value, 0, $limit, '…', 'UTF-8') : substr($value, 0, max(0, $limit - 3)) . '...';
}

function input_string(array $source, string $key, bool $trim = true): string
{
    $value = $source[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    return $trim ? trim($value) : $value;
}

function valid_iso_date(string $value): bool
{
    if ($value === '') {
        return true;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function csrf_token(): string
{
    $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token = null): void
{
    $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $token ??= is_string($submitted) ? $submitted : '';
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Your session expired. Refresh the page and try again.');
    }
}

function client_ip(): string
{
    return filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown';
}

function rate_limit(string $action, int $limit, int $windowSeconds, string $identity = ''): bool
{
    $identity = $identity !== '' ? strtolower($identity) : client_ip();
    $path = sys_get_temp_dir() . '/kennedy-rate-' . hash('sha256', $action . '|' . $identity) . '.json';
    $handle = @fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        return false;
    }
    $events = json_decode((string) stream_get_contents($handle), true);
    $events = is_array($events) ? $events : [];
    $cutoff = time() - $windowSeconds;
    $events = array_values(array_filter($events, static fn ($timestamp): bool => is_int($timestamp) && $timestamp >= $cutoff));
    if (count($events) >= $limit) {
        flock($handle, LOCK_UN);
        fclose($handle);
        return false;
    }
    $events[] = time();
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($events, JSON_THROW_ON_ERROR));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return true;
}

function site_url(string $path = ''): string
{
    $configured = rtrim((string) (getenv('APP_URL') ?: ''), '/');
    $requestHost = strtolower((string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
    $isLocalRequest = in_array($requestHost, ['localhost', '127.0.0.1', '::1'], true)
        || str_ends_with($requestHost, '.local')
        || str_ends_with($requestHost, '.test');

    // Keep local assets same-origin so the self-only CSP can load them, even
    // when a production APP_URL remains in the local environment file.
    if ($configured !== '' && !$isLocalRequest) {
        return $configured . '/' . ltrim($path, '/');
    }

    static $basePath = null;
    if ($basePath === null) {
        $basePath = '';
        $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $applicationRoot = realpath(dirname(__DIR__));

        if ($documentRoot && $applicationRoot && str_starts_with($applicationRoot, $documentRoot)) {
            $relativeRoot = str_replace(DIRECTORY_SEPARATOR, '/', substr($applicationRoot, strlen($documentRoot)));
            $basePath = '/' . trim($relativeRoot, '/');
        } else {
            $scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
            if (in_array(basename($scriptDirectory), ['admin', 'api', 'forms'], true)) {
                $scriptDirectory = dirname($scriptDirectory);
            }
            $basePath = '/' . trim($scriptDirectory, '/');
        }

        $basePath = $basePath === '/' ? '' : rtrim($basePath, '/');
    }

    return $basePath . '/' . ltrim($path, '/');
}

function redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

function safe_external_url(?string $url): ?string
{
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null;
}

function project_image_url(?string $path, string $fallback = 'assets/img/portfolio/portfolio-1.webp'): string
{
    $path = trim((string) $path);
    if ($path === '') {
        $path = $fallback;
    }

    $external = safe_external_url($path);
    if ($external !== null) {
        return $external;
    }

    $cleanPath = ltrim($path, '/');
    $fullPath = dirname(__DIR__) . '/' . $cleanPath;
    if (file_exists($fullPath)) {
        return site_url($cleanPath);
    }

    $cleanFallback = ltrim($fallback, '/');
    $fullFallback = dirname(__DIR__) . '/' . $cleanFallback;
    if (file_exists($fullFallback)) {
        return site_url($cleanFallback);
    }

    return site_url('assets/img/cheroben_logo.webp');
}

function save_project_upload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The image upload did not complete.');
    }
    if (($file['size'] ?? 0) < 1 || $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Images must be smaller than 5 MB.');
    }
    $tmp = $file['tmp_name'] ?? '';
    if (!is_string($tmp) || $tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('The uploaded image is invalid.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Use a JPEG, PNG, or WebP image.');
    }
    $dimensions = @getimagesize($tmp);
    if (!$dimensions || $dimensions[0] * $dimensions[1] > 20_000_000) {
        throw new RuntimeException('The image is invalid or too large to process safely.');
    }
    $directory = dirname(__DIR__) . '/uploads/projects';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The upload directory is unavailable.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmp, $directory . '/' . $filename)) {
        throw new RuntimeException('The image could not be stored.');
    }
    return 'uploads/projects/' . $filename;
}
