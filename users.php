<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = trim($_POST['username']);
                $password = $_POST['password'];
                $role = $_POST['role'];
                
                if (!empty($username) && !empty($password) && in_array($role, ['admin', 'cashier'])) {
                    // Check if username already exists
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    
                    if ($stmt->fetchColumn() > 0) {
                        $message = 'Username already exists.';
                        $messageType = 'warning';
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                        if ($stmt->execute([$username, $hashedPassword, $role])) {
                            $message = 'User added successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Error adding user.';
                            $messageType = 'danger';
                        }
                    }
                } else {
                    $message = 'Please fill all fields with valid values.';
                    $messageType = 'warning';
                }
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $username = trim($_POST['username']);
                $role = $_POST['role'];
                $password = $_POST['password'];
                
                if ($id > 0 && !empty($username) && in_array($role, ['admin', 'cashier'])) {
                    // Check if username already exists for other users
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                    $stmt->execute([$username, $id]);
                    
                    if ($stmt->fetchColumn() > 0) {
                        $message = 'Username already exists.';
                        $messageType = 'warning';
                    } else {
                        if (!empty($password)) {
                            // Update with new password
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
                            $result = $stmt->execute([$username, $hashedPassword, $role, $id]);
                        } else {
                            // Update without changing password
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                            $result = $stmt->execute([$username, $role, $id]);
                        }
                        
                        if ($result) {
                            $message = 'User updated successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Error updating user.';
                            $messageType = 'danger';
                        }
                    }
                } else {
                    $message = 'Please fill all fields with valid values.';
                    $messageType = 'warning';
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                if ($id > 0 && $id != $_SESSION['user_id']) {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $message = 'User deleted successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Error deleting user.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Cannot delete your own account or invalid user ID.';
                    $messageType = 'warning';
                }
                break;
        }
    }
}

// Get all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// Get user statistics
$stmt = $pdo->query("SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN role = 'cashier' THEN 1 ELSE 0 END) as cashier_count
    FROM users");
$userStats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Users Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-plus"></i> Add User
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- User Statistics -->
                <div class="row mb-4">
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Users
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $userStats['total_users']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Administrators
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $userStats['admin_count']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-shield fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Cashiers
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $userStats['cashier_count']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-cash-register fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            System Users 
                            <span class="badge bg-primary"><?php echo count($users); ?> users</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Created Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                            <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                <span class="badge bg-info ms-2">You</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['role'] == 'admin'): ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-user-shield"></i> Administrator
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">
                                                    <i class="fas fa-cash-register"></i> Cashier
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle"></i> Active
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-control" id="role" name="role" required>
                                <option value="cashier">Cashier</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="edit_password" name="password" 
                                   placeholder="Leave blank to keep current password">
                            <small class="text-muted">Leave blank to keep current password</small>
                        </div>
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-control" id="edit_role" name="role" required>
                                <option value="cashier">Cashier</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this user?</p>
                    <p><strong id="delete_username"></strong></p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        This action cannot be undone!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_id">
                        <button type="submit" class="btn btn-danger">Delete User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_role').value = user.role;
            
            var modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }
        
        function deleteUser(id, username) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_username').textContent = username;
            
            var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>