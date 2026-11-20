<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: devpilot-devices.php');
    exit;
}

verify_csrf();

$deviceId = $_POST['deviceId'] ?? '';
if ($deviceId === '' || $deviceId === 'self') {
    header('Location: devpilot.php?error=invalid_device');
    exit;
}

if (\DevPilot\Api\DeviceAuth::revokeDevice($deviceId)) {
    \DevPilot\Logger::info('Device revoked', ['deviceId' => $deviceId, 'adminId' => $_SESSION['user_id'] ?? 0]);
    header('Location: devpilot.php?success=device_revoked');
} else {
    header('Location: devpilot.php?error=device_not_found');
}
exit;