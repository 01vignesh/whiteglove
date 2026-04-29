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
        if ($action === 'submit_review') {
            $id = submit_review(
                (int) ($_POST['booking_id'] ?? 0),
                (int) $user['id'],
                (int) ($_POST['provider_id'] ?? 0),
                (int) ($_POST['rating'] ?? 5),
                (string) ($_POST['comment'] ?? '')
            );
            $message = 'Review submitted. ID: ' . $id;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$bookingsStmt = $pdo->prepare(
    'SELECT b.id, b.booking_status, s.title, s.provider_id, u.name AS provider_name
      FROM bookings b
      INNER JOIN services s ON s.id = b.service_id
      INNER JOIN users u ON u.id = s.provider_id
      LEFT JOIN reviews r ON r.booking_id = b.id AND r.client_id = b.client_id
      WHERE b.client_id = ?
       AND b.booking_status = "COMPLETED"
       AND r.id IS NULL
      ORDER BY b.created_at DESC'
);
$bookingsStmt->execute([$user['id']]);
$bookings = $bookingsStmt->fetchAll();

$reviewStmt = $pdo->prepare(
    'SELECT r.id, r.booking_id, r.rating, r.comment, r.created_at, u.name AS provider_name, s.title AS service_title
      FROM reviews r
      INNER JOIN users u ON u.id = r.provider_id
      INNER JOIN bookings b ON b.id = r.booking_id
      INNER JOIN services s ON s.id = b.service_id
      WHERE r.client_id = ?
      ORDER BY r.created_at DESC'
);
$reviewStmt->execute([$user['id']]);
$reviews = $reviewStmt->fetchAll();
?>
<?php render_client_module_page_start('Client Reviews'); ?>
    <section class="provider-hero">
        <h1 class="h4 mb-1">Verified Reviews</h1>
        <p class="mb-0">Submit and track reviews for completed bookings.</p>
    </section>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <section class="provider-card">
        <h2 class="h5">Submit Review</h2>
        <form method="post" class="row g-2 mb-3">
            <input type="hidden" name="action" value="submit_review">
            <input type="hidden" name="provider_id" id="provider_id_auto" value="">
            <div class="col-md-6">
                <select class="form-select" name="booking_id" id="booking_id_select" required>
                    <option value="">Select completed booking</option>
                    <?php foreach ($bookings as $b): ?>
                        <?php if ((string) $b['booking_status'] === 'COMPLETED'): ?>
                            <option
                                value="<?php echo (int) $b['id']; ?>"
                                data-provider-id="<?php echo (int) $b['provider_id']; ?>"
                                data-provider-name="<?php echo htmlspecialchars((string) $b['provider_name'], ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                #<?php echo (int) $b['id']; ?> - <?php echo htmlspecialchars((string) $b['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <input class="form-control" id="provider_name_auto" placeholder="Provider auto-selected from booking" readonly>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="rating">
                    <option value="5">5 stars</option>
                    <option value="4">4 stars</option>
                    <option value="3">3 stars</option>
                    <option value="2">2 stars</option>
                    <option value="1">1 star</option>
                </select>
            </div>
            <div class="col-md-9"><input class="form-control" name="comment" placeholder="Share your feedback" required></div>
            <div class="col-12"><button class="btn btn-primary w-100">Submit Review</button></div>
        </form>
    </section>

    <section class="provider-card">
        <h2 class="h5">My Reviews</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>Booking ID</th><th>Booking Name</th><th>Provider</th><th>Rating</th><th>Comment</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($reviews as $r): ?>
                    <tr>
                        <td>#<?php echo (int) $r['booking_id']; ?></td>
                        <td><?php echo htmlspecialchars((string) $r['service_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $r['provider_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) $r['rating']; ?>/5</td>
                        <td><?php echo htmlspecialchars((string) $r['comment'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $r['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <script>
        (function () {
            const bookingSelect = document.getElementById('booking_id_select');
            const providerIdInput = document.getElementById('provider_id_auto');
            const providerNameInput = document.getElementById('provider_name_auto');
            if (!bookingSelect || !providerIdInput || !providerNameInput) {
                return;
            }
            const updateProvider = () => {
                const opt = bookingSelect.options[bookingSelect.selectedIndex];
                if (!opt || !opt.value) {
                    providerIdInput.value = '';
                    providerNameInput.value = '';
                    return;
                }
                providerIdInput.value = opt.getAttribute('data-provider-id') || '';
                providerNameInput.value = opt.getAttribute('data-provider-name') || '';
            };
            bookingSelect.addEventListener('change', updateProvider);
            updateProvider();
        })();
    </script>
<?php render_client_module_page_end(); ?>
