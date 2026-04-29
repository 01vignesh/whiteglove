<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/partials/provider_layout.php';
require_once __DIR__ . '/partials/client_module_layout.php';
require_once __DIR__ . '/partials/admin_module_layout.php';

$user = require_auth();
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid notification id.';
    exit;
}

$role = (string) ($user['role'] ?? '');
$notification = null;
$clientName = null;
$heading = 'Notification Detail';
$subtitle = 'View complete notification context and delivery status.';

if ($role === 'CLIENT') {
    $stmt = $pdo->prepare(
        'SELECT n.id, n.user_id, n.channel, n.title, n.message, n.delivery_status, n.created_at
         FROM notifications n
         WHERE n.id = ? AND n.user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$id, (int) $user['id']]);
    $notification = $stmt->fetch();

    if ($notification && (string) $notification['delivery_status'] !== 'READ') {
        $markRead = $pdo->prepare('UPDATE notifications SET delivery_status = "READ" WHERE id = ? AND user_id = ?');
        $markRead->execute([$id, (int) $user['id']]);
        $notification['delivery_status'] = 'READ';
    }
} elseif ($role === 'PROVIDER') {
    $stmt = $pdo->prepare(
        'SELECT n.id, n.user_id, n.channel, n.title, n.message, n.delivery_status, n.created_at, u.name AS client_name
         FROM notifications n
         LEFT JOIN users u ON u.id = n.user_id
         WHERE n.id = ?
           AND (
               n.user_id = ?
               OR n.user_id IN (
                   SELECT DISTINCT b.client_id
                   FROM bookings b
                   INNER JOIN services s ON s.id = b.service_id
                   WHERE s.provider_id = ?
               )
           )
         LIMIT 1'
    );
    $stmt->execute([$id, (int) $user['id'], (int) $user['id']]);
    $notification = $stmt->fetch();
    if ($notification) {
        if ((int) $notification['user_id'] === (int) $user['id'] && (string) $notification['delivery_status'] !== 'READ') {
            $markRead = $pdo->prepare('UPDATE notifications SET delivery_status = "READ" WHERE id = ? AND user_id = ?');
            $markRead->execute([$id, (int) $user['id']]);
            $notification['delivery_status'] = 'READ';
        }
        if ((int) $notification['user_id'] !== (int) $user['id']) {
            $clientName = (string) ($notification['client_name'] ?? '');
        }
    }
} elseif ($role === 'ADMIN') {
    $stmt = $pdo->prepare(
        'SELECT n.id, n.user_id, n.channel, n.title, n.message, n.delivery_status, n.created_at, u.name AS client_name
         FROM notifications n
         INNER JOIN users u ON u.id = n.user_id
         WHERE n.id = ?
         LIMIT 1'
    );
    $stmt->execute([$id]);
    $notification = $stmt->fetch();
    if ($notification) {
        $clientName = (string) ($notification['client_name'] ?? '');
    }
}

if (!$notification) {
    http_response_code(404);
    echo 'Notification not found or access denied.';
    exit;
}

if ($role === 'CLIENT') {
    render_client_module_page_start('Notification Detail', '/WhiteGlove/public/client_notifications.php', 'Client Notifications');
} elseif ($role === 'PROVIDER') {
    render_provider_page_start('Notification Detail', '/WhiteGlove/public/provider_notifications.php', 'Provider Notifications');
} else {
    render_admin_module_page_start('Notification Detail', '/WhiteGlove/public/admin_reports.php', 'Admin Reports');
}
?>
    <section class="provider-hero">
        <h1 class="h4 mb-1"><?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="mb-0"><?php echo htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'); ?></p>
    </section>

    <section class="provider-card">
        <h2 class="h5 mb-3">Notification #<?php echo (int) $notification['id']; ?></h2>
        <div class="row g-3">
            <?php if ($clientName !== null && $clientName !== ''): ?>
                <div class="col-md-6">
                    <label class="form-label text-muted small mb-1">Client</label>
                    <div><?php echo htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            <?php endif; ?>
            <div class="col-md-6">
                <label class="form-label text-muted small mb-1">Status</label>
                <div><?php echo htmlspecialchars((string) $notification['delivery_status'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label text-muted small mb-1">Channel</label>
                <div><?php echo htmlspecialchars((string) $notification['channel'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label text-muted small mb-1">Created At</label>
                <div><?php echo htmlspecialchars((string) $notification['created_at'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="col-12">
                <label class="form-label text-muted small mb-1">Title</label>
                <div class="fw-semibold"><?php echo htmlspecialchars((string) $notification['title'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="col-12">
                <label class="form-label text-muted small mb-1">Message</label>
                <div class="border rounded-3 p-3 bg-light"><?php echo nl2br(htmlspecialchars((string) $notification['message'], ENT_QUOTES, 'UTF-8')); ?></div>
            </div>
        </div>
    </section>
<?php
if ($role === 'CLIENT') {
    render_client_module_page_end();
} elseif ($role === 'PROVIDER') {
    render_provider_page_end();
} else {
    render_admin_module_page_end();
}
