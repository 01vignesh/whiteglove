<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/partials/client_module_layout.php';

$user = require_role(['CLIENT']);
?>
<?php render_client_module_page_start('Client Hub', '/WhiteGlove/public/dashboard.php', 'Dashboard'); ?>
    <section class="provider-hero">
        <h1 class="h4 mb-1">Client Hub</h1>
        <p class="mb-0">Access all client workflows from dedicated modules for streamlined planning, booking, and service coordination.</p>
    </section>

    <section class="provider-grid">
        <div class="provider-card"><a href="/WhiteGlove/public/client_profile.php"><h2>Profile</h2><p>Update account details and profile picture</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/client_bookings.php"><h2>Booking Center</h2><p>Browse services and create bookings</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/client_bids.php"><h2>Bids</h2><p>Create requests, compare and award bids</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/client_milestones.php"><h2>Milestones</h2><p>Track and pay milestone amounts</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/client_quotes.php"><h2>Quotes</h2><p>Review and accept/reject provider quotations</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/client_invoices.php"><h2>Invoices</h2><p>View invoice and payment status</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/client_checklists.php"><h2>Checklists</h2><p>Create and manage planning tasks</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/client_reviews.php"><h2>Reviews</h2><p>Submit and track verified reviews</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/client_notifications.php"><h2>Notifications</h2><p>Read and manage updates</p></a></div>
    </section>
<?php render_client_module_page_end(); ?>
