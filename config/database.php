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
            echo "Connection error: " . $exception->getMessage();
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
                type ENUM('opay', 'kuda', 'moniepoint', 'gtbank_personal', 'gtbank_corporate', 'access_corporate', 'palmpay', 'cash') NOT NULL,
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
        
        // Create default bank accounts if not exists
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM bank_accounts");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $defaultBanks = [
                ['Opay', 'opay'],
                ['Kuda Bank', 'kuda'],
                ['MoniePoint', 'moniepoint'],
                ['GTBank Personal', 'gtbank_personal'],
                ['GTBank Corporate', 'gtbank_corporate'],
                ['Access Bank Corporate', 'access_corporate'],
                ['PalmPay', 'palmpay'],
                ['Cash', 'cash']
            ];
            
            $stmt = $this->conn->prepare("INSERT INTO bank_accounts (name, type) VALUES (?, ?)");
            foreach ($defaultBanks as $bank) {
                $stmt->execute($bank);
            }
        }
    }
}
?>