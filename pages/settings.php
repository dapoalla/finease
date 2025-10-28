<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !checkUserPermission('admin')) {
    header('Location: ../auth/login.php');
    exit;
}

$pageTitle = 'Settings';
$error = '';
$success = '';

// Handle backup messages
if (isset($_SESSION['backup_success'])) {
    $success = $_SESSION['backup_success'];
    unset($_SESSION['backup_success']);
}

if (isset($_SESSION['backup_error'])) {
    $error = $_SESSION['backup_error'];
    unset($_SESSION['backup_error']);
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['update_company'])) {
        $companyName = sanitizeInput($_POST['company_name']);
        $address = sanitizeInput($_POST['address']);
        $contactInfo = sanitizeInput($_POST['contact_info']);
        $country = sanitizeInput($_POST['country']);
        $currency = sanitizeInput($_POST['currency']);
        $taxEnabled = isset($_POST['tax_enabled']) ? 1 : 0;
        $taxRate = floatval($_POST['tax_rate']);
        $titheRate = floatval($_POST['tithe_rate']);
        
        $db = getDB();
        // Get the latest settings ID first
        $idStmt = $db->prepare("SELECT id FROM company_settings ORDER BY id DESC LIMIT 1");
        $idStmt->execute();
        $settingsId = $idStmt->fetchColumn();
        
        if ($settingsId) {
            $stmt = $db->prepare("UPDATE company_settings SET company_name = ?, address = ?, contact_info = ?, country = ?, currency = ?, tax_enabled = ?, tax_rate = ?, tithe_rate = ? WHERE id = ?");
            $success = $stmt->execute([$companyName, $address, $contactInfo, $country, $currency, $taxEnabled, $taxRate, $titheRate, $settingsId]);
        } else {
            // Insert new settings if none exist
            $stmt = $db->prepare("INSERT INTO company_settings (company_name, address, contact_info, country, currency, tax_enabled, tax_rate, tithe_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $success = $stmt->execute([$companyName, $address, $contactInfo, $country, $currency, $taxEnabled, $taxRate, $titheRate]);
        }
        
        if ($success) {
            $success = "Company settings updated successfully!";
        } else {
            $error = "Failed to update company settings.";
        }
    }
    
    if (isset($_POST['create_user'])) {
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        
        $db = getDB();
        
        // Check if username exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetchColumn() > 0) {
            $error = "Username already exists.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            
            if ($stmt->execute([$username, $hashedPassword, $role])) {
                $success = "User created successfully!";
            } else {
                $error = "Failed to create user.";
            }
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $userId = intval($_POST['user_id']);
        
        // Don't allow deleting the current user
        if ($userId == $_SESSION['user_id']) {
            $error = "You cannot delete your own account.";
        } else {
            $db = getDB();
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            
            if ($stmt->execute([$userId])) {
                $success = "User deleted successfully!";
            } else {
                $error = "Failed to delete user.";
            }
        }
    }
    
    // Handle logo upload
    if (isset($_POST['upload_logo'])) {
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($fileExtension, $allowedExtensions) && $_FILES['company_logo']['size'] <= 2 * 1024 * 1024) {
                $fileName = 'company_logo.' . $fileExtension;
                $uploadPath = $uploadDir . $fileName;
                
                // Remove old logo if exists
                $oldLogos = glob($uploadDir . 'company_logo.*');
                foreach ($oldLogos as $oldLogo) {
                    unlink($oldLogo);
                }
                
                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $uploadPath)) {
                    // Update company settings with logo path
                    $db = getDB();
                    // Update the first settings row without using a subquery on the same table
                    $stmt = $db->prepare("UPDATE company_settings SET logo_path = ? ORDER BY id ASC LIMIT 1");
                    $stmt->execute([$fileName]);
                    
                    $success = "Company logo uploaded successfully!";
                } else {
                    $error = "Failed to upload logo file.";
                }
            } else {
                $error = "Invalid file type or file too large (max 2MB). Allowed: JPG, PNG, GIF.";
            }
        } else {
            $error = "Please select a logo file to upload.";
        }
    }
    
    // Handle source management
    if (isset($_POST['add_source'])) {
        $sourceName = sanitizeInput($_POST['source_name']);
        $sourceType = $_POST['source_type'];
        
        if (!empty($sourceName)) {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO bank_accounts (name, type, is_active) VALUES (?, ?, 1)");
            
            if ($stmt->execute([$sourceName, $sourceType])) {
                $success = "Source added successfully!";
            } else {
                $error = "Failed to add source.";
            }
        } else {
            $error = "Source name is required.";
        }
    }
    
    if (isset($_POST['delete_source'])) {
        $sourceId = intval($_POST['source_id']);
        
        // Check if source is being used in transactions
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE bank_account_id = ?");
        $stmt->execute([$sourceId]);
        
        if ($stmt->fetchColumn() > 0) {
            $error = "Cannot delete source that has associated transactions.";
        } else {
            $stmt = $db->prepare("DELETE FROM bank_accounts WHERE id = ?");
            
            if ($stmt->execute([$sourceId])) {
                $success = "Source deleted successfully!";
            } else {
                $error = "Failed to delete source.";
            }
        }
    }
}

// Get current settings
$settings = getCompanySettings();

// Get all users
$db = getDB();
$stmt = $db->prepare("SELECT * FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="settings-container">
    <h1>Settings</h1>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <!-- Company Settings -->
    <div class="settings-section">
        <h2>Company Information</h2>
        <form method="POST" class="settings-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="company_name">Company Name</label>
                    <input type="text" id="company_name" name="company_name" class="form-control" value="<?php echo $settings['company_name']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="country">Country</label>
                    <select id="country" name="country" class="form-control" onchange="updateCurrency()">
                        <option value="Nigeria" <?php echo $settings['country'] === 'Nigeria' ? 'selected' : ''; ?>>Nigeria</option>
                        <option value="United States" <?php echo $settings['country'] === 'United States' ? 'selected' : ''; ?>>United States</option>
                        <option value="United Kingdom" <?php echo $settings['country'] === 'United Kingdom' ? 'selected' : ''; ?>>United Kingdom</option>
                        <option value="European Union" <?php echo $settings['country'] === 'European Union' ? 'selected' : ''; ?>>European Union</option>
                        <option value="Other" <?php echo $settings['country'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" class="form-control" rows="3"><?php echo $settings['address']; ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="contact_info">Contact Information</label>
                <textarea id="contact_info" name="contact_info" class="form-control" rows="2"><?php echo $settings['contact_info']; ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="currency">Currency Symbol</label>
                    <select id="currency" name="currency" class="form-control">
                        <option value="₦" <?php echo $settings['currency'] === '₦' ? 'selected' : ''; ?>>₦ (Nigerian Naira)</option>
                        <option value="$" <?php echo $settings['currency'] === '$' ? 'selected' : ''; ?>>$ (US Dollar)</option>
                        <option value="£" <?php echo $settings['currency'] === '£' ? 'selected' : ''; ?>>£ (British Pound)</option>
                        <option value="€" <?php echo $settings['currency'] === '€' ? 'selected' : ''; ?>>€ (Euro)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="tithe_rate">Tithe Rate (%)</label>
                    <input type="number" id="tithe_rate" name="tithe_rate" class="form-control" value="<?php echo $settings['tithe_rate']; ?>" step="0.01" min="0" max="100" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="tax_enabled" id="tax_enabled" <?php echo $settings['tax_enabled'] ? 'checked' : ''; ?> onchange="toggleTaxRate()"> Enable Tax/VAT
                    </label>
                </div>
                
                <div class="form-group" id="tax_rate_group" style="<?php echo $settings['tax_enabled'] ? '' : 'display: none;'; ?>">
                    <label for="tax_rate">Tax Rate (%)</label>
                    <input type="number" id="tax_rate" name="tax_rate" class="form-control" value="<?php echo $settings['tax_rate']; ?>" step="0.01" min="0" max="100">
                </div>
            </div>
            
            <button type="submit" name="update_company" class="btn btn-primary">Update Company Settings</button>
        </form>
        
        <!-- Logo Upload Section -->
        <div class="logo-section">
            <h3>Company Logo</h3>
            <div class="logo-upload-container">
                <?php 
                $logoPath = $settings['logo_path'] ?? '';
                if ($logoPath && file_exists('../uploads/' . $logoPath)): ?>
                <div class="current-logo">
                    <img src="../uploads/<?php echo $logoPath; ?>" alt="Company Logo" class="logo-preview">
                    <p>Current Logo</p>
                </div>
                <?php else: ?>
                <div class="no-logo">
                    <div class="logo-placeholder">📄</div>
                    <p>No logo uploaded</p>
                </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" class="logo-upload-form">
                    <div class="form-group">
                        <label for="company_logo">Upload New Logo</label>
                        <input type="file" id="company_logo" name="company_logo" class="form-control" accept=".jpg,.jpeg,.png,.gif">
                        <small class="form-text">Supported formats: JPG, PNG, GIF (Max 2MB)</small>
                    </div>
                    <button type="submit" name="upload_logo" class="btn btn-primary">Upload Logo</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- User Management -->
    <div class="settings-section">
        <div class="section-header">
            <h2>User Management</h2>
            <button class="btn btn-primary" onclick="showCreateUserForm()">+ Add User</button>
        </div>
        
        <!-- Create User Form -->
        <div id="createUserForm" class="form-container" style="display: none; margin-bottom: 2rem;">
            <h3>Create New User</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" class="form-control" required>
                            <option value="viewer">Viewer</option>
                            <option value="general">General</option>
                            <option value="accountant">Accountant</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
                <button type="button" class="btn btn-secondary" onclick="hideCreateUserForm()">Cancel</button>
            </form>
        </div>
        
        <!-- Users Table -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['username']; ?></td>
                        <td>
                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo $user['username']; ?>')">Delete</button>
                            <?php else: ?>
                            <span class="text-muted">Current User</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Sources Management -->
    <div class="settings-section">
        <div class="section-header">
            <h2>Sources Management</h2>
            <button class="btn btn-primary" onclick="showAddSourceForm()">+ Add Source</button>
        </div>
        
        <!-- Add Source Form -->
        <div id="addSourceForm" class="form-container" style="display: none; margin-bottom: 2rem;">
            <h3>Add New Source</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="source_name">Source Name *</label>
                    <input type="text" id="source_name" name="source_name" class="form-control" required placeholder="e.g., GTBank Corporate, Cash, Opay">
                </div>
                
                <div class="form-group">
                    <label for="source_type">Source Type</label>
                    <select id="source_type" name="source_type" class="form-control" required>
                        <option value="opay">Opay</option>
                        <option value="kuda">Kuda Bank</option>
                        <option value="moniepoint">MoniePoint</option>
                        <option value="gtbank_personal">GTBank Personal</option>
                        <option value="gtbank_corporate">GTBank Corporate</option>
                        <option value="access_corporate">Access Bank Corporate</option>
                        <option value="palmpay">PalmPay</option>
                        <option value="cash">Cash</option>
                    </select>
                </div>
                
                <button type="submit" name="add_source" class="btn btn-primary">Add Source</button>
                <button type="button" class="btn btn-secondary" onclick="hideAddSourceForm()">Cancel</button>
            </form>
        </div>
        
        <!-- Sources List -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Source Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sources = getBankAccounts();
                    foreach ($sources as $source): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($source['name']); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $source['type'])); ?></td>
                        <td>
                            <span class="status-badge <?php echo $source['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $source['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-danger btn-sm" onclick="deleteSource(<?php echo $source['id']; ?>, '<?php echo htmlspecialchars($source['name']); ?>')">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Backup & Restore -->
    <div class="settings-section">
        <h2>Backup & Restore</h2>
        <div class="backup-section">
            <div class="backup-card">
                <h3>Database Backup</h3>
                <p>Create a backup of all your business data including invoices, transactions, and tithes.</p>
                <button class="btn btn-success" onclick="createBackup()">Create Backup</button>
            </div>
            
            <div class="backup-card">
                <h3>Restore Data</h3>
                <p>Restore your business data from a previously created backup file.</p>
                <input type="file" id="restoreFile" accept=".sql,.zip" style="margin-bottom: 1rem;">
                <button class="btn btn-warning" onclick="restoreBackup()">Restore Backup</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div id="deleteUserModal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3>Delete User</h3>
        <p id="deleteUserMessage"></p>
        <form method="POST">
            <input type="hidden" id="delete_user_id" name="user_id">
            <button type="submit" name="delete_user" class="btn btn-danger">Yes, Delete User</button>
            <button type="button" class="btn btn-secondary" onclick="hideDeleteUserModal()">Cancel</button>
        </form>
    </div>
</div>

<style>
.settings-container {
    max-width: 1000px;
    margin: 0 auto;
}

.settings-section {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.settings-form .form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.role-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.role-badge.role-admin {
    background-color: #fed7d7;
    color: #c53030;
}

.role-badge.role-accountant {
    background-color: #bee3f8;
    color: #2b6cb0;
}

.role-badge.role-viewer {
    background-color: #c6f6d5;
    color: #2f855a;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
}

.text-muted {
    color: #718096;
}

.backup-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.backup-card {
    background: #f7fafc;
    padding: 1.5rem;
    border-radius: 10px;
    border-left: 4px solid #667eea;
}

.backup-card h3 {
    color: #2d3748;
    margin-bottom: 1rem;
}

.backup-card p {
    color: #718096;
    margin-bottom: 1.5rem;
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

@media (max-width: 768px) {
    .section-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .settings-form .form-row {
        grid-template-columns: 1fr;
    }
    
    .backup-section {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function updateCurrency() {
    const country = document.getElementById('country').value;
    const currency = document.getElementById('currency');
    const taxEnabled = document.getElementById('tax_enabled');
    const taxRate = document.getElementById('tax_rate');
    
    switch(country) {
        case 'Nigeria':
            currency.value = '₦';
            taxEnabled.checked = true;
            taxRate.value = '7.50';
            break;
        case 'United States':
            currency.value = '$';
            break;
        case 'United Kingdom':
            currency.value = '£';
            break;
        case 'European Union':
            currency.value = '€';
            break;
    }
    
    toggleTaxRate();
}

function toggleTaxRate() {
    const taxEnabled = document.getElementById('tax_enabled').checked;
    const taxRateGroup = document.getElementById('tax_rate_group');
    taxRateGroup.style.display = taxEnabled ? 'block' : 'none';
}

function showCreateUserForm() {
    document.getElementById('createUserForm').style.display = 'block';
}

function hideCreateUserForm() {
    document.getElementById('createUserForm').style.display = 'none';
}

function deleteUser(userId, username) {
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('deleteUserMessage').textContent = `Are you sure you want to delete user "${username}"? This action cannot be undone.`;
    document.getElementById('deleteUserModal').style.display = 'block';
}

function hideDeleteUserModal() {
    document.getElementById('deleteUserModal').style.display = 'none';
}

function createBackup() {
    // Show loading indicator
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = 'Creating Backup...';
    button.disabled = true;
    
    // Navigate to backup creation
    window.location.href = '../backup.php?action=create';
}

function restoreBackup() {
    const fileInput = document.getElementById('restoreFile');
    if (!fileInput.files.length) {
        alert('Please select a backup file first.');
        return;
    }
    
    const file = fileInput.files[0];
    
    // Validate file type
    if (!file.name.toLowerCase().endsWith('.sql')) {
        alert('Please select a valid SQL backup file (.sql extension).');
        return;
    }
    
    // Validate file size (max 50MB)
    if (file.size > 50 * 1024 * 1024) {
        alert('Backup file is too large. Maximum size is 50MB.');
        return;
    }
    
    if (confirm('Are you sure you want to restore from this backup? This will overwrite all current data and cannot be undone.')) {
        const button = document.querySelector('button[onclick="restoreBackup()"]');
        const originalText = button.textContent;
        button.textContent = 'Restoring...';
        button.disabled = true;
        
        const fileData = new FormData();
        fileData.append('backup_file', file);
        
        // Use fetch to upload the file with proper headers for AJAX
        fetch('../backup.php?action=restore', {
            method: 'POST',
            body: fileData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            button.textContent = originalText;
            button.disabled = false;
            
            if (data.success) {
                alert('Backup restored successfully!\n' + data.message);
                window.location.reload();
            } else {
                alert('Restore failed: ' + data.message);
            }
        })
        .catch(error => {
            button.textContent = originalText;
            button.disabled = false;
            alert('Restore failed: ' + error.message);
        });
    }
}

function showAddSourceForm() {
    document.getElementById('addSourceForm').style.display = 'block';
}

function hideAddSourceForm() {
    document.getElementById('addSourceForm').style.display = 'none';
}

function deleteSource(sourceId, sourceName) {
    if (confirm(`Are you sure you want to delete source "${sourceName}"? This action cannot be undone if the source has no associated transactions.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="source_id" value="${sourceId}"><input type="hidden" name="delete_source" value="1">`;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>