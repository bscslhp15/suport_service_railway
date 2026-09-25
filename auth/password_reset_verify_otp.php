<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$email = secure_input($input['email'] ?? '');
$code = secure_input($input['code'] ?? '');

if (!$email || !$code) {
    echo json_encode(['success' => false, 'message' => 'Email and code are required.']);
    exit;
}

$user = find_user_by_email($email);
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'No account found for that email.']);
    exit;
}

if (empty($user['reset_code']) || $user['reset_code'] !== $code) {
    echo json_encode(['success' => false, 'message' => 'Invalid verification code.']);
    exit;
}

if (empty($user['reset_expires']) || new DateTime() > new DateTime($user['reset_expires'])) {
    echo json_encode(['success' => false, 'message' => 'The verification code has expired.']);
    exit;
}

echo json_encode(['success' => true]);
