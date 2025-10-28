<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Only admins can manage users
if (!checkUserPermission('admin')) {
    header('Location: ../index.php');
    exit;
}

$pageTitle = 'User Management';
$error = '';
$success = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['add_user'])) {
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        
        if (strlen($username) < 3) {
            $error = "Username must be at least 3 characters long.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            $db = getDB();
            
            // Check if username already exists
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
    }
    
    if (isset($_POST['update_user'])) {
        $userId = intval($_POST['user_id']);
        $username = sanitizeInput($_POST['username']);
        $role = $_POST['role'];
        $newPassword = $_POST['new_password'];
        
        if (strlen($username) < 3) {
            $error = "Username must be at least 3 characters long.";
        } else {
            $db = getDB();
            
            // Check if username already exists for other users
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $userId]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = "Username already exists.";
            } else {
                if (!empty($newPassword)) {
                    if (strlen($newPassword) < 6) {
                        $error = "Password must be at least 6 characters long.";
                    } else {
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
                        $result = $stmt->execute([$username, $hashedPassword, $role, $userId]);
                    }
                } else {
                    $stmt = $db->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                    $result = $stmt->execute([$username, $role, $userId]);
                }
                
                if (isset($result) && $result) {
                    $success = "User updated successfully!";
                } elseif (!isset($result)) {
                    // Password validation failed
                } else {
                    $error = "Failed to update user.";
                }
            }
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $userId = intval($_POST['user_id']);
        
        // Prevent deleting the current user
        if ($userId == $_SESSION['user_id']) {
            $error = "You cannot delete your own account.";
        } else {
            $db = getDB();
            
            // Check if this is the last admin
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id != ?");
            $stmt->execute([$userId]);
            $adminCount = $stmt->fetchColumn();
            
            $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userRole = $stmt->fetchColumn();
            
            if ($userRole === 'admin' && $adminCount == 0) {
                $error = "Cannot delete the last admin user.";
            } else {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                
                if ($stmt->execute([$userId])) {
                    $success = "User deleted successfully!";
                } else {
                    $error = "Failed to delete user.";
                }
            }
        }
    }
}

// Get all users
$db = getDB();
$stmt = $db->prepare("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>User Management</h1>
    <button class="btn btn-primary" onclick="showAddForm()">+ Add User</button>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Add User Form -->
<div id="addUserForm" class="form-container" style="display: none; margin-bottom: 2rem;">
    <h3>Add New User</h3>
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" required minlength="3">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required minlength="6">
            </div>
            
            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" class="form-control" required>
                    <option value="viewer">Viewer (Read-only access)</option>
                    <option value="accountant">Accountant</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
        </div>
        
        <button type="submit" name="add_user" class="btn btn-primary">Create User</button>
        <button type="button" class="btn btn-secondary" onclick="hideAddForm()">Cancel</button>
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
            <?php if (empty($users)): ?>
            <tr>
                <td colspan="4" class="no-data">No users found.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($users as $user): ?>
            <tr>
                <td>
                    <?php echo htmlspecialchars($user['username']); ?>
                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                    <span class="current-user-badge">You</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="role-badge <?php echo $user['role']; ?>">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                </td>
                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-edit btn-sm" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo $user['role']; ?>')">Edit</button>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <button class="btn btn-delete btn-sm" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">Delete</button>
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

.role-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.role-badge.admin {
    background-color: #fed7d7;
    color: #c53030;
}

.role-badge.accountant {
    background-color: #bee3f8;
    color: #2b6cb0;
}

.role-badge.viewer {
    background-color: #c6f6d5;
    color: #2f855a;
}



.current-user-badge {
    background-color: #fbb6ce;
    color: #b83280;
    padding: 0.125rem 0.5rem;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 500;
    margin-left: 0.5rem;
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
    document.getElementById('addUserForm').style.display = 'block';
}

function hideAddForm() {
    document.getElementById('addUserForm').style.display = 'none';
}

function editUser(userId, username, role) {
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_new_password').value = '';
    document.getElementById('editUserModal').style.display = 'flex';
}

function hideEditUserModal() {
    document.getElementById('editUserModal').style.display = 'none';
}

function deleteUser(userId, username) {
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('deleteUserMessage').textContent = `Are you sure you want to delete user "${username}"? This action cannot be undone.`;
    document.getElementById('deleteUserModal').style.display = 'flex';
}

function hideDeleteUserModal() {
    document.getElementById('deleteUserModal').style.display = 'none';
}
</script>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px; width: 90%;">
        <h3>Edit User</h3>
        <form method="POST">
            <input type="hidden" id="edit_user_id" name="user_id">
            
            <div class="form-group">
                <label for="edit_username">Username</label>
                <input type="text" id="edit_username" name="username" class="form-control" required minlength="3">
            </div>
            
            <div class="form-group">
                <label for="edit_role">Role</label>
                <select id="edit_role" name="role" class="form-control" required>
                    <option value="viewer">Viewer (Read-only access)</option>
                    <option value="accountant">Accountant</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="edit_new_password">New Password (leave blank to keep current)</label>
                <input type="password" id="edit_new_password" name="new_password" class="form-control" minlength="6">
            </div>
            
            <div class="form-actions">
                <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                <button type="button" class="btn btn-secondary" onclick="hideEditUserModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Confirmation Modal -->
<div id="deleteUserModal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3>Delete User</h3>
        <p id="deleteUserMessage"></p>
        <form method="POST">
            <input type="hidden" id="delete_user_id" name="user_id">
            <button type="submit" name="delete_user" class="btn btn-danger">Yes, Delete</button>
            <button type="button" class="btn btn-secondary" onclick="hideDeleteUserModal()">Cancel</button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>