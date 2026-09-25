<?php
// Migration to add officer_year column to ssc_candidates table
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();

// Only allow teacher with ssc_scholarship role
if ($user['role'] !== 'teacher' || $user['head_service'] !== 'ssc_scholarship') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

try {
    // Check if officer_year column exists
    $stmt = $conn->prepare("SHOW COLUMNS FROM ssc_candidates LIKE 'officer_year'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if (!$result) {
        // Add officer_year column
        $alter = $conn->prepare("ALTER TABLE ssc_candidates ADD COLUMN officer_year VARCHAR(20) NULL AFTER status");
        $alter->execute();
        echo json_encode(['success' => true, 'message' => 'officer_year column added successfully']);
    } else {
        echo json_encode(['success' => true, 'message' => 'officer_year column already exists']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
