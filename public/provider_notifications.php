<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/partials/provider_layout.php';

$user = require_role(['PROVIDER']);
$pdo = db();
$message = '';
$error = '';

$approval = $pdo->prepare('SELECT approval_status FROM provider_profiles WHERE user_id = ? LIMIT 1');
$approval->execute([$user['id']]);
$profile = $approval->fetch();
$isApproved = $profile && $profile['approval_status'] === 'APPROVED';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$isApproved) {
            throw new RuntimeException('Provider profile is pending approval. Notifications are locked.');
        }
        $id = notify_user(
            (int) ($_POST['client_id'] ?? 0),
            'APP',
            (string) ($_POST['title'] ?? ''),
            (string) ($_POST['message'] ?? '')
        );
        $message = 'Notification sent. ID: ' . $id;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$bookingsStmt = $pdo->prepare(
    'SELECT b.client_id, u.name AS client_name
     FROM bookings b
     INNER JOIN services s ON s.id = b.service_id
     INNER JOIN users u ON u.id = b.client_id
     WHERE s.provider_id = ?
     ORDER BY b.created_at DESC'
);
$bookingsStmt->execute([$user['id']]);
$rows = $bookingsStmt->fetchAll();
$clients = [];
foreach ($rows as $row) {
    $clients[(int) $row['client_id']] = $row['client_name'];
}

$notificationsStmt = $pdo->prepare(
    'SELECT n.id, n.channel, n.title, n.message, n.delivery_status, n.created_at, u.name AS client_name
     FROM notifications n
     INNER JOIN users u ON u.id = n.user_id
     WHERE n.user_id IN (
        SELECT DISTINCT b.client_id
        FROM bookings b
        INNER JOIN services s ON s.id = b.service_id
        WHERE s.provider_id = ?
     )
     ORDER BY n.created_at DESC
     LIMIT 50'
);
$notificationsStmt->execute([$user['id']]);
$sentNotifications = $notificationsStmt->fetchAll();

?>
<?php render_provider_page_start('Provider Notifications'); ?>
    <div class="provider-hero">
        <h1 class="h4 mb-1">Provider Notifications</h1>
        <p class="mb-0">Send updates to your clients and monitor delivery status.</p>
    </div>
    <?php if (!$isApproved): ?>
        <div class="alert alert-warning">Your provider account is pending approval. Sending notifications is locked.</div>
    <?php endif; ?>
    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="provider-card">
            <h2 class="h5">Send Notification</h2>
            <form method="post" class="row g-2">
                <div class="col-12">
                    <select class="form-select" name="client_id" required>
                        <option value="">Select client from bookings</option>
                        <?php foreach ($clients as $clientId => $clientName): ?>
                            <option value="<?php echo (int) $clientId; ?>"><?php echo htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12"><input class="form-control" name="title" placeholder="Notification title" required></div>
                <div class="col-12"><textarea class="form-control" name="message" rows="3" placeholder="Write message..." required></textarea></div>
                <div class="col-12"><button class="btn btn-primary w-100">Send Notification</button></div>
            </form>
    </section>

    <section class="provider-card">
            <h2 class="h5">Recent Notifications</h2>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead><tr><th>Client</th><th>Title</th><th>Channel</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($sentNotifications as $n): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($n['client_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <a href="/WhiteGlove/public/notification_view.php?id=<?php echo (int) $n['id']; ?>">
                                    <?php echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($n['channel'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($n['delivery_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($n['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    </section>
<?php render_provider_page_end(); ?>


