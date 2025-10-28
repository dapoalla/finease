<?php
/**
 * FinEase v1.1b Final Setup Script
 * Ensures all database columns and features are properly configured
 */

require_once 'config/database.php';

echo "FinEase v1.1b Final Setup\n";
echo "=========================\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Cannot connect to database");
    }
    
    echo "Connected to database successfully.\n\n";
    
    // List of all required columns and their SQL
    $requiredColumns = [
        'invoices.has_line_items' => "ALTER TABLE invoices ADD COLUMN has_line_items BOOLEAN DEFAULT FALSE",
        'invoices.line_items_total' => "ALTER TABLE invoices ADD COLUMN line_items_total DECIMAL(15,2) DEFAULT 0",
        'transactions.receipt_file' => "ALTER TABLE transactions ADD COLUMN receipt_file VARCHAR(255) NULL",
        'transactions.seller_details' => "ALTER TABLE transactions ADD COLUMN seller_details VARCHAR(255) NULL",
        'transactions.receipt_number' => "ALTER TABLE transactions ADD COLUMN receipt_number VARCHAR(100) NULL",
        'company_settings.logo_path' => "ALTER TABLE company_settings ADD COLUMN logo_path VARCHAR(255) NULL"
    ];
    
    // Check and add missing columns
    foreach ($requiredColumns as $column => $sql) {
        list($table, $columnName) = explode('.', $column);
        
        // Check if column exists
        $stmt = $db->prepare("SHOW COLUMNS FROM $table LIKE ?");
        $stmt->execute([$columnName]);
        
        if ($stmt->rowCount() == 0) {
            try {
                $db->exec($sql);
                echo "✓ Added column: $column\n";
            } catch (Exception $e) {
                echo "✗ Failed to add column $column: " . $e->getMessage() . "\n";
            }
        } else {
            echo "✓ Column exists: $column\n";
        }
    }
    
    // Create required tables
    $requiredTables = [
        'job_order_line_items' => "CREATE TABLE IF NOT EXISTS job_order_line_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_order_id INT NOT NULL,
            description VARCHAR(255) NOT NULL,
            unit_price DECIMAL(15,2) NOT NULL,
            quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
            total DECIMAL(15,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (job_order_id) REFERENCES invoices(id) ON DELETE CASCADE
        )",
        'generated_invoices' => "CREATE TABLE IF NOT EXISTS generated_invoices (
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
        )"
    ];
    
    foreach ($requiredTables as $tableName => $sql) {
        try {
            $db->exec($sql);
            echo "✓ Table ready: $tableName\n";
        } catch (Exception $e) {
            echo "✗ Failed to create table $tableName: " . $e->getMessage() . "\n";
        }
    }
    
    // Create uploads directories
    $directories = [
        'uploads',
        'uploads/receipts'
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
            file_put_contents($dir . '/.gitkeep', '');
            echo "✓ Created directory: $dir\n";
        } else {
            echo "✓ Directory exists: $dir\n";
        }
    }
    
    // Update application settings
    $stmt = $db->prepare("SELECT COUNT(*) FROM company_settings");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO company_settings (company_name, currency, tax_enabled, tax_rate, tithe_rate, vat_threshold) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['FinEase', '₦', 0, 7.5, 10.0, 25000000]);
        echo "✓ Created default company settings\n";
    } else {
        echo "✓ Company settings exist\n";
    }
    
    // Verify admin user exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
        $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
        echo "✓ Created default admin user (admin/admin123)\n";
    } else {
        echo "✓ Admin user exists\n";
    }
    
    // Verify bank accounts exist (do NOT auto-create defaults)
    $stmt = $db->prepare("SELECT COUNT(*) FROM bank_accounts");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        echo "• No sources found yet (this is expected).\n";
        echo "  Add sources/banks in Settings when ready.\n";
    } else {
        echo "✓ Sources exist\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "FinEase v1.1b Setup Complete!\n";
    echo str_repeat("=", 50) . "\n\n";
    
    echo "🎉 Your FinEase installation is ready!\n\n";
    echo "📋 Next Steps:\n";
    echo "1. Visit your website to access FinEase\n";
    echo "2. Login with: admin / admin123\n";
    echo "3. Go to Settings to configure your company details\n";
    echo "4. Add your sources (banks/payment methods)\n";
    echo "5. Start managing your finances!\n\n";
    
    echo "🔧 Features Available:\n";
    echo "• Job Order Management with Line Items\n";
    echo "• Client Management\n";
    echo "• Transaction Tracking with Receipt Upload\n";
    echo "• Financial Reports with Custom Date Ranges\n";
    echo "• VAT/Tax Management\n";
    echo "• Role-Based Permissions\n";
    echo "• Professional Invoice Generation\n";
    echo "• Sources Management\n\n";
    
    echo "📚 Documentation: README.md\n";
    echo "🆘 Support: Check the documentation for troubleshooting\n\n";
    
} catch (Exception $e) {
    echo "❌ Setup failed: " . $e->getMessage() . "\n";
    echo "Please check your database connection and try again.\n";
}
?>