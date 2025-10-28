<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pageTitle = 'Tithes';
$error = '';
$success = '';

// Handle tithe payment
if ($_POST && checkUserPermission('accountant')) {
    if (isset($_POST['mark_paid'])) {
        $titheId = intval($_POST['tithe_id']);
        
        $db = getDB();
        $stmt = $db->prepare("UPDATE tithes SET status = 'paid', date_paid = CURDATE() WHERE id = ?");
        
        if ($stmt->execute([$titheId])) {
            $success = "Tithe marked as paid successfully!";
        } else {
            $error = "Failed to update tithe status.";
        }
    }
}

// Get all tithes
$db = getDB();
$stmt = $db->prepare("
    SELECT t.*, i.invoice_id, i.client_name 
    FROM tithes t 
    JOIN invoices i ON t.invoice_id = i.id 
    ORDER BY t.date_generated DESC
");
$stmt->execute();
$tithes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalOwed = 0;
$totalPaid = 0;
$totalGenerated = 0;

foreach ($tithes as $tithe) {
    $totalGenerated += $tithe['amount'];
    if ($tithe['status'] === 'paid') {
        $totalPaid += $tithe['amount'];
    } else {
        $totalOwed += $tithe['amount'];
    }
}

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>Tithes Management</h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Tithes Summary -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <div class="stat-card tithes">
        <div class="stat-icon">🙏</div>
        <div class="stat-content">
            <h3>Total Generated</h3>
            <div class="stat-value"><?php echo formatCurrency($totalGenerated); ?></div>
        </div>
    </div>
    
    <div class="stat-card outflow">
        <div class="stat-icon">⏳</div>
        <div class="stat-content">
            <h3>Total Owed</h3>
            <div class="stat-value"><?php echo formatCurrency($totalOwed); ?></div>
        </div>
    </div>
    
    <div class="stat-card inflow">
        <div class="stat-icon">✅</div>
        <div class="stat-content">
            <h3>Total Paid</h3>
            <div class="stat-value"><?php echo formatCurrency($totalPaid); ?></div>
        </div>
    </div>
    
    <div class="stat-card profit">
        <div class="stat-icon">📊</div>
        <div class="stat-content">
            <h3>Payment Rate</h3>
            <div class="stat-value">
                <?php echo $totalGenerated > 0 ? round(($totalPaid / $totalGenerated) * 100, 1) : 0; ?>%
            </div>
        </div>
    </div>
</div>

<!-- Tithes Table -->
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Date Generated</th>
                <th>Invoice</th>
                <th>Client</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date Paid</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tithes)): ?>
            <tr>
                <td colspan="7" class="no-data">No tithes generated yet. Complete some invoices to generate tithes.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($tithes as $tithe): ?>
            <tr>
                <td><?php echo date('M j, Y', strtotime($tithe['date_generated'])); ?></td>
                <td>
                    <a href="invoice_detail.php?id=<?php echo $tithe['invoice_id']; ?>">
                        <?php echo $tithe['invoice_id']; ?>
                    </a>
                </td>
                <td><?php echo $tithe['client_name']; ?></td>
                <td class="amount"><?php echo formatCurrency($tithe['amount']); ?></td>
                <td>
                    <span class="status-badge status-<?php echo $tithe['status']; ?>">
                        <?php echo ucfirst($tithe['status']); ?>
                    </span>
                </td>
                <td>
                    <?php echo $tithe['date_paid'] ? date('M j, Y', strtotime($tithe['date_paid'])) : '-'; ?>
                </td>
                <td>
                    <?php if ($tithe['status'] === 'owed' && checkUserPermission('accountant')): ?>
                    <button class="btn btn-success btn-sm" onclick="markAsPaid(<?php echo $tithe['id']; ?>)">
                        Mark as Paid
                    </button>
                    <?php else: ?>
                    <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Tithe Information -->
<div class="info-section">
    <h2>About Tithes</h2>
    <div class="info-content">
        <p>Tithes are automatically calculated when job orders are marked as "Completed". The tithe amount is based on the profit (inflow - outflow) of each job order.</p>
        
        <div class="info-grid">
            <div class="info-card">
                <h4>Current Tithe Rate</h4>
                <p class="info-value"><?php echo getCompanySettings()['tithe_rate']; ?>%</p>
                <p class="info-desc">Of job order profit</p>
            </div>
            
            <div class="info-card">
                <h4>Calculation Method</h4>
                <p class="info-desc">Tithe = (Job Order Inflow - Job Order Outflow) × Tithe Rate</p>
            </div>
            
            <div class="info-card">
                <h4>When Generated</h4>
                <p class="info-desc">Automatically when job order status changes to "Completed"</p>
            </div>
        </div>
    </div>
</div>

<!-- Mark as Paid Modal -->
<div id="paymentModal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3>Mark Tithe as Paid</h3>
        <p>Are you sure you want to mark this tithe as paid?</p>
        <form method="POST">
            <input type="hidden" id="tithe_id" name="tithe_id">
            <button type="submit" name="mark_paid" class="btn btn-success">Yes, Mark as Paid</button>
            <button type="button" class="btn btn-secondary" onclick="hidePaymentModal()">Cancel</button>
        </form>
    </div>
</div>

<style>
.page-header {
    margin-bottom: 2rem;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
}

.amount {
    font-weight: 600;
    color: #2d3748;
}

.text-muted {
    color: #718096;
}

.info-section {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-top: 2rem;
}

.info-section h2 {
    color: #2d3748;
    margin-bottom: 1rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
}

.info-card {
    background: #f7fafc;
    padding: 1.5rem;
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.info-card h4 {
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.info-value {
    font-size: 1.5rem;
    font-weight: 600;
    color: #667eea;
    margin: 0.5rem 0;
}

.info-desc {
    color: #718096;
    font-size: 0.9rem;
    margin: 0;
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
    text-align: center;
}

.modal-content h3 {
    margin-bottom: 1rem;
    color: #2d3748;
}

.modal-content p {
    margin-bottom: 1.5rem;
    color: #718096;
}

.no-data {
    text-align: center;
    color: #718096;
    font-style: italic;
    padding: 2rem;
}

@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function markAsPaid(titheId) {
    document.getElementById('tithe_id').value = titheId;
    document.getElementById('paymentModal').style.display = 'block';
}

function hidePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}
</script>

<?php require_once '../includes/footer.php'; ?>