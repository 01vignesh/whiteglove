<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/partials/provider_layout.php';

$user = require_role(['PROVIDER']);
$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'mark_refund_paid') {
            provider_mark_refund_paid((int) ($_POST['refund_id'] ?? 0), (int) $user['id']);
            $message = 'Refund marked as paid successfully.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$approval = $pdo->prepare('SELECT approval_status FROM provider_profiles WHERE user_id = ? LIMIT 1');
$approval->execute([$user['id']]);
$profile = $approval->fetch();
$isApproved = $profile && $profile['approval_status'] === 'APPROVED';

$summaryStmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS total_milestones,
        SUM(CASE WHEN pm.milestone_status = "PAID" THEN 1 ELSE 0 END) AS paid_milestones,
        SUM(CASE WHEN pm.milestone_status <> "PAID" THEN 1 ELSE 0 END) AS due_milestones,
        COALESCE(SUM(CASE WHEN pm.milestone_status = "PAID" THEN pm.amount ELSE 0 END), 0) AS paid_amount
     FROM payment_milestones pm
     INNER JOIN bookings b ON b.id = pm.booking_id
     INNER JOIN services s ON s.id = b.service_id
     WHERE s.provider_id = ?'
);
$summaryStmt->execute([$user['id']]);
$summary = $summaryStmt->fetch() ?: [
    'total_milestones' => 0,
    'paid_milestones' => 0,
    'due_milestones' => 0,
    'paid_amount' => 0,
];

$refundSummaryStmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS refund_count,
        COALESCE(SUM(CASE WHEN rr.refund_status IN ("APPROVED", "PAID") THEN rr.refund_amount ELSE 0 END), 0) AS refund_deduction
     FROM refund_requests rr
     INNER JOIN bookings b ON b.id = rr.booking_id
     INNER JOIN services s ON s.id = b.service_id
     WHERE s.provider_id = ?'
);
$refundSummaryStmt->execute([$user['id']]);
$refundSummary = $refundSummaryStmt->fetch() ?: [
    'refund_count' => 0,
    'refund_deduction' => 0,
];
$grossRevenue = (float) ($summary['paid_amount'] ?? 0);
$refundDeduction = (float) ($refundSummary['refund_deduction'] ?? 0);
$netRevenue = $grossRevenue - $refundDeduction;
if ($netRevenue < 0) {
    $netRevenue = 0;
}

$rowsStmt = $pdo->prepare(
    'SELECT
        pm.id,
        pm.booking_id,
        s.title AS service_title,
        pm.milestone_name,
        pm.amount,
        pm.due_date,
        pm.milestone_status,
        pm.paid_at,
        (
            SELECT t.reference_no
            FROM transactions t
            WHERE t.milestone_id = pm.id
            ORDER BY t.created_at DESC
            LIMIT 1
        ) AS reference_no,
        (
            SELECT t.payment_status
            FROM transactions t
            WHERE t.milestone_id = pm.id
            ORDER BY t.created_at DESC
            LIMIT 1
        ) AS payment_status
     FROM payment_milestones pm
     INNER JOIN bookings b ON b.id = pm.booking_id
     INNER JOIN services s ON s.id = b.service_id
     WHERE s.provider_id = ?
     ORDER BY pm.id DESC'
);
$rowsStmt->execute([$user['id']]);
$payments = $rowsStmt->fetchAll();

$refundRowsStmt = $pdo->prepare(
    'SELECT
        rr.id,
        rr.booking_id,
        s.title AS service_title,
        c.name AS client_name,
        rr.refund_percentage,
        rr.refund_amount,
        rr.refund_status,
        rr.paid_at,
        rr.created_at
     FROM refund_requests rr
     INNER JOIN bookings b ON b.id = rr.booking_id
     INNER JOIN services s ON s.id = b.service_id
     INNER JOIN users c ON c.id = b.client_id
     WHERE s.provider_id = ?
     ORDER BY rr.created_at DESC
     LIMIT 300'
);
$refundRowsStmt->execute([$user['id']]);
$refundRows = $refundRowsStmt->fetchAll();
?>
<?php render_provider_page_start('Provider Payments'); ?>
    <div class="provider-hero">
        <h1 class="h4 mb-1">Provider Payments</h1>
        <p class="mb-0">Track milestone payment completion, refund deductions, and transaction references for your bookings.</p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!$isApproved): ?>
        <div class="alert alert-warning">Your provider account is pending approval. Payment analytics are read-only until approval.</div>
    <?php endif; ?>

    <section class="provider-grid">
        <div class="provider-card">
            <h2>Total Milestones</h2>
            <p><?php echo (int) $summary['total_milestones']; ?></p>
        </div>
        <div class="provider-card">
            <h2>Paid Milestones</h2>
            <p><?php echo (int) $summary['paid_milestones']; ?></p>
        </div>
        <div class="provider-card">
            <h2>Due Milestones</h2>
            <p><?php echo (int) $summary['due_milestones']; ?></p>
        </div>
    </section>

    <section class="provider-card">
        <h2 class="h5">Gross Revenue (Paid)</h2>
        <p><strong>INR <?php echo number_format($grossRevenue, 2); ?></strong></p>
    </section>

    <section class="provider-grid">
        <div class="provider-card">
            <h2>Refund Deductions</h2>
            <p>INR <?php echo number_format($refundDeduction, 2); ?></p>
        </div>
        <div class="provider-card">
            <h2>Net Revenue</h2>
            <p>INR <?php echo number_format($netRevenue, 2); ?></p>
        </div>
        <div class="provider-card">
            <h2>Refund Requests</h2>
            <p><?php echo (int) $refundSummary['refund_count']; ?></p>
        </div>
    </section>

    <section class="provider-card">
        <h2 class="h5">Refund Deduction Ledger</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                <tr>
                    <th>Refund ID</th>
                    <th>Booking</th>
                    <th>Service</th>
                    <th>Client</th>
                    <th>Status</th>
                    <th>Approved Amount</th>
                    <th>Refund %</th>
                    <th>Paid Date</th>
                    <th>Requested At</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($refundRows as $r): ?>
                    <tr>
                        <td>#<?php echo (int) $r['id']; ?></td>
                        <td>#<?php echo (int) $r['booking_id']; ?></td>
                        <td><?php echo htmlspecialchars((string) $r['service_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $r['client_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $r['refund_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>INR <?php echo number_format((float) $r['refund_amount'], 2); ?></td>
                        <td><?php echo number_format((float) $r['refund_percentage'], 2); ?>%</td>
                        <td><?php echo htmlspecialchars((string) (($r['paid_at'] ?? '') !== '' ? $r['paid_at'] : '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $r['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php if ($isApproved && (string) $r['refund_status'] === 'APPROVED'): ?>
                                <form method="post" style="margin:0;">
                                    <input type="hidden" name="action" value="mark_refund_paid">
                                    <input type="hidden" name="refund_id" value="<?php echo (int) $r['id']; ?>">
                                    <button class="btn btn-sm btn-primary" type="submit">Mark as Paid</button>
                                </form>
                            <?php elseif ((string) $r['refund_status'] === 'PAID'): ?>
                                <span class="text-success">Settled</span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="provider-card">
        <h2 class="h5">Milestone Payment Ledger</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                <tr>
                    <th>Booking</th>
                    <th>Service</th>
                    <th>Milestone</th>
                    <th>Amount</th>
                    <th>Due Date</th>
                    <th>Milestone Status</th>
                    <th>Paid At</th>
                    <th>Transaction Ref</th>
                    <th>Txn Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td>#<?php echo (int) $p['booking_id']; ?></td>
                        <td><?php echo htmlspecialchars((string) $p['service_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $p['milestone_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo number_format((float) $p['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars((string) $p['due_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $p['milestone_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($p['paid_at'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($p['reference_no'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($p['payment_status'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php render_provider_page_end(); ?>
