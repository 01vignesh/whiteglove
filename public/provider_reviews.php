<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/partials/provider_layout.php';

$user = require_role(['PROVIDER']);
$pdo = db();

$summaryStmt = $pdo->prepare(
    'SELECT COUNT(*) AS total_reviews, AVG(rating) AS avg_rating
     FROM reviews
     WHERE provider_id = ?'
);
$summaryStmt->execute([(int) $user['id']]);
$summary = $summaryStmt->fetch() ?: ['total_reviews' => 0, 'avg_rating' => null];

$reviewsStmt = $pdo->prepare(
    'SELECT r.id, r.booking_id, r.rating, r.comment, r.created_at, c.name AS client_name, s.title AS service_title
     FROM reviews r
     INNER JOIN users c ON c.id = r.client_id
     INNER JOIN bookings b ON b.id = r.booking_id
     INNER JOIN services s ON s.id = b.service_id
     WHERE r.provider_id = ?
     ORDER BY r.created_at DESC'
);
$reviewsStmt->execute([(int) $user['id']]);
$reviews = $reviewsStmt->fetchAll();

$avgRating = $summary['avg_rating'] !== null ? number_format((float) $summary['avg_rating'], 2) : '0.00';
?>
<?php render_provider_page_start('Provider Reviews', '/WhiteGlove/public/provider_hub.php', 'Provider Hub'); ?>
    <section class="provider-hero">
        <h1 class="h4 mb-1">Client Reviews</h1>
        <p class="mb-0">See what clients are saying about your completed bookings.</p>
    </section>

    <section class="provider-grid">
        <article class="provider-card">
            <h2>Total Reviews</h2>
            <p class="mb-0"><?php echo (int) ($summary['total_reviews'] ?? 0); ?></p>
        </article>
        <article class="provider-card">
            <h2>Average Rating</h2>
            <p class="mb-0"><?php echo htmlspecialchars($avgRating, ENT_QUOTES, 'UTF-8'); ?> / 5</p>
        </article>
    </section>

    <section class="provider-card">
        <h2 class="h5">All Reviews</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr><th>Booking</th><th>Service</th><th>Client</th><th>Rating</th><th>Comment</th><th>Date</th></tr>
                </thead>
                <tbody>
                <?php if (count($reviews) === 0): ?>
                    <tr><td colspan="6" class="text-muted">No client reviews yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                        <tr>
                            <td>#<?php echo (int) $r['booking_id']; ?></td>
                            <td><?php echo htmlspecialchars((string) $r['service_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $r['client_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) $r['rating']; ?>/5</td>
                            <td><?php echo htmlspecialchars((string) $r['comment'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $r['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php render_provider_page_end(); ?>

