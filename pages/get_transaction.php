<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !checkUserPermission('accountant')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$transactionId = intval($_GET['id'] ?? 0);

if (!$transactionId) {
    echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$transactionId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit;
    }
    
    echo json_encode(['success' => true, 'transaction' => $transaction]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>