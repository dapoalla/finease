<?php
// Database will be included when needed

function getDB() {
    static $connection = null;
    
    // Return cached connection if available
    if ($connection !== null) {
        return $connection;
    }
    
    try {
        // Include database class if not already included
        if (!class_exists('Database')) {
            $configPath = __DIR__ . '/../config/database.php';
            if (file_exists($configPath)) {
                require_once $configPath;
            } else {
                throw new Exception("Database class not found");
            }
        }
        
        // Check if config file exists
        if (!file_exists(__DIR__ . '/../config/config.php')) {
            throw new Exception("Configuration file not found");
        }
        
        $database = new Database();
        $connection = $database->getConnection();
        if (!$connection) {
            throw new Exception("Database connection failed");
        }
        return $connection;
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        // Return null instead of redirecting to avoid header issues
        $connection = null;
        return null;
    }
}

function getCompanySettings() {
    static $settings = null;
    
    // Return cached settings if available
    if ($settings !== null) {
        return $settings;
    }
    
    $db = getDB();
    if (!$db) {
        $settings = [
            'company_name' => 'Your Company',
            'currency' => '₦',
            'tax_enabled' => false,
            'tax_rate' => 7.50,
            'tithe_rate' => 10.00,
            'country' => 'Nigeria',
            'address' => '',
            'contact_info' => ''
        ];
        return $settings;
    }
    
    $stmt = $db->prepare("SELECT * FROM company_settings ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Return default settings if none exist
    if (!$settings) {
        $settings = [
            'company_name' => 'Your Company',
            'currency' => '₦',
            'tax_enabled' => false,
            'tax_rate' => 7.50,
            'tithe_rate' => 10.00,
            'country' => 'Nigeria',
            'address' => '',
            'contact_info' => ''
        ];
    }
    
    return $settings;
}

function formatCurrency($amount) {
    $settings = getCompanySettings();
    $currency = $settings['currency'] ?? '₦';
    return $currency . number_format($amount, 2);
}

function getTotalInflow() {
    $db = getDB();
    if (!$db) return 0;
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'inflow'");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

function getTotalOutflow() {
    $db = getDB();
    if (!$db) return 0;
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'outflow'");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

function getTotalProfit() {
    return getTotalInflow() - getTotalOutflow();
}

function getUnpaidInvoicesCount() {
    // Returns count of open job orders (unpaid invoices)
    $db = getDB();
    if (!$db) return 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM invoices WHERE payment_status != 'fully_paid'");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function getUnpaidTithes() {
    $db = getDB();
    if (!$db) return 0;
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM tithes WHERE status = 'owed'");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

function generateInvoiceId($clientName, $date) {
    // Generates job order ID - Format: DDMMYY-JOB-CLIENT-###
    $db = getDB();
    
    $dateFormatted = date('dmy', strtotime($date));
    $clientCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $clientName), 0, 3));
    
    // Fallback if client name has no letters
    if (empty($clientCode)) {
        $clientCode = 'CLI';
    }
    
    // Get next sequence number for this client
    $stmt = $db->prepare("SELECT COUNT(*) + 1 as next_num FROM invoices WHERE client_name = ?");
    $stmt->execute([$clientName]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextNum = str_pad($result['next_num'], 3, '0', STR_PAD_LEFT);
    
    return $dateFormatted . '-JOB-' . $clientCode . '-' . $nextNum;
}

function calculateInvoiceProfit($invoiceId) {
    $db = getDB();
    
    // Get total inflows for this invoice
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as inflow FROM transactions WHERE invoice_id = ? AND type = 'inflow'");
    $stmt->execute([$invoiceId]);
    $inflow = $stmt->fetch(PDO::FETCH_ASSOC)['inflow'];
    
    // Get total outflows for this invoice
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as outflow FROM transactions WHERE invoice_id = ? AND type = 'outflow'");
    $stmt->execute([$invoiceId]);
    $outflow = $stmt->fetch(PDO::FETCH_ASSOC)['outflow'];
    
    return $inflow - $outflow;
}

function createTitheEntry($invoiceId) {
    $db = getDB();
    $settings = getCompanySettings();
    $titheRate = $settings['tithe_rate'] / 100;
    
    $profit = calculateInvoiceProfit($invoiceId);
    
    if ($profit > 0) {
        $titheAmount = $profit * $titheRate;
        
        $stmt = $db->prepare("INSERT INTO tithes (invoice_id, amount, date_generated) VALUES (?, ?, CURDATE())");
        $stmt->execute([$invoiceId, $titheAmount]);
    }
}

function getMonthlyData() {
    $db = getDB();
    $data = [];
    
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        
        $stmt = $db->prepare("SELECT 
            COALESCE(SUM(CASE WHEN type = 'inflow' THEN amount ELSE 0 END), 0) as inflow,
            COALESCE(SUM(CASE WHEN type = 'outflow' THEN amount ELSE 0 END), 0) as outflow
            FROM transactions 
            WHERE DATE_FORMAT(transaction_date, '%Y-%m') = ?");
        $stmt->execute([$month]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $data[] = [
            'month' => date('M Y', strtotime($month . '-01')),
            'inflow' => $result['inflow'],
            'outflow' => $result['outflow']
        ];
    }
    
    return $data;
}

function checkUserPermission($requiredRole) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    $userRole = $_SESSION['user_role'];
    
    // Define role hierarchy: viewer < accountant < admin
    $roles = ['viewer' => 1, 'accountant' => 2, 'admin' => 3];
    $userLevel = $roles[$userRole] ?? 0;
    $requiredLevel = $roles[$requiredRole] ?? 3;
    
    return $userLevel >= $requiredLevel;
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// Client Management Functions
function createClient($name, $email, $phone, $address) {
    $db = getDB();
    if (!$db) return false;
    
    $stmt = $db->prepare("INSERT INTO clients (name, email, phone, address) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$name, $email, $phone, $address]);
}

function getClientById($id) {
    $db = getDB();
    if (!$db) return null;
    
    $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getAllClients() {
    $db = getDB();
    if (!$db) return [];
    
    $stmt = $db->prepare("SELECT * FROM clients ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getClientReceivables($clientId) {
    $db = getDB();
    if (!$db) return 0;
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(i.total_with_vat - COALESCE(t.total_inflow, 0)), 0) as receivables
        FROM invoices i
        LEFT JOIN (
            SELECT invoice_id, SUM(amount) as total_inflow 
            FROM transactions 
            WHERE type = 'inflow' 
            GROUP BY invoice_id
        ) t ON i.id = t.invoice_id
        WHERE i.client_id = ?
    ");
    $stmt->execute([$clientId]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['receivables'];
}

function deleteClient($id) {
    $db = getDB();
    if (!$db) return false;
    
    try {
        $db->beginTransaction();
        
        // Check if client has associated job orders
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM invoices WHERE client_id = ?");
        $stmt->execute([$id]);
        $jobOrderCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($jobOrderCount > 0) {
            $db->rollback();
            return false; // Cannot delete client with existing job orders
        }
        
        // Delete the client
        $stmt = $db->prepare("DELETE FROM clients WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        $db->commit();
        return $result;
    } catch (Exception $e) {
        $db->rollback();
        return false;
    }
}

function getTotalReceivables() {
    $db = getDB();
    if (!$db) return 0;
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(i.total_with_vat - COALESCE(t.total_inflow, 0)), 0) as receivables
        FROM invoices i
        LEFT JOIN (
            SELECT invoice_id, SUM(amount) as total_inflow 
            FROM transactions 
            WHERE type = 'inflow' 
            GROUP BY invoice_id
        ) t ON i.id = t.invoice_id
        WHERE i.total_with_vat > COALESCE(t.total_inflow, 0)
    ");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['receivables'];
}

// Bank Account Functions
function getBankAccounts() {
    $db = getDB();
    if (!$db) return [];
    
    $stmt = $db->prepare("SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getBankBalance($bankId) {
    $db = getDB();
    if (!$db) return 0;
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(CASE WHEN type = 'inflow' THEN amount ELSE -amount END), 0) as balance
        FROM transactions 
        WHERE bank_account_id = ?
    ");
    $stmt->execute([$bankId]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['balance'];
}

// VAT Functions
function checkVATThreshold() {
    $totalInflow = getTotalInflow();
    $settings = getCompanySettings();
    $threshold = $settings['vat_threshold'] ?? 25000000;
    
    return $totalInflow >= $threshold;
}

function calculateVAT($amount, $vatRate = null) {
    if ($vatRate === null) {
        $settings = getCompanySettings();
        $vatRate = $settings['tax_rate'] ?? 7.5;
    }
    return $amount * ($vatRate / 100);
}

function shouldApplyVAT() {
    $settings = getCompanySettings();
    $taxEnabled = $settings['tax_enabled'] ?? false;
    
    if (!$taxEnabled) {
        return false;
    }
    
    return checkVATThreshold();
}

// Enhanced Job Order Functions
function updateInvoiceStatus($id, $status) {
    $db = getDB();
    if (!$db) return false;
    
    $stmt = $db->prepare("UPDATE invoices SET status = ? WHERE id = ?");
    return $stmt->execute([$status, $id]);
}

function updatePaymentStatus($id, $paymentStatus) {
    $db = getDB();
    if (!$db) return false;
    
    $stmt = $db->prepare("UPDATE invoices SET payment_status = ? WHERE id = ?");
    return $stmt->execute([$paymentStatus, $id]);
}

function deleteInvoice($id) {
    $db = getDB();
    if (!$db) return false;
    
    try {
        $db->beginTransaction();
        
        // Delete related transactions
        $stmt = $db->prepare("DELETE FROM transactions WHERE invoice_id = ?");
        $stmt->execute([$id]);
        
        // Delete related tithes
        $stmt = $db->prepare("DELETE FROM tithes WHERE invoice_id = ?");
        $stmt->execute([$id]);
        
        // Delete related VAT records
        $stmt = $db->prepare("DELETE FROM vat_records WHERE invoice_id = ?");
        $stmt->execute([$id]);
        
        // Delete invoice
        $stmt = $db->prepare("DELETE FROM invoices WHERE id = ?");
        $stmt->execute([$id]);
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollback();
        return false;
    }
}

// Inventory Functions
function addInventoryItem($name, $description, $cost, $purchaseDate, $depreciationRate, $usefulLife) {
    $db = getDB();
    if (!$db) return false;
    
    $stmt = $db->prepare("
        INSERT INTO inventory (name, description, purchase_cost, current_value, purchase_date, depreciation_rate, useful_life_years) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$name, $description, $cost, $cost, $purchaseDate, $depreciationRate, $usefulLife]);
}

function calculateDepreciation($itemId) {
    $db = getDB();
    if (!$db) return 0;
    
    $stmt = $db->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) return 0;
    
    $purchaseDate = new DateTime($item['purchase_date']);
    $currentDate = new DateTime();
    $yearsElapsed = $currentDate->diff($purchaseDate)->y + ($currentDate->diff($purchaseDate)->days % 365) / 365;
    
    if ($item['depreciation_method'] === 'straight_line') {
        $annualDepreciation = $item['purchase_cost'] / $item['useful_life_years'];
        $totalDepreciation = min($annualDepreciation * $yearsElapsed, $item['purchase_cost']);
    } else {
        // Declining balance method
        $rate = $item['depreciation_rate'] / 100;
        $totalDepreciation = $item['purchase_cost'] * (1 - pow(1 - $rate, $yearsElapsed));
    }
    
    $currentValue = max(0, $item['purchase_cost'] - $totalDepreciation);
    
    // Update current value
    $stmt = $db->prepare("UPDATE inventory SET current_value = ? WHERE id = ?");
    $stmt->execute([$currentValue, $itemId]);
    
    return $totalDepreciation;
}

function getTotalInventoryValue() {
    $db = getDB();
    if (!$db) return 0;
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(current_value), 0) as total FROM inventory");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

// Recurring Costs Functions
function getRecurringCosts($frequency = null) {
    $db = getDB();
    if (!$db) return [];
    
    $sql = "SELECT * FROM transactions WHERE is_recurring = 1 AND type = 'outflow'";
    $params = [];
    
    if ($frequency) {
        $sql .= " AND recurring_frequency = ?";
        $params[] = $frequency;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMonthlyRecurringTotal() {
    $db = getDB();
    if (!$db) return 0;
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM transactions 
        WHERE is_recurring = 1 AND type = 'outflow' AND recurring_frequency = 'monthly'
    ");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

function generateInvoiceNumber($jobOrderId) {
    // Generate invoice number based on job order ID
    // Format: INV-YYYYMMDD-###
    $date = date('Ymd');
    $sequence = str_pad(substr($jobOrderId, -3), 3, '0', STR_PAD_LEFT);
    return "INV-{$date}-{$sequence}";
}?>
