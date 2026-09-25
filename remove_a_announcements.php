<?php
require_once __DIR__ . '/includes/functions.php';

// Connect to database
$conn = get_db();

try {
    // Delete all announcements with just "a" and newlines
    $stmt = $conn->prepare("DELETE FROM library_calendar WHERE description IN ('a', 'a\n\na') OR TRIM(description) = 'a'");
    $stmt->execute();
    
    $rowsDeleted = $stmt->rowCount();
    
    echo "Successfully deleted " . $rowsDeleted . " announcement(s)<br>";
    
    // Show remaining announcements to verify
    $verifyStmt = $conn->prepare('SELECT id, description, event_date FROM library_calendar ORDER BY id DESC LIMIT 10');
    $verifyStmt->execute();
    $remaining = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<br>Remaining announcements (latest 10):<br>";
    foreach ($remaining as $ann) {
        $desc = htmlspecialchars(substr($ann['description'], 0, 50));
        echo "ID: " . $ann['id'] . " | Date: " . $ann['event_date'] . " | " . $desc . "...<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
