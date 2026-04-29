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
            throw new RuntimeException('Provider profile is pending approval. Invoice generation is locked.');
        }
        $quoteId = (int) ($_POST['quote_id'] ?? 0);
        $ownQuote = $pdo->prepare(
            'SELECT q.id, q.quote_status
             FROM quotes q
             INNER JOIN bookings b ON b.id = q.booking_id
             INNER JOIN services s ON s.id = b.service_id
             WHERE q.id = ? AND s.provider_id = ?'
        );
        $ownQuote->execute([$quoteId, $user['id']]);
        $quote = $ownQuote->fetch();
        if (!$quote) {
            throw new RuntimeException('You can only generate invoice for your own quote.');
        }
        if ((string) $quote['quote_status'] !== 'ACCEPTED') {
            throw new RuntimeException('Invoice can be generated only for ACCEPTED quotes.');
        }
        $id = generate_invoice($quoteId);
        $message = 'Invoice generated successfully. ID: ' . $id;
        log_activity((int) $user['id'], 'PROVIDER', 'invoice_generated', 'invoice', $id, [
            'quote_id' => $quoteId,
        ]);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$quotesStmt = $pdo->prepare(
    'SELECT q.id, q.booking_id, q.quote_status
     FROM quotes q
     INNER JOIN bookings b ON b.id = q.booking_id
     INNER JOIN services s ON s.id = b.service_id
     LEFT JOIN invoices i ON i.quote_id = q.id
     WHERE s.provider_id = ? AND q.quote_status = "ACCEPTED" AND i.id IS NULL
     ORDER BY q.created_at DESC'
);
$quotesStmt->execute([$user['id']]);
$myQuotes = $quotesStmt->fetchAll();

$invoicesStmt = $pdo->prepare(
    'SELECT i.id, i.invoice_no, i.booking_id, i.total_amount, i.invoice_status, i.created_at
     FROM invoices i
     INNER JOIN bookings b ON b.id = i.booking_id
     INNER JOIN services s ON s.id = b.service_id
     WHERE s.provider_id = ?
     ORDER BY i.created_at DESC'
);
$invoicesStmt->execute([$user['id']]);
$myInvoices = $invoicesStmt->fetchAll();
?>
<?php render_provider_page_start('Provider Invoices'); ?>
    <div class="provider-hero">
        <h1 class="h4 mb-1">Provider Invoices</h1>
        <p class="mb-0">Generate invoices from your quotes and track invoice statuses.</p>
    </div>
    <?php if (!$isApproved): ?>
        <div class="alert alert-warning">Your provider account is pending approval. Invoice generation is locked.</div>
    <?php endif; ?>
    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="provider-card">
            <h2 class="h5">Generate Invoice</h2>
            <form method="post" class="row g-2">
                <div class="col-12">
                    <select class="form-select" name="quote_id" required>
                        <option value="">Select quote</option>
                        <?php foreach ($myQuotes as $q): ?>
                            <option value="<?php echo (int) $q['id']; ?>">
                                Quote #<?php echo (int) $q['id']; ?> - Booking #<?php echo (int) $q['booking_id']; ?> (<?php echo htmlspecialchars($q['quote_status'], ENT_QUOTES, 'UTF-8'); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12"><button class="btn btn-primary w-100">Generate Invoice</button></div>
            </form>
    </section>

    <section class="provider-card">
            <h2 class="h5">My Invoices</h2>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead><tr><th>Invoice</th><th>Booking</th><th>Amount</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($myInvoices as $i): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($i['invoice_no'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>#<?php echo (int) $i['booking_id']; ?></td>
                            <td><?php echo number_format((float) $i['total_amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($i['invoice_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($i['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a class="btn btn-sm btn-outline-primary" href="/WhiteGlove/public/invoice_view.php?id=<?php echo (int) $i['id']; ?>">View</a>
                                    <a class="btn btn-sm btn-outline-secondary" href="/WhiteGlove/public/invoice_view.php?id=<?php echo (int) $i['id']; ?>&download=1">Download</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    </section>
<?php render_provider_page_end(); ?>


