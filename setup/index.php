<?php
session_start();

if (file_exists('../config/setup_complete.txt')) {
    header('Location: ../index.php');
    exit;
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

if ($_POST) {
    switch ($step) {
        case 1:
            // Database setup
            $host = $_POST['db_host'] ?? 'localhost';
            $dbname = $_POST['db_name'] ?? '';
            $username = $_POST['db_user'] ?? '';
            $password = $_POST['db_pass'] ?? '';
            
            try {
                $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Save database config
                $config = "<?php\ndefine('DB_HOST', '$host');\ndefine('DB_NAME', '$dbname');\ndefine('DB_USER', '$username');\ndefine('DB_PASS', '$password');\n?>";
                file_put_contents('../config/config.php', $config);
                
                // Create tables
                require_once '../config/database.php';
                $database = new Database();
                $database->createTables();
                
                header('Location: ?step=2');
                exit;
            } catch (Exception $e) {
                $error = 'Database connection failed: ' . $e->getMessage();
            }
            break;
            
        case 2:
            // Company setup
            require_once '../config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            $companyName = $_POST['company_name'] ?? '';
            $address = $_POST['address'] ?? '';
            $contactInfo = $_POST['contact_info'] ?? '';
            $country = $_POST['country'] ?? '';
            $currency = $_POST['currency'] ?? '₦';
            $taxEnabled = isset($_POST['tax_enabled']) ? 1 : 0;
            $taxRate = $_POST['tax_rate'] ?? 7.50;
            $titheRate = $_POST['tithe_rate'] ?? 10.00;
            
            $stmt = $db->prepare("INSERT INTO company_settings (company_name, address, contact_info, country, currency, tax_enabled, tax_rate, tithe_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$companyName, $address, $contactInfo, $country, $currency, $taxEnabled, $taxRate, $titheRate]);
            
            // Mark setup as complete
            file_put_contents('../config/setup_complete.txt', date('Y-m-d H:i:s'));
            
            header('Location: ../auth/login.php?setup=complete');
            exit;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Business Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>📊 Business Manager Setup</h1>
            <p>Let's get your business management system ready!</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($step == 1): ?>
        <div class="form-container">
            <h2>Step 1: Database Configuration</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="db_host">Database Host</label>
                    <input type="text" id="db_host" name="db_host" class="form-control" value="localhost" required>
                </div>
                
                <div class="form-group">
                    <label for="db_name">Database Name</label>
                    <input type="text" id="db_name" name="db_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Database Username</label>
                    <input type="text" id="db_user" name="db_user" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">Database Password</label>
                    <input type="password" id="db_pass" name="db_pass" class="form-control">
                </div>
                
                <button type="submit" class="btn btn-primary">Test Connection & Continue</button>
            </form>
        </div>
        <?php endif; ?>
        
        <?php if ($step == 2): ?>
        <div class="form-container">
            <h2>Step 2: Company Information</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="company_name">Company Name</label>
                    <input type="text" id="company_name" name="company_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="contact_info">Contact Information</label>
                    <textarea id="contact_info" name="contact_info" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="country">Country</label>
                    <select id="country" name="country" class="form-control" onchange="updateCurrency()">
                        <option value="Nigeria">Nigeria</option>
                        <option value="United States">United States</option>
                        <option value="United Kingdom">United Kingdom</option>
                        <option value="European Union">European Union</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="currency">Currency Symbol</label>
                    <select id="currency" name="currency" class="form-control">
                        <option value="₦">₦ (Nigerian Naira)</option>
                        <option value="$">$ (US Dollar)</option>
                        <option value="£">£ (British Pound)</option>
                        <option value="€">€ (Euro)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="tax_enabled" id="tax_enabled" onchange="toggleTaxRate()"> Enable Tax/VAT
                    </label>
                </div>
                
                <div class="form-group" id="tax_rate_group" style="display: none;">
                    <label for="tax_rate">Tax Rate (%)</label>
                    <input type="number" id="tax_rate" name="tax_rate" class="form-control" value="7.50" step="0.01" min="0" max="100">
                </div>
                
                <div class="form-group">
                    <label for="tithe_rate">Tithe Rate (%)</label>
                    <input type="number" id="tithe_rate" name="tithe_rate" class="form-control" value="10.00" step="0.01" min="0" max="100">
                </div>
                
                <button type="submit" class="btn btn-primary">Complete Setup</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        function updateCurrency() {
            const country = document.getElementById('country').value;
            const currency = document.getElementById('currency');
            const taxEnabled = document.getElementById('tax_enabled');
            const taxRate = document.getElementById('tax_rate');
            
            switch(country) {
                case 'Nigeria':
                    currency.value = '₦';
                    taxEnabled.checked = true;
                    taxRate.value = '7.50';
                    break;
                case 'United States':
                    currency.value = '$';
                    break;
                case 'United Kingdom':
                    currency.value = '£';
                    break;
                case 'European Union':
                    currency.value = '€';
                    break;
            }
            
            toggleTaxRate();
        }
        
        function toggleTaxRate() {
            const taxEnabled = document.getElementById('tax_enabled').checked;
            const taxRateGroup = document.getElementById('tax_rate_group');
            taxRateGroup.style.display = taxEnabled ? 'block' : 'none';
        }
    </script>
</body>
</html>