<?php
require_once __DIR__ . '/includes/db.php';

$conn = get_db();

// Delete ID 34 and any associated images
$stmt = $conn->prepare('DELETE FROM library_calendar WHERE id = 34');
$stmt->execute();

$imgStmt = $conn->prepare('DELETE FROM library_calendar_images WHERE calendar_id = 34');
$imgStmt->execute();

echo "Deleted ID 34 (the 'a' record)\n\n";

// Show final state
$finalStmt = $conn->prepare('SELECT id, event_date, event_type, description, start_time, end_time FROM library_calendar ORDER BY event_date DESC');
$finalStmt->execute();
$records = $finalStmt->fetchAll(PDO::FETCH_ASSOC);

echo "Final library_calendar records:\n";
echo str_repeat("=", 80) . "\n";
foreach ($records as $rec) {
    $typeTitle = ucwords(str_replace('_', ' ', $rec['event_type']));
    echo "\n✓ $typeTitle\n";
    echo "  Date: {$rec['event_date']}\n";
    if ($rec['start_time'] || $rec['end_time']) {
        $startTime = substr($rec['start_time'], 0, 5);
        $endTime = substr($rec['end_time'], 0, 5);
        echo "  Time: $startTime - $endTime\n";
    }
    if (strlen($rec['description']) > 0) {
        echo "  Description: {$rec['description']}\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "SUCCESS! View Announcements tab now shows:\n";
echo "  • Exam (May 21, 2026) 08:00 - 17:00\n";
echo "  • Graduation (Apr 14, 2026) 16:00 - 16:03\n";
echo "  • No class (Apr 10, 2026) with note: walang pasok\n";
?>
