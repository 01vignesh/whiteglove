<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/partials/admin_module_layout.php';

$admin = require_role(['ADMIN']);
$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profileId = (int) ($_POST['profile_id'] ?? 0);
    $decision = strtoupper((string) ($_POST['decision'] ?? ''));

    try {
        if ($profileId <= 0 || !in_array($decision, ['APPROVED', 'REJECTED'], true)) {
            throw new RuntimeException('Invalid action.');
        }

        $currentStmt = $pdo->prepare('SELECT user_id, approval_status FROM provider_profiles WHERE id = ? LIMIT 1');
        $currentStmt->execute([$profileId]);
        $current = $currentStmt->fetch();
        if (!$current) {
            throw new RuntimeException('Provider profile not found.');
        }

        $stmt = $pdo->prepare('UPDATE provider_profiles SET approval_status = ? WHERE id = ?');
        $stmt->execute([$decision, $profileId]);
        if ($stmt->rowCount() > 0) {
            log_activity(
                (int) $admin['id'],
                'ADMIN',
                'provider_approval_decided',
                'provider_profile',
                $profileId,
                [
                    'provider_user_id' => (int) ($current['user_id'] ?? 0),
                    'from_status' => (string) ($current['approval_status'] ?? ''),
                    'to_status' => $decision,
                ]
            );
        }
        $message = 'Provider status updated to ' . $decision . '.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rows = $pdo->query(
    'SELECT pp.id, pp.user_id, u.name, u.email, pp.business_name, pp.city, pp.approval_status, pp.created_at
     FROM provider_profiles pp
     INNER JOIN users u ON u.id = pp.user_id
     ORDER BY FIELD(pp.approval_status, "PENDING", "REJECTED", "APPROVED"), pp.created_at DESC'
)->fetchAll();
?>
<?php render_admin_module_page_start('Admin Provider Approvals'); ?>
    <section class="provider-hero">
        <h1 class="h4 mb-1">Provider Approvals</h1>
        <p class="mb-0">Review partner onboarding and control listing quality on the platform.</p>
    </section>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <section class="provider-card">
        <h2 class="h5 mb-3">Approval Queue</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Business</th>
                    <th>City</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo (int) $row['id']; ?></td>
                        <td><?php echo htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $row['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['business_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['city'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $row['approval_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="profile_id" value="<?php echo (int) $row['id']; ?>">
                                <input type="hidden" name="decision" value="APPROVED">
                                <button type="submit" class="btn btn-sm btn-success">Approve</button>
                            </form>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="profile_id" value="<?php echo (int) $row['id']; ?>">
                                <input type="hidden" name="decision" value="REJECTED">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php render_admin_module_page_end(); ?>
