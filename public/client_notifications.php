<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/partials/client_module_layout.php';

$user = require_role(['CLIENT']);
$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'mark_notification_read') {
            $stmt = $pdo->prepare('UPDATE notifications SET delivery_status = "READ" WHERE id = ? AND user_id = ?');
            $stmt->execute([(int) ($_POST['notification_id'] ?? 0), (int) $user['id']]);
            $message = 'Notification marked as read.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$notifyStmt = $pdo->prepare(
    'SELECT id, channel, title, message, delivery_status, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC'
);
$notifyStmt->execute([$user['id']]);
$notifications = $notifyStmt->fetchAll();
?>
<?php render_client_module_page_start('Client Notifications'); ?>
    <section class="provider-hero">
        <h1 class="h4 mb-1">Notification Center</h1>
        <p class="mb-0">Track all system and provider updates in one feed.</p>
    </section>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <section class="provider-card">
        <h2 class="h5">Recent Notifications</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>Title</th><th>Message</th><th>Channel</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($notifications as $n): ?>
                    <tr>
                        <td>
                            <a href="/WhiteGlove/public/notification_view.php?id=<?php echo (int) $n['id']; ?>">
                                <?php echo htmlspecialchars((string) $n['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </td>
                        <td>
                            <a href="/WhiteGlove/public/notification_view.php?id=<?php echo (int) $n['id']; ?>" style="text-decoration:none;color:inherit;">
                                <?php echo htmlspecialchars((string) $n['message'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars((string) $n['channel'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $n['delivery_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $n['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php if ((string) $n['delivery_status'] !== 'READ'): ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="mark_notification_read">
                                    <input type="hidden" name="notification_id" value="<?php echo (int) $n['id']; ?>">
                                    <button class="btn btn-sm btn-outline-primary">Mark Read</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php render_client_module_page_end(); ?>
