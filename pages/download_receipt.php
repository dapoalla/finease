<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$fileName = $_GET['file'] ?? '';

if (empty($fileName)) {
    header('HTTP/1.0 404 Not Found');
    echo 'File not found';
    exit;
}

// Sanitize filename to prevent directory traversal
$fileName = basename($fileName);
$filePath = '../uploads/receipts/' . $fileName;

if (!file_exists($filePath)) {
    header('HTTP/1.0 404 Not Found');
    echo 'File not found';
    exit;
}

// Verify the file belongs to a transaction (security check)
$db = getDB();
$stmt = $db->prepare("SELECT id FROM transactions WHERE receipt_file = ?");
$stmt->execute([$fileName]);
if (!$stmt->fetch()) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Access denied';
    exit;
}

// Get file info
$fileInfo = pathinfo($filePath);
$fileExtension = strtolower($fileInfo['extension']);

// Set appropriate content type
$contentTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

$contentType = $contentTypes[$fileExtension] ?? 'application/octet-stream';

// Send file headers
header('Content-Type: ' . $contentType);
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output file
readfile($filePath);
exit;
?>