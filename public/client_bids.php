<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/partials/client_module_layout.php';

$user = require_role(['CLIENT']);
$pdo = db();
$message = '';
$error = '';

function bid_status_badge(string $status): string
{
    $upper = strtoupper($status);
    if ($upper === 'ACCEPTED' || $upper === 'AWARDED' || $upper === 'OPEN') {
        return 'text-bg-success';
    }
    if ($upper === 'REJECTED' || $upper === 'CLOSED') {
        return 'text-bg-danger';
    }
    return 'text-bg-secondary';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'create_bid_request') {
            $id = create_bid_request(
                (int) $user['id'],
                (string) ($_POST['event_type'] ?? ''),
                (string) ($_POST['city'] ?? ''),
                (float) ($_POST['budget'] ?? 0),
                (string) ($_POST['event_date'] ?? ''),
                (int) ($_POST['guest_count'] ?? 0)
            );
            $message = 'Bid request created. ID: ' . $id;
        } elseif ($action === 'award_bid') {
            award_bid((int) $user['id'], (int) ($_POST['bid_request_id'] ?? 0), (int) ($_POST['bid_id'] ?? 0));
            $message = 'Bid awarded successfully.';
        } elseif ($action === 'reject_bid') {
            reject_bid((int) $user['id'], (int) ($_POST['bid_request_id'] ?? 0), (int) ($_POST['bid_id'] ?? 0));
            $message = 'Bid rejected.';
        } elseif ($action === 'close_bid_request') {
            close_bid_request((int) $user['id'], (int) ($_POST['bid_request_id'] ?? 0));
            $message = 'Bid request closed.';
        } elseif ($action === 'convert_awarded_bid') {
            $bookingId = convert_awarded_bid_to_booking((int) $user['id'], (int) ($_POST['bid_request_id'] ?? 0));
            $message = 'Accepted bid converted to booking. Booking ID: ' . $bookingId;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$bidReqStmt = $pdo->prepare(
    'SELECT id, event_type, city, budget, event_date, guest_count, request_status
     FROM bid_requests
     WHERE client_id = ?
     ORDER BY created_at DESC'
);
$bidReqStmt->execute([$user['id']]);
$bidRequests = $bidReqStmt->fetchAll();

$selectedBidRequestId = (int) ($_GET['bid_request_id'] ?? ($_POST['selected_bid_request_id'] ?? 0));
$selectedBidRequestStatus = '';
$selectedBidRequest = null;
foreach ($bidRequests as $row) {
    if ((int) $row['id'] === $selectedBidRequestId) {
        $selectedBidRequestStatus = (string) $row['request_status'];
        $selectedBidRequest = $row;
        break;
    }
}

$comparedBids = [];
if ($selectedBidRequestId > 0) {
    $comparedBids = compare_bids($selectedBidRequestId);
}

$showDecisionColumn = false;
if ($selectedBidRequestStatus === 'OPEN') {
    foreach ($comparedBids as $bid) {
        if ((string) ($bid['bid_status'] ?? '') === 'SUBMITTED') {
            $showDecisionColumn = true;
            break;
        }
    }
}

$acceptedBid = null;
foreach ($comparedBids as $bid) {
    if ((string) ($bid['bid_status'] ?? '') === 'ACCEPTED') {
        $acceptedBid = $bid;
        break;
    }
}
?>
<?php render_client_module_page_start('Client Bids'); ?>
    <section class="provider-hero">
        <h1 class="h4 mb-1">Bid Workspace</h1>
        <p class="mb-0">Create requests, compare provider offers, and finalize decisions.</p>
    </section>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="provider-card">
        <h2 class="h5">Create Bid Request</h2>
        <form method="post" class="row g-2">
            <input type="hidden" name="action" value="create_bid_request">
            <div class="col-md-6"><input class="form-control" name="event_type" placeholder="Event Type" required></div>
            <div class="col-md-6"><input class="form-control" name="city" placeholder="City" required></div>
            <div class="col-md-6"><input class="form-control" type="number" step="0.01" name="budget" placeholder="Budget" required></div>
            <div class="col-md-6"><input class="form-control" type="date" name="event_date" required></div>
            <div class="col-md-6"><input class="form-control" type="number" name="guest_count" min="1" placeholder="Guest Count" required></div>
            <div class="col-12"><button class="btn btn-primary w-100">Publish Request</button></div>
        </form>
    </section>

    <section class="provider-card">
        <h2 class="h5">Compare Bids</h2>
        <form method="get" class="d-flex gap-2 mb-3" id="compare-bids-form">
            <select class="form-select" name="bid_request_id" id="bid-request-select">
                <option value="">Select request</option>
                <?php foreach ($bidRequests as $br): ?>
                    <option value="<?php echo (int) $br['id']; ?>" <?php echo $selectedBidRequestId === (int) $br['id'] ? 'selected' : ''; ?>>
                        #<?php echo (int) $br['id']; ?> - <?php echo htmlspecialchars((string) ($br['event_type'] . ' / ' . $br['city'] . ' / Guests: ' . (int) $br['guest_count'] . ' / ' . $br['request_status']), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($selectedBidRequestId > 0 && $selectedBidRequestStatus === 'OPEN'): ?>
            <form method="post" class="mb-3">
                <input type="hidden" name="action" value="close_bid_request">
                <input type="hidden" name="bid_request_id" value="<?php echo (int) $selectedBidRequestId; ?>">
                <input type="hidden" name="selected_bid_request_id" value="<?php echo (int) $selectedBidRequestId; ?>">
                <button class="btn btn-sm btn-outline-secondary">Close Request</button>
            </form>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                <tr>
                    <th>Provider</th><th>Price</th><th>Brief Proposal</th><th>Status</th>
                    <?php if ($showDecisionColumn): ?><th>Decision</th><?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($comparedBids as $b): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $b['provider_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo number_format((float) $b['quoted_price'], 2); ?></td>
                        <td><?php echo nl2br(htmlspecialchars((string) ($b['proposal'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></td>
                        <td><span class="badge <?php echo bid_status_badge((string) $b['bid_status']); ?>"><?php echo htmlspecialchars((string) $b['bid_status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <?php if ($showDecisionColumn): ?>
                            <td>
                                <?php if ((string) $b['bid_status'] === 'SUBMITTED'): ?>
                                    <div class="d-flex gap-2">
                                        <form method="post">
                                            <input type="hidden" name="action" value="award_bid">
                                            <input type="hidden" name="bid_request_id" value="<?php echo (int) $selectedBidRequestId; ?>">
                                            <input type="hidden" name="bid_id" value="<?php echo (int) $b['id']; ?>">
                                            <input type="hidden" name="selected_bid_request_id" value="<?php echo (int) $selectedBidRequestId; ?>">
                                            <button class="btn btn-sm btn-success">Accept & Award</button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="action" value="reject_bid">
                                            <input type="hidden" name="bid_request_id" value="<?php echo (int) $selectedBidRequestId; ?>">
                                            <input type="hidden" name="bid_id" value="<?php echo (int) $b['id']; ?>">
                                            <input type="hidden" name="selected_bid_request_id" value="<?php echo (int) $selectedBidRequestId; ?>">
                                            <button class="btn btn-sm btn-outline-danger">Reject</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($selectedBidRequestStatus === 'AWARDED' && is_array($selectedBidRequest) && is_array($acceptedBid)): ?>
        <section class="provider-card">
            <h2 class="h5">Convert Awarded Bid to Booking</h2>
            <p class="text-muted mb-3">All details below are fetched from the awarded bid and are read-only.</p>
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Event Type</label>
                    <input class="form-control" value="<?php echo htmlspecialchars((string) $selectedBidRequest['event_type'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">City</label>
                    <input class="form-control" value="<?php echo htmlspecialchars((string) $selectedBidRequest['city'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Event Date</label>
                    <input class="form-control" value="<?php echo htmlspecialchars((string) $selectedBidRequest['event_date'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Guest Count</label>
                    <input class="form-control" value="<?php echo (int) $selectedBidRequest['guest_count']; ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Accepted Provider</label>
                    <input class="form-control" value="<?php echo htmlspecialchars((string) $acceptedBid['provider_name'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Accepted Price</label>
                    <input class="form-control" value="<?php echo number_format((float) $acceptedBid['quoted_price'], 2); ?>" readonly>
                </div>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="convert_awarded_bid">
                <input type="hidden" name="bid_request_id" value="<?php echo (int) $selectedBidRequestId; ?>">
                <input type="hidden" name="selected_bid_request_id" value="<?php echo (int) $selectedBidRequestId; ?>">
                <button class="btn btn-primary" type="submit">Convert to Booking</button>
            </form>
        </section>
    <?php endif; ?>

    <script>
        (function () {
            const select = document.getElementById('bid-request-select');
            const form = document.getElementById('compare-bids-form');
            if (select && form) {
                select.addEventListener('change', function () { form.submit(); });
            }
        })();
    </script>
<?php render_client_module_page_end(); ?>
