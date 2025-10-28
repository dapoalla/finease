<?php
session_start();

// Check if config file exists
if (!file_exists('config/config.php')) {
    header('Location: install.php');
    exit;
}

// Include required files only once
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if setup is complete
if (!file_exists('config/setup_complete.txt')) {
    header('Location: setup/index.php');
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

// Check if user has permission to view dashboard
if (!checkUserPermission('accountant')) {
    // Redirect viewers to transactions page instead of dashboard
    header('Location: pages/transactions.php');
    exit;
}

require_once 'includes/header.php';
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Dashboard</h1>
        <div class="date"><?php echo date('F j, Y'); ?></div>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card inflow">
            <div class="stat-icon">📈</div>
            <div class="stat-content">
                <h3>Total Inflow</h3>
                <div class="stat-value" id="total-inflow"><?php 
                try {
                    echo formatCurrency(getTotalInflow());
                } catch (Exception $e) {
                    echo formatCurrency(0);
                }
                ?></div>
            </div>
        </div>
        
        <div class="stat-card outflow">
            <div class="stat-icon">📉</div>
            <div class="stat-content">
                <h3>Total Outflow</h3>
                <div class="stat-value" id="total-outflow"><?php 
                try {
                    echo formatCurrency(getTotalOutflow());
                } catch (Exception $e) {
                    echo formatCurrency(0);
                }
                ?></div>
            </div>
        </div>
        
        <div class="stat-card profit">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <h3>Total Profit</h3>
                <div class="stat-value" id="total-profit"><?php 
                try {
                    echo formatCurrency(getTotalProfit());
                } catch (Exception $e) {
                    echo formatCurrency(0);
                }
                ?></div>
            </div>
        </div>
        
        <div class="stat-card invoices">
            <div class="stat-icon">📄</div>
            <div class="stat-content">
                <h3>Open Job Orders</h3>
                <div class="stat-value" id="unpaid-invoices"><?php 
                try {
                    echo getUnpaidInvoicesCount();
                } catch (Exception $e) {
                    echo '0';
                }
                ?></div>
            </div>
        </div>
        
        <div class="stat-card tithes">
            <div class="stat-icon">🙏</div>
            <div class="stat-content">
                <h3>Unpaid Tithes</h3>
                <div class="stat-value" id="unpaid-tithes"><?php 
                try {
                    echo formatCurrency(getUnpaidTithes());
                } catch (Exception $e) {
                    echo formatCurrency(0);
                }
                ?></div>
            </div>
        </div>
        
        <div class="stat-card receivables">
            <div class="stat-icon">💳</div>
            <div class="stat-content">
                <h3>Trade Receivables</h3>
                <div class="stat-value" id="receivables"><?php 
                try {
                    echo formatCurrency(getTotalReceivables());
                } catch (Exception $e) {
                    echo formatCurrency(0);
                }
                ?></div>
            </div>
        </div>
        
        <?php 
        $vatRequired = false;
        $totalInflow = 0;
        try {
            $totalInflow = getTotalInflow();
            $vatRequired = checkVATThreshold();
        } catch (Exception $e) {
            // Handle error silently
        }
        ?>
        <div class="stat-card <?php echo $vatRequired ? 'vat-alert' : 'vat-safe'; ?>">
            <div class="stat-icon"><?php echo $vatRequired ? '⚠️' : '✅'; ?></div>
            <div class="stat-content">
                <h3>VAT Status</h3>
                <div class="stat-value"><?php 
                if ($vatRequired) {
                    echo 'Required';
                } else {
                    $remaining = 25000000 - $totalInflow;
                    if ($remaining > 0) {
                        echo formatCurrency($remaining) . ' to VAT';
                    } else {
                        echo 'Not Required';
                    }
                }
                ?></div>
            </div>
        </div>
        
        <?php if (shouldApplyVAT()): ?>
        <div class="stat-card vat-alert">
            <div class="stat-icon">⚠️</div>
            <div class="stat-content">
                <h3>VAT Threshold Reached</h3>
                <div class="stat-value">Action Required</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <h2>Quick Actions</h2>
        <div class="actions-grid">
            <a href="pages/invoices.php" class="action-card">
                <div class="action-icon">📄</div>
                <div class="action-content">
                    <h3>Create Job Order</h3>
                    <p>Generate new job order for clients</p>
                </div>
            </a>
            
            <a href="pages/transactions.php" class="action-card">
                <div class="action-icon">💰</div>
                <div class="action-content">
                    <h3>Add Transaction</h3>
                    <p>Record income or expense</p>
                </div>
            </a>
            
            <a href="pages/clients.php" class="action-card">
                <div class="action-icon">👥</div>
                <div class="action-content">
                    <h3>Add Client</h3>
                    <p>Register new client</p>
                </div>
            </a>
            
            <a href="pages/reports.php" class="action-card">
                <div class="action-icon">📊</div>
                <div class="action-content">
                    <h3>View Reports</h3>
                    <p>Financial insights & analytics</p>
                </div>
            </a>
        </div>
    </div>
    
    <!-- Recent Transactions -->
    <div class="recent-transactions">
        <h2>Recent Transactions</h2>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Bank</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        $db = getDB();
                        $stmt = $db->prepare("
                            SELECT t.*, b.name as bank_name 
                            FROM transactions t 
                            LEFT JOIN bank_accounts b ON t.bank_account_id = b.id 
                            ORDER BY t.created_at DESC 
                            LIMIT 5
                        ");
                        $stmt->execute();
                        $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($recentTransactions)):
                    ?>
                    <tr>
                        <td colspan="5" class="no-data">No transactions found. <a href="pages/transactions.php">Add your first transaction</a></td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($recentTransactions as $transaction): ?>
                        <tr>
                            <td><?php echo date('M j', strtotime($transaction['transaction_date'])); ?></td>
                            <td><?php echo substr($transaction['description'], 0, 30) . (strlen($transaction['description']) > 30 ? '...' : ''); ?></td>
                            <td>
                                <span class="transaction-type <?php echo $transaction['type']; ?>">
                                    <?php echo ucfirst($transaction['type']); ?>
                                </span>
                            </td>
                            <td class="amount <?php echo $transaction['type']; ?>">
                                <?php echo ($transaction['type'] === 'inflow' ? '+' : '-') . formatCurrency($transaction['amount']); ?>
                            </td>
                            <td><?php echo $transaction['bank_name'] ?: 'N/A'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php } catch (Exception $e) { ?>
                    <tr>
                        <td colspan="5" class="no-data">Unable to load transactions</td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="view-all">
            <a href="pages/transactions.php" class="btn btn-primary">View All Transactions</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>