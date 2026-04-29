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
        if ($action === 'create_checklist') {
            $id = create_checklist(
                (int) $user['id'],
                (int) ($_POST['booking_id'] ?? 0)
            );
            $message = 'Checklist created. ID: ' . $id;
        } elseif ($action === 'add_checklist_item') {
            $id = add_checklist_item((int) ($_POST['checklist_id'] ?? 0), (string) ($_POST['task_title'] ?? ''), (string) ($_POST['due_date'] ?? ''));
            $message = 'Checklist item added. ID: ' . $id;
        } elseif ($action === 'update_item') {
            update_checklist_item_status((int) ($_POST['item_id'] ?? 0), (string) ($_POST['item_status'] ?? 'PENDING'));
            $message = 'Checklist item status updated.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$bookingsStmt = $pdo->prepare(
    'SELECT b.id, s.title
     FROM bookings b
     INNER JOIN services s ON s.id = b.service_id
     WHERE b.client_id = ? AND b.booking_status = "APPROVED"
     ORDER BY b.created_at DESC'
);
$bookingsStmt->execute([$user['id']]);
$bookings = $bookingsStmt->fetchAll();

$checklistsStmt = $pdo->prepare(
    'SELECT pc.id, pc.booking_id, s.title AS service_title,
            CONCAT("#", pc.booking_id, " - ", s.title) AS booking_label
     FROM planning_checklists pc
     INNER JOIN bookings b ON b.id = pc.booking_id
     INNER JOIN services s ON s.id = b.service_id
     WHERE b.client_id = ?
     ORDER BY pc.created_at DESC'
);
$checklistsStmt->execute([$user['id']]);
$checklists = $checklistsStmt->fetchAll();

$itemsStmt = $pdo->prepare(
    'SELECT ci.id, ci.task_title, ci.due_date, ci.item_status,
            CONCAT("#", b.id, " - ", s.title) AS booking_label
     FROM checklist_items ci
     INNER JOIN planning_checklists pc ON pc.id = ci.checklist_id
     INNER JOIN bookings b ON b.id = pc.booking_id
     INNER JOIN services s ON s.id = b.service_id
     WHERE b.client_id = ?
     ORDER BY ci.created_at DESC'
);
$itemsStmt->execute([$user['id']]);
$items = $itemsStmt->fetchAll();
?>
<?php render_client_module_page_start('Client Checklists'); ?>
    <section class="provider-hero">
        <h1 class="h4 mb-1">Planning Checklists</h1>
        <p class="mb-0">Manage event tasks and track progress.</p>
    </section>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <section class="provider-card">
        <h2 class="h5">Create Checklist</h2>
        <form method="post" class="row g-2 mb-3">
            <input type="hidden" name="action" value="create_checklist">
            <div class="col-md-6">
                <select class="form-select" name="booking_id" required>
                    <option value="">Select booking</option>
                    <?php foreach ($bookings as $b): ?>
                        <option value="<?php echo (int) $b['id']; ?>">#<?php echo (int) $b['id']; ?> - <?php echo htmlspecialchars((string) $b['title'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12"><button class="btn btn-primary w-100">Create Checklist</button></div>
        </form>

        <h2 class="h5">Add Task</h2>
        <form method="post" class="row g-2">
            <input type="hidden" name="action" value="add_checklist_item">
            <div class="col-md-6">
                <select class="form-select" name="checklist_id" required>
                    <option value="">Select checklist</option>
                    <?php foreach ($checklists as $c): ?>
                        <option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars((string) ($c['booking_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><input class="form-control" name="task_title" placeholder="Task title" required></div>
            <div class="col-md-2"><input class="form-control" type="date" name="due_date" required></div>
            <div class="col-12"><button class="btn btn-outline-success w-100">Add Task</button></div>
        </form>
    </section>

    <section class="provider-card">
        <h2 class="h5">Checklist Tracker</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>Booking</th><th>Task</th><th>Due</th><th>Status</th><th>Update</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) ($it['booking_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $it['task_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $it['due_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $it['item_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <form method="post" class="d-flex gap-2">
                                <input type="hidden" name="action" value="update_item">
                                <input type="hidden" name="item_id" value="<?php echo (int) $it['id']; ?>">
                                <select class="form-select form-select-sm" name="item_status">
                                    <option value="PENDING">PENDING</option>
                                    <option value="IN_PROGRESS">IN_PROGRESS</option>
                                    <option value="DONE">DONE</option>
                                </select>
                                <button class="btn btn-sm btn-primary">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php render_client_module_page_end(); ?>
