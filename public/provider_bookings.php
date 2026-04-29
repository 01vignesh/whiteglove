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
            throw new RuntimeException('Your provider profile is not approved yet.');
        }
        $action = (string) ($_POST['action'] ?? 'update_booking_status');
        if ($action === 'decide_cancellation_request') {
            $requestId = (int) ($_POST['cancellation_request_id'] ?? 0);
            $decision = strtoupper((string) ($_POST['decision'] ?? ''));
            $providerNote = (string) ($_POST['provider_note'] ?? '');
            $result = provider_decide_cancellation_request($requestId, (int) $user['id'], $decision, $providerNote);
            if ($decision === 'APPROVED') {
                $message = 'Cancellation approved for booking #' . (int) $result['booking_id'] .
                    '. Refund #' . (int) ($result['refund_id'] ?? 0) . ' initiated.';
            } else {
                $message = 'Cancellation request rejected.';
            }
        } else {
            $bookingId = (int) ($_POST['booking_id'] ?? 0);
            $status = strtoupper((string) ($_POST['status'] ?? ''));
            if (!in_array($status, ['APPROVED', 'REJECTED', 'COMPLETED'], true)) {
                throw new RuntimeException('Invalid booking status.');
            }

            $currentStmt = $pdo->prepare(
                'SELECT b.booking_status
                 FROM bookings b
                 INNER JOIN services s ON s.id = b.service_id
                 WHERE b.id = ? AND s.provider_id = ?
                 LIMIT 1'
            );
            $currentStmt->execute([$bookingId, $user['id']]);
            $current = $currentStmt->fetch();
            if (!$current) {
                throw new RuntimeException('Booking not found.');
            }

            $from = (string) $current['booking_status'];
            $allowedTransitions = [
                'PENDING' => ['APPROVED', 'REJECTED'],
                'APPROVED' => ['COMPLETED'],
                'REJECTED' => [],
                'COMPLETED' => [],
                'CANCELLED' => [],
            ];
            $allowedNext = $allowedTransitions[$from] ?? [];
            if (!in_array($status, $allowedNext, true)) {
                throw new RuntimeException('Invalid booking transition: ' . $from . ' -> ' . $status . '.');
            }

            $stmt = $pdo->prepare(
                'UPDATE bookings b
                 INNER JOIN services s ON s.id = b.service_id
                 SET b.booking_status = ?
                 WHERE b.id = ? AND s.provider_id = ?'
            );
            $stmt->execute([$status, $bookingId, $user['id']]);
            $message = 'Booking updated to ' . $status . '.';
            log_activity((int) $user['id'], 'PROVIDER', 'booking_status_changed', 'booking', $bookingId, [
                'from' => $from,
                'to' => $status,
            ]);
            $clientStmt = $pdo->prepare('SELECT client_id FROM bookings WHERE id = ? LIMIT 1');
            $clientStmt->execute([$bookingId]);
            $client = $clientStmt->fetch();
            if ($client) {
                notify_safe(
                    (int) $client['client_id'],
                    'Booking Status Updated',
                    'Your booking #' . $bookingId . ' status changed to ' . $status . '.'
                );
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$bookingStmt = $pdo->prepare(
    'SELECT b.id, b.event_date, b.booking_status, b.estimated_budget, b.guest_count, u.name AS client_name, s.title
     FROM bookings b
     INNER JOIN services s ON s.id = b.service_id
     INNER JOIN users u ON u.id = b.client_id
     WHERE s.provider_id = ?
     ORDER BY b.created_at DESC'
);
$bookingStmt->execute([$user['id']]);
$bookings = $bookingStmt->fetchAll();

$cancellationStmt = $pdo->prepare(
    'SELECT cr.id, cr.booking_id, cr.reason, cr.request_status, cr.created_at, cr.resolved_at, cr.provider_note,
            b.event_date, b.estimated_budget, u.name AS client_name, s.title AS service_title
     FROM cancellation_requests cr
     INNER JOIN bookings b ON b.id = cr.booking_id
     INNER JOIN services s ON s.id = b.service_id
     INNER JOIN users u ON u.id = cr.client_id
     WHERE cr.provider_id = ?
     ORDER BY cr.created_at DESC'
);
$cancellationStmt->execute([(int) $user['id']]);
$cancellationRequests = $cancellationStmt->fetchAll();
?>
<?php render_provider_page_start('Provider Bookings'); ?>
    <div class="provider-hero">
        <h1 class="h4 mb-1">Provider Bookings</h1>
        <p class="mb-0">Review and update booking status.</p>
    </div>
    <?php if (!$isApproved): ?>
        <div class="alert alert-warning">Your provider account is pending approval. Booking decisions are locked.</div>
    <?php endif; ?>
    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="provider-card">
            <h2 class="h5">Booking Decisions</h2>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead><tr><th>ID</th><th>Client</th><th>Service</th><th>Date</th><th>Guests</th><th>Budget</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><?php echo (int) $b['id']; ?></td>
                            <td><?php echo htmlspecialchars($b['client_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($b['event_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) $b['guest_count']; ?></td>
                            <td><?php echo number_format((float) $b['estimated_budget'], 2); ?></td>
                            <td><?php echo htmlspecialchars($b['booking_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <form method="post" class="d-flex gap-1">
                                    <input type="hidden" name="action" value="update_booking_status">
                                    <input type="hidden" name="booking_id" value="<?php echo (int) $b['id']; ?>">
                                    <select class="form-select form-select-sm" name="status">
                                        <?php
                                            $currentStatus = (string) $b['booking_status'];
                                            $options = [];
                                            if ($currentStatus === 'PENDING') {
                                                $options = ['APPROVED', 'REJECTED'];
                                            } elseif ($currentStatus === 'APPROVED') {
                                                $options = ['COMPLETED'];
                                            }
                                        ?>
                                        <?php foreach ($options as $opt): ?>
                                            <option value="<?php echo $opt; ?>"><?php echo $opt; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-success" type="submit" <?php echo count($options) === 0 ? 'disabled' : ''; ?>>Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    </section>

    <section class="provider-card">
            <h2 class="h5">Cancellation Requests</h2>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead><tr><th>Request</th><th>Booking</th><th>Client</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php if (count($cancellationRequests) === 0): ?>
                        <tr><td colspan="6" class="text-muted">No cancellation requests yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($cancellationRequests as $r): ?>
                        <tr>
                            <td>#<?php echo (int) $r['id']; ?><br><small class="text-muted"><?php echo htmlspecialchars((string) $r['created_at'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                            <td>#<?php echo (int) $r['booking_id']; ?><br><small class="text-muted"><?php echo htmlspecialchars((string) $r['service_title'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                            <td><?php echo htmlspecialchars((string) $r['client_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $r['reason'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $r['request_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php if ((string) $r['request_status'] === 'REQUESTED'): ?>
                                    <form method="post" class="d-flex gap-1">
                                        <input type="hidden" name="action" value="decide_cancellation_request">
                                        <input type="hidden" name="cancellation_request_id" value="<?php echo (int) $r['id']; ?>">
                                        <select class="form-select form-select-sm" name="decision" required>
                                            <option value="APPROVED">APPROVE</option>
                                            <option value="REJECTED">REJECT</option>
                                        </select>
                                        <button class="btn btn-sm btn-primary" type="submit">Save</button>
                                    </form>
                                <?php else: ?>
                                    <small class="text-muted"><?php echo htmlspecialchars((string) ($r['resolved_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
    </section>
<?php render_provider_page_end(); ?>


