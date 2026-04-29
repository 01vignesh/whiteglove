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
            throw new RuntimeException('Provider profile is pending approval. Bidding is locked.');
        }
        $id = submit_bid(
            (int) ($_POST['bid_request_id'] ?? 0),
            (int) $user['id'],
            (float) ($_POST['quoted_price'] ?? 0),
            (string) ($_POST['proposal'] ?? '')
        );
        $message = 'Bid submitted successfully. ID: ' . $id;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$openBidRequests = $pdo->query(
    'SELECT br.id, br.event_type, br.city, br.budget, br.event_date, br.guest_count, u.name AS client_name
     FROM bid_requests br
     INNER JOIN users u ON u.id = br.client_id
     WHERE br.request_status = "OPEN"
     ORDER BY br.created_at DESC'
)->fetchAll();

$myBidsStmt = $pdo->prepare(
    'SELECT b.id, b.bid_request_id, b.quoted_price, b.bid_status, b.created_at,
            br.event_type, br.city, br.budget, br.event_date, br.guest_count
     FROM bids b
     INNER JOIN bid_requests br ON br.id = b.bid_request_id
     WHERE b.provider_id = ?
     ORDER BY b.created_at DESC'
);
$myBidsStmt->execute([$user['id']]);
$myBids = $myBidsStmt->fetchAll();
?>
<?php render_provider_page_start('Provider Bids'); ?>
    <div class="provider-hero">
        <h1 class="h4 mb-1">Provider Bids</h1>
        <p class="mb-0">Submit proposals on open client requests and monitor bid status.</p>
    </div>
    <?php if (!$isApproved): ?>
        <div class="alert alert-warning">Your provider account is pending approval. Bid submission is locked.</div>
    <?php endif; ?>
    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="provider-card">
            <h2 class="h5">Submit Bid</h2>
            <form method="post" class="row g-2">
                <div class="col-12">
                    <select class="form-select" name="bid_request_id" required>
                        <option value="">Select open request</option>
                        <?php foreach ($openBidRequests as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>" data-budget="<?php echo htmlspecialchars((string) $r['budget'], ENT_QUOTES, 'UTF-8'); ?>">
                                #<?php echo (int) $r['id']; ?> - <?php echo htmlspecialchars(
                                    $r['event_type'] . ' / ' . $r['city'] .
                                    ' / Budget: ' . number_format((float) $r['budget'], 2) .
                                    ' / Date: ' . $r['event_date'] .
                                    ' / Guests: ' . (int) $r['guest_count'] .
                                    ' / ' . $r['client_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12"><input class="form-control" type="number" step="0.01" name="quoted_price" id="quoted-price-input" placeholder="Quoted price" required></div>
                <div class="col-12"><textarea class="form-control" name="proposal" rows="3" placeholder="Brief proposal" required></textarea></div>
                <div class="col-12"><button class="btn btn-primary w-100">Submit Bid</button></div>
            </form>
    </section>

    <section class="provider-card">
            <h2 class="h5">My Bids</h2>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead><tr><th>ID</th><th>Request</th><th>Client Budget</th><th>Event Date</th><th>Guests</th><th>Your Price</th><th>Status</th><th>Bid Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($myBids as $b): ?>
                        <tr>
                            <td><?php echo (int) $b['id']; ?></td>
                            <td>#<?php echo (int) $b['bid_request_id']; ?> - <?php echo htmlspecialchars($b['event_type'] . ' / ' . $b['city'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo number_format((float) $b['budget'], 2); ?></td>
                            <td><?php echo htmlspecialchars((string) $b['event_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) $b['guest_count']; ?></td>
                            <td><?php echo number_format((float) $b['quoted_price'], 2); ?></td>
                            <td><?php echo htmlspecialchars($b['bid_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($b['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    </section>
    <script>
        (function () {
            const requestSelect = document.querySelector('select[name="bid_request_id"]');
            const quotedInput = document.getElementById('quoted-price-input');
            if (!requestSelect || !quotedInput) {
                return;
            }

            function updateQuotedPlaceholder() {
                const selected = requestSelect.options[requestSelect.selectedIndex];
                const budgetRaw = selected ? selected.getAttribute('data-budget') : '';
                const budget = budgetRaw ? parseFloat(budgetRaw) : NaN;
                if (!isNaN(budget)) {
                    quotedInput.placeholder = 'Quoted price (max: ' + budget.toFixed(2) + ')';
                } else {
                    quotedInput.placeholder = 'Quoted price';
                }
            }

            requestSelect.addEventListener('change', updateQuotedPlaceholder);
            updateQuotedPlaceholder();
        })();
    </script>
<?php render_provider_page_end(); ?>


