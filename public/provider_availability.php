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
            throw new RuntimeException('Your provider profile is not approved yet.');
        }
        set_service_availability(
            (int) ($_POST['service_id'] ?? 0),
            (string) ($_POST['slot_date'] ?? ''),
            (string) ($_POST['slot_status'] ?? 'AVAILABLE')
        );
        $message = 'Availability updated.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$serviceStmt = $pdo->prepare(
    'SELECT id, title
     FROM services
     WHERE provider_id = ? AND title NOT LIKE "Custom Bid Booking #%"
     ORDER BY created_at DESC'
);
$serviceStmt->execute([$user['id']]);
$services = $serviceStmt->fetchAll();

$slotsStmt = $pdo->prepare(
    'SELECT sa.id, sa.service_id, s.title, sa.slot_date, sa.slot_status
     FROM service_availability sa
     INNER JOIN services s ON s.id = sa.service_id
     WHERE s.provider_id = ?
     ORDER BY sa.slot_date DESC'
);
$slotsStmt->execute([$user['id']]);
$slots = $slotsStmt->fetchAll();
?>
<?php render_provider_page_start('Provider Availability'); ?>
    <div class="provider-hero">
        <h1 class="h4 mb-1">Provider Availability</h1>
        <p class="mb-0">Block or open dates per service.</p>
    </div>
    <?php if (!$isApproved): ?>
        <div class="alert alert-warning">Your provider account is pending approval. Availability update is locked.</div>
    <?php endif; ?>
    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="provider-card">
            <h2 class="h5">Set Availability</h2>
            <form method="post" class="row g-2">
                <div class="col-12">
                    <select class="form-select" name="service_id" required>
                        <option value="">Select Service</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?php echo (int) $s['id']; ?>"><?php echo htmlspecialchars($s['title'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><input class="form-control" type="date" name="slot_date" required></div>
                <div class="col-md-6">
                    <select class="form-select" name="slot_status">
                        <option value="AVAILABLE">AVAILABLE</option>
                        <option value="BLOCKED">BLOCKED</option>
                    </select>
                </div>
                <div class="col-12"><button class="btn btn-primary w-100" type="submit">Save Availability</button></div>
            </form>
    </section>

    <section class="provider-card">
            <h2 class="h5">Availability History</h2>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead><tr><th>Service</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($slots as $slot): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($slot['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($slot['slot_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($slot['slot_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    </section>
<?php render_provider_page_end(); ?>


