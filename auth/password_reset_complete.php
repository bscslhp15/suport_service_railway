<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$email = secure_input($input['email'] ?? '');
$code = secure_input($input['code'] ?? '');
$password = $input['password'] ?? '';
$confirmPassword = $input['confirm_password'] ?? '';

if (!$email || !$code || !$password || !$confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if ($password !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
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

$updated = update_user([
    'id' => $user['id'],
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'reset_code' => null,
    'reset_expires' => null,
]);

if (!$updated) {
    echo json_encode(['success' => false, 'message' => 'Unable to update password.']);
    exit;
}

echo json_encode(['success' => true]);
