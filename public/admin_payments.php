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
        throw new RuntimeException('Refund statuses are system-managed and view-only in admin.');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$txQ = trim((string) ($_GET['tx_q'] ?? ''));
$txStatus = strtoupper(trim((string) ($_GET['tx_status'] ?? '')));
$txDateFrom = trim((string) ($_GET['tx_date_from'] ?? ''));
$txDateTo = trim((string) ($_GET['tx_date_to'] ?? ''));

$rfQ = trim((string) ($_GET['rf_q'] ?? ''));
$rfStatus = strtoupper(trim((string) ($_GET['rf_status'] ?? '')));
$rfClient = trim((string) ($_GET['rf_client'] ?? ''));
$rfProvider = trim((string) ($_GET['rf_provider'] ?? ''));
$rfMinAmount = trim((string) ($_GET['rf_min_amount'] ?? ''));
$rfMaxAmount = trim((string) ($_GET['rf_max_amount'] ?? ''));
$rfDateFrom = trim((string) ($_GET['rf_date_from'] ?? ''));
$rfDateTo = trim((string) ($_GET['rf_date_to'] ?? ''));

$txWhere = [];
$txParams = [];

if ($txQ !== '') {
    $txWhere[] = '(CAST(id AS CHAR) LIKE ? OR CAST(booking_id AS CHAR) LIKE ? OR reference_no LIKE ?)';
    $txLike = '%' . $txQ . '%';
    $txParams[] = $txLike;
    $txParams[] = $txLike;
    $txParams[] = $txLike;
}

if ($txStatus !== '' && in_array($txStatus, ['SUCCESS', 'FAILED', 'PENDING'], true)) {
    $txWhere[] = 'payment_status = ?';
    $txParams[] = $txStatus;
}

if ($txDateFrom !== '') {
    $txWhere[] = 'DATE(created_at) >= ?';
    $txParams[] = $txDateFrom;
}

if ($txDateTo !== '') {
    $txWhere[] = 'DATE(created_at) <= ?';
    $txParams[] = $txDateTo;
}

$txSql =
    'SELECT id, booking_id, milestone_id, amount, payment_status, reference_no, created_at
     FROM transactions';
if (count($txWhere) > 0) {
    $txSql .= ' WHERE ' . implode(' AND ', $txWhere);
}
$txSql .= ' ORDER BY created_at DESC LIMIT 300';

$txStmt = $pdo->prepare($txSql);
$txStmt->execute($txParams);
$transactions = $txStmt->fetchAll();

$rfWhere = [];
$rfParams = [];
if ($rfQ !== '') {
    $rfWhere[] = '(CAST(rr.id AS CHAR) LIKE ? OR CAST(rr.booking_id AS CHAR) LIKE ? OR rr.reason LIKE ? OR EXISTS (
        SELECT 1
        FROM cancellation_requests crs
        WHERE crs.booking_id = rr.booking_id AND crs.reason LIKE ?
    ))';
    $rfLike = '%' . $rfQ . '%';
    $rfParams[] = $rfLike;
    $rfParams[] = $rfLike;
    $rfParams[] = $rfLike;
    $rfParams[] = $rfLike;
}

if ($rfStatus !== '' && in_array($rfStatus, ['REQUESTED', 'APPROVED', 'REJECTED', 'PAID'], true)) {
    $rfWhere[] = 'rr.refund_status = ?';
    $rfParams[] = $rfStatus;
}

if ($rfClient !== '') {
    $rfWhere[] = 'c.name LIKE ?';
    $rfParams[] = '%' . $rfClient . '%';
}

if ($rfProvider !== '') {
    $rfWhere[] = 'p.name LIKE ?';
    $rfParams[] = '%' . $rfProvider . '%';
}

if ($rfMinAmount !== '' && is_numeric($rfMinAmount)) {
    $rfWhere[] = 'rr.refund_amount >= ?';
    $rfParams[] = (float) $rfMinAmount;
}

if ($rfMaxAmount !== '' && is_numeric($rfMaxAmount)) {
    $rfWhere[] = 'rr.refund_amount <= ?';
    $rfParams[] = (float) $rfMaxAmount;
}

if ($rfDateFrom !== '') {
    $rfWhere[] = 'DATE(rr.created_at) >= ?';
    $rfParams[] = $rfDateFrom;
}

if ($rfDateTo !== '') {
    $rfWhere[] = 'DATE(rr.created_at) <= ?';
    $rfParams[] = $rfDateTo;
}

$rfSql =
    'SELECT rr.id, rr.booking_id, rr.reason, rr.refund_percentage, rr.refund_amount, rr.refund_status, rr.paid_at, rr.created_at,
            CASE
                WHEN rr.reason = "Auto-initiated after provider-approved cancellation."
                     AND COALESCE(TRIM(cr.reason), "") <> ""
                THEN cr.reason
                ELSE rr.reason
            END AS display_reason,
            c.name AS client_name, p.name AS provider_name, s.title AS service_title, b.booking_status
     FROM refund_requests rr
     INNER JOIN bookings b ON b.id = rr.booking_id
     INNER JOIN users c ON c.id = b.client_id
     INNER JOIN services s ON s.id = b.service_id
     INNER JOIN users p ON p.id = s.provider_id
     LEFT JOIN (
         SELECT c1.booking_id, c1.reason
         FROM cancellation_requests c1
         INNER JOIN (
             SELECT booking_id, MAX(id) AS latest_id
             FROM cancellation_requests
             GROUP BY booking_id
         ) c2 ON c2.latest_id = c1.id
     ) cr ON cr.booking_id = rr.booking_id';
if (count($rfWhere) > 0) {
    $rfSql .= ' WHERE ' . implode(' AND ', $rfWhere);
}
$rfSql .= ' ORDER BY created_at DESC LIMIT 300';

$rfStmt = $pdo->prepare($rfSql);
$rfStmt->execute($rfParams);
$refunds = $rfStmt->fetchAll();
?>
<?php render_admin_module_page_start('Admin Payments & Refunds'); ?>
    <section class="provider-hero">
        <h1 class="h4 mb-1">Payments & Refunds</h1>
        <p class="mb-0">Track simulated transactions and manage refund request statuses.</p>
    </section>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <section class="provider-card">
        <h2 class="h5 mb-3">Transaction Filters</h2>
        <form method="get" class="row g-2 mb-3">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input class="form-control" type="text" name="tx_q" value="<?php echo htmlspecialchars($txQ, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Txn ID, booking, reference">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="tx_status">
                    <option value="">All</option>
                    <?php foreach (['SUCCESS', 'FAILED', 'PENDING'] as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo ($txStatus === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input class="form-control" type="date" name="tx_date_from" value="<?php echo htmlspecialchars($txDateFrom, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input class="form-control" type="date" name="tx_date_to" value="<?php echo htmlspecialchars($txDateTo, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">Apply</button>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <p class="mb-0 text-muted small">Showing <?php echo count($transactions); ?> transaction(s).</p>
            <a class="btn btn-sm btn-outline-secondary" href="/WhiteGlove/public/admin_payments.php">Reset</a>
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>ID</th><th>Booking</th><th>Milestone</th><th>Amount</th><th>Status</th><th>Reference</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><?php echo (int) $t['id']; ?></td>
                        <td>#<?php echo (int) $t['booking_id']; ?></td>
                        <td><?php echo (int) $t['milestone_id']; ?></td>
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
        <h2 class="h5 mb-3">Refund Queue Filters</h2>
        <form method="get" class="row g-2 mb-3">
            <input type="hidden" name="tx_q" value="<?php echo htmlspecialchars($txQ, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="tx_status" value="<?php echo htmlspecialchars($txStatus, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="tx_date_from" value="<?php echo htmlspecialchars($txDateFrom, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="tx_date_to" value="<?php echo htmlspecialchars($txDateTo, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input class="form-control" type="text" name="rf_q" value="<?php echo htmlspecialchars($rfQ, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Refund ID, booking, reason">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="rf_status">
                    <option value="">All</option>
                    <?php foreach (['REQUESTED', 'APPROVED', 'REJECTED', 'PAID'] as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo ($rfStatus === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Client</label>
                <input class="form-control" type="text" name="rf_client" value="<?php echo htmlspecialchars($rfClient, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Client name">
            </div>
            <div class="col-md-2">
                <label class="form-label">Provider</label>
                <input class="form-control" type="text" name="rf_provider" value="<?php echo htmlspecialchars($rfProvider, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Provider name">
            </div>
            <div class="col-md-1">
                <label class="form-label">Min Amt</label>
                <input class="form-control" type="number" step="0.01" min="0" name="rf_min_amount" value="<?php echo htmlspecialchars($rfMinAmount, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label">Max Amt</label>
                <input class="form-control" type="number" step="0.01" min="0" name="rf_max_amount" value="<?php echo htmlspecialchars($rfMaxAmount, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input class="form-control" type="date" name="rf_date_from" value="<?php echo htmlspecialchars($rfDateFrom, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input class="form-control" type="date" name="rf_date_to" value="<?php echo htmlspecialchars($rfDateTo, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">Apply</button>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <p class="mb-0 text-muted small">Showing <?php echo count($refunds); ?> refund request(s).</p>
            <a class="btn btn-sm btn-outline-secondary" href="/WhiteGlove/public/admin_payments.php">Reset</a>
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>ID</th><th>Booking</th><th>Client</th><th>Provider</th><th>Service</th><th>Booking Status</th><th>Reason</th><th>Refund %</th><th>Approved Amount</th><th>Status</th><th>Paid Date</th><th>Requested At</th></tr></thead>
                <tbody>
                <?php foreach ($refunds as $r): ?>
                    <tr>
                        <td><?php echo (int) $r['id']; ?></td>
                        <td>#<?php echo (int) $r['booking_id']; ?></td>
                        <td><?php echo htmlspecialchars((string) $r['client_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $r['provider_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $r['service_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $r['booking_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['display_reason'] ?? $r['reason']), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo number_format((float) $r['refund_percentage'], 2); ?>%</td>
                        <td>INR <?php echo number_format((float) $r['refund_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars((string) $r['refund_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) (($r['paid_at'] ?? '') !== '' ? $r['paid_at'] : '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $r['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php render_admin_module_page_end(); ?>
