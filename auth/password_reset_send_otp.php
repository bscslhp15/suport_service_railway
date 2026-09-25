<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$email = secure_input($input['email'] ?? '');

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

$user = find_user_by_email($email);
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'No account found for that email.']);
    exit;
}

$reset_code = generate_code();
$expires = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');
$updated = update_user([
    'id' => $user['id'],
    'reset_code' => $reset_code,
    'reset_expires' => $expires,
]);

if (!$updated) {
    echo json_encode(['success' => false, 'message' => 'Unable to create reset code.']);
    exit;
}

echo json_encode(['success' => true, 'code' => $reset_code]);
