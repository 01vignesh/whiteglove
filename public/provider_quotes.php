<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/partials/provider_layout.php';

$user = require_role(['PROVIDER']);
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

$approval = $pdo->prepare('SELECT approval_status FROM provider_profiles WHERE user_id = ? LIMIT 1');
$approval->execute([$user['id']]);
$profile = $approval->fetch();
$isApproved = $profile && $profile['approval_status'] === 'APPROVED';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$isApproved) {
            throw new RuntimeException('Provider profile is pending approval. Quote creation is locked.');
        }
        $bookingId = (int) ($_POST['booking_id'] ?? 0);
        $ownBooking = $pdo->prepare(
            'SELECT b.id, b.booking_status
             FROM bookings b
             INNER JOIN services s ON s.id = b.service_id
             WHERE b.id = ? AND s.provider_id = ?'
        );
        $ownBooking->execute([$bookingId, $user['id']]);
        $booking = $ownBooking->fetch();
        if (!$booking) {
            throw new RuntimeException('You can only quote on your own bookings.');
        }
        if ((string) $booking['booking_status'] !== 'APPROVED') {
            throw new RuntimeException('Quote can be created only for APPROVED bookings.');
        }

        $id = create_quote(
            $bookingId,
            (float) ($_POST['subtotal'] ?? 0),
            (float) ($_POST['tax'] ?? 0),
            (float) ($_POST['discount'] ?? 0)
        );
        $message = 'Quote created successfully. ID: ' . $id;
        log_activity((int) $user['id'], 'PROVIDER', 'quote_created', 'quote', $id, [
            'booking_id' => $bookingId,
            'subtotal' => (float) ($_POST['subtotal'] ?? 0),
            'tax' => (float) ($_POST['tax'] ?? 0),
            'discount' => (float) ($_POST['discount'] ?? 0),
        ]);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$bookingsStmt = $pdo->prepare(
    'SELECT b.id, b.estimated_budget, b.booking_status, u.name AS client_name, s.title
     FROM bookings b
     INNER JOIN services s ON s.id = b.service_id
     INNER JOIN users u ON u.id = b.client_id
     LEFT JOIN quotes aq ON aq.booking_id = b.id AND aq.quote_status = "ACCEPTED"
     WHERE s.provider_id = ? AND b.booking_status = "APPROVED" AND aq.id IS NULL
     ORDER BY b.created_at DESC'
);
$bookingsStmt->execute([$user['id']]);
$myBookings = $bookingsStmt->fetchAll();

$quotesStmt = $pdo->prepare(
    'SELECT q.id, q.booking_id, q.total, q.quote_status, q.created_at, s.title
     FROM quotes q
     INNER JOIN bookings b ON b.id = q.booking_id
     INNER JOIN services s ON s.id = b.service_id
     WHERE s.provider_id = ?
     ORDER BY q.created_at DESC'
);
$quotesStmt->execute([$user['id']]);
$myQuotes = $quotesStmt->fetchAll();
?>
<?php render_provider_page_start('Provider Quotes'); ?>
    <div class="provider-hero">
        <h1 class="h4 mb-1">Provider Quotes</h1>
        <p class="mb-0">Create quotes for your bookings and monitor statuses.</p>
    </div>
    <?php if (!$isApproved): ?>
        <div class="alert alert-warning">Your provider account is pending approval. Quote creation is locked.</div>
    <?php endif; ?>
    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="provider-card">
            <h2 class="h5">Create Quote</h2>
            <form method="post" class="row g-2">
                <div class="col-12">
                    <select class="form-select" name="booking_id" id="quote-booking-id" required>
                        <option value="">Select booking</option>
                        <?php foreach ($myBookings as $b): ?>
                            <option value="<?php echo (int) $b['id']; ?>" data-budget="<?php echo htmlspecialchars((string) $b['estimated_budget'], ENT_QUOTES, 'UTF-8'); ?>">
                                #<?php echo (int) $b['id']; ?> - <?php echo htmlspecialchars($b['title'] . ' / ' . $b['client_name'] . ' / ' . $b['booking_status'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><input class="form-control" type="number" step="0.01" name="subtotal" id="quote-subtotal" placeholder="Subtotal" required></div>
                <div class="col-md-4"><input class="form-control" type="number" step="0.01" name="tax" id="quote-tax" placeholder="Tax" required></div>
                <div class="col-md-4"><input class="form-control" type="number" step="0.01" name="discount" id="quote-discount" placeholder="Discount" required></div>
                <div class="col-12">
                    <small id="quote-budget-hint" class="text-muted">Final amount must be less than or equal to booking budget.</small>
                </div>
                <div class="col-12"><button class="btn btn-primary w-100">Create Quote</button></div>
            </form>
    </section>

    <section class="provider-card">
            <h2 class="h5">My Quotes</h2>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead><tr><th>Quote</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($myQuotes as $q): ?>
                        <tr>
                            <td>#<?php echo (int) $q['id']; ?> / Booking #<?php echo (int) $q['booking_id']; ?></td>
                            <td><?php echo number_format((float) $q['total'], 2); ?></td>
                            <td><span class="badge <?php echo quote_status_badge_class((string) $q['quote_status']); ?>"><?php echo htmlspecialchars($q['quote_status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo htmlspecialchars($q['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    </section>
    <script>
        (function () {
            const bookingSelect = document.getElementById('quote-booking-id');
            const subtotalInput = document.getElementById('quote-subtotal');
            const taxInput = document.getElementById('quote-tax');
            const discountInput = document.getElementById('quote-discount');
            const hint = document.getElementById('quote-budget-hint');

            if (!bookingSelect || !subtotalInput || !taxInput || !discountInput || !hint) {
                return;
            }

            function toNumber(value) {
                const n = parseFloat(value);
                return isNaN(n) ? 0 : n;
            }

            function updateHint() {
                const selected = bookingSelect.options[bookingSelect.selectedIndex];
                const budgetRaw = selected ? selected.getAttribute('data-budget') : '';
                const budget = toNumber(budgetRaw);
                const total = Math.max(0, toNumber(subtotalInput.value) + toNumber(taxInput.value) - toNumber(discountInput.value));

                if (budget > 0) {
                    hint.textContent = 'Final: ' + total.toFixed(2) + ' | Booking Budget: ' + budget.toFixed(2) + ' (final must be <= budget)';
                    hint.style.color = total > budget ? '#b3261e' : '#1f7a3f';
                    subtotalInput.placeholder = 'Subtotal (budget: ' + budget.toFixed(2) + ')';
                } else {
                    hint.textContent = 'Final amount must be less than or equal to booking budget.';
                    hint.style.color = '';
                    subtotalInput.placeholder = 'Subtotal';
                }
            }

            bookingSelect.addEventListener('change', updateHint);
            subtotalInput.addEventListener('input', updateHint);
            taxInput.addEventListener('input', updateHint);
            discountInput.addEventListener('input', updateHint);
            updateHint();
        })();
    </script>
<?php render_provider_page_end(); ?>


