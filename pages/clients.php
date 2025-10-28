<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pageTitle = 'Clients';
$error = '';
$success = '';

// Handle form submissions
if ($_POST && checkUserPermission('accountant')) {
    if (isset($_POST['create_client'])) {
        $name = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        $address = sanitizeInput($_POST['address']);
        
        if (createClient($name, $email, $phone, $address)) {
            $success = "Client created successfully!";
        } else {
            $error = "Failed to create client.";
        }
    }
}

// Handle delete client (Admin only)
if ($_POST && isset($_POST['delete_client'])) {
    if (!checkUserPermission('admin')) {
        $error = "You don't have permission to delete clients.";
    } else {
        $clientId = intval($_POST['client_id']);
        
        if (deleteClient($clientId)) {
            $success = "Client deleted successfully!";
        } else {
            $error = "Failed to delete client. Client may have associated job orders.";
        }
    }
}

// Get all clients
$clients = getAllClients();

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>Client Management</h1>
    <?php if (checkUserPermission('accountant')): ?>
    <button class="btn btn-primary" onclick="showCreateForm()">+ Add Client</button>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Create Client Form -->
<?php if (checkUserPermission('accountant')): ?>
<div id="createClientForm" class="form-container" style="display: none; margin-bottom: 2rem;">
    <h3>Add New Client</h3>
    <form method="POST" id="clientForm">
        <div class="form-row">
            <div class="form-group">
                <label for="name">Client Name *</label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" class="form-control">
            </div>
        </div>
        
        <div class="form-group">
            <label for="address">Address</label>
            <textarea id="address" name="address" class="form-control" rows="3"></textarea>
        </div>
        
        <button type="submit" name="create_client" class="btn btn-primary">Add Client</button>
        <button type="button" class="btn btn-secondary" onclick="hideCreateForm()">Cancel</button>
    </form>
</div>
<?php endif; ?>

<!-- Clients Table -->
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Receivables</th>
                <th>Invoices</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($clients)): ?>
            <tr>
                <td colspan="6" class="no-data">No clients found. Add your first client to get started.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($clients as $client): ?>
            <?php 
                $receivables = getClientReceivables($client['id']);
                $db = getDB();
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM invoices WHERE client_id = ?");
                $stmt->execute([$client['id']]);
                $invoiceCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            ?>
            <tr>
                <td><strong><?php echo $client['name']; ?></strong></td>
                <td><?php echo $client['email'] ?: '-'; ?></td>
                <td><?php echo $client['phone'] ?: '-'; ?></td>
                <td class="<?php echo $receivables > 0 ? 'amount outflow' : 'amount'; ?>">
                    <?php echo formatCurrency($receivables); ?>
                </td>
                <td><?php echo $invoiceCount; ?></td>
                <td>
                    <div class="action-buttons">
                        <a href="client_detail.php?id=<?php echo $client['id']; ?>" class="btn btn-view btn-sm">View Details</a>
                        <?php if (checkUserPermission('admin')): ?>
                        <button class="btn btn-delete btn-sm" onclick="deleteClient(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($client['name']); ?>')">Delete</button>
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
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
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
function showCreateForm() {
    document.getElementById('createClientForm').style.display = 'block';
}

function hideCreateForm() {
    document.getElementById('createClientForm').style.display = 'none';
}
</script>

<?php require_once '../includes/footer.php'; ?><
!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3>Delete Client</h3>
        <p id="deleteMessage"></p>
        <form method="POST">
            <input type="hidden" id="delete_client_id" name="client_id">
            <button type="submit" name="delete_client" class="btn btn-danger">Yes, Delete</button>
            <button type="button" class="btn btn-secondary" onclick="hideDeleteModal()">Cancel</button>
        </form>
    </div>
</div>

<script>
function showCreateForm() {
    document.getElementById('createClientForm').style.display = 'block';
}

function hideCreateForm() {
    document.getElementById('createClientForm').style.display = 'none';
}

function deleteClient(clientId, clientName) {
    document.getElementById('delete_client_id').value = clientId;
    document.getElementById('deleteMessage').textContent = `Are you sure you want to delete client "${clientName}"? This action cannot be undone. Note: Clients with existing job orders cannot be deleted.`;
    document.getElementById('deleteModal').style.display = 'flex';
}

function hideDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
</script>