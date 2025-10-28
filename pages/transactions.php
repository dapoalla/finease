<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pageTitle = 'Transactions';
$error = '';
$success = '';

// Handle form submissions
if ($_POST && checkUserPermission('accountant')) {
    if (isset($_POST['add_transaction'])) {
        $type = $_POST['type'];
        $amount = floatval($_POST['amount']);
        $description = sanitizeInput($_POST['description']);
        $sellerDetails = sanitizeInput($_POST['seller_details']);
        $receiptNumber = sanitizeInput($_POST['receipt_number']);
        $category = $_POST['category'];
        $invoiceId = $category === 'invoice_linked' ? intval($_POST['invoice_id']) : null;
        $bankAccountId = intval($_POST['bank_account_id']);
        $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;
        $recurringFrequency = $isRecurring ? $_POST['recurring_frequency'] : null;
        $date = $_POST['transaction_date'];
        
        // Handle file upload
        $receiptFile = null;
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/receipts/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
            
            if (in_array($fileExtension, $allowedExtensions) && $_FILES['receipt_file']['size'] <= 5 * 1024 * 1024) {
                $fileName = 'receipt_' . time() . '_' . uniqid() . '.' . $fileExtension;
                $uploadPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $uploadPath)) {
                    $receiptFile = $fileName;
                } else {
                    $error = "Failed to upload receipt file.";
                }
            } else {
                $error = "Invalid file type or file too large (max 5MB).";
            }
        }
        
        if (!$error) {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO transactions (type, amount, description, seller_details, receipt_number, receipt_file, category, invoice_id, bank_account_id, is_recurring, recurring_frequency, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$type, $amount, $description, $sellerDetails, $receiptNumber, $receiptFile, $category, $invoiceId, $bankAccountId, $isRecurring, $recurringFrequency, $date])) {
                $success = "Transaction added successfully!";
            } else {
                $error = "Failed to add transaction.";
            }
        }
    }
}

// Handle edit transaction
if ($_POST && isset($_POST['edit_transaction']) && checkUserPermission('accountant')) {
    $transactionId = intval($_POST['transaction_id']);
    $type = $_POST['type'];
    $amount = floatval($_POST['amount']);
    $description = sanitizeInput($_POST['description']);
    $sellerDetails = sanitizeInput($_POST['seller_details']);
    $receiptNumber = sanitizeInput($_POST['receipt_number']);
    $category = $_POST['category'];
    $invoiceId = $category === 'invoice_linked' ? intval($_POST['invoice_id']) : null;
    $bankAccountId = intval($_POST['bank_account_id']);
    $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;
    $recurringFrequency = $isRecurring ? $_POST['recurring_frequency'] : null;
    $date = $_POST['transaction_date'];
    
    $db = getDB();
    $stmt = $db->prepare("UPDATE transactions SET type = ?, amount = ?, description = ?, seller_details = ?, receipt_number = ?, category = ?, invoice_id = ?, bank_account_id = ?, is_recurring = ?, recurring_frequency = ?, transaction_date = ? WHERE id = ?");
    
    if ($stmt->execute([$type, $amount, $description, $sellerDetails, $receiptNumber, $category, $invoiceId, $bankAccountId, $isRecurring, $recurringFrequency, $date, $transactionId])) {
        $success = "Transaction updated successfully!";
    } else {
        $error = "Failed to update transaction.";
    }
}

// Handle delete transaction (Admin only)
if ($_POST && isset($_POST['delete_transaction'])) {
    if (!checkUserPermission('admin')) {
        $error = "You don't have permission to delete transactions.";
    } else {
        $transactionId = intval($_POST['transaction_id']);
        
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM transactions WHERE id = ?");
        
        if ($stmt->execute([$transactionId])) {
            $success = "Transaction deleted successfully!";
        } else {
            $error = "Failed to delete transaction.";
        }
    }
}

// Get all transactions
$db = getDB();
$stmt = $db->prepare("
    SELECT t.*, i.invoice_id, i.client_name, b.name as bank_name
    FROM transactions t 
    LEFT JOIN invoices i ON t.invoice_id = i.id 
    LEFT JOIN bank_accounts b ON t.bank_account_id = b.id
    ORDER BY t.transaction_date DESC, t.created_at DESC
");
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get invoices for dropdown
$stmt = $db->prepare("SELECT id, invoice_id, client_name FROM invoices WHERE status != 'fully_paid' ORDER BY created_at DESC");
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>Transactions</h1>
    <?php if (checkUserPermission('accountant')): ?>
    <button class="btn btn-primary" onclick="showAddForm()">+ Add Transaction</button>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Add Transaction Form -->
<?php if (checkUserPermission('accountant')): ?>
<div id="addTransactionForm" class="form-container" style="display: none; margin-bottom: 2rem;">
    <h3>Add New Transaction</h3>
    <form method="POST" id="addTransactionForm" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group">
                <label for="type">Type</label>
                <select id="type" name="type" class="form-control" required>
                    <option value="inflow">Inflow (Income)</option>
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
            
            <div class="form-group">
                <label for="bank_account_id">Source *</label>
                <select id="bank_account_id" name="bank_account_id" class="form-control" required>
                    <option value="">Select source...</option>
                    <?php 
                    $banks = getBankAccounts();
                    foreach ($banks as $bank): ?>
                    <option value="<?php echo $bank['id']; ?>"><?php echo $bank['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label for="description">Description</label>
            <input type="text" id="description" name="description" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="seller_details">Seller/Vendor Details (Optional)</label>
            <input type="text" id="seller_details" name="seller_details" class="form-control" placeholder="Company name, contact person, phone, etc.">
        </div>
        
        <div class="form-group">
            <label for="receipt_number">Receipt Number (Optional)</label>
            <input type="text" id="receipt_number" name="receipt_number" class="form-control" placeholder="Enter receipt number if available">
        </div>
        
        <div class="form-group">
            <label for="receipt_file">Upload Receipt (Optional)</label>
            <input type="file" id="receipt_file" name="receipt_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
            <small class="form-text">Supported formats: JPG, PNG, PDF, DOC, DOCX (Max 5MB)</small>
        </div>
        
        <div class="form-group">
            <label for="category">Category</label>
            <select id="category" name="category" class="form-control" onchange="toggleInvoiceSelect()" required>
                <option value="internal">Internal Expense</option>
                <option value="invoice_linked">Job Order</option>
            </select>
        </div>
        
        <div class="form-group" id="invoice_select" style="display: none;">
            <label for="invoice_id">Select Invoice</label>
            <select id="invoice_id" name="invoice_id" class="form-control">
                <option value="">Select an invoice...</option>
                <?php foreach ($invoices as $invoice): ?>
                <option value="<?php echo $invoice['id']; ?>">
                    <?php echo $invoice['invoice_id'] . ' - ' . $invoice['client_name']; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group" id="recurring_section" style="display: none;">
            <label>
                <input type="checkbox" id="is_recurring" name="is_recurring" onchange="toggleRecurringFrequency()"> Mark as Recurring Cost
            </label>
        </div>
        
        <div class="form-group" id="frequency_select" style="display: none;">
            <label for="recurring_frequency">Frequency</label>
            <select id="recurring_frequency" name="recurring_frequency" class="form-control">
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option>
                <option value="yearly">Yearly</option>
            </select>
        </div>
        
        <button type="submit" name="add_transaction" class="btn btn-primary">Add Transaction</button>
        <button type="button" class="btn btn-secondary" onclick="hideAddForm()">Cancel</button>
    </form>
</div>
<?php endif; ?>

<!-- Summary Cards - Only visible to accountants and admins -->
<?php if (checkUserPermission('accountant')): ?>
<div class="stats-grid" style="margin-bottom: 2rem;">
    <div class="stat-card inflow">
        <div class="stat-icon">📈</div>
        <div class="stat-content">
            <h3>Total Inflow</h3>
            <div class="stat-value"><?php echo formatCurrency(getTotalInflow()); ?></div>
        </div>
    </div>
    
    <div class="stat-card outflow">
        <div class="stat-icon">📉</div>
        <div class="stat-content">
            <h3>Total Outflow</h3>
            <div class="stat-value"><?php echo formatCurrency(getTotalOutflow()); ?></div>
        </div>
    </div>
    
    <div class="stat-card profit">
        <div class="stat-icon">💰</div>
        <div class="stat-content">
            <h3>Net Profit</h3>
            <div class="stat-value"><?php echo formatCurrency(getTotalProfit()); ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Transactions Table -->
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Description</th>
                <th>Seller</th>
                <th>Category</th>
                <th>Source</th>
                <th>Invoice</th>
                <th>Receipt</th>
                <th>Amount</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($transactions)): ?>
            <tr>
                <td colspan="10" class="no-data">No transactions found.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($transactions as $transaction): ?>
            <tr>
                <td><?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?></td>
                <td>
                    <span class="transaction-type <?php echo $transaction['type']; ?>">
                        <?php echo ucfirst($transaction['type']); ?>
                        <?php if ($transaction['is_recurring']): ?>
                        <span class="recurring-badge">🔄</span>
                        <?php endif; ?>
                    </span>
                </td>
                <td><?php 
                    $words = explode(' ', $transaction['description']);
                    $shortDescription = implode(' ', array_slice($words, 0, 10));
                    echo $shortDescription . (count($words) > 10 ? '...' : '');
                ?></td>
                <td><?php echo !empty($transaction['seller_details']) ? htmlspecialchars($transaction['seller_details']) : '-'; ?></td>
                <td>
                    <span class="category-badge <?php echo $transaction['category']; ?>">
                        <?php echo $transaction['category'] === 'invoice_linked' ? 'Job Order' : ucfirst(str_replace('_', ' ', $transaction['category'])); ?>
                    </span>
                </td>
                <td><?php echo $transaction['bank_name'] ?: 'Not specified'; ?></td>
                <td>
                    <?php if ($transaction['invoice_id']): ?>
                        <a href="invoice_detail.php?id=<?php echo $transaction['invoice_id']; ?>">
                            <?php echo $transaction['invoice_id']; ?>
                        </a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($transaction['receipt_file'])): ?>
                        <a href="download_receipt.php?file=<?php echo urlencode($transaction['receipt_file']); ?>" class="btn btn-sm btn-secondary" target="_blank">📄 Download</a>
                    <?php elseif (!empty($transaction['receipt_number'])): ?>
                        <span class="receipt-number">#<?php echo $transaction['receipt_number']; ?></span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td class="amount <?php echo $transaction['type']; ?>">
                    <?php echo ($transaction['type'] === 'inflow' ? '+' : '-') . formatCurrency($transaction['amount']); ?>
                </td>
                <td>
                    <div class="action-buttons">
                        <?php if (checkUserPermission('accountant')): ?>
                        <button class="btn btn-edit btn-sm" onclick="editTransaction(<?php echo $transaction['id']; ?>)">Edit</button>
                        <?php endif; ?>
                        <?php if (checkUserPermission('admin')): ?>
                        <button class="btn btn-delete btn-sm" onclick="deleteTransaction(<?php echo $transaction['id']; ?>, '<?php echo htmlspecialchars($transaction['description']); ?>')">Delete</button>
                        <?php endif; ?>
                        <?php if (!checkUserPermission('accountant')): ?>
                        -
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.form-row {
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

.category-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.category-badge.internal {
    background-color: #bee3f8;
    color: #2b6cb0;
}

.category-badge.invoice_linked {
    background-color: #fbb6ce;
    color: #b83280;
}

.amount.inflow {
    color: #38a169;
    font-weight: 600;
}

.amount.outflow {
    color: #e53e3e;
    font-weight: 600;
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
    
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function showAddForm() {
    document.getElementById('addTransactionForm').style.display = 'block';
}

function hideAddForm() {
    document.getElementById('addTransactionForm').style.display = 'none';
}

function toggleInvoiceSelect() {
    const category = document.getElementById('category').value;
    const invoiceSelect = document.getElementById('invoice_select');
    const invoiceIdField = document.getElementById('invoice_id');
    const recurringSection = document.getElementById('recurring_section');
    
    if (category === 'invoice_linked') {
        invoiceSelect.style.display = 'block';
        invoiceIdField.required = true;
        recurringSection.style.display = 'none';
    } else {
        invoiceSelect.style.display = 'none';
        invoiceIdField.required = false;
        invoiceIdField.value = '';
        recurringSection.style.display = 'block';
    }
}

function toggleRecurringFrequency() {
    const isRecurring = document.getElementById('is_recurring').checked;
    const frequencySelect = document.getElementById('frequency_select');
    
    if (isRecurring) {
        frequencySelect.style.display = 'block';
    } else {
        frequencySelect.style.display = 'none';
    }
}

function deleteTransaction(transactionId, description) {
    document.getElementById('delete_transaction_id').value = transactionId;
    document.getElementById('deleteTransactionMessage').textContent = `Are you sure you want to delete the transaction "${description}"? This action cannot be undone.`;
    document.getElementById('deleteTransactionModal').style.display = 'flex';
}

function hideDeleteTransactionModal() {
    document.getElementById('deleteTransactionModal').style.display = 'none';
}

function editTransaction(transactionId) {
    // Fetch transaction data and populate edit form
    fetch(`get_transaction.php?id=${transactionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const transaction = data.transaction;
                
                // Populate edit form fields
                document.getElementById('edit_transaction_id').value = transaction.id;
                document.getElementById('edit_type').value = transaction.type;
                document.getElementById('edit_amount').value = transaction.amount;
                document.getElementById('edit_description').value = transaction.description;
                document.getElementById('edit_seller_details').value = transaction.seller_details || '';
                document.getElementById('edit_receipt_number').value = transaction.receipt_number || '';
                document.getElementById('edit_category').value = transaction.category;
                document.getElementById('edit_bank_account_id').value = transaction.bank_account_id || '';
                document.getElementById('edit_transaction_date').value = transaction.transaction_date;
                document.getElementById('edit_is_recurring').checked = transaction.is_recurring == 1;
                document.getElementById('edit_recurring_frequency').value = transaction.recurring_frequency || '';
                
                // Handle invoice selection
                if (transaction.category === 'invoice_linked') {
                    document.getElementById('edit_invoice_select').style.display = 'block';
                    document.getElementById('edit_invoice_id').value = transaction.invoice_id || '';
                    document.getElementById('edit_recurring_section').style.display = 'none';
                } else {
                    document.getElementById('edit_invoice_select').style.display = 'none';
                    document.getElementById('edit_recurring_section').style.display = 'block';
                }
                
                // Handle recurring frequency
                if (transaction.is_recurring == 1) {
                    document.getElementById('edit_frequency_select').style.display = 'block';
                } else {
                    document.getElementById('edit_frequency_select').style.display = 'none';
                }
                
                // Show modal
                document.getElementById('editTransactionModal').style.display = 'flex';
            } else {
                alert('Error loading transaction data: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error loading transaction data: ' + error.message);
        });
}

function hideEditTransactionModal() {
    document.getElementById('editTransactionModal').style.display = 'none';
}

function toggleEditInvoiceSelect() {
    const category = document.getElementById('edit_category').value;
    const invoiceSelect = document.getElementById('edit_invoice_select');
    const invoiceIdField = document.getElementById('edit_invoice_id');
    const recurringSection = document.getElementById('edit_recurring_section');
    
    if (category === 'invoice_linked') {
        invoiceSelect.style.display = 'block';
        invoiceIdField.required = true;
        recurringSection.style.display = 'none';
    } else {
        invoiceSelect.style.display = 'none';
        invoiceIdField.required = false;
        invoiceIdField.value = '';
        recurringSection.style.display = 'block';
    }
}

function toggleEditRecurringFrequency() {
    const isRecurring = document.getElementById('edit_is_recurring').checked;
    const frequencySelect = document.getElementById('edit_frequency_select');
    
    if (isRecurring) {
        frequencySelect.style.display = 'block';
    } else {
        frequencySelect.style.display = 'none';
    }
}
</script>

<!-- Edit Transaction Modal -->
<div id="editTransactionModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px; width: 90%;">
        <h3>Edit Transaction</h3>
        <form method="POST">
            <input type="hidden" id="edit_transaction_id" name="transaction_id">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_type">Type</label>
                    <select id="edit_type" name="type" class="form-control" required>
                        <option value="inflow">Inflow (Income)</option>
                        <option value="outflow">Outflow (Expense)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_amount">Amount</label>
                    <input type="number" id="edit_amount" name="amount" class="form-control" step="0.01" min="0" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="edit_description">Description</label>
                <input type="text" id="edit_description" name="description" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="edit_seller_details">Seller/Vendor Details (Optional)</label>
                <input type="text" id="edit_seller_details" name="seller_details" class="form-control" placeholder="Company name, contact person, phone, etc.">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_transaction_date">Date</label>
                    <input type="date" id="edit_transaction_date" name="transaction_date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_bank_account_id">Source</label>
                    <select id="edit_bank_account_id" name="bank_account_id" class="form-control" required>
                        <option value="">Select source...</option>
                        <?php foreach ($bankAccounts as $bank): ?>
                        <option value="<?php echo $bank['id']; ?>"><?php echo $bank['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="edit_category">Category</label>
                <select id="edit_category" name="category" class="form-control" onchange="toggleEditInvoiceSelect()" required>
                    <option value="internal">Internal Expense</option>
                    <option value="invoice_linked">Job Order</option>
                </select>
            </div>
            
            <div class="form-group" id="edit_invoice_select" style="display: none;">
                <label for="edit_invoice_id">Select Job Order</label>
                <select id="edit_invoice_id" name="invoice_id" class="form-control">
                    <option value="">Select job order...</option>
                    <?php foreach ($invoices as $invoice): ?>
                    <option value="<?php echo $invoice['id']; ?>"><?php echo $invoice['invoice_id']; ?> - <?php echo $invoice['client_name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="edit_receipt_number">Receipt Number (Optional)</label>
                <input type="text" id="edit_receipt_number" name="receipt_number" class="form-control" placeholder="Enter receipt number if available">
            </div>
            
            <div id="edit_recurring_section">
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="edit_is_recurring" name="is_recurring" onchange="toggleEditRecurringFrequency()">
                        Recurring Transaction
                    </label>
                </div>
                
                <div class="form-group" id="edit_frequency_select" style="display: none;">
                    <label for="edit_recurring_frequency">Frequency</label>
                    <select id="edit_recurring_frequency" name="recurring_frequency" class="form-control">
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="edit_transaction" class="btn btn-primary">Update Transaction</button>
                <button type="button" class="btn btn-secondary" onclick="hideEditTransactionModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Transaction Confirmation Modal -->
<div id="deleteTransactionModal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3>Delete Transaction</h3>
        <p id="deleteTransactionMessage"></p>
        <form method="POST">
            <input type="hidden" id="delete_transaction_id" name="transaction_id">
            <button type="submit" name="delete_transaction" class="btn btn-danger">Yes, Delete</button>
            <button type="button" class="btn btn-secondary" onclick="hideDeleteTransactionModal()">Cancel</button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>