<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/partials/admin_module_layout.php';

require_role(['ADMIN']);
?>
<?php render_admin_module_page_start('Admin Hub'); ?>
    <section class="provider-hero">
        <h1 class="h4 mb-1">Admin Hub</h1>
        <p class="mb-0">Central control for platform operations, quality, bookings, and finance simulation.</p>
    </section>

    <section class="provider-grid">
        <div class="provider-card"><a href="/WhiteGlove/public/admin_profile.php"><h2>Profile</h2><p>Update admin account, image, and security settings</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/admin_providers.php"><h2>Provider Approvals</h2><p>Review and approve partner accounts</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/admin_users.php"><h2>User Management</h2><p>View user roles and account status</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/admin_bookings.php"><h2>Booking Oversight</h2><p>Track booking lifecycle and outcomes</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/admin_payments.php"><h2>Payments & Refunds</h2><p>Monitor transactions and refund updates</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/admin_analytics.php"><h2>Analytics Center</h2><p>KPIs, trends, and risk indicators</p></a></div>
        <div class="provider-card"><a href="/WhiteGlove/public/admin_reports.php"><h2>Reports</h2><p>Service, review, and notification reports</p></a></div>
    </section>
<?php render_admin_module_page_end(); ?>
