<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$clientId = intval($_GET['id'] ?? 0);
if (!$clientId) {
    header('Location: clients.php');
    exit;
}

$pageTitle = 'Client Details';
$client = getClientById($clientId);

if (!$client) {
    header('Location: clients.php');
    exit;
}

// Get client invoices
$db = getDB();
$stmt = $db->prepare("SELECT * FROM invoices WHERE client_id = ? ORDER BY created_at DESC");
$stmt->execute([$clientId]);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$receivables = getClientReceivables($clientId);

require_once '../includes/header.php';
?>

<div class="client-detail">
    <div class="page-header">
        <div>
            <h1><?php echo $client['name']; ?></h1>
            <p class="client-subtitle">Client Details & Job Order History</p>
        </div>
        <a href="clients.php" class="btn btn-secondary">← Back to Clients</a>
    </div>
    
    <!-- Client Information -->
    <div class="client-info-grid">
        <div class="info-card">
            <h3>Contact Information</h3>
            <div class="info-item">
                <strong>Email:</strong> <?php echo $client['email'] ?: 'Not provided'; ?>
            </div>
            <div class="info-item">
                <strong>Phone:</strong> <?php echo $client['phone'] ?: 'Not provided'; ?>
            </div>
            <div class="info-item">
                <strong>Address:</strong> <?php echo $client['address'] ?: 'Not provided'; ?>
            </div>
        </div>
        
        <div class="info-card">
            <h3>Financial Summary</h3>
            <div class="info-item">
                <strong>Outstanding Receivables:</strong> 
                <span class="amount <?php echo $receivables > 0 ? 'outflow' : ''; ?>">
                    <?php echo formatCurrency($receivables); ?>
                </span>
            </div>
            <div class="info-item">
                <strong>Total Invoices:</strong> <?php echo count($invoices); ?>
            </div>
            <div class="info-item">
                <strong>Client Since:</strong> <?php echo date('M j, Y', strtotime($client['created_at'])); ?>
            </div>
        </div>
    </div>
    
    <!-- Client Invoices -->
    <div class="client-invoices">
        <h2>Job Order History</h2>
        <?php if (empty($invoices)): ?>
            <div class="no-data">No job orders found for this client.</div>
        <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice ID</th>
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
                        <td><?php echo substr($invoice['service_description'], 0, 40) . '...'; ?></td>
                        <td><?php echo formatCurrency($invoice['total_with_vat'] ?: $invoice['amount']); ?></td>
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
                            <a href="invoice_detail.php?id=<?php echo $invoice['id']; ?>" class="btn btn-primary btn-sm">View</a>
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
.client-detail {
    max-width: 1200px;
    margin: 0 auto;
}

.client-subtitle {
    color: var(--text-muted);
    margin-top: 0.5rem;
}

.client-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.info-card {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: var(--shadow);
}

.info-card h3 {
    color: var(--text-primary);
    margin-bottom: 1rem;
    font-weight: 600;
}

.info-item {
    margin-bottom: 0.75rem;
    color: var(--text-secondary);
}

.info-item strong {
    color: var(--text-primary);
}

.client-invoices h2 {
    color: var(--text-primary);
    margin-bottom: 1rem;
    font-weight: 600;
}

.no-data {
    text-align: center;
    color: var(--text-muted);
    font-style: italic;
    padding: 2rem;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .client-info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>