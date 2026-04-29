<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/partials/admin_module_layout.php';

require_role(['ADMIN']);
$pdo = db();

$auditFilters = [
    'role' => strtoupper(trim((string) ($_GET['audit_role'] ?? ''))),
    'action' => trim((string) ($_GET['audit_action'] ?? '')),
    'entity' => trim((string) ($_GET['audit_entity'] ?? '')),
    'date_from' => trim((string) ($_GET['audit_date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['audit_date_to'] ?? '')),
    'q' => trim((string) ($_GET['audit_q'] ?? '')),
];

$topServices = $pdo->query(
    'SELECT s.id, s.title, s.city, s.event_type, s.base_price, u.name AS provider_name
     FROM services s
     INNER JOIN users u ON u.id = s.provider_id
     ORDER BY s.created_at DESC
     LIMIT 25'
)->fetchAll();

$recentReviews = $pdo->query(
    'SELECT r.id, r.booking_id, r.rating, r.comment, r.created_at, c.name AS client_name, p.name AS provider_name
     FROM reviews r
     INNER JOIN users c ON c.id = r.client_id
     INNER JOIN users p ON p.id = r.provider_id
     ORDER BY r.created_at DESC
     LIMIT 25'
)->fetchAll();

$notifySummary = $pdo->query(
    'SELECT delivery_status, COUNT(*) AS cnt
     FROM notifications
     GROUP BY delivery_status
     ORDER BY cnt DESC'
)->fetchAll();

$auditWhere = [];
$auditParams = [];

if ($auditFilters['role'] !== '' && in_array($auditFilters['role'], ['ADMIN', 'CLIENT', 'PROVIDER', 'SYSTEM'], true)) {
    $auditWhere[] = 'al.actor_role = ?';
    $auditParams[] = $auditFilters['role'];
}
if ($auditFilters['action'] !== '') {
    $auditWhere[] = 'al.action_key LIKE ?';
    $auditParams[] = '%' . $auditFilters['action'] . '%';
}
if ($auditFilters['entity'] !== '') {
    $auditWhere[] = 'al.entity_type LIKE ?';
    $auditParams[] = '%' . $auditFilters['entity'] . '%';
}
if ($auditFilters['date_from'] !== '') {
    $auditWhere[] = 'DATE(al.created_at) >= ?';
    $auditParams[] = $auditFilters['date_from'];
}
if ($auditFilters['date_to'] !== '') {
    $auditWhere[] = 'DATE(al.created_at) <= ?';
    $auditParams[] = $auditFilters['date_to'];
}
if ($auditFilters['q'] !== '') {
    $auditWhere[] = '(u.name LIKE ? OR al.action_key LIKE ? OR al.entity_type LIKE ? OR al.details LIKE ?)';
    $like = '%' . $auditFilters['q'] . '%';
    $auditParams[] = $like;
    $auditParams[] = $like;
    $auditParams[] = $like;
    $auditParams[] = $like;
}

$auditSql =
    'SELECT al.id, al.actor_role, u.name AS actor_name, al.action_key, al.entity_type, al.entity_id, al.details, al.created_at
     FROM activity_logs al
     LEFT JOIN users u ON u.id = al.actor_user_id';
if (count($auditWhere) > 0) {
    $auditSql .= ' WHERE ' . implode(' AND ', $auditWhere);
}
$auditSql .= ' ORDER BY al.created_at DESC LIMIT 200';

$activityStmt = $pdo->prepare($auditSql);
$activityStmt->execute($auditParams);
$activityLogs = $activityStmt->fetchAll();
?>
<?php render_admin_module_page_start('Admin Reports'); ?>
    <section class="provider-hero">
        <h1 class="h4 mb-1">Admin Reports</h1>
        <p class="mb-0">Operational report snapshots for services, reviews, and communication delivery.</p>
    </section>

    <section class="provider-card">
        <h2 class="h5 mb-3">Latest Services Report</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>ID</th><th>Title</th><th>Provider</th><th>Event Type</th><th>City</th><th>Base Price</th></tr></thead>
                <tbody>
                <?php foreach ($topServices as $service): ?>
                    <tr>
                        <td>#<?php echo (int) $service['id']; ?></td>
                        <td><?php echo htmlspecialchars((string) $service['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $service['provider_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $service['event_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $service['city'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>INR <?php echo number_format((float) $service['base_price'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="provider-card">
        <h2 class="h5 mb-3">Recent Reviews Report</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>ID</th><th>Booking</th><th>Client</th><th>Provider</th><th>Rating</th><th>Comment</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($recentReviews as $review): ?>
                    <tr>
                        <td><?php echo (int) $review['id']; ?></td>
                        <td>#<?php echo (int) $review['booking_id']; ?></td>
                        <td><?php echo htmlspecialchars((string) $review['client_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $review['provider_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) $review['rating']; ?>/5</td>
                        <td><?php echo htmlspecialchars((string) $review['comment'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $review['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="provider-card">
        <h2 class="h5 mb-3">Notification Delivery Report</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>Status</th><th>Count</th></tr></thead>
                <tbody>
                <?php foreach ($notifySummary as $status): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $status['delivery_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) $status['cnt']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="provider-card">
        <h2 class="h5 mb-3">Activity Audit Trail</h2>
        <form method="get" class="row g-2 mb-3">
            <div class="col-md-2">
                <label class="form-label">Role</label>
                <select class="form-select" name="audit_role">
                    <option value="">All</option>
                    <?php foreach (['ADMIN', 'CLIENT', 'PROVIDER', 'SYSTEM'] as $role): ?>
                        <option value="<?php echo $role; ?>" <?php echo $auditFilters['role'] === $role ? 'selected' : ''; ?>><?php echo $role; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Action</label>
                <input class="form-control" name="audit_action" value="<?php echo htmlspecialchars($auditFilters['action'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. quote">
            </div>
            <div class="col-md-2">
                <label class="form-label">Entity</label>
                <input class="form-control" name="audit_entity" value="<?php echo htmlspecialchars($auditFilters['entity'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. booking">
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input class="form-control" type="date" name="audit_date_from" value="<?php echo htmlspecialchars($auditFilters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input class="form-control" type="date" name="audit_date_to" value="<?php echo htmlspecialchars($auditFilters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Keyword</label>
                <input class="form-control" name="audit_q" value="<?php echo htmlspecialchars($auditFilters['q'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="name/details">
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Apply Filters</button>
                <a class="btn btn-outline-secondary" href="/WhiteGlove/public/admin_reports.php">Reset</a>
                <span class="text-muted align-self-center small">Showing <?php echo count($activityLogs); ?> record(s).</span>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>ID</th><th>Actor</th><th>Role</th><th>Action</th><th>Entity</th><th>Details</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($activityLogs as $log): ?>
                    <tr>
                        <td><?php echo (int) $log['id']; ?></td>
                        <td><?php echo htmlspecialchars((string) ($log['actor_name'] ?? 'System'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $log['actor_role'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $log['action_key'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $log['entity_type'], ENT_QUOTES, 'UTF-8'); ?>#<?php echo (int) ($log['entity_id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($log['details'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $log['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php render_admin_module_page_end(); ?>
