<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/partials/admin_module_layout.php';

require_role(['ADMIN']);
$pdo = db();

$q = trim((string) ($_GET['q'] ?? ''));
$status = strtoupper(trim((string) ($_GET['status'] ?? '')));
$city = trim((string) ($_GET['city'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(CAST(b.id AS CHAR) LIKE ? OR c.name LIKE ? OR p.name LIKE ? OR b.event_type LIKE ? OR b.city LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($status !== '' && in_array($status, ['PENDING', 'APPROVED', 'REJECTED', 'COMPLETED', 'CANCELLED'], true)) {
    $where[] = 'b.booking_status = ?';
    $params[] = $status;
}

if ($city !== '') {
    $where[] = 'b.city LIKE ?';
    $params[] = '%' . $city . '%';
}

if ($dateFrom !== '') {
    $where[] = 'b.event_date >= ?';
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 'b.event_date <= ?';
    $params[] = $dateTo;
}

$sql =
    'SELECT b.id, c.name AS client_name, p.name AS provider_name, b.event_type, b.city, b.event_date, b.guest_count, b.estimated_budget, b.booking_status, b.created_at
     FROM bookings b
     INNER JOIN users c ON c.id = b.client_id
     INNER JOIN services s ON s.id = b.service_id
     INNER JOIN users p ON p.id = s.provider_id';

if (count($where) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY b.created_at DESC LIMIT 300';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<?php render_admin_module_page_start('Admin Booking Oversight'); ?>
    <section class="provider-hero">
        <h1 class="h4 mb-1">Booking Oversight</h1>
        <p class="mb-0">Monitor booking lifecycle, event demand, and booking quality across providers.</p>
    </section>

    <section class="provider-card">
        <h2 class="h5 mb-3">Booking Filters</h2>
        <form method="get" class="row g-2 mb-3">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input class="form-control" type="text" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="ID, client, provider, event, city">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All</option>
                    <?php foreach (['PENDING', 'APPROVED', 'REJECTED', 'COMPLETED', 'CANCELLED'] as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo ($status === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">City</label>
                <input class="form-control" type="text" name="city" value="<?php echo htmlspecialchars($city, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Mumbai">
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input class="form-control" type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input class="form-control" type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-1 d-flex align-items-end gap-2">
                <button class="btn btn-primary w-100" type="submit">Apply</button>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <p class="mb-0 text-muted small">Showing <?php echo count($rows); ?> result(s).</p>
            <a class="btn btn-sm btn-outline-secondary" href="/WhiteGlove/public/admin_bookings.php">Reset</a>
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>ID</th><th>Client</th><th>Provider</th><th>Event</th><th>City</th><th>Date</th><th>Guests</th><th>Budget</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>#<?php echo (int) $row['id']; ?></td>
                        <td><?php echo htmlspecialchars((string) $row['client_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $row['provider_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $row['event_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $row['city'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $row['event_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) $row['guest_count']; ?></td>
                        <td>INR <?php echo number_format((float) $row['estimated_budget'], 2); ?></td>
                        <td><?php echo htmlspecialchars((string) $row['booking_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php render_admin_module_page_end(); ?>
