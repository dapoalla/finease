<?php
// Simple installation script for cPanel deployment
if (file_exists('config/setup_complete.txt')) {
    echo "<h1>Installation Complete</h1>";
    echo "<p>The application has already been installed. <a href='index.php'>Go to Application</a></p>";
    exit;
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

if ($_POST && $step == 1) {
    // Test database connection and create config
    $host = $_POST['db_host'] ?? 'localhost';
    $dbname = $_POST['db_name'] ?? '';
    $username = $_POST['db_user'] ?? '';
    $password = $_POST['db_pass'] ?? '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create config directory if it doesn't exist
        if (!is_dir('config')) {
            mkdir('config', 0755, true);
        }
        
        // Save database config
        $config = "<?php\ndefine('DB_HOST', '$host');\ndefine('DB_NAME', '$dbname');\ndefine('DB_USER', '$username');\ndefine('DB_PASS', '$password');\n?>";
        file_put_contents('config/config.php', $config);
        
        // Create tables
        require_once 'config/database.php';
        $database = new Database();
        $database->createTables();
        
        $success = "Database setup completed successfully!";
        $step = 2;
    } catch (Exception $e) {
        $error = 'Database connection failed: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Business Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .install-container {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 500px;
        }
        
        .install-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .install-header h1 {
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .install-header p {
            color: #718096;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #2d3748;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            width: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background-color: #c6f6d5;
            color: #2f855a;
            border: 1px solid #9ae6b4;
        }
        
        .alert-error {
            background-color: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }
        
        .success-message {
            text-align: center;
            padding: 2rem;
        }
        
        .success-message h2 {
            color: #38a169;
            margin-bottom: 1rem;
        }
        
        .success-message p {
            color: #718096;
            margin-bottom: 2rem;
        }
        
        .credentials {
            background: #f7fafc;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            text-align: left;
        }
        
        .credentials strong {
            color: #2d3748;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <?php if ($step == 1): ?>
        <div class="install-header">
            <h1>📊 Business Manager</h1>
            <p>Installation Setup</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="db_host">Database Host</label>
                <input type="text" id="db_host" name="db_host" class="form-control" value="localhost" required>
                <small style="color: #718096;">Usually 'localhost' for cPanel hosting</small>
            </div>
            
            <div class="form-group">
                <label for="db_name">Database Name</label>
                <input type="text" id="db_name" name="db_name" class="form-control" required>
                <small style="color: #718096;">Create this database in cPanel first</small>
            </div>
            
            <div class="form-group">
                <label for="db_user">Database Username</label>
                <input type="text" id="db_user" name="db_user" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="db_pass">Database Password</label>
                <input type="password" id="db_pass" name="db_pass" class="form-control">
            </div>
            
            <button type="submit" class="btn btn-primary">Install & Setup Database</button>
        </form>
        
        <?php elseif ($step == 2): ?>
        <div class="success-message">
            <h2>✅ Installation Complete!</h2>
            <p>Your Business Manager has been successfully installed and configured.</p>
            
            <div class="credentials">
                <strong>Default Login Credentials:</strong><br>
                Username: <strong>admin</strong><br>
                Password: <strong>admin123</strong>
            </div>
            
            <p style="font-size: 0.9rem; color: #e53e3e;">
                ⚠️ Please change the default password after your first login for security.
            </p>
            
            <a href="setup/index.php" class="btn btn-primary">Continue to Company Setup</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>