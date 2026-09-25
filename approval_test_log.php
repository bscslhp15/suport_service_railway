<?php
// This will help us debug if the form is being submitted
session_start();

// Log the request
$logFile = __DIR__ . '/approval_debug.log';
$logMessage = date('Y-m-d H:i:s') . " | ";
$logMessage .= "METHOD: " . $_SERVER['REQUEST_METHOD'] . " | ";
$logMessage .= "ACTION: " . ($_POST['action'] ?? 'NONE') . " | ";
$logMessage .= "PENDING_ID: " . ($_POST['pending_id'] ?? 'NONE') . "\n";

file_put_contents($logFile, $logMessage, FILE_APPEND);

// Show what we received
echo "<pre>";
echo "REQUEST METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "POST DATA:\n";
print_r($_POST);
echo "</pre>";
?>
