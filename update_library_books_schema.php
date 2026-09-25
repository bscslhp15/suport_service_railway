<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $pdo = get_db();

    // Add new columns for additional info type and quantity
    $sql = "ALTER TABLE library_books
            ADD COLUMN additional_info_type ENUM('volume','part','number','copies') DEFAULT NULL AFTER lcc_additional_info,
            ADD COLUMN additional_info_quantity INT DEFAULT NULL AFTER additional_info_type";

    $pdo->exec($sql);

    echo "Successfully added additional_info_type and additional_info_quantity columns to library_books table.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>