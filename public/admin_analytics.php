<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/partials/admin_module_layout.php';

require_role(['ADMIN']);
$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        throw new RuntimeException('Refund statuses are system-managed and view-only in analytics.');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$dashboard = admin_risk_dashboard();
$summary = $dashboard['summary'];

$categories = $dashboard['top_event_categories'];
$statusDist = $pdo->query(
    'SELECT booking_status, COUNT(*) AS cnt FROM bookings GROUP BY booking_status ORDER BY cnt DESC'
)->fetchAll();

$recentTransactions = $pdo->query(
    'SELECT t.id, t.booking_id, t.amount, t.payment_status, t.reference_no, t.created_at
     FROM transactions t
     ORDER BY t.created_at DESC LIMIT 10'
)->fetchAll();

$refunds = $pdo->query(
    'SELECT rr.id, rr.booking_id, rr.reason, rr.refund_percentage, rr.refund_status, rr.created_at,
            CASE
                WHEN rr.reason = "Auto-initiated after provider-approved cancellation."
                     AND COALESCE(TRIM(cr.reason), "") <> ""
                THEN cr.reason
                ELSE rr.reason
            END AS display_reason
     FROM refund_requests rr
     LEFT JOIN (
         SELECT c1.booking_id, c1.reason
         FROM cancellation_requests c1
         INNER JOIN (
             SELECT booking_id, MAX(id) AS latest_id
             FROM cancellation_requests
             GROUP BY booking_id
         ) c2 ON c2.latest_id = c1.id
     ) cr ON cr.booking_id = rr.booking_id
     ORDER BY rr.created_at DESC
     LIMIT 20'
)->fetchAll();
?>
<?php render_admin_module_page_start('Admin Analytics Center'); ?>
    <section class="provider-hero">
        <h1 class="h4 mb-1">Analytics Center</h1>
        <p class="mb-0">Track platform KPIs, booking patterns, transactions, and refund activity.</p>
    </section>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <section class="provider-grid">
        <article class="provider-card"><h2>Total Users</h2><p class="mb-0"><?php echo (int) $summary['total_users']; ?></p></article>
        <article class="provider-card"><h2>Total Bookings</h2><p class="mb-0"><?php echo (int) $summary['total_bookings']; ?></p></article>
        <article class="provider-card"><h2>Cancellation Rate</h2><p class="mb-0"><?php echo number_format((float) $summary['cancellation_rate_percent'], 2); ?>%</p></article>
        <article class="provider-card"><h2>Revenue</h2><p class="mb-0">INR <?php echo number_format((float) $summary['total_revenue'], 2); ?></p></article>
        <article class="provider-card"><h2>Pending Provider Approvals</h2><p class="mb-0"><?php echo (int) $summary['pending_provider_approvals']; ?></p></article>
    </section>

    <section class="provider-card">
        <h2 class="h5 mb-3">Top Event Categories</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>Event Type</th><th>Count</th></tr></thead>
                <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $category['event_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) $category['cnt']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="provider-card">
        <h2 class="h5 mb-3">Booking Status Distribution</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>Status</th><th>Count</th></tr></thead>
                <tbody>
                <?php foreach ($statusDist as $status): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $status['booking_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) $status['cnt']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="provider-card">
        <h2 class="h5 mb-3">Recent Transactions</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>ID</th><th>Booking</th><th>Amount</th><th>Status</th><th>Reference</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($recentTransactions as $t): ?>
                    <tr>
                        <td><?php echo (int) $t['id']; ?></td>
                        <td>#<?php echo (int) $t['booking_id']; ?></td>
                        <td>INR <?php echo number_format((float) $t['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars((string) $t['payment_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $t['reference_no'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $t['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="provider-card">
        <h2 class="h5 mb-3">Refund Command Center</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>ID</th><th>Booking</th><th>Reason</th><th>Refund %</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($refunds as $r): ?>
                    <tr>
                        <td><?php echo (int) $r['id']; ?></td>
                        <td>#<?php echo (int) $r['booking_id']; ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['display_reason'] ?? $r['reason']), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo number_format((float) $r['refund_percentage'], 2); ?>%</td>
                        <td><?php echo htmlspecialchars((string) $r['refund_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php render_admin_module_page_end(); ?>
