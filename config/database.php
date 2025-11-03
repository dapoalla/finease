<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;
    
    public function __construct() {
        if (file_exists(__DIR__ . '/config.php')) {
            require_once __DIR__ . '/config.php';
            $this->host = DB_HOST ?? 'localhost';
            $this->db_name = DB_NAME ?? '';
            $this->username = DB_USER ?? '';
            $this->password = DB_PASS ?? '';
        } else {
            // Default values if config doesn't exist
            $this->host = 'localhost';
            $this->db_name = '';
            $this->username = '';
            $this->password = '';
        }
    }
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                                $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            // Do not echo here to avoid breaking headers/UI; log instead
            error_log("Connection error: " . $exception->getMessage());
            $this->conn = null;
        }
        
        return $this->conn;
    }
    
    public function createTables() {
        // Ensure we have a connection
        if (!$this->conn) {
            $this->conn = $this->getConnection();
        }
        
        if (!$this->conn) {
            throw new Exception("Cannot create tables: No database connection");
        }
        
        // Create tables one by one to avoid issues
        $tables = [
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('admin', 'accountant', 'viewer') DEFAULT 'viewer',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS company_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_name VARCHAR(255) NOT NULL,
                address TEXT,
                contact_info TEXT,
                logo_path VARCHAR(255) NULL,
                country VARCHAR(100),
                currency VARCHAR(10) DEFAULT '₦',
                tax_enabled BOOLEAN DEFAULT FALSE,
                tax_rate DECIMAL(5,2) DEFAULT 7.50,
                tithe_rate DECIMAL(5,2) DEFAULT 10.00,
                vat_threshold DECIMAL(15,2) DEFAULT 25000000.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS clients (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255),
                phone VARCHAR(50),
                address TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS bank_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                type ENUM('cash', 'bank') NOT NULL,
                balance DECIMAL(15,2) DEFAULT 0,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS inventory (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                purchase_cost DECIMAL(15,2) NOT NULL,
                current_value DECIMAL(15,2) NOT NULL,
                purchase_date DATE NOT NULL,
                depreciation_rate DECIMAL(5,2) DEFAULT 0,
                depreciation_method ENUM('straight_line', 'declining_balance') DEFAULT 'straight_line',
                useful_life_years INT DEFAULT 5,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS vat_records (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice_id INT,
                vat_amount DECIMAL(15,2) NOT NULL,
                vat_rate DECIMAL(5,2) NOT NULL,
                period_month VARCHAR(7),
                status ENUM('collected', 'paid', 'pending') DEFAULT 'collected',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS invoices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice_id VARCHAR(50) UNIQUE NOT NULL,
                client_id INT,
                client_name VARCHAR(255) NOT NULL,
                service_description TEXT NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                vat_amount DECIMAL(15,2) DEFAULT 0,
                total_with_vat DECIMAL(15,2) DEFAULT 0,
                has_line_items BOOLEAN DEFAULT FALSE,
                apply_vat BOOLEAN DEFAULT TRUE,
                line_items_total DECIMAL(15,2) DEFAULT 0,
                date DATE NOT NULL,
                notes TEXT,
                status ENUM('open', 'completed') DEFAULT 'open',
                payment_status ENUM('unpaid', 'partly_paid', 'fully_paid') DEFAULT 'unpaid',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS job_order_line_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                job_order_id INT NOT NULL,
                description VARCHAR(255) NOT NULL,
                unit_price DECIMAL(15,2) NOT NULL,
                quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
                total DECIMAL(15,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type ENUM('inflow', 'outflow') NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                description TEXT NOT NULL,
                seller_details VARCHAR(255) NULL,
                receipt_number VARCHAR(100) NULL,
                receipt_file VARCHAR(255) NULL,
                category ENUM('internal', 'invoice_linked') NOT NULL,
                invoice_id INT NULL,
                bank_account_id INT,
                is_recurring BOOLEAN DEFAULT FALSE,
                recurring_frequency ENUM('monthly', 'quarterly', 'yearly') NULL,
                transaction_date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS tithes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice_id INT NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                status ENUM('owed', 'paid') DEFAULT 'owed',
                date_generated DATE NOT NULL,
                date_paid DATE NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS generated_invoices (
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
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        ];
        
        foreach ($tables as $sql) {
            $this->conn->exec($sql);
        }
        
        // Add foreign keys separately
        try {
            $this->conn->exec("ALTER TABLE invoices ADD CONSTRAINT fk_invoices_client 
                              FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL");
        } catch (Exception $e) {
            // Foreign key might already exist
        }
        
        try {
            $this->conn->exec("ALTER TABLE transactions ADD CONSTRAINT fk_transactions_invoice 
                              FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL");
        } catch (Exception $e) {
            // Foreign key might already exist
        }
        
        try {
            $this->conn->exec("ALTER TABLE transactions ADD CONSTRAINT fk_transactions_bank 
                              FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL");
        } catch (Exception $e) {
            // Foreign key might already exist
        }
        
        try {
            $this->conn->exec("ALTER TABLE tithes ADD CONSTRAINT fk_tithes_invoice 
                              FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE");
        } catch (Exception $e) {
            // Foreign key might already exist
        }
        
        try {
            $this->conn->exec("ALTER TABLE vat_records ADD CONSTRAINT fk_vat_invoice 
                              FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE");
        } catch (Exception $e) {
            // Foreign key might already exist
        }
        
        try {
            $this->conn->exec("ALTER TABLE job_order_line_items ADD CONSTRAINT fk_line_items_job_order 
                              FOREIGN KEY (job_order_id) REFERENCES invoices(id) ON DELETE CASCADE");
        } catch (Exception $e) {
            // Foreign key might already exist
        }
        
        try {
            $this->conn->exec("ALTER TABLE generated_invoices ADD CONSTRAINT fk_generated_invoices_job_order 
                              FOREIGN KEY (job_order_id) REFERENCES invoices(id) ON DELETE CASCADE");
        } catch (Exception $e) {
            // Foreign key might already exist
        }
        
        try {
            $this->conn->exec("ALTER TABLE generated_invoices ADD CONSTRAINT fk_generated_invoices_client 
                              FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL");
        } catch (Exception $e) {
            // Foreign key might already exist
        }
        
        // Create default admin user if not exists
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $stmt = $this->conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
            $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
        }
        
        // Do not auto-create default bank accounts; start with a blank list
    }

    // Run migrations and environment checks; returns a list of messages
    public function runMigrationsAndChecks() {
        if (!$this->conn) {
            $this->conn = $this->getConnection();
        }
        $messages = [];
        if (!$this->conn) {
            $messages[] = ['type' => 'error', 'text' => 'Database connection failed; cannot run migrations.'];
            return $messages;
        }

        // Required columns
        $requiredColumns = [
            'invoices.has_line_items' => "ALTER TABLE invoices ADD COLUMN has_line_items BOOLEAN DEFAULT FALSE",
            'invoices.line_items_total' => "ALTER TABLE invoices ADD COLUMN line_items_total DECIMAL(15,2) DEFAULT 0",
            'invoices.apply_vat' => "ALTER TABLE invoices ADD COLUMN apply_vat BOOLEAN DEFAULT TRUE",
            'transactions.receipt_file' => "ALTER TABLE transactions ADD COLUMN receipt_file VARCHAR(255) NULL",
            'transactions.seller_details' => "ALTER TABLE transactions ADD COLUMN seller_details VARCHAR(255) NULL",
            'transactions.receipt_number' => "ALTER TABLE transactions ADD COLUMN receipt_number VARCHAR(100) NULL",
            'company_settings.logo_path' => "ALTER TABLE company_settings ADD COLUMN logo_path VARCHAR(255) NULL",
            'company_settings.invoice_bank_name' => "ALTER TABLE company_settings ADD COLUMN invoice_bank_name VARCHAR(100) NULL",
            'company_settings.invoice_bank_account_number' => "ALTER TABLE company_settings ADD COLUMN invoice_bank_account_number VARCHAR(50) NULL"
        ];

        foreach ($requiredColumns as $column => $sql) {
            list($table, $columnName) = explode('.', $column);
            try {
                $stmt = $this->conn->prepare("SHOW COLUMNS FROM $table LIKE ?");
                $stmt->execute([$columnName]);
                if ($stmt->rowCount() == 0) {
                    $this->conn->exec($sql);
                    $messages[] = ['type' => 'success', 'text' => "Added column: $column"]; 
                } else {
                    $messages[] = ['type' => 'success', 'text' => "Column exists: $column"]; 
                }
            } catch (Exception $e) {
                $messages[] = ['type' => 'error', 'text' => "Failed to ensure $column: " . $e->getMessage()];
            }
        }

        // Required tables
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
                $this->conn->exec($sql);
                $messages[] = ['type' => 'success', 'text' => "Table ready: $tableName"]; 
            } catch (Exception $e) {
                $messages[] = ['type' => 'error', 'text' => "Failed to create table $tableName: " . $e->getMessage()];
            }
        }

        // Directories and write-permission checks
        $directories = [
            __DIR__ . '/../uploads',
            __DIR__ . '/../uploads/receipts'
        ];
        foreach ($directories as $dir) {
            try {
                if (!file_exists($dir)) {
                    mkdir($dir, 0755, true);
                    @file_put_contents($dir . '/.gitkeep', '');
                    $messages[] = ['type' => 'success', 'text' => "Created directory: " . basename($dir)];
                } else {
                    $messages[] = ['type' => 'success', 'text' => "Directory exists: " . basename($dir)];
                }
                // Permission test
                $testFile = $dir . '/perm_test.txt';
                if (@file_put_contents($testFile, 'ok') !== false) {
                    @unlink($testFile);
                    $messages[] = ['type' => 'success', 'text' => basename($dir) . ' is writable'];
                } else {
                    $messages[] = ['type' => 'error', 'text' => basename($dir) . ' is not writable; adjust permissions'];
                }
            } catch (Exception $e) {
                $messages[] = ['type' => 'error', 'text' => "Failed to initialize directory " . basename($dir) . ": " . $e->getMessage()];
            }
        }

        // Admin user check
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $stmt = $this->conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
                $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
                $messages[] = ['type' => 'success', 'text' => 'Created default admin user (admin/admin123)'];
            } else {
                $messages[] = ['type' => 'success', 'text' => 'Admin user exists'];
            }
        } catch (Exception $e) {
            $messages[] = ['type' => 'error', 'text' => 'Admin user check failed: ' . $e->getMessage()];
        }

        // Sources check: do not auto-seed
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM bank_accounts");
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $messages[] = ['type' => 'info', 'text' => 'No sources found — add banks/payment sources in Settings'];
            } else {
                $messages[] = ['type' => 'success', 'text' => 'Sources exist'];
            }
        } catch (Exception $e) {
            $messages[] = ['type' => 'error', 'text' => 'Sources check failed: ' . $e->getMessage()];
        }

        // Migrate bank_accounts.type to only 'cash' or 'bank'
        try {
            // Convert legacy types to 'bank'
            $this->conn->exec("UPDATE bank_accounts SET type='bank' WHERE type NOT IN ('cash','bank')");
            // Ensure enum restriction
            $this->conn->exec("ALTER TABLE bank_accounts MODIFY COLUMN type ENUM('cash','bank') NOT NULL");
            $messages[] = ['type' => 'success', 'text' => "Aligned source types to 'cash'/'bank'."]; 
        } catch (Exception $e) {
            $messages[] = ['type' => 'error', 'text' => "Source type migration failed: " . $e->getMessage()];
        }

        return $messages;
    }
}
?>