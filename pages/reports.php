<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pageTitle = 'Reports';
$reportType = $_GET['type'] ?? 'monthly';
$month = $_GET['month'] ?? date('Y-m');
$invoiceId = $_GET['invoice_id'] ?? '';

$db = getDB();

require_once '../includes/header.php';
?>

<div class="reports-container">
    <div class="page-header">
        <h1>Financial Reports</h1>
        <div class="report-controls">
            <select id="reportType" onchange="changeReportType()" class="form-control">
                <option value="monthly" <?php echo $reportType === 'monthly' ? 'selected' : ''; ?>>Monthly Report</option>
                <option value="custom" <?php echo $reportType === 'custom' ? 'selected' : ''; ?>>Custom Date Range</option>
                <option value="invoice" <?php echo $reportType === 'invoice' ? 'selected' : ''; ?>>Job Order Report</option>
                <option value="category" <?php echo $reportType === 'category' ? 'selected' : ''; ?>>Category Report</option>
                <option value="tithes" <?php echo $reportType === 'tithes' ? 'selected' : ''; ?>>Tithes Report</option>
            </select>
        </div>
    </div>
    
    <?php if ($reportType === 'monthly'): ?>
    <!-- Monthly Report -->
    <div class="report-section">
        <div class="report-header">
            <h2>Monthly Financial Report</h2>
            <input type="month" id="monthSelect" value="<?php echo $month; ?>" onchange="changeMonth()" class="form-control">
        </div>
        
        <?php
        // Get monthly data
        $stmt = $db->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN type = 'inflow' THEN amount ELSE 0 END), 0) as total_inflow,
                COALESCE(SUM(CASE WHEN type = 'outflow' THEN amount ELSE 0 END), 0) as total_outflow,
                COALESCE(SUM(CASE WHEN type = 'inflow' AND category = 'invoice_linked' THEN amount ELSE 0 END), 0) as invoice_inflow,
                COALESCE(SUM(CASE WHEN type = 'outflow' AND category = 'invoice_linked' THEN amount ELSE 0 END), 0) as invoice_outflow,
                COALESCE(SUM(CASE WHEN type = 'outflow' AND category = 'internal' THEN amount ELSE 0 END), 0) as internal_expenses
            FROM transactions 
            WHERE DATE_FORMAT(transaction_date, '%Y-%m') = ?
        ");
        $stmt->execute([$month]);
        $monthlyData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $profit = $monthlyData['total_inflow'] - $monthlyData['total_outflow'];
        ?>
        
        <div class="stats-grid">
            <div class="stat-card inflow">
                <div class="stat-content">
                    <h3>Total Inflow</h3>
                    <div class="stat-value"><?php echo formatCurrency($monthlyData['total_inflow']); ?></div>
                </div>
            </div>
            
            <div class="stat-card outflow">
                <div class="stat-content">
                    <h3>Total Outflow</h3>
                    <div class="stat-value"><?php echo formatCurrency($monthlyData['total_outflow']); ?></div>
                </div>
            </div>
            
            <div class="stat-card profit">
                <div class="stat-content">
                    <h3>Net Profit</h3>
                    <div class="stat-value"><?php echo formatCurrency($profit); ?></div>
                </div>
            </div>
        </div>
        
        <div class="breakdown-section">
            <h3>Breakdown</h3>
            <div class="breakdown-grid">
                <div class="breakdown-card">
                    <h4>Job Order Related</h4>
                    <p>Inflow: <?php echo formatCurrency($monthlyData['invoice_inflow']); ?></p>
                    <p>Outflow: <?php echo formatCurrency($monthlyData['invoice_outflow']); ?></p>
                    <p>Profit: <?php echo formatCurrency($monthlyData['invoice_inflow'] - $monthlyData['invoice_outflow']); ?></p>
                </div>
                
                <div class="breakdown-card">
                    <h4>Internal Expenses</h4>
                    <p>Total: <?php echo formatCurrency($monthlyData['internal_expenses']); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($reportType === 'custom'): ?>
    <!-- Custom Date Range Report -->
    <div class="report-section">
        <div class="report-header">
            <h2>Custom Date Range Report</h2>
            <div class="date-range-controls">
                <input type="date" id="startDate" value="<?php echo $_GET['start_date'] ?? date('Y-m-01'); ?>" class="form-control">
                <span>to</span>
                <input type="date" id="endDate" value="<?php echo $_GET['end_date'] ?? date('Y-m-t'); ?>" class="form-control">
                <button onclick="generateCustomReport()" class="btn btn-primary">Generate Report</button>
            </div>
        </div>
        
        <?php
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        
        // Get custom date range data
        $stmt = $db->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN type = 'inflow' THEN amount ELSE 0 END), 0) as total_inflow,
                COALESCE(SUM(CASE WHEN type = 'outflow' THEN amount ELSE 0 END), 0) as total_outflow,
                COALESCE(SUM(CASE WHEN type = 'inflow' AND category = 'invoice_linked' THEN amount ELSE 0 END), 0) as invoice_inflow,
                COALESCE(SUM(CASE WHEN type = 'outflow' AND category = 'invoice_linked' THEN amount ELSE 0 END), 0) as invoice_outflow,
                COALESCE(SUM(CASE WHEN type = 'outflow' AND category = 'internal' THEN amount ELSE 0 END), 0) as internal_expenses
            FROM transactions 
            WHERE transaction_date BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $customData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $customProfit = $customData['total_inflow'] - $customData['total_outflow'];
        ?>
        
        <div class="breakdown-section">
            <h3>Financial Summary (<?php echo date('M j, Y', strtotime($startDate)); ?> - <?php echo date('M j, Y', strtotime($endDate)); ?>)</h3>
            <div class="breakdown-grid">
                <div class="breakdown-card">
                    <h4>Total Inflow</h4>
                    <p class="amount inflow"><?php echo formatCurrency($customData['total_inflow']); ?></p>
                </div>
                
                <div class="breakdown-card">
                    <h4>Total Outflow</h4>
                    <p class="amount outflow"><?php echo formatCurrency($customData['total_outflow']); ?></p>
                </div>
                
                <div class="breakdown-card">
                    <h4>Net Profit/Loss</h4>
                    <p class="amount <?php echo $customProfit >= 0 ? 'profit' : 'loss'; ?>">
                        <?php echo formatCurrency($customProfit); ?>
                    </p>
                </div>
                
                <div class="breakdown-card">
                    <h4>Internal Expenses</h4>
                    <p class="amount outflow"><?php echo formatCurrency($customData['internal_expenses']); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Custom Range Transactions -->
        <div class="transactions-section">
            <h3>Transactions in Selected Period</h3>
            <?php
            $stmt = $db->prepare("
                SELECT t.*, i.invoice_id, i.client_name, b.name as bank_name
                FROM transactions t 
                LEFT JOIN invoices i ON t.invoice_id = i.id 
                LEFT JOIN bank_accounts b ON t.bank_account_id = b.id
                WHERE t.transaction_date BETWEEN ? AND ?
                ORDER BY t.transaction_date DESC
            ");
            $stmt->execute([$startDate, $endDate]);
            $customTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customTransactions as $transaction): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?></td>
                            <td>
                                <span class="transaction-type <?php echo $transaction['type']; ?>">
                                    <?php echo ucfirst($transaction['type']); ?>
                                </span>
                            </td>
                            <td><?php 
                                $words = explode(' ', $transaction['description']);
                                $shortDescription = implode(' ', array_slice($words, 0, 8));
                                echo $shortDescription . (count($words) > 8 ? '...' : '');
                            ?></td>
                            <td>
                                <span class="category-badge <?php echo $transaction['category']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $transaction['category'])); ?>
                                </span>
                            </td>
                            <td class="amount <?php echo $transaction['type']; ?>">
                                <?php echo ($transaction['type'] === 'inflow' ? '+' : '-') . formatCurrency($transaction['amount']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php elseif ($reportType === 'invoice'): ?>
    <!-- Job Order Report -->
    <div class="report-section">
        <div class="report-header">
            <h2>Job Order Report</h2>
            <select id="invoiceSelect" onchange="changeInvoice()" class="form-control">
                <option value="">Select a job order...</option>
                <?php
                $stmt = $db->prepare("SELECT id, invoice_id, client_name FROM invoices ORDER BY created_at DESC");
                $stmt->execute();
                $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($invoices as $invoice):
                ?>
                <option value="<?php echo $invoice['id']; ?>" <?php echo $invoiceId == $invoice['id'] ? 'selected' : ''; ?>>
                    <?php echo $invoice['invoice_id'] . ' - ' . $invoice['client_name']; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php if ($invoiceId): ?>
        <?php
        // Get invoice details
        $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get transactions
        $stmt = $db->prepare("SELECT * FROM transactions WHERE invoice_id = ? ORDER BY transaction_date");
        $stmt->execute([$invoiceId]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalInflow = 0;
        $totalOutflow = 0;
        foreach ($transactions as $transaction) {
            if ($transaction['type'] === 'inflow') {
                $totalInflow += $transaction['amount'];
            } else {
                $totalOutflow += $transaction['amount'];
            }
        }
        $invoiceProfit = $totalInflow - $totalOutflow;
        ?>
        
        <div class="invoice-report-details">
            <h3>Job Order: <?php echo $invoice['invoice_id']; ?></h3>
            <p><strong>Client:</strong> <?php echo $invoice['client_name']; ?></p>
            <p><strong>Service:</strong> <?php echo $invoice['service_description']; ?></p>
            <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($invoice['date'])); ?></p>
            
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
                        <div class="stat-value"><?php echo formatCurrency($invoiceProfit); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="transactions-table">
                <h4>Transactions</h4>
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
        </div>
        <?php endif; ?>
    </div>
    
    <?php elseif ($reportType === 'category'): ?>
    <!-- Category Report -->
    <div class="report-section">
        <h2>Category Report</h2>
        
        <?php
        $stmt = $db->prepare("
            SELECT 
                category,
                type,
                COALESCE(SUM(amount), 0) as total_amount,
                COUNT(*) as transaction_count
            FROM transactions 
            GROUP BY category, type
            ORDER BY category, type
        ");
        $stmt->execute();
        $categoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <div class="category-breakdown">
            <?php
            $categories = [];
            foreach ($categoryData as $data) {
                $categories[$data['category']][$data['type']] = $data;
            }
            ?>
            
            <?php foreach ($categories as $category => $types): ?>
            <div class="category-card">
                <h3><?php echo ucfirst(str_replace('_', ' ', $category)); ?></h3>
                <div class="category-stats">
                    <?php if (isset($types['inflow'])): ?>
                    <div class="category-stat inflow">
                        <span class="label">Inflow:</span>
                        <span class="value"><?php echo formatCurrency($types['inflow']['total_amount']); ?></span>
                        <span class="count">(<?php echo $types['inflow']['transaction_count']; ?> transactions)</span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($types['outflow'])): ?>
                    <div class="category-stat outflow">
                        <span class="label">Outflow:</span>
                        <span class="value"><?php echo formatCurrency($types['outflow']['total_amount']); ?></span>
                        <span class="count">(<?php echo $types['outflow']['transaction_count']; ?> transactions)</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php elseif ($reportType === 'tithes'): ?>
    <!-- Tithes Report -->
    <div class="report-section">
        <h2>Tithes Report</h2>
        
        <?php
        $stmt = $db->prepare("
            SELECT 
                status,
                COALESCE(SUM(amount), 0) as total_amount,
                COUNT(*) as count
            FROM tithes 
            GROUP BY status
        ");
        $stmt->execute();
        $titheData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalGenerated = 0;
        $totalOwed = 0;
        $totalPaid = 0;
        
        foreach ($titheData as $data) {
            $totalGenerated += $data['total_amount'];
            if ($data['status'] === 'owed') {
                $totalOwed = $data['total_amount'];
            } else {
                $totalPaid = $data['total_amount'];
            }
        }
        ?>
        
        <div class="stats-grid">
            <div class="stat-card tithes">
                <div class="stat-content">
                    <h3>Total Generated</h3>
                    <div class="stat-value"><?php echo formatCurrency($totalGenerated); ?></div>
                </div>
            </div>
            
            <div class="stat-card outflow">
                <div class="stat-content">
                    <h3>Total Owed</h3>
                    <div class="stat-value"><?php echo formatCurrency($totalOwed); ?></div>
                </div>
            </div>
            
            <div class="stat-card inflow">
                <div class="stat-content">
                    <h3>Total Paid</h3>
                    <div class="stat-value"><?php echo formatCurrency($totalPaid); ?></div>
                </div>
            </div>
            
            <div class="stat-card profit">
                <div class="stat-content">
                    <h3>Payment Rate</h3>
                    <div class="stat-value">
                        <?php echo $totalGenerated > 0 ? round(($totalPaid / $totalGenerated) * 100, 1) : 0; ?>%
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Export Options -->
    <div class="export-section">
        <h3>Export Options</h3>
        <div class="export-buttons">
            <button class="btn btn-primary" onclick="exportReport('pdf')">📄 Export as PDF</button>
            <button class="btn btn-success" onclick="exportReport('excel')">📊 Export as Excel</button>
            <button class="btn btn-warning" onclick="exportReport('csv')">📋 Export as CSV</button>
        </div>
    </div>
</div>

<style>
.reports-container {
    max-width: 1200px;
    margin: 0 auto;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.report-controls {
    width: 200px;
}

.report-section {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.report-header .form-control {
    width: 200px;
}

.breakdown-section {
    margin-top: 2rem;
}

.breakdown-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.breakdown-card {
    background: #f7fafc;
    padding: 1.5rem;
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.breakdown-card h4 {
    color: #2d3748;
    margin-bottom: 1rem;
}

.breakdown-card p {
    margin: 0.5rem 0;
    color: #718096;
}

.invoice-report-details {
    margin-top: 1rem;
}

.invoice-report-details h3 {
    color: #2d3748;
    margin-bottom: 1rem;
}

.transactions-table {
    margin-top: 2rem;
}

.category-breakdown {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.category-card {
    background: #f7fafc;
    padding: 1.5rem;
    border-radius: 10px;
    border-left: 4px solid #667eea;
}

.category-card h3 {
    color: #2d3748;
    margin-bottom: 1rem;
}

.category-stats {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.category-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.category-stat .label {
    font-weight: 500;
}

.category-stat.inflow .value {
    color: #38a169;
    font-weight: 600;
}

.category-stat.outflow .value {
    color: #e53e3e;
    font-weight: 600;
}

.category-stat .count {
    font-size: 0.8rem;
    color: #718096;
}

.export-section {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.export-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
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
    font-weight: 600;
}

.amount.outflow {
    color: #e53e3e;
    font-weight: 600;
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .report-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .export-buttons {
        flex-direction: column;
    }
    
    .category-breakdown {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function changeReportType() {
    const reportType = document.getElementById('reportType').value;
    window.location.href = `reports.php?type=${reportType}`;
}

function generateCustomReport() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    if (!startDate || !endDate) {
        alert('Please select both start and end dates');
        return;
    }
    
    if (startDate > endDate) {
        alert('Start date cannot be after end date');
        return;
    }
    
    window.location.href = `reports.php?type=custom&start_date=${startDate}&end_date=${endDate}`;
}

function changeMonth() {
    const month = document.getElementById('monthSelect').value;
    const reportType = document.getElementById('reportType').value;
    window.location.href = `reports.php?type=${reportType}&month=${month}`;
}

function changeInvoice() {
    const invoiceId = document.getElementById('invoiceSelect').value;
    const reportType = document.getElementById('reportType').value;
    if (invoiceId) {
        window.location.href = `reports.php?type=${reportType}&invoice_id=${invoiceId}`;
    }
}

function exportReport(format) {
    const reportType = document.getElementById('reportType').value;
    const params = new URLSearchParams(window.location.search);
    params.set('format', format);
    params.set('type', reportType);
    
    // Add specific parameters based on report type
    if (reportType === 'monthly') {
        const monthSelect = document.getElementById('monthSelect');
        if (monthSelect) {
            params.set('month', monthSelect.value);
        }
    } else if (reportType === 'invoice') {
        const invoiceSelect = document.getElementById('invoiceSelect');
        if (invoiceSelect && invoiceSelect.value) {
            params.set('invoice_id', invoiceSelect.value);
        }
    }
    
    window.open(`../export.php?${params.toString()}`, '_blank');
}
</script>

<?php include '../includes/footer.php'; ?>