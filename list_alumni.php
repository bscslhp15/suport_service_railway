<?php
try {
    $conn = new PDO('mysql:host=localhost;dbname=support_system', 'root', '');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>All Alumni Records</h1>";
    
    $stmt = $conn->query("SELECT id, full_name, employment_status, personal_email FROM ssaa_alumni ORDER BY id DESC LIMIT 20");
    $alumni = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($alumni)) {
        echo "<p style='color: red;'>❌ No alumni records found!</p>";
    } else {
        echo "<p>Found " . count($alumni) . " alumni records</p>";
        echo "<table border='1' cellpadding='10' style='width: 100%;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Employment Status</th><th>Email</th></tr>";
        foreach ($alumni as $a) {
            echo "<tr>";
            echo "<td>{$a['id']}</td>";
            echo "<td>" . htmlspecialchars($a['full_name']) . "</td>";
            echo "<td>" . ($a['employment_status'] ?: '(empty)') . "</td>";
            echo "<td>" . htmlspecialchars($a['personal_email'] ?? '(none)') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<hr>";
    echo "<h2>Test: Load Alumni Tracer Form with Different Alumni</h2>";
    if (!empty($alumni)) {
        $testId = $alumni[0]['id'];
        echo "<p>Try loading the form with alumniid=" . $testId . ": <a href='alumni_tracer_form.php?alumni_id=" . $testId . "' target='_blank'>Open Form</a></p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
