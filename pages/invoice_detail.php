<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$invoiceId = intval($_GET['id'] ?? 0);
if (!$invoiceId) {
    header('Location: invoices.php');
    exit;
}

$pageTitle = 'Invoice Detail';
$error = '';
$success = '';

// Handle transaction additions
if ($_POST) {
    if (isset($_POST['add_transaction'])) {
        $type = $_POST['type'];
        $amount = floatval($_POST['amount']);
        $description = sanitizeInput($_POST['description']);
        $date = $_POST['transaction_date'];
        
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO transactions (type, amount, description, category, invoice_id, transaction_date) VALUES (?, ?, ?, 'invoice_linked', ?, ?)");
        
        if ($stmt->execute([$type, $amount, $description, $invoiceId, $date])) {
            $success = "Transaction added successfully!";
        } else {
            $error = "Failed to add transaction.";
        }
    }
}

// Get invoice details
$db = getDB();
$stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    header('Location: invoices.php');
    exit;
}

// Get line items if they exist
$lineItems = [];
if (isset($invoice['has_line_items']) && $invoice['has_line_items']) {
    $stmt = $db->prepare("SELECT * FROM job_order_line_items WHERE job_order_id = ? ORDER BY id");
    $stmt->execute([$invoiceId]);
    $lineItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get transactions for this invoice
$stmt = $db->prepare("SELECT * FROM transactions WHERE invoice_id = ? ORDER BY transaction_date DESC");
$stmt->execute([$invoiceId]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalInflow = 0;
$totalOutflow = 0;
foreach ($transactions as $transaction) {
    if ($transaction['type'] === 'inflow') {
        $totalInflow += $transaction['amount'];
    } else {
        $totalOutflow += $transaction['amount'];
    }
}

$profit = $totalInflow - $totalOutflow;
$settings = getCompanySettings();
$titheAmount = $profit > 0 ? ($profit * ($settings['tithe_rate'] / 100)) : 0;

require_once '../includes/header.php';
?>

<div class="invoice-detail">
    <div class="page-header">
        <div>
            <h1>Invoice: <?php echo $invoice['invoice_id']; ?></h1>
            <p class="invoice-client">Client: <?php echo $invoice['client_name']; ?></p>
        </div>
        <div>
            <span class="status-badge status-<?php echo $invoice['status']; ?>">
                <?php echo ucfirst(str_replace('_', ' ', $invoice['status'])); ?>
            </span>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <!-- Invoice Summary -->
    <div class="invoice-summary">
        <div class="summary-grid">
            <div class="summary-card">
                <h3>Service Description</h3>
                <p><?php echo $invoice['service_description']; ?></p>
            </div>
            
            <?php if (isset($invoice['has_line_items']) && $invoice['has_line_items'] && !empty($lineItems)): ?>
            <div class="summary-card line-items-card">
                <h3>📋 Line Items</h3>
                <div class="line-items-table">
                    <table class="table">
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
                        <tfoot>
                            <tr>
                                <td colspan="3"><strong>Subtotal:</strong></td>
                                <td><strong><?php echo formatCurrency($invoice['line_items_total']); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="summary-card">
                <h3>Job Order Amount</h3>
                <p class="amount"><?php echo formatCurrency($invoice['amount']); ?></p>
                <?php if (isset($invoice['has_line_items']) && $invoice['has_line_items']): ?>
                <small class="line-items-indicator">📋 Calculated from line items</small>
                <?php endif; ?>
            </div>
            
            <div class="summary-card">
                <h3>Date</h3>
                <p><?php echo date('F j, Y', strtotime($invoice['date'])); ?></p>
            </div>
            
            <div class="summary-card">
                <h3>Notes</h3>
                <p><?php echo $invoice['notes'] ?: 'No notes'; ?></p>
            </div>
        </div>
    </div>
    
    <!-- Financial Summary -->
    <div class="financial-summary">
        <h2>Financial Summary</h2>
        <div class="stats-grid">
            <div class="stat-card inflow">
                <div class="stat-content">
                    <h3>Total Inflow</h3>
                    <div class="stat-value"><?php echo formatCurrency($totalInflow); ?></div>
                </div>
            </div>
            
            <div class="stat-card outflow">
                <div class="stat-content">
                    <h3>Total Outflow</h3>
                    <div class="stat-value"><?php echo formatCurrency($totalOutflow); ?></div>
                </div>
            </div>
            
            <div class="stat-card profit">
                <div class="stat-content">
                    <h3>Profit/Loss</h3>
                    <div class="stat-value"><?php echo formatCurrency($profit); ?></div>
                </div>
            </div>
            
            <div class="stat-card tithes">
                <div class="stat-content">
                    <h3>Tithe (<?php echo $settings['tithe_rate']; ?>%)</h3>
                    <div class="stat-value"><?php echo formatCurrency($titheAmount); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Transaction Form -->
    <?php if (checkUserPermission('accountant')): ?>
    <div class="add-transaction">
        <h2>Add Transaction</h2>
        <form method="POST" class="transaction-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="type">Type</label>
                    <select id="type" name="type" class="form-control" required>
                        <option value="inflow">Inflow (Payment Received)</option>
                        <option value="outflow">Outflow (Expense)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="amount">Amount</label>
                    <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="transaction_date">Date</label>
                    <input type="date" id="transaction_date" name="transaction_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <input type="text" id="description" name="description" class="form-control" required>
            </div>
            
            <button type="submit" name="add_transaction" class="btn btn-primary">Add Transaction</button>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Transactions List -->
    <div class="transactions-list">
        <h2>Transactions</h2>
        <?php if (empty($transactions)): ?>
            <p class="no-data">No transactions recorded for this invoice yet.</p>
        <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?></td>
                        <td>
                            <span class="transaction-type <?php echo $transaction['type']; ?>">
                                <?php echo ucfirst($transaction['type']); ?>
                            </span>
                        </td>
                        <td><?php echo $transaction['description']; ?></td>
                        <td class="amount <?php echo $transaction['type']; ?>">
                            <?php echo ($transaction['type'] === 'inflow' ? '+' : '-') . formatCurrency($transaction['amount']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.invoice-detail {
    max-width: 1200px;
    margin: 0 auto;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
}

.invoice-client {
    color: #718096;
    font-size: 1.1rem;
    margin-top: 0.5rem;
}

.invoice-summary {
    margin-bottom: 2rem;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.summary-card {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.summary-card h3 {
    color: #718096;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.summary-card .amount {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2d3748;
}

.financial-summary {
    margin-bottom: 2rem;
}

.add-transaction {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.transaction-form .form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.transaction-type {
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.transaction-type.inflow {
    background-color: #c6f6d5;
    color: #2f855a;
}

.transaction-type.outflow {
    background-color: #fed7d7;
    color: #c53030;
}

.amount.inflow {
    color: #38a169;
}

.amount.outflow {
    color: #e53e3e;
}

.no-data {
    text-align: center;
    color: #718096;
    font-style: italic;
    padding: 2rem;
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .transaction-form .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>