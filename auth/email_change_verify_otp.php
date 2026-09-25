<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Your session has expired. Please log in again.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$code = trim((string)($input['code'] ?? ''));
$pending = $_SESSION['pending_email_change'] ?? null;

if (!$pending || (int)$pending['user_id'] !== (int)$_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'No pending email change was found.']);
    exit;
}
if (!preg_match('/^\d{6}$/', $code)) {
    echo json_encode(['success' => false, 'message' => 'Enter all 6 digits.']);
    exit;
}
if (time() > (int)$pending['expires']) {
    unset($_SESSION['pending_email_change']);
    echo json_encode(['success' => false, 'message' => 'The verification code has expired.']);
    exit;
}
if (!hash_equals((string)$pending['code'], $code)) {
    echo json_encode(['success' => false, 'message' => 'Invalid verification code.']);
    exit;
}
if (find_user_by_email($pending['email'])) {
    unset($_SESSION['pending_email_change']);
    echo json_encode(['success' => false, 'message' => 'That email is already registered.']);
    exit;
}

$updated = update_user(['id' => (int)$_SESSION['user_id'], 'email' => $pending['email']]);
if (!$updated) {
    echo json_encode(['success' => false, 'message' => 'Unable to update your email address.']);
    exit;
}

unset($_SESSION['pending_email_change']);
record_user_activity('Updated account email address');
echo json_encode(['success' => true, 'email' => $pending['email']]);