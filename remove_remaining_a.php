<?php
require_once __DIR__ . '/includes/functions.php';

$conn = get_db();

try {
    // First, let's see exactly what we have
    $checkStmt = $conn->prepare("SELECT id, description, LENGTH(description) as len FROM library_calendar WHERE description = 'a' OR TRIM(description) = 'a' ORDER BY id DESC");
    $checkStmt->execute();
    $toDelete = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found announcements to delete:<br>";
    foreach ($toDelete as $item) {
        echo "ID: " . $item['id'] . " | Length: " . $item['len'] . " | Content: '" . htmlspecialchars($item['description']) . "'<br>";
    }
    
    if (!empty($toDelete)) {
        // Delete them
        $ids = array_column($toDelete, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("DELETE FROM library_calendar WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        
        echo "<br>Deleted " . $stmt->rowCount() . " announcement(s)<br>";
    } else {
        echo "No announcements found to delete<br>";
    }
    
    // Verify
    echo "<br>Remaining announcements (latest 5):<br>";
    $verifyStmt = $conn->prepare('SELECT id, description, event_date FROM library_calendar ORDER BY id DESC LIMIT 5');
    $verifyStmt->execute();
    $remaining = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($remaining as $ann) {
        $desc = htmlspecialchars(substr($ann['description'], 0, 50));
        echo "ID: " . $ann['id'] . " | Date: " . $ann['event_date'] . " | " . $desc . "...<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
