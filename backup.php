<?php
session_start();
require_once 'includes/session_check.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Only admin can access backup functionality
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'create') {
    createDatabaseBackup();
} elseif ($action === 'restore') {
    restoreDatabaseBackup();
} else {
    header('Location: pages/settings.php');
    exit;
}

function createDatabaseBackup() {
    try {
        $db = getDB();
        if (!$db) {
            throw new Exception("Database connection failed");
        }
        
        // Get database name from config
        require_once 'config/config.php';
        $dbName = DB_NAME;
        
        // Generate backup filename with timestamp
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "finease_backup_{$timestamp}.sql";
        
        // Start building the SQL dump
        $dump = "-- FinEase Database Backup\n";
        $dump .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
        $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        
        // Get all tables
        $tables = [];
        $result = $db->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        // Export each table
        foreach ($tables as $table) {
            $dump .= exportTable($db, $table);
        }
        
        $dump .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
        
        // Set headers for download
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($dump));
        
        echo $dump;
        exit;
        
    } catch (Exception $e) {
        $_SESSION['backup_error'] = 'Backup failed: ' . $e->getMessage();
        header('Location: pages/settings.php');
        exit;
    }
}

function exportTable($db, $tableName) {
    $dump = "-- Table structure for `{$tableName}`\n";
    $dump .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
    
    // Get CREATE TABLE statement
    $result = $db->query("SHOW CREATE TABLE `{$tableName}`");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    $dump .= $row['Create Table'] . ";\n\n";
    
    // Get table data
    $result = $db->query("SELECT * FROM `{$tableName}`");
    $rows = $result->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($rows)) {
        $dump .= "-- Data for table `{$tableName}`\n";
        
        // Get column names
        $columns = array_keys($rows[0]);
        $columnList = '`' . implode('`, `', $columns) . '`';
        
        foreach ($rows as $row) {
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    $values[] = "'" . addslashes($value) . "'";
                }
            }
            $dump .= "INSERT INTO `{$tableName}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n";
        }
        $dump .= "\n";
    }
    
    return $dump;
}

function restoreDatabaseBackup() {
    try {
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("No backup file uploaded or upload error occurred");
        }
        
        $uploadedFile = $_FILES['backup_file'];
        $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        
        if ($fileExtension !== 'sql') {
            throw new Exception("Invalid file type. Only .sql files are supported");
        }
        
        // Read the SQL file
        $sqlContent = file_get_contents($uploadedFile['tmp_name']);
        if ($sqlContent === false) {
            throw new Exception("Failed to read backup file");
        }
        
        $db = getDB();
        if (!$db) {
            throw new Exception("Database connection failed");
        }
        
        // Disable foreign key checks during restore
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Split SQL content into individual statements
        $statements = explode(';', $sqlContent);
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            
            // Skip empty statements and comments
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            try {
                $db->exec($statement);
                $successCount++;
            } catch (PDOException $e) {
                $errorCount++;
                error_log("SQL Error during restore: " . $e->getMessage() . " - Statement: " . $statement);
            }
        }
        
        // Re-enable foreign key checks
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        if ($errorCount > 0) {
            $_SESSION['backup_error'] = "Restore completed with {$errorCount} errors. {$successCount} statements executed successfully.";
        } else {
            $_SESSION['backup_success'] = "Backup restored successfully! {$successCount} statements executed.";
        }
        
    } catch (Exception $e) {
        $_SESSION['backup_error'] = 'Restore failed: ' . $e->getMessage();
    }
    
    // Return JSON response for AJAX request
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        if (isset($_SESSION['backup_success'])) {
            echo json_encode(['success' => true, 'message' => $_SESSION['backup_success']]);
            unset($_SESSION['backup_success']);
        } else {
            echo json_encode(['success' => false, 'message' => $_SESSION['backup_error'] ?? 'Unknown error']);
            unset($_SESSION['backup_error']);
        }
        exit;
    }
    
    header('Location: pages/settings.php');
    exit;
}
?>