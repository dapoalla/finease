<?php
require_once 'config/database.php';

echo "Adding receipt_file column to transactions table...\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Cannot connect to database");
    }
    
    // Add receipt_file column to transactions table
    $db->exec("ALTER TABLE transactions ADD COLUMN receipt_file VARCHAR(255) NULL");
    echo "Successfully added receipt_file column to transactions table.\n";
    
    // Create uploads directory if it doesn't exist
    if (!file_exists('uploads')) {
        mkdir('uploads', 0755, true);
        echo "Created uploads directory.\n";
    }
    
    if (!file_exists('uploads/receipts')) {
        mkdir('uploads/receipts', 0755, true);
        echo "Created uploads/receipts directory.\n";
    }
    
    echo "Migration completed successfully!\n";
    echo "You can now upload receipt files.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists - migration not needed.\n";
    }
}
?>