<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/clinic_functions.php';

// Check if user is logged in and authorized
require_login();
$user = current_user();
if ($user['role'] !== 'teacher' || $user['head_service'] !== 'clinic') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get medicine ID from query parameter
$medicineId = isset($_GET['medicine_id']) ? (int)$_GET['medicine_id'] : 0;

if (!$medicineId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid medicine ID']);
    exit;
}

// Fetch active batches for the medicine
$batches = get_active_medicine_batches($medicineId);

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['batches' => $batches]);
