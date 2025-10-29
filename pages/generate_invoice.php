<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$jobOrderId = intval($_GET['job_order_id'] ?? 0);
$documentType = $_GET['type'] ?? 'invoice'; // 'invoice' or 'receipt'

if (!$jobOrderId) {
    header('Location: invoices.php');
    exit;
}

// Get job order details
$db = getDB();
$stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$jobOrderId]);
$jobOrder = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$jobOrder) {
    header('Location: invoices.php');
    exit;
}

// Get client details
$client = null;
if ($jobOrder['client_id']) {
    $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$jobOrder['client_id']]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get line items if they exist
$lineItems = [];
if (isset($jobOrder['has_line_items']) && $jobOrder['has_line_items']) {
    $stmt = $db->prepare("SELECT * FROM job_order_line_items WHERE job_order_id = ? ORDER BY id");
    $stmt->execute([$jobOrderId]);
    $lineItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get company settings
$stmt = $db->prepare("SELECT * FROM company_settings LIMIT 1");
$stmt->execute();
$companySettings = $stmt->fetch(PDO::FETCH_ASSOC);

// Generate invoice number
$invoiceNumber = generateInvoiceNumber($jobOrder['invoice_id']);

$pageTitle = 'Generate Invoice';
require_once '../includes/header.php';
?>

<div class="invoice-container">
    <div class="invoice-header">
        <div class="company-logo">
            <?php 
            $logoPath = $companySettings['logo_path'] ?? '';
            if ($logoPath && file_exists('../uploads/' . $logoPath)): ?>
            <img src="../uploads/<?php echo $logoPath; ?>" alt="Company Logo" class="invoice-logo">
            <?php endif; ?>
        </div>
        <div class="company-info">
            <h1><?php echo $companySettings['company_name'] ?? 'FinEase'; ?></h1>
            <?php if ($companySettings['address']): ?>
            <p><?php echo nl2br($companySettings['address']); ?></p>
            <?php endif; ?>
            <?php if ($companySettings['contact_info']): ?>
            <p><?php echo nl2br($companySettings['contact_info']); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="invoice-info">
            <h2><?php echo strtoupper($documentType); ?></h2>
            <p><strong><?php echo ucfirst($documentType); ?> #:</strong> <?php echo $invoiceNumber; ?></p>
            <p><strong>Job Order #:</strong> <?php echo $jobOrder['invoice_id']; ?></p>
            <p><strong>Date:</strong> <?php echo date('F j, Y'); ?></p>
            <?php if ($documentType === 'invoice'): ?>
            <p><strong>Due Date:</strong> <?php echo date('F j, Y', strtotime('+30 days')); ?></p>
            <?php else: ?>
            <p><strong>Payment Date:</strong> <?php echo date('F j, Y', strtotime($jobOrder['updated_at'])); ?></p>
            <?php endif; ?>
        </div>
        <?php if ($documentType === 'receipt'): ?>
        <div class="paid-stamp">
            <img src="../assets/img/cyberrose_paid.svg" alt="Paid Stamp">
        </div>
        <?php endif; ?>
    </div>
    
    <div class="invoice-parties">
        <div class="bill-to">
            <h3>Bill To:</h3>
            <div class="client-details">
                <p class="client-name"><strong><?php echo $jobOrder['client_name']; ?></strong></p>
                <?php if ($client): ?>
                    <?php if ($client['email']): ?>
                    <p>Email: <?php echo $client['email']; ?></p>
                    <?php endif; ?>
                    <?php if ($client['phone']): ?>
                    <p>Phone: <?php echo $client['phone']; ?></p>
                    <?php endif; ?>
                    <?php if ($client['address']): ?>
                    <p><?php echo nl2br($client['address']); ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="invoice-details">
        <h3>Service Details</h3>
        <p><?php echo $jobOrder['service_description']; ?></p>
        
        <?php if (isset($jobOrder['has_line_items']) && $jobOrder['has_line_items'] && !empty($lineItems)): ?>
        <div class="line-items-section">
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Unit Price</th>
                        <th>Quantity</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lineItems as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td><?php echo formatCurrency($item['unit_price']); ?></td>
                        <td><?php echo number_format($item['quantity'], 2); ?></td>
                        <td><?php echo formatCurrency($item['total']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="invoice-totals">
        <div class="totals-table">
            <div class="total-row">
                <span>Subtotal:</span>
                <span><?php echo formatCurrency($jobOrder['amount']); ?></span>
            </div>
            
            <?php if ($jobOrder['vat_amount'] > 0): ?>
            <div class="total-row">
                <span>VAT (<?php echo ($companySettings['tax_rate'] ?? 7.5); ?>%):</span>
                <span><?php echo formatCurrency($jobOrder['vat_amount']); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="total-row grand-total">
                <span><strong>Total Amount:</strong></span>
                <span><strong><?php echo formatCurrency($jobOrder['total_with_vat'] ?: $jobOrder['amount']); ?></strong></span>
            </div>
        </div>
    </div>
    
    <div class="invoice-notes">
        <?php if ($jobOrder['notes']): ?>
        <h3>Notes</h3>
        <p><?php echo nl2br($jobOrder['notes']); ?></p>
        <?php endif; ?>
        
        <div class="payment-terms">
            <?php if ($documentType === 'invoice'): ?>
            <h3>Payment Terms</h3>
            <p>We look forward to you confirmed Order</p>
            <?php else: ?>
            <h3>Payment Received</h3>
            <p><strong>Status:</strong> <span class="paid-full">PAID IN FULL</span></p>
            <p>Thank you for your patronage!</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="invoice-actions">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Print <?php echo ucfirst($documentType); ?></button>
        <?php if ($documentType === 'invoice'): ?>
        <button onclick="saveInvoice()" class="btn btn-success">💾 Save Invoice</button>
        <?php endif; ?>
        <a href="invoices.php" class="btn btn-secondary">← Back to Job Orders</a>
    </div>
</div>

<style>
@media print {
    .navbar, .nav-user, .footer, .invoice-actions, .btn {
        display: none !important;
    }
    
    .main-content {
        padding: 0 !important;
    }
    
    .invoice-container {
        max-width: none !important;
        margin: 0 !important;
        box-shadow: none !important;
        border: none !important;
    }
}

.invoice-container {
    max-width: 820px;
    margin: 0 auto;
    background: white;
    color: #333;
    padding: 1rem;
    border-radius: 8px;
    box-shadow: var(--shadow-lg);
}

.invoice-header {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #eee;
}

.company-logo {
    position: absolute;
    right: 0;
    top: 0;
}

.invoice-logo {
    max-height: 60px;
}

.company-info h1 {
    color: var(--accent-primary);
    margin-bottom: 0.5rem;
}

.company-info p {
    margin: 0.25rem 0;
    color: #666;
}

.invoice-info {
    text-align: right;
}

.invoice-info h2 {
    color: var(--accent-primary);
    font-size: 1.75rem;
    margin-bottom: 0.5rem;
}

.invoice-info p {
    margin: 0.15rem 0;
    color: #666;
}

.invoice-info p strong {
    font-weight: 500;
}

.invoice-parties {
    margin-bottom: 2rem;
}

.bill-to h3 {
    color: #333;
    margin-bottom: 1rem;
    border-bottom: 1px solid #eee;
    padding-bottom: 0.5rem;
}

.client-details p {
    margin: 0.25rem 0;
    color: #666;
    font-size: 1rem;
}

.client-details .client-name {
    font-size: 1.25rem;
    color: #333;
}

.invoice-details {
    margin-bottom: 1rem;
}

.invoice-details h3 {
    color: #333;
    margin-bottom: 1rem;
}

.line-items-section {
    margin-top: 1.5rem;
}

.invoice-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1rem;
}

.invoice-table th,
.invoice-table td {
    padding: 0.4rem 0.5rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.invoice-table th {
    background: #f8f9fa;
    font-weight: 500;
    color: #333;
}

.invoice-table td:last-child,
.invoice-table th:last-child {
    text-align: right;
}

.invoice-totals {
    margin-bottom: 1rem;
    display: flex;
    justify-content: flex-end;
}

.totals-table {
    min-width: 300px;
}

.total-row {
    display: flex;
    justify-content: space-between;
    padding: 0.4rem 0;
    border-bottom: 1px solid #eee;
}

.grand-total {
    border-top: 2px solid #333;
    border-bottom: 2px solid #333;
    font-size: 1.1rem;
    margin-top: 0.25rem;
    padding-top: 0.6rem;
}

.invoice-notes {
    margin-bottom: 1rem;
}

.invoice-notes h3 {
    color: #333;
    margin-bottom: 1rem;
}

.payment-terms {
    margin-top: 1rem;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 6px;
}

.paid-full {
    color: #10b981;
    font-size: 1.2rem;
    font-weight: 700;
}

.paid-stamp {
    position: absolute;
    right: 10px;
    top: 70px;
    opacity: 0.9;
    transform: rotate(-12deg);
}

.paid-stamp img {
    height: 120px;
    filter: drop-shadow(0 3px 6px rgba(0,0,0,0.25));
}

.invoice-actions {
    text-align: center;
    padding-top: 1rem;
    border-top: 1px solid #eee;
}

.invoice-actions .btn {
    margin: 0 0.5rem;
}

@media (max-width: 768px) {
    .invoice-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .invoice-info {
        text-align: left;
    }
    
    .invoice-container {
        padding: 1rem;
    }
    
    .totals-table {
        min-width: 100%;
    }
}
</style>

<script>
function saveInvoice() {
    if (confirm('Save this invoice? This will create a formal invoice record.')) {
        // Make AJAX call to save the invoice
        const jobOrderId = <?php echo $jobOrderId; ?>;
        const documentType = '<?php echo $documentType; ?>';
        const invoiceNumber = '<?php echo $invoiceNumber; ?>';
        
        fetch('save_invoice.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                job_order_id: jobOrderId,
                document_type: documentType,
                invoice_number: invoiceNumber
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Invoice saved successfully!');
            } else {
                alert('Error saving invoice: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error saving invoice: ' + error.message);
        });
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>