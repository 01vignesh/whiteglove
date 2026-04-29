<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/partials/provider_layout.php';

$user = require_role(['PROVIDER']);
$pdo = db();

$approval = $pdo->prepare('SELECT approval_status, business_name, profile_image_url FROM provider_profiles WHERE user_id = ? LIMIT 1');
$approval->execute([$user['id']]);
$profile = $approval->fetch();
$isApproved = $profile && $profile['approval_status'] === 'APPROVED';
?>
<?php render_provider_page_start('Provider Hub', '/WhiteGlove/public/dashboard.php', 'Dashboard'); ?>
    <section class="provider-hero">
        <h1 class="h4 mb-1">Provider Hub</h1>
        <p class="mb-0">Access and manage all provider workflows</p>
        <?php if ($profile && (string) ($profile['business_name'] ?? '') !== ''): ?>
            <p class="mb-0 mt-1">Business: <strong><?php echo htmlspecialchars((string) $profile['business_name'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
        <?php endif; ?>
        <?php if ($profile && (string) ($profile['profile_image_url'] ?? '') !== ''): ?>
            <div class="provider-avatar-wrap mt-2">
                <img class="provider-avatar" src="<?php echo htmlspecialchars((string) $profile['profile_image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Provider profile picture">
            </div>
        <?php endif; ?>
    </section>

    <?php if (!$isApproved): ?>
        <div class="alert alert-warning">Your provider profile is pending admin approval. Some actions remain locked until approved.</div>
    <?php endif; ?>

    <section class="provider-grid">
        <div class="provider-card"><a href="/WhiteGlove/public/provider_profile.php"><h2>Profile</h2><p>Business name, city, description</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/provider_services.php"><h2>Services</h2><p>Create and manage listings</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/provider_availability.php"><h2>Availability</h2><p>Block and open service dates</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/provider_bookings.php"><h2>Bookings</h2><p>Approve, reject, complete</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/provider_payments.php"><h2>Payments</h2><p>Track milestone payments, refunds and transaction status</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/provider_bids.php"><h2>Bids</h2><p>Submit and track proposals</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/provider_quotes.php"><h2>Quotes</h2><p>Create and monitor quotes</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/provider_invoices.php"><h2>Invoices</h2><p>Generate and review invoices</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/provider_notifications.php"><h2>Notifications</h2><p>Send updates to clients</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/provider_reviews.php"><h2>Reviews</h2><p>View client ratings and feedback</p></a></div>
    </section>
<?php render_provider_page_end(); ?>
