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
$email = strtolower(trim((string)($input['email'] ?? '')));
$currentUser = current_user();

if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Your session has expired. Please log in again.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid email address.']);
    exit;
}
if (strcasecmp($email, $currentUser['email']) === 0) {
    echo json_encode(['success' => false, 'message' => 'Enter a different email address.']);
    exit;
}
if (find_user_by_email($email)) {
    echo json_encode(['success' => false, 'message' => 'That email is already registered.']);
    exit;
}

$code = generate_code();
$_SESSION['pending_email_change'] = [
    'user_id' => (int)$currentUser['id'],
    'email' => $email,
    'code' => $code,
    'expires' => time() + 1800,
];

echo json_encode(['success' => true, 'code' => $code]);