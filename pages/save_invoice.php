<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !checkUserPermission('accountant')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['job_order_id']) || !isset($input['document_type']) || !isset($input['invoice_number'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$jobOrderId = intval($input['job_order_id']);
$documentType = $input['document_type'];
$invoiceNumber = $input['invoice_number'];

try {
    $db = getDB();
    
    // Get job order details
    $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$jobOrderId]);
    $jobOrder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$jobOrder) {
        throw new Exception('Job order not found');
    }
    
    // Check if invoice already exists
    $stmt = $db->prepare("SELECT id FROM generated_invoices WHERE job_order_id = ? AND document_type = ?");
    $stmt->execute([$jobOrderId, $documentType]);
    if ($stmt->fetch()) {
        throw new Exception('Invoice already exists for this job order');
    }
    
    // Calculate due date (30 days from now for invoices)
    $dueDate = $documentType === 'invoice' ? date('Y-m-d', strtotime('+30 days')) : null;
    
    // Save the generated invoice
    $stmt = $db->prepare("
        INSERT INTO generated_invoices 
        (invoice_number, job_order_id, client_id, client_name, amount, vat_amount, total_amount, document_type, due_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $invoiceNumber,
        $jobOrderId,
        $jobOrder['client_id'],
        $jobOrder['client_name'],
        $jobOrder['amount'],
        $jobOrder['vat_amount'],
        $jobOrder['total_with_vat'] ?: $jobOrder['amount'],
        $documentType,
        $dueDate
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => ucfirst($documentType) . ' saved successfully']);
    } else {
        throw new Exception('Failed to save ' . $documentType);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>