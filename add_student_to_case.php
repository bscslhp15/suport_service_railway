<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
$pdo = get_db();

// Add student Har Vie (ID: 15) as reported person to case 34 (G-20260420-C6F416)
$stmt = $pdo->prepare('INSERT IGNORE INTO case_reported_persons (case_id, person_user_id) VALUES (?, ?)');
try {
    $stmt->execute([34, 15]);
    echo 'SUCCESS: Added student Har Vie (ID: 15) to case G-20260420-C6F416 (Case ID: 34)' . PHP_EOL;
    
    // Verify
    $verify = $pdo->prepare('SELECT crp.person_user_id, u.full_name FROM case_reported_persons crp JOIN users u ON crp.person_user_id = u.id WHERE crp.case_id = 34');
    $verify->execute();
    
    echo 'Reported persons in case 34:' . PHP_EOL;
    while ($row = $verify->fetch(PDO::FETCH_ASSOC)) {
        echo    '- ' . $row['full_name'] . ' (ID: ' . $row['person_user_id'] . ')' . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>