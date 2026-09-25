<?php
require_once __DIR__ . '/includes/db.php';

$conn = get_db();

echo "Searching for 'School' or 'Calendar' in descriptions:\n";
$stmt = $conn->prepare('SELECT id, event_date, event_type, description FROM library_calendar WHERE description LIKE ? OR description LIKE ?');
$stmt->execute(['%School%', '%Calendar%']);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($records)) {
    echo "None found.\n\n";
}else {
    foreach ($records as $rec) {
        echo "ID: " . $rec['id'] . " | Type: " . $rec['event_type'] . " | Date: " . $rec['event_date'] . "\n";
        echo "Description: " . $rec['description'] . "\n\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "Summary of library_calendar with event_type='others':\n\n";

$stmt2 = $conn->prepare('SELECT id, description FROM library_calendar WHERE event_type = ? ORDER BY id DESC');
$stmt2->execute(['others']);
$others = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Total 'others' type announcements: " . count($others) . "\n";
foreach ($others as $rec) {
    echo "ID: " . $rec['id'] . " - " . substr($rec['description'], 0, 50) . "...\n";
}
?>
