<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pageTitle = 'Job Orders';
$error = '';
$success = '';

// Handle form submissions
if ($_POST && checkUserPermission('accountant')) {
    if (isset($_POST['create_invoice'])) {
        $clientId = intval($_POST['client_id']);
        $clientName = sanitizeInput($_POST['client_name']);
        $serviceDescription = sanitizeInput($_POST['service_description']);
        $date = $_POST['date'];
        $notes = sanitizeInput($_POST['notes']);
        $useLineItems = isset($_POST['use_line_items']);
        
        $db = getDB();
        
        try {
            $db->beginTransaction();
            
            // Calculate amount based on line items or simple amount
            if ($useLineItems && isset($_POST['line_items']) && !empty($_POST['line_items'])) {
                $lineItemsTotal = 0;
                foreach ($_POST['line_items'] as $item) {
                    if (!empty($item['description']) && !empty($item['unit_price']) && !empty($item['quantity'])) {
                        $unitPrice = floatval($item['unit_price']);
                        $quantity = floatval($item['quantity']);
                        $lineItemsTotal += $unitPrice * $quantity;
                    }
                }
                $amount = $lineItemsTotal;
            } else {
                $amount = floatval($_POST['amount']);
            }
            
            // Calculate VAT if applicable
            $vatAmount = 0;
            $totalWithVat = $amount;
            if (shouldApplyVAT()) {
                $vatAmount = calculateVAT($amount);
                $totalWithVat = $amount + $vatAmount;
            }
            
            $invoiceId = generateInvoiceId($clientName, $date);
            
            // Create job order
            $stmt = $db->prepare("INSERT INTO invoices (invoice_id, client_id, client_name, service_description, amount, vat_amount, total_with_vat, has_line_items, line_items_total, date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if (!$stmt->execute([$invoiceId, $clientId ?: null, $clientName, $serviceDescription, $amount, $vatAmount, $totalWithVat, $useLineItems ? 1 : 0, $useLineItems ? $amount : 0, $date, $notes])) {
                throw new Exception("Failed to create job order");
            }
            
            $jobOrderDbId = $db->lastInsertId();
            
            // Add line items if used
            if ($useLineItems && isset($_POST['line_items']) && !empty($_POST['line_items'])) {
                $lineItemStmt = $db->prepare("INSERT INTO job_order_line_items (job_order_id, description, unit_price, quantity, total) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($_POST['line_items'] as $item) {
                    if (!empty($item['description']) && !empty($item['unit_price']) && !empty($item['quantity'])) {
                        $description = sanitizeInput($item['description']);
                        $unitPrice = floatval($item['unit_price']);
                        $quantity = floatval($item['quantity']);
                        $total = $unitPrice * $quantity;
                        
                        if (!$lineItemStmt->execute([$jobOrderDbId, $description, $unitPrice, $quantity, $total])) {
                            throw new Exception("Failed to add line item");
                        }
                    }
                }
            }
            
            $db->commit();
            $success = "Job Order $invoiceId created successfully!" . ($vatAmount > 0 ? " (VAT: " . formatCurrency($vatAmount) . ")" : "");
            
        } catch (Exception $e) {
            $db->rollback();
            $error = "Failed to create job order: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['update_status'])) {
        if (!checkUserPermission('accountant')) {
            $error = "You don't have permission to edit job orders.";
        } else {
            $invoiceDbId = intval($_POST['invoice_db_id']);
            $status = $_POST['status'];
            
            if (updateInvoiceStatus($invoiceDbId, $status)) {
                if ($status === 'completed') {
                    createTitheEntry($invoiceDbId);
                }
                $success = "Job Order status updated successfully!";
            } else {
                $error = "Failed to update job order status.";
            }
        }
    }
    
    if (isset($_POST['update_payment'])) {
        if (!checkUserPermission('accountant')) {
            $error = "You don't have permission to edit job orders.";
        } else {
            $invoiceDbId = intval($_POST['invoice_db_id']);
            $paymentStatus = $_POST['payment_status'];
            
            if (updatePaymentStatus($invoiceDbId, $paymentStatus)) {
                $success = "Payment status updated successfully!";
            } else {
                $error = "Failed to update payment status.";
            }
        }
    }
    
    if (isset($_POST['delete_invoice'])) {
        // Only admin can delete job orders
        if (!checkUserPermission('admin')) {
            $error = "You don't have permission to delete job orders.";
        } else {
            $invoiceDbId = intval($_POST['invoice_db_id']);
            
            if (deleteInvoice($invoiceDbId)) {
                $success = "Job Order deleted successfully!";
            } else {
                $error = "Failed to delete job order.";
            }
        }
    }
}

// Get all invoices
$db = getDB();
$stmt = $db->prepare("SELECT * FROM invoices ORDER BY created_at DESC");
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>Job Orders Management</h1>
    <?php if (checkUserPermission('accountant')): ?>
    <button class="btn btn-primary" onclick="showCreateForm()">+ Create Job Order</button>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Create Invoice Form -->
<?php if (checkUserPermission('accountant')): ?>
<div id="createInvoiceForm" class="form-container" style="display: none; margin-bottom: 2rem;">
    <h3>Create New Job Order</h3>
    <form method="POST" id="createInvoiceForm">
        <div class="form-group">
            <label for="client_id">Select Client</label>
            <select id="client_id" name="client_id" class="form-control" onchange="updateClientName()">
                <option value="">Select existing client...</option>
                <?php 
                $clients = getAllClients();
                foreach ($clients as $client): ?>
                <option value="<?php echo $client['id']; ?>"><?php echo $client['name']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="client_name">Or Enter New Client Name</label>
            <input type="text" id="client_name" name="client_name" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="service_description">Service Description</label>
            <textarea id="service_description" name="service_description" class="form-control" rows="3" required></textarea>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" id="use_line_items" name="use_line_items" onchange="toggleLineItems()">
                Use Line Items (itemized billing)
            </label>
        </div>
        
        <div id="simple_amount" class="form-group">
            <label for="amount">Amount</label>
            <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0" required>
        </div>
        
        <div id="line_items_section" class="form-group" style="display: none;">
            <label>Line Items</label>
            <div id="line_items_container">
                <div class="line-item-row" data-row="0">
                    <div class="line-item-grid">
                        <input type="text" name="line_items[0][description]" placeholder="Description" class="form-control" required>
                        <input type="number" name="line_items[0][unit_price]" placeholder="Unit Price" class="form-control" step="0.01" min="0" onchange="calculateLineTotal(0)" required>
                        <input type="number" name="line_items[0][quantity]" placeholder="Quantity" class="form-control" step="0.01" min="0.01" value="1" onchange="calculateLineTotal(0)" required>
                        <input type="number" name="line_items[0][total]" placeholder="Total" class="form-control line-total" readonly>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeLineItem(0)">Remove</button>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addLineItem()">+ Add Line Item</button>
            <div class="line-items-summary">
                <strong>Total: <span id="line_items_grand_total">₦0.00</span></strong>
            </div>
        </div>
        
        <div class="form-group">
            <label for="date">Date</label>
            <input type="date" id="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" class="form-control" rows="2"></textarea>
        </div>
        
        <button type="submit" name="create_invoice" class="btn btn-primary">Create Job Order</button>
        <button type="button" class="btn btn-secondary" onclick="hideCreateForm()">Cancel</button>
    </form>
</div>
<?php endif; ?>

<!-- Invoices Table -->
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Job Order ID</th>
                <th>Client</th>
                <th>Service</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoices as $invoice): ?>
            <tr>
                <td><?php echo $invoice['invoice_id']; ?></td>
                <td><?php echo $invoice['client_name']; ?></td>
                <td><?php echo substr($invoice['service_description'], 0, 50) . '...'; ?></td>
                <td>
                    <?php if ($_SESSION['user_role'] === 'general'): ?>
                        <span class="restricted-info">***</span>
                    <?php else: ?>
                        <?php echo formatCurrency($invoice['total_with_vat'] ?: $invoice['amount']); ?>
                        <?php if (isset($invoice['has_line_items']) && $invoice['has_line_items']): ?>
                        <small class="line-items-indicator">📋 Itemized</small>
                        <?php endif; ?>
                        <?php if ($invoice['vat_amount'] > 0): ?>
                        <small>(+VAT: <?php echo formatCurrency($invoice['vat_amount']); ?>)</small>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td><?php echo date('M j, Y', strtotime($invoice['date'])); ?></td>
                <td>
                    <span class="status-badge status-<?php echo $invoice['status']; ?>">
                        <?php echo ucfirst($invoice['status']); ?>
                    </span>
                </td>
                <td>
                    <span class="status-badge status-<?php echo $invoice['payment_status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $invoice['payment_status'])); ?>
                    </span>
                </td>
                <td>
                    <div class="action-buttons">
                        <a href="invoice_detail.php?id=<?php echo $invoice['id']; ?>" class="btn btn-view btn-sm">View</a>
                        
                        <?php if (checkUserPermission('accountant')): ?>
                        <button class="btn btn-edit btn-sm" onclick="updateStatus(<?php echo $invoice['id']; ?>, '<?php echo $invoice['status']; ?>')">Status</button>
                        <button class="btn btn-edit btn-sm" onclick="updatePayment(<?php echo $invoice['id']; ?>, '<?php echo $invoice['payment_status']; ?>')">Payment</button>
                        
                        <?php 
                        // Show Create Invoice or Print Receipt based on status
                        if ($invoice['status'] === 'completed' && $invoice['payment_status'] === 'fully_paid'): ?>
                            <a href="generate_invoice.php?job_order_id=<?php echo $invoice['id']; ?>&type=receipt" class="btn btn-generate btn-sm">📄 Print Receipt</a>
                        <?php else: ?>
                            <a href="generate_invoice.php?job_order_id=<?php echo $invoice['id']; ?>&type=invoice" class="btn btn-generate btn-sm">📄 Create Invoice</a>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if (checkUserPermission('admin')): ?>
                        <button class="btn btn-delete btn-sm" onclick="deleteInvoice(<?php echo $invoice['id']; ?>, '<?php echo $invoice['invoice_id']; ?>')">Delete</button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3>Update Job Order Status</h3>
        <form method="POST">
            <input type="hidden" id="invoice_db_id" name="invoice_db_id">
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="open">Open</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <button type="submit" name="update_status" class="btn btn-primary">Update</button>
            <button type="button" class="btn btn-secondary" onclick="hideStatusModal()">Cancel</button>
        </form>
    </div>
</div>

<!-- Payment Update Modal -->
<div id="paymentModal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3>Update Payment Status</h3>
        <form method="POST">
            <input type="hidden" id="payment_invoice_id" name="invoice_db_id">
            <div class="form-group">
                <label for="payment_status">Payment Status</label>
                <select id="payment_status" name="payment_status" class="form-control">
                    <option value="unpaid">Unpaid</option>
                    <option value="partly_paid">Partly Paid</option>
                    <option value="fully_paid">Fully Paid</option>
                </select>
            </div>
            <button type="submit" name="update_payment" class="btn btn-primary">Update</button>
            <button type="button" class="btn btn-secondary" onclick="hidePaymentModal()">Cancel</button>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3>Delete Job Order</h3>
        <p id="deleteMessage"></p>
        <form method="POST">
            <input type="hidden" id="delete_invoice_id" name="invoice_db_id">
            <button type="submit" name="delete_invoice" class="btn btn-danger">Yes, Delete</button>
            <button type="button" class="btn btn-secondary" onclick="hideDeleteModal()">Cancel</button>
        </form>
    </div>
</div>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
    margin-right: 0.5rem;
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    z-index: 1000;
}

.modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 2rem;
    border-radius: 10px;
    width: 90%;
    max-width: 400px;
}

/* Line Items Styling */
.line-item-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto;
    gap: 0.5rem;
    align-items: center;
    margin-bottom: 0.5rem;
}

.line-item-row {
    padding: 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    margin-bottom: 0.5rem;
    background: var(--bg-secondary);
}

.line-items-summary {
    margin-top: 1rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: 6px;
    text-align: right;
}

@media (max-width: 768px) {
    .line-item-grid {
        grid-template-columns: 1fr;
        gap: 0.25rem;
    }
    
    .line-item-grid input,
    .line-item-grid button {
        width: 100%;
    }
}
</style>

<script>
function showCreateForm() {
    const form = document.getElementById('createInvoiceForm');
    if (form) {
        form.style.display = 'block';
    }
}

function hideCreateForm() {
    const form = document.getElementById('createInvoiceForm');
    if (form) {
        form.style.display = 'none';
    }
}

function updateClientName() {
    const clientSelect = document.getElementById('client_id');
    const clientNameInput = document.getElementById('client_name');
    
    if (clientSelect.value) {
        const selectedOption = clientSelect.options[clientSelect.selectedIndex];
        clientNameInput.value = selectedOption.text;
        clientNameInput.readOnly = true;
    } else {
        clientNameInput.value = '';
        clientNameInput.readOnly = false;
    }
}

function updateStatus(invoiceId, currentStatus) {
    document.getElementById('invoice_db_id').value = invoiceId;
    document.getElementById('status').value = currentStatus;
    document.getElementById('statusModal').style.display = 'block';
}

function hideStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}

function updatePayment(invoiceId, currentPaymentStatus) {
    document.getElementById('payment_invoice_id').value = invoiceId;
    document.getElementById('payment_status').value = currentPaymentStatus;
    document.getElementById('paymentModal').style.display = 'block';
}

function hidePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

function deleteInvoice(invoiceId, invoiceNumber) {
    document.getElementById('delete_invoice_id').value = invoiceId;
    document.getElementById('deleteMessage').textContent = `Are you sure you want to delete job order ${invoiceNumber}? This will also delete all related transactions and cannot be undone.`;
    document.getElementById('deleteModal').style.display = 'block';
}

function hideDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

let lineItemCounter = 1;

function toggleLineItems() {
    const useLineItems = document.getElementById('use_line_items').checked;
    const simpleAmount = document.getElementById('simple_amount');
    const lineItemsSection = document.getElementById('line_items_section');
    const amountInput = document.getElementById('amount');
    
    if (useLineItems) {
        simpleAmount.style.display = 'none';
        lineItemsSection.style.display = 'block';
        amountInput.required = false;
        // Make first line item required
        const firstLineInputs = document.querySelectorAll('.line-item-row[data-row="0"] input[required]');
        firstLineInputs.forEach(input => input.required = true);
    } else {
        simpleAmount.style.display = 'block';
        lineItemsSection.style.display = 'none';
        amountInput.required = true;
        // Remove required from line items
        const lineItemInputs = document.querySelectorAll('.line-item-row input[required]');
        lineItemInputs.forEach(input => input.required = false);
    }
}

function addLineItem() {
    const container = document.getElementById('line_items_container');
    const newRow = document.createElement('div');
    newRow.className = 'line-item-row';
    newRow.setAttribute('data-row', lineItemCounter);
    
    newRow.innerHTML = `
        <div class="line-item-grid">
            <input type="text" name="line_items[${lineItemCounter}][description]" placeholder="Description" class="form-control" required>
            <input type="number" name="line_items[${lineItemCounter}][unit_price]" placeholder="Unit Price" class="form-control" step="0.01" min="0" onchange="calculateLineTotal(${lineItemCounter})" required>
            <input type="number" name="line_items[${lineItemCounter}][quantity]" placeholder="Quantity" class="form-control" step="0.01" min="0.01" value="1" onchange="calculateLineTotal(${lineItemCounter})" required>
            <input type="number" name="line_items[${lineItemCounter}][total]" placeholder="Total" class="form-control line-total" readonly>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeLineItem(${lineItemCounter})">Remove</button>
        </div>
    `;
    
    container.appendChild(newRow);
    lineItemCounter++;
}

function removeLineItem(rowId) {
    const row = document.querySelector(`.line-item-row[data-row="${rowId}"]`);
    if (row) {
        row.remove();
        calculateGrandTotal();
    }
}

function calculateLineTotal(rowId) {
    const row = document.querySelector(`.line-item-row[data-row="${rowId}"]`);
    if (!row) return;
    
    const unitPrice = parseFloat(row.querySelector('input[name*="unit_price"]').value) || 0;
    const quantity = parseFloat(row.querySelector('input[name*="quantity"]').value) || 0;
    const total = unitPrice * quantity;
    
    row.querySelector('input[name*="total"]').value = total.toFixed(2);
    calculateGrandTotal();
}

function calculateGrandTotal() {
    const totals = document.querySelectorAll('.line-total');
    let grandTotal = 0;
    
    totals.forEach(totalInput => {
        grandTotal += parseFloat(totalInput.value) || 0;
    });
    
    document.getElementById('line_items_grand_total').textContent = '₦' + grandTotal.toFixed(2);
}
</script>

<?php require_once '../includes/footer.php'; ?>