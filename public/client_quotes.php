<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/partials/client_module_layout.php';

$user = require_role(['CLIENT']);
$pdo = db();
$message = '';
$error = '';

function quote_status_badge_class(string $status): string
{
    $upper = strtoupper($status);
    if ($upper === 'ACCEPTED') {
        return 'text-bg-success';
    }
    if ($upper === 'REJECTED') {
        return 'text-bg-danger';
    }
    if ($upper === 'SENT') {
        return 'text-bg-primary';
    }
    return 'text-bg-secondary';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'update_quote_status') {
            $quoteId = (int) ($_POST['quote_id'] ?? 0);
            $decision = strtoupper((string) ($_POST['quote_status'] ?? ''));
            if (!in_array($decision, ['ACCEPTED', 'REJECTED'], true)) {
                throw new RuntimeException('Invalid quote decision.');
            }

            $checkStmt = $pdo->prepare(
                'SELECT q.id, q.quote_status
                 FROM quotes q
                 INNER JOIN bookings b ON b.id = q.booking_id
                 WHERE q.id = ? AND b.client_id = ?
                 LIMIT 1'
            );
            $checkStmt->execute([$quoteId, (int) $user['id']]);
            $quote = $checkStmt->fetch();
            if (!$quote) {
                throw new RuntimeException('Quote not found.');
            }
            if ((string) $quote['quote_status'] !== 'SENT') {
                throw new RuntimeException('Only SENT quotes can be accepted or rejected.');
            }

            $updateStmt = $pdo->prepare('UPDATE quotes SET quote_status = ? WHERE id = ?');
            $updateStmt->execute([$decision, $quoteId]);
            $message = 'Quote updated to ' . $decision . '.';
            log_activity((int) $user['id'], 'CLIENT', 'quote_status_changed', 'quote', $quoteId, [
                'to' => $decision,
            ]);
            $providerStmt = $pdo->prepare(
                'SELECT s.provider_id, b.id AS booking_id
                 FROM quotes q
                 INNER JOIN bookings b ON b.id = q.booking_id
                 INNER JOIN services s ON s.id = b.service_id
                 WHERE q.id = ?
                 LIMIT 1'
            );
            $providerStmt->execute([$quoteId]);
            $provider = $providerStmt->fetch();
            if ($provider) {
                notify_safe(
                    (int) $provider['provider_id'],
                    'Quote Decision Received',
                    'Client marked quote #' . $quoteId . ' as ' . $decision . ' for booking #' . (int) $provider['booking_id'] . '.'
                );
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$quoteStmt = $pdo->prepare(
    'SELECT q.id, q.booking_id, q.subtotal, q.tax, q.discount, q.total, q.quote_status, q.created_at, s.title
     FROM quotes q
     INNER JOIN bookings b ON b.id = q.booking_id
     INNER JOIN services s ON s.id = b.service_id
     WHERE b.client_id = ?
     ORDER BY q.created_at DESC'
);
$quoteStmt->execute([$user['id']]);
$quotes = $quoteStmt->fetchAll();
?>
<?php render_client_module_page_start('Client Quotes'); ?>
    <section class="provider-hero">
        <h1 class="h4 mb-1">Quotes</h1>
        <p class="mb-0">Review provider quotations and accept or reject before invoice generation.</p>
    </section>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <section class="provider-card">
        <h2 class="h5">Quote Decisions</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>Quote</th><th>Service</th><th>Breakdown</th><th>Total</th><th>Status</th><th>Action</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($quotes as $q): ?>
                    <tr>
                        <td>#<?php echo (int) $q['id']; ?> / Booking #<?php echo (int) $q['booking_id']; ?></td>
                        <td><?php echo htmlspecialchars((string) $q['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            Sub: <?php echo number_format((float) $q['subtotal'], 2); ?> |
                            Tax: <?php echo number_format((float) $q['tax'], 2); ?> |
                            Disc: <?php echo number_format((float) $q['discount'], 2); ?>
                        </td>
                        <td><?php echo number_format((float) $q['total'], 2); ?></td>
                        <td><span class="badge <?php echo quote_status_badge_class((string) $q['quote_status']); ?>"><?php echo htmlspecialchars((string) $q['quote_status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td>
                            <?php if ((string) $q['quote_status'] === 'SENT'): ?>
                                <div class="d-flex gap-2">
                                    <form method="post">
                                        <input type="hidden" name="action" value="update_quote_status">
                                        <input type="hidden" name="quote_id" value="<?php echo (int) $q['id']; ?>">
                                        <input type="hidden" name="quote_status" value="ACCEPTED">
                                        <button class="btn btn-sm btn-success" type="submit">Accept</button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="action" value="update_quote_status">
                                        <input type="hidden" name="quote_id" value="<?php echo (int) $q['id']; ?>">
                                        <input type="hidden" name="quote_status" value="REJECTED">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Reject</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string) $q['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php render_client_module_page_end(); ?>
