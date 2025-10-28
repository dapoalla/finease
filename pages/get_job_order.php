<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing id']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM invoices WHERE id = ?');
    $stmt->execute([$id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Job order not found']);
        exit;
    }

    echo json_encode(['success' => true, 'invoice' => $invoice]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
}