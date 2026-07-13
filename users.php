<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: index.php'); exit(); }

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

$message = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) { $message = 'Invalid request.'; $msgType = 'danger'; }
    else {
        $action = $_POST['action'] ?? '';
        if ($action == 'add') {
            $username = trim($_POST['username']); $password = $_POST['password']; $role = $_POST['role'];
            if ($username && $password && in_array($role, ['admin','cashier'])) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=?");
                $stmt->execute([$username]);
                if ($stmt->fetchColumn() > 0) { $message = 'Username exists.'; $msgType = 'warning'; }
                else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?,?,?)");
                    if ($stmt->execute([$username, $hashed, $role])) { $message = 'User added.'; $msgType = 'success'; }
                    else { $message = 'Error.'; $msgType = 'danger'; }
                }
            } else { $message = 'Invalid input.'; $msgType = 'warning'; }
        } elseif ($action == 'edit') {
            $id = intval($_POST['id']); $username = trim($_POST['username']); $role = $_POST['role']; $password = $_POST['password'] ?? '';
            if ($id > 0 && $username && in_array($role, ['admin','cashier'])) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=? AND id!=?");
                $stmt->execute([$username, $id]);
                if ($stmt->fetchColumn() > 0) { $message = 'Username exists.'; $msgType = 'warning'; }
                else {
                    if (!empty($password)) {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET username=?, password=?, role=? WHERE id=?");
                        $ok = $stmt->execute([$username, $hashed, $role, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET username=?, role=? WHERE id=?");
                        $ok = $stmt->execute([$username, $role, $id]);
                    }
                    if ($ok) { $message = 'User updated.'; $msgType = 'success'; } else { $message = 'Error.'; $msgType = 'danger'; }
                }
            } else { $message = 'Invalid input.'; $msgType = 'warning'; }
        } elseif ($action == 'delete') {
            $id = intval($_POST['id']);
            if ($id > 0 && $id != $_SESSION['user_id']) {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
                if ($stmt->execute([$id])) { $message = 'User deleted.'; $msgType = 'success'; } else { $message = 'Error.'; $msgType = 'danger'; }
            } else { $message = 'Cannot delete yourself.'; $msgType = 'warning'; }
        }
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
$stats = $pdo->query("SELECT COUNT(*) as total, SUM(role='admin') as admins, SUM(role='cashier') as cashiers FROM users")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👥 Users - POS Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>body { background: #f4f6f9; font-family: 'Inter', sans-serif; } .sidebar { background: #1a1a2e; min-height: 100vh; }</style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-users me-2 text-primary"></i>Users</h1>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="fas fa-plus"></i> Add User</button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><h5>Total Users</h5><h2><?= $stats['total'] ?></h2></div></div></div>
                    <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><h5>Admins</h5><h2><?= $stats['admins'] ?></h2></div></div></div>
                    <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><h5>Cashiers</h5><h2><?= $stats['cashiers'] ?></h2></div></div></div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-transparent"><i class="fas fa-list me-2"></i>All Users</div>
                    <div class="card-body">
                        <table id="usersTable" class="table table-hover">
                            <thead><tr><th>ID</th><th>Username</th><th>Role</th><th>Created</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?= $u['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($u['username']) ?></strong> <?= ($u['id'] == $_SESSION['user_id']) ? '<span class="badge bg-info">You</span>' : '' ?></td>
                                    <td><?= $u['role'] == 'admin' ? '<span class="badge bg-danger">Admin</span>' : '<span class="badge bg-primary">Cashier</span>' ?></td>
                                    <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)"><i class="fas fa-edit"></i></button>
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')"><i class="fas fa-trash"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Add User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST"><div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="add">
                <div class="mb-3"><label>Username</label><input type="text" class="form-control" name="username" required></div>
                <div class="mb-3"><label>Password</label><input type="password" class="form-control" name="password" required></div>
                <div class="mb-3"><label>Role</label><select class="form-control" name="role"><option value="cashier">Cashier</option><option value="admin">Admin</option></select></div>
            </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add</button></div></form>
        </div></div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST"><div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="mb-3"><label>Username</label><input type="text" class="form-control" id="edit_username" name="username" required></div>
                <div class="mb-3"><label>New Password (leave blank to keep)</label><input type="password" class="form-control" name="password" placeholder="Optional"></div>
                <div class="mb-3"><label>Role</label><select class="form-control" id="edit_role" name="role"><option value="cashier">Cashier</option><option value="admin">Admin</option></select></div>
            </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update</button></div></form>
        </div></div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Confirm Delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><p>Delete user <strong id="delName"></strong>?</p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="delId"><button type="submit" class="btn btn-danger">Delete</button></form>
            </div>
        </div></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(u) {
            document.getElementById('edit_id').value = u.id;
            document.getElementById('edit_username').value = u.username;
            document.getElementById('edit_role').value = u.role;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
        function deleteUser(id, name) {
            document.getElementById('delId').value = id;
            document.getElementById('delName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>
