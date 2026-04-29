<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/partials/client_booking_layout.php';

$user = require_role(['CLIENT']);
$pdo = db();
$message = '';
$error = '';

function status_badge_class(string $status): string
{
    $upper = strtoupper($status);

    switch ($upper) {
        case 'PENDING':
        case 'REQUESTED':
            return 'status-pending';

        case 'APPROVED':
            return 'status-approved';

        case 'REJECTED':
            return 'status-rejected';

        case 'COMPLETED':
            return 'status-completed';

        case 'CANCELLED':
            return 'status-cancelled';

        default:
            return 'status-open';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_booking') {
        try {
            $bookingId = create_booking(
                (int) $user['id'],
                (int) ($_POST['service_id'] ?? 0),
                (string) ($_POST['event_date'] ?? ''),
                (int) ($_POST['guest_count'] ?? 0),
                (float) ($_POST['estimated_budget'] ?? 0)
            );
            $message = 'Booking created successfully. Booking ID: ' . $bookingId;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    if ($action === 'request_refund') {
        try {
            $refundId = request_refund((int) ($_POST['booking_id'] ?? 0), (string) ($_POST['reason'] ?? 'No reason provided.'));
            $message = 'Refund requested successfully. Request ID: ' . $refundId;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    if ($action === 'request_cancellation') {
        try {
            $requestId = request_cancellation(
                (int) ($_POST['booking_id'] ?? 0),
                (int) $user['id'],
                (string) ($_POST['reason'] ?? 'Client requested cancellation')
            );
            $message = 'Cancellation request submitted. Request ID: ' . $requestId;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

}

$filters = [
    'city' => trim((string) ($_GET['city'] ?? '')),
    'event_type' => trim((string) ($_GET['event_type'] ?? '')),
    'max_price' => trim((string) ($_GET['max_price'] ?? '')),
];

$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 9;
$whereSql = ' WHERE s.status = "ACTIVE"';
$params = [];

if ($filters['city'] !== '') {
    $whereSql .= ' AND s.city = ?';
    $params[] = $filters['city'];
}
if ($filters['event_type'] !== '') {
    $whereSql .= ' AND s.event_type = ?';
    $params[] = $filters['event_type'];
}
if ($filters['max_price'] !== '' && is_numeric($filters['max_price'])) {
    $whereSql .= ' AND s.base_price <= ?';
    $params[] = (float) $filters['max_price'];
}

$countSql = 'SELECT COUNT(*) FROM services s' . $whereSql;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalServices = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalServices / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $perPage;

$sql = 'SELECT s.id, s.title, s.city, s.event_type, s.description, s.base_price, u.name AS provider_name,
               (SELECT si.image_url
                FROM service_images si
                WHERE si.service_id = s.id
                ORDER BY si.sort_order ASC, si.id ASC
                LIMIT 1) AS cover_image,
               (SELECT COUNT(*) FROM service_images sc WHERE sc.service_id = s.id) AS image_count
        FROM services s
        INNER JOIN users u ON u.id = s.provider_id' .
        $whereSql .
        ' ORDER BY s.created_at DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();

$availableDatesByService = [];
if (count($services) > 0) {
    $serviceIds = array_map(static fn(array $row): int => (int) $row['id'], $services);
    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
    $availabilitySql =
        'SELECT service_id, slot_date
         FROM service_availability
         WHERE slot_status = "AVAILABLE" AND slot_date >= CURDATE() AND service_id IN (' . $placeholders . ')
         ORDER BY slot_date ASC';
    $availabilityStmt = $pdo->prepare($availabilitySql);
    $availabilityStmt->execute($serviceIds);
    $availabilityRows = $availabilityStmt->fetchAll();

    foreach ($availabilityRows as $row) {
        $sid = (int) $row['service_id'];
        if (!isset($availableDatesByService[$sid])) {
            $availableDatesByService[$sid] = [];
        }
        $availableDatesByService[$sid][] = (string) $row['slot_date'];
    }
}

$bookingStmt = $pdo->prepare(
    'SELECT b.id, b.event_date, b.guest_count, b.estimated_budget, b.booking_status, s.title, u.name AS provider_name,
            cr.id AS cancellation_request_id, cr.request_status AS cancellation_status,
            rr.id AS refund_request_id, rr.refund_status, rr.refund_percentage, rr.refund_amount, rr.paid_at AS refund_paid_at
      FROM bookings b
      INNER JOIN services s ON s.id = b.service_id
      INNER JOIN users u ON u.id = s.provider_id
      LEFT JOIN (
          SELECT c1.booking_id, c1.id, c1.request_status
          FROM cancellation_requests c1
          INNER JOIN (
              SELECT booking_id, MAX(id) AS latest_id
              FROM cancellation_requests
              GROUP BY booking_id
          ) c2 ON c2.latest_id = c1.id
      ) cr ON cr.booking_id = b.id
      LEFT JOIN (
          SELECT r1.booking_id, r1.id, r1.refund_status, r1.refund_percentage, r1.refund_amount, r1.paid_at
          FROM refund_requests r1
          INNER JOIN (
              SELECT booking_id, MAX(id) AS latest_id
              FROM refund_requests
              GROUP BY booking_id
          ) r2 ON r2.latest_id = r1.id
      ) rr ON rr.booking_id = b.id
      WHERE b.client_id = ?
      ORDER BY b.created_at DESC'
);
$bookingStmt->execute([$user['id']]);
$bookings = $bookingStmt->fetchAll();

$paginationQuery = [
    'city' => $filters['city'],
    'event_type' => $filters['event_type'],
    'max_price' => $filters['max_price'],
];
?>
<?php render_client_booking_page_start('Client Booking Center'); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <section class="hero">
        <h1>Client Booking Center</h1>
        <p>Discover services, create bookings quickly, and manage refunds all in one place</p>
    </section>

    <section class="timeline">
        <div class="step active">Request</div>
        <div class="step active">Provider Review</div>
        <div class="step">Milestone Payments</div>
        <div class="step">Event Day</div>
        <div class="step">Review / Refund</div>
    </section>

    <div class="head">
        <h2>Service Discovery & Booking</h2>
        <div class="actions">
            <a class="btn btn-soft" href="/WhiteGlove/public/client_hub.php">Open Client Hub</a>
            <a class="btn btn-line" href="/WhiteGlove/public/dashboard.php">Back to Dashboard</a>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert ok"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="panel">
        <h3>Browse Services</h3>
        <form method="get" class="filter-grid">
            <div class="col-4"><input name="city" placeholder="City" value="<?php echo htmlspecialchars($filters['city'], ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="col-4"><input name="event_type" placeholder="Event Type" value="<?php echo htmlspecialchars($filters['event_type'], ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="col-3"><input name="max_price" placeholder="Max Price" value="<?php echo htmlspecialchars($filters['max_price'], ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="col-1"><button class="btn btn-primary" style="width:100%;" type="submit">Filter</button></div>
        </form>

        <div class="service-grid">
            <?php if (count($services) === 0): ?>
                <div class="service-empty">No services found for selected filters.</div>
            <?php endif; ?>
            <?php foreach ($services as $svc): ?>
                <article class="service-card-item">
                    <div class="service-card-media">
                        <?php if ((string) ($svc['cover_image'] ?? '') !== ''): ?>
                            <img class="service-thumb" src="<?php echo htmlspecialchars((string) $svc['cover_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="Service image">
                        <?php else: ?>
                            <div class="service-thumb service-thumb-placeholder">No image</div>
                        <?php endif; ?>
                    </div>
                    <div class="service-card-body">
                        <div class="service-title">
                            <a href="/WhiteGlove/public/service_view.php?id=<?php echo (int) $svc['id']; ?>"><?php echo htmlspecialchars($svc['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                        </div>
                        <div class="service-card-meta">
                            <span><strong>Provider:</strong> <?php echo htmlspecialchars($svc['provider_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><strong>City:</strong> <?php echo htmlspecialchars($svc['city'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><strong>Type:</strong> <?php echo htmlspecialchars($svc['event_type'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><strong>Price:</strong> INR <?php echo number_format((float) $svc['base_price'], 2); ?></span>
                            <span><strong>Gallery:</strong> <?php echo (int) ($svc['image_count'] ?? 0); ?> image(s)</span>
                        </div>
                        <?php $svcDates = $availableDatesByService[(int) $svc['id']] ?? []; ?>
                        <form method="post" class="book-form">
                            <input type="hidden" name="action" value="create_booking">
                            <input type="hidden" name="service_id" value="<?php echo (int) $svc['id']; ?>">
                            <?php
                            $datesCsv = implode(',', $svcDates);
                            $minDate = count($svcDates) > 0 ? $svcDates[0] : '';
                            $maxDate = count($svcDates) > 0 ? $svcDates[count($svcDates) - 1] : '';
                            ?>
                            <input
                                class="full available-date-picker"
                                type="date"
                                name="event_date"
                                required
                                <?php echo $minDate !== '' ? 'min="' . htmlspecialchars($minDate, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>
                                <?php echo $maxDate !== '' ? 'max="' . htmlspecialchars($maxDate, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>
                                data-available-dates="<?php echo htmlspecialchars($datesCsv, ENT_QUOTES, 'UTF-8'); ?>"
                                readonly
                            >
                            <input type="number" name="guest_count" placeholder="Guests" required>
                            <input
                                type="number"
                                step="0.01"
                                min="<?php echo htmlspecialchars((string) $svc['base_price'], ENT_QUOTES, 'UTF-8'); ?>"
                                name="estimated_budget"
                                placeholder="Budget (min <?php echo number_format((float) $svc['base_price'], 2); ?>)"
                                required
                            >
                            <button class="btn btn-primary full" type="submit" <?php echo count($svcDates) === 0 ? 'disabled' : ''; ?>>Book</button>
                            <?php if (count($svcDates) === 0): ?>
                                <small class="full" style="color:#9a3412;">No available dates published.</small>
                            <?php endif; ?>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Service pages">
                <?php
                $prevQuery = $paginationQuery;
                $prevQuery['page'] = max(1, $currentPage - 1);
                $nextQuery = $paginationQuery;
                $nextQuery['page'] = min($totalPages, $currentPage + 1);
                ?>
                <a
                    class="page-btn <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>"
                    href="<?php echo $currentPage > 1 ? '?' . htmlspecialchars(http_build_query($prevQuery), ENT_QUOTES, 'UTF-8') : '#'; ?>"
                >Previous</a>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php $pageQuery = $paginationQuery; $pageQuery['page'] = $p; ?>
                    <a
                        class="page-btn <?php echo $p === $currentPage ? 'active' : ''; ?>"
                        href="?<?php echo htmlspecialchars(http_build_query($pageQuery), ENT_QUOTES, 'UTF-8'); ?>"
                    ><?php echo $p; ?></a>
                <?php endfor; ?>
                <a
                    class="page-btn <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>"
                    href="<?php echo $currentPage < $totalPages ? '?' . htmlspecialchars(http_build_query($nextQuery), ENT_QUOTES, 'UTF-8') : '#'; ?>"
                >Next</a>
            </nav>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h3>My Bookings</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Service</th><th>Provider</th><th>Date</th><th>Status</th><th>Budget</th><th>Cancellation</th><th>Refund</th></tr></thead>
                <tbody>
                <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td><?php echo (int) $b['id']; ?></td>
                        <td><?php echo htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($b['provider_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($b['event_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <span class="badge <?php echo status_badge_class((string) $b['booking_status']); ?>">
                                <?php echo htmlspecialchars($b['booking_status'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td><?php echo number_format((float) $b['estimated_budget'], 2); ?></td>
                        <td>
                            <?php
                            $bookingStatus = (string) $b['booking_status'];
                            $cancellationStatus = (string) ($b['cancellation_status'] ?? '');
                            ?>
                            <?php if ($bookingStatus === 'APPROVED' && $cancellationStatus === ''): ?>
                                <form method="post" class="book-form">
                                    <input type="hidden" name="action" value="request_cancellation">
                                    <input type="hidden" name="booking_id" value="<?php echo (int) $b['id']; ?>">
                                    <textarea class="full" name="reason" placeholder="Enter cancellation reason" rows="3" required maxlength="500"></textarea>
                                    <button class="btn btn-danger-line full" type="submit">Request Cancellation</button>
                                </form>
                            <?php elseif ($cancellationStatus !== ''): ?>
                                <span class="badge <?php echo status_badge_class($cancellationStatus); ?>">
                                    <?php echo htmlspecialchars($cancellationStatus, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            <?php elseif ($bookingStatus === 'COMPLETED'): ?>
                                <small style="color:#617388;">Not available after completion</small>
                            <?php else: ?>
                                <small style="color:#617388;">-</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) ($b['refund_request_id'] ?? 0) > 0): ?>
                                <div style="font-size:0.78rem;color:#617388;">Request Status:</div>
                                <span class="badge <?php echo status_badge_class((string) $b['refund_status']); ?>">
                                    <?php echo htmlspecialchars((string) $b['refund_status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <div style="font-size:0.78rem;color:#617388;">
                                    Approved: INR <?php echo number_format((float) ($b['refund_amount'] ?? 0), 2); ?>
                                </div>
                                <div style="font-size:0.78rem;color:#617388;">
                                    Paid Date: <?php echo htmlspecialchars((string) (($b['refund_paid_at'] ?? '') !== '' ? $b['refund_paid_at'] : '-'), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php else: ?>
                                <small style="color:#617388;">Not initiated</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        (function () {
            const dateInputs = document.querySelectorAll('.available-date-picker');
            dateInputs.forEach((input) => {
                const dates = (input.dataset.availableDates || '')
                    .split(',')
                    .map((d) => d.trim())
                    .filter((d) => d.length > 0);
                const allowed = new Set(dates);
                if (dates.length > 0 && typeof flatpickr !== 'undefined') {
                    flatpickr(input, {
                        dateFormat: 'Y-m-d',
                        altInput: true,
                        altFormat: 'd M Y',
                        enable: dates,
                        allowInput: false,
                        disableMobile: true,
                        onReady: function (_selectedDates, _dateStr, instance) {
                            if (instance.altInput) {
                                instance.altInput.placeholder = 'Select available date';
                            }
                        },
                    });
                }
                const form = input.closest('form');
                const validate = () => {
                    if (!input.value) {
                        input.setCustomValidity('Please select an available date.');
                        return false;
                    }
                    if (!allowed.has(input.value)) {
                        input.setCustomValidity('Please choose a date marked available by provider.');
                        return false;
                    }
                    input.setCustomValidity('');
                    return true;
                };
                input.addEventListener('change', validate);
                input.addEventListener('input', validate);
                if (form) {
                    form.addEventListener('submit', (event) => {
                        if (!validate()) {
                            event.preventDefault();
                            input.reportValidity();
                        }
                    });
                }
            });
        })();
    </script>
<?php render_client_booking_page_end('Client Experience Suite'); ?>
