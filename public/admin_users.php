<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/partials/admin_module_layout.php';

$admin = require_role(['ADMIN']);
$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $targetUserId = (int) ($_POST['user_id'] ?? 0);

    if ($action === 'toggle_active' && $targetUserId > 0) {
        try {
            $targetStmt = $pdo->prepare('SELECT id, role, is_active FROM users WHERE id = ? LIMIT 1');
            $targetStmt->execute([$targetUserId]);
            $target = $targetStmt->fetch();

            if (!$target) {
                throw new RuntimeException('User not found.');
            }
            if (strtoupper((string) $target['role']) === 'ADMIN') {
                throw new RuntimeException('Admin accounts cannot be activated/deactivated from this screen.');
            }

            $nextActive = ((int) ($target['is_active'] ?? 0) === 1) ? 0 : 1;
            $updateStmt = $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
            $updateStmt->execute([$nextActive, $targetUserId]);
            if ($updateStmt->rowCount() > 0) {
                log_activity(
                    (int) $admin['id'],
                    'ADMIN',
                    'user_activation_toggled',
                    'user',
                    $targetUserId,
                    [
                        'target_role' => (string) ($target['role'] ?? ''),
                        'from_active' => (int) ($target['is_active'] ?? 0),
                        'to_active' => $nextActive,
                    ]
                );
            }

            $message = $nextActive === 1
                ? 'User account activated successfully.'
                : 'User account deactivated successfully.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$users = $pdo->query(
    'SELECT id, name, email, role, is_active, created_at
     FROM users
     ORDER BY created_at DESC
     LIMIT 200'
)->fetchAll();
?>
<?php render_admin_module_page_start('Admin User Management'); ?>
    <section class="provider-hero">
        <h1 class="h4 mb-1">User Management</h1>
        <p class="mb-0">View account directory, role distribution, and activation status. Admin users are protected from activation changes.</p>
    </section>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="provider-card">
        <h2 class="h5 mb-3">User Directory</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th>Action</th><th>Created</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <?php
                    $role = strtoupper((string) ($u['role'] ?? ''));
                    $isActive = (int) ($u['is_active'] ?? 0) === 1;
                    $isAdminRow = $role === 'ADMIN';
                    ?>
                    <tr>
                        <td><?php echo (int) $u['id']; ?></td>
                        <td><?php echo htmlspecialchars((string) $u['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $u['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $u['role'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $isActive ? 'YES' : 'NO'; ?></td>
                        <td>
                            <?php if ($isAdminRow): ?>
                                <span class="text-muted small">Protected</span>
                            <?php else: ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                    <button
                                        class="btn btn-sm <?php echo $isActive ? 'btn-outline-danger' : 'btn-outline-success'; ?>"
                                        type="submit"
                                    >
                                        <?php echo $isActive ? 'Set Inactive' : 'Set Active'; ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string) $u['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php render_admin_module_page_end(); ?>
