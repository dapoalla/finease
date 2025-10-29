<?php
session_start();

if (file_exists('../config/setup_complete.txt')) {
    header('Location: ../index.php');
    exit;
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';
$checks = [];

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
                
                // Detect existing application tables
                $overwrite = isset($_POST['overwrite_existing']) ? 1 : 0;
                $existingTables = [];
                $appTables = [
                    'users','company_settings','clients','bank_accounts','inventory','vat_records','invoices','transactions','tithes','job_order_line_items','generated_invoices'
                ];
                foreach ($appTables as $t) {
                    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                    $stmt->execute([$t]);
                    if ($stmt->rowCount() > 0) {
                        $existingTables[] = $t;
                    }
                }

                if (!empty($existingTables) && !$overwrite) {
                    $error = "Existing data detected (" . implode(', ', $existingTables) . "). Check 'Overwrite existing data' to proceed.";
                    break;
                }

                // Save database config
                $config = "<?php\ndefine('DB_HOST', '$host');\ndefine('DB_NAME', '$dbname');\ndefine('DB_USER', '$username');\ndefine('DB_PASS', '$password');\n?>";
                file_put_contents('../config/config.php', $config);
                
                // Create tables
                require_once '../config/database.php';
                $database = new Database();
                if (!empty($existingTables) && $overwrite) {
                    // Wipe existing app tables
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                    foreach ($existingTables as $t) {
                        try { $pdo->exec("DROP TABLE IF EXISTS `$t`"); } catch (Exception $e) { /* ignore */ }
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                }
                $database->createTables();
                // Run migrations and environment checks
                $checks = $database->runMigrationsAndChecks();
                
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
            
            $stmt = $db->prepare("INSERT INTO company_settings (company_name, address, contact_info, country, currency, tax_enabled, tax_rate, tithe_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ");
            $stmt->execute([$companyName, $address, $contactInfo, $country, $currency, $taxEnabled, $taxRate, $titheRate]);
            
            // Run migrations and environment checks again
            $checks = $database->runMigrationsAndChecks();

            // Proceed to admin account creation
            header('Location: ?step=3');
            exit;
            break;

        case 3:
            // Admin credentials setup
            require_once '../config/database.php';
            $database = new Database();
            $db = $database->getConnection();

            $adminUsername = $_POST['admin_username'] ?? '';
            $adminPassword = $_POST['admin_password'] ?? '';
            $adminPasswordConfirm = $_POST['admin_password_confirm'] ?? '';

            // Validate input
            if ($_POST) {
                if (strlen($adminUsername) < 3) {
                    $error = "Username must be at least 3 characters long.";
                    break;
                }
                if (strlen($adminPassword) < 6) {
                    $error = "Password must be at least 6 characters long.";
                    break;
                }
                if ($adminPassword !== $adminPasswordConfirm) {
                    $error = "Passwords do not match.";
                    break;
                }

                // Ensure username is unique
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$adminUsername]);
                $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($existingUser) {
                    $error = "Username already exists. Choose another.";
                    break;
                }

                // Find any existing admin; if exists, update first admin; otherwise insert new
                $stmt = $db->prepare("SELECT id, username FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
                $stmt->execute();
                $adminRow = $stmt->fetch(PDO::FETCH_ASSOC);

                $hashed = password_hash($adminPassword, PASSWORD_DEFAULT);

                if ($adminRow) {
                    $stmt = $db->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
                    $stmt->execute([$adminUsername, $hashed, $adminRow['id']]);
                } else {
                    $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
                    $stmt->execute([$adminUsername, $hashed]);
                }

                // Mark setup as complete and redirect to login
                file_put_contents('../config/setup_complete.txt', date('Y-m-d H:i:s'));
                header('Location: ../auth/login.php?setup=complete');
                exit;
            }
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
        <div class="setup-header"></div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($checks) && $step == 2): ?>
        <div class="form-container">
            <h2>System Checks & Migrations</h2>
            <ul style="margin: 0; padding-left: 1.2rem;">
                <?php foreach ($checks as $item): ?>
                    <li style="color: <?php echo $item['type'] === 'error' ? '#c53030' : ($item['type'] === 'info' ? '#2b6cb0' : '#2f855a'); ?>;">
                        <?php echo htmlspecialchars($item['text']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (array_filter($checks, fn($c) => $c['type'] === 'error')): ?>
                <p style="color:#c53030; margin-top: 1rem;">Please resolve the errors above (e.g., directory permissions) and re-run setup.</p>
            <?php endif; ?>
        </div>
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

                <div class="form-group" style="margin-top:0.5rem;">
                    <label>
                        <input type="checkbox" name="overwrite_existing" value="1"> Overwrite existing data if found
                    </label>
                    <small style="color:#c53030; display:block; margin-top:0.25rem;">Warning: This will delete existing Business Manager tables and data.</small>
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

        <?php if ($step == 3): ?>
        <div class="form-container">
            <h2>Step 3: Create Admin Account</h2>
            <p style="color:#b8bcc8; margin-top:0.5rem;">Set your administrator credentials. You can add more users later in Settings.</p>
            <form method="POST">
                <div class="form-group">
                    <label for="admin_username">Admin Username</label>
                    <input type="text" id="admin_username" name="admin_username" class="form-control" required minlength="3">
                </div>
                <div class="form-group">
                    <label for="admin_password">Password</label>
                    <input type="password" id="admin_password" name="admin_password" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="admin_password_confirm">Confirm Password</label>
                    <input type="password" id="admin_password_confirm" name="admin_password_confirm" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="btn btn-primary">Save Admin & Finish</button>
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