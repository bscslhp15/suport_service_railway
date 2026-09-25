<?php
$logFile = "C:\\xampp\\apache\\logs\\error.log";

echo "<h1>Apache/PHP Error Log Viewer</h1>";
echo "<p>Log file: " . htmlspecialchars($logFile) . "</p>";
echo "<p><strong>Tip:</strong> Submit your form and then refresh this page to see debug output.</p>";
echo "<hr>";

if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -50);  // Get last 50 lines
    
    echo "<h2>Last 50 log lines:</h2>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc; overflow-x: auto; font-size: 12px; max-height: 600px; overflow-y: auto;'>";
    
    foreach ($lastLines as $line) {
        // Highlight DEBUG lines in yellow
        if (strpos($line, 'DEBUG') !== false || strpos($line, 'ADD_BOOK') !== false || strpos($line, 'SAVE_BOOK') !== false) {
            echo "<span style='background: yellow;'>" . htmlspecialchars($line) . "</span>";
        } else {
            echo htmlspecialchars($line);
        }
    }
    
    echo "</pre>";
    echo "<p><a href='javascript:location.reload()' style='padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>⟲ Refresh</a></p>";
} else {
    echo "<p style='color: red;'>Error log not found at: " . htmlspecialchars($logFile) . "</p>";
    echo "<p>File will be created when the next error/debug message is logged.</p>";
}
?>

