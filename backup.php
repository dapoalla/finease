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

        $sqlContent = '';
        if ($fileExtension === 'sql') {
            $sqlContent = file_get_contents($uploadedFile['tmp_name']);
            if ($sqlContent === false) {
                throw new Exception("Failed to read SQL backup file");
            }
        } elseif ($fileExtension === 'zip') {
            if (!class_exists('ZipArchive')) {
                throw new Exception("ZIP restore is not supported on this server");
            }
            $zip = new ZipArchive();
            if ($zip->open($uploadedFile['tmp_name']) !== true) {
                throw new Exception("Failed to open ZIP backup file");
            }
            // Find first .sql file inside the zip
            $sqlIndex = -1;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat && isset($stat['name']) && strtolower(pathinfo($stat['name'], PATHINFO_EXTENSION)) === 'sql') {
                    $sqlIndex = $i;
                    break;
                }
            }
            if ($sqlIndex === -1) {
                $zip->close();
                throw new Exception("No .sql file found inside the ZIP backup");
            }
            $sqlContent = $zip->getFromIndex($sqlIndex);
            $zip->close();
            if ($sqlContent === false) {
                throw new Exception("Failed to read .sql from ZIP backup");
            }
        } else {
            throw new Exception("Invalid file type. Only .sql or .zip files are supported");
        }

        // Normalize line endings and strip BOM
        $sqlContent = preg_replace("/\r\n?|\n/", "\n", $sqlContent);
        if (substr($sqlContent, 0, 3) === "\xEF\xBB\xBF") {
            $sqlContent = substr($sqlContent, 3);
        }

        $db = getDB();
        if (!$db) {
            throw new Exception("Database connection failed");
        }

        // Increase limits for large imports
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        // Disable foreign key checks during restore
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Split SQL content into individual statements robustly
        $statements = splitSqlStatements($sqlContent);

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($statements as $statement) {
            $trimmed = trim($statement);
            if ($trimmed === '') { continue; }
            // Skip comment-only statements
            if (preg_match('/^(--|#|\/\*!|\/\*)/', $trimmed)) { continue; }
            try {
                $db->exec($statement);
                $successCount++;
            } catch (PDOException $e) {
                $errorCount++;
                $msg = $e->getMessage();
                $snippet = substr($trimmed, 0, 200);
                $errors[] = "Error: $msg | Statement: $snippet";
                error_log("SQL Error during restore: $msg - Statement: $trimmed");
            }
        }

        // Re-enable foreign key checks
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");

        // Run migrations and checks after restore to align schema
        try {
            $database = new Database();
            $results = $database->runMigrationsAndChecks();
            // Append migration messages to success report
            $migrationNotes = array_map(function($m) { return ($m['type'] ?? 'info') . ': ' . ($m['text'] ?? ''); }, $results);
        } catch (Exception $e) {
            $migrationNotes = ["error: Post-restore migrations failed: " . $e->getMessage()];
        }

        if ($errorCount > 0) {
            $summary = "Restore completed with {$errorCount} errors. {$successCount} statements executed successfully.";
            $details = "\n" . implode("\n", array_slice($errors, 0, 5));
            $migrations = "\nPost-restore checks:\n" . implode("\n", $migrationNotes);
            $_SESSION['backup_error'] = $summary . $details . $migrations;
        } else {
            $migrations = "\nPost-restore checks:\n" . implode("\n", $migrationNotes);
            $_SESSION['backup_success'] = "Backup restored successfully! {$successCount} statements executed." . $migrations;
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

// Split SQL into statements respecting quotes and comments
function splitSqlStatements($sql) {
    $statements = [];
    $buffer = '';
    $inSingle = false;
    $inDouble = false;
    $inLineComment = false; // -- or #
    $inBlockComment = false; // /* */
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i+1] : '';

        // Handle start of comments when not in quotes/comments
        if (!$inSingle && !$inDouble && !$inBlockComment) {
            // -- comment
            if (!$inLineComment && $ch === '-' && $next === '-' ) {
                $inLineComment = true;
            }
            // # comment
            if (!$inLineComment && $ch === '#') {
                $inLineComment = true;
            }
            // /* block comment */
            if (!$inLineComment && $ch === '/' && $next === '*') {
                $inBlockComment = true;
                $i++; // consume *
                continue;
            }
        }

        // If in line comment, skip until newline
        if ($inLineComment) {
            if ($ch === "\n") { $inLineComment = false; }
            continue;
        }

        // If in block comment, look for */
        if ($inBlockComment) {
            if ($ch === '*' && $next === '/') {
                $inBlockComment = false;
                $i++; // consume /
            }
            continue;
        }

        // Toggle quotes
        if ($ch === "'" && !$inDouble) {
            // handle escaped single quotes
            $escaped = $i > 0 && $sql[$i-1] === '\\';
            if (!$escaped) { $inSingle = !$inSingle; }
        } elseif ($ch === '"' && !$inSingle) {
            $escaped = $i > 0 && $sql[$i-1] === '\\';
            if (!$escaped) { $inDouble = !$inDouble; }
        }

        // Statement terminator
        if ($ch === ';' && !$inSingle && !$inDouble) {
            $statements[] = $buffer;
            $buffer = '';
        } else {
            $buffer .= $ch;
        }
    }
    if (trim($buffer) !== '') { $statements[] = $buffer; }
    return $statements;
}
?>