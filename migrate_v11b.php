<?php
require_once 'config/database.php';

echo "Starting migration to v1.1b...\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Cannot connect to database");
    }
    
    echo "Connected to database successfully.\n";
    
    // Add missing columns to invoices table
    echo "Adding missing columns to invoices table...\n";
    
    try {
        $db->exec("ALTER TABLE invoices ADD COLUMN has_line_items BOOLEAN DEFAULT FALSE");
        echo "Added has_line_items column.\n";
    } catch (Exception $e) {
        echo "has_line_items column might already exist: " . $e->getMessage() . "\n";
    }
    
    try {
        $db->exec("ALTER TABLE invoices ADD COLUMN line_items_total DECIMAL(15,2) DEFAULT 0");
        echo "Added line_items_total column.\n";
    } catch (Exception $e) {
        echo "line_items_total column might already exist: " . $e->getMessage() . "\n";
    }
    
    // Create job_order_line_items table
    echo "Creating job_order_line_items table...\n";
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS job_order_line_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_order_id INT NOT NULL,
            description VARCHAR(255) NOT NULL,
            unit_price DECIMAL(15,2) NOT NULL,
            quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
            total DECIMAL(15,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (job_order_id) REFERENCES invoices(id) ON DELETE CASCADE
        )");
        echo "Created job_order_line_items table.\n";
    } catch (Exception $e) {
        echo "job_order_line_items table creation failed: " . $e->getMessage() . "\n";
    }
    
    // Create generated_invoices table
    echo "Creating generated_invoices table...\n";
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS generated_invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_number VARCHAR(50) NOT NULL UNIQUE,
            job_order_id INT NOT NULL,
            client_id INT,
            client_name VARCHAR(255) NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            vat_amount DECIMAL(15,2) DEFAULT 0,
            total_amount DECIMAL(15,2) NOT NULL,
            document_type ENUM('invoice', 'receipt') DEFAULT 'invoice',
            status ENUM('sent', 'paid', 'overdue') DEFAULT 'sent',
            due_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (job_order_id) REFERENCES invoices(id) ON DELETE CASCADE
        )");
        echo "Created generated_invoices table.\n";
    } catch (Exception $e) {
        echo "generated_invoices table creation failed: " . $e->getMessage() . "\n";
    }
    
    echo "Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>