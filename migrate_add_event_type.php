<?php
require_once __DIR__ . '/includes/db.php';

try {
    $conn = get_db();
    
    // Check if event_type column exists
    $checkStmt = $conn->prepare("SHOW COLUMNS FROM ssc_events LIKE 'event_type'");
    $checkStmt->execute();
    $columnExists = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$columnExists) {
        // Add event_type column with default value 'event'
        $alterStmt = $conn->prepare("ALTER TABLE ssc_events ADD COLUMN event_type VARCHAR(50) DEFAULT 'event'");
        $alterStmt->execute();
        echo json_encode(['success' => true, 'message' => 'Added event_type column to ssc_events table']);
    } else {
        echo json_encode(['success' => true, 'message' => 'event_type column already exists']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
