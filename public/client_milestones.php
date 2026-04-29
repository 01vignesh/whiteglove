<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/partials/client_module_layout.php';

$user = require_role(['CLIENT']);
$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'pay_milestone') {
            $milestoneId = (int) ($_POST['milestone_id'] ?? 0);
            $ref = 'SIM-CL-' . date('YmdHis');
            $txnId = simulate_payment($milestoneId, $ref);
            $message = 'Milestone payment simulated. Transaction ID: ' . $txnId;
            log_activity((int) $user['id'], 'CLIENT', 'milestone_paid', 'transaction', $txnId, [
                'milestone_id' => $milestoneId,
                'reference_no' => $ref,
            ]);
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$milestonesStmt = $pdo->prepare(
    'SELECT pm.id, pm.booking_id, pm.milestone_name, pm.amount, pm.due_date, pm.milestone_status, s.title
     FROM payment_milestones pm
     INNER JOIN bookings b ON b.id = pm.booking_id
     INNER JOIN services s ON s.id = b.service_id
     WHERE b.client_id = ?
     ORDER BY pm.id DESC'
);
$milestonesStmt->execute([$user['id']]);
$milestones = $milestonesStmt->fetchAll();
?>
<?php render_client_module_page_start('Client Milestones'); ?>
    <section class="provider-hero">
        <h1 class="h4 mb-1">Milestone Payments</h1>
        <p class="mb-0">Track payment stages and simulate milestone transactions.</p>
    </section>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <section class="provider-card">
        <h2 class="h5">Payment Ledger</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>Booking</th><th>Milestone</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($milestones as $m): ?>
                    <tr>
                        <td>#<?php echo (int) $m['booking_id']; ?> - <?php echo htmlspecialchars((string) $m['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $m['milestone_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo number_format((float) $m['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars((string) $m['due_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $m['milestone_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php if ((string) $m['milestone_status'] !== 'PAID'): ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="pay_milestone">
                                    <input type="hidden" name="milestone_id" value="<?php echo (int) $m['id']; ?>">
                                    <button class="btn btn-sm btn-success">Pay Now</button>
                                </form>
                            <?php else: ?>
                                <span class="badge text-bg-success">Paid</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php render_client_module_page_end(); ?>
