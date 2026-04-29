<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/functions.php';

$user = require_auth();
$pdo = db();
$role = (string) ($user['role'] ?? '');
$serviceId = (int) ($_GET['id'] ?? 0);
$message = '';
$error = '';

if ($serviceId <= 0) {
    http_response_code(400);
    echo 'Invalid service id.';
    exit;
}

$serviceStmt = $pdo->prepare(
    'SELECT s.id, s.provider_id, s.title, s.city, s.event_type, s.description, s.base_price, s.status, s.created_at,
            u.name AS provider_name, pp.business_name
     FROM services s
     INNER JOIN users u ON u.id = s.provider_id
     LEFT JOIN provider_profiles pp ON pp.user_id = s.provider_id
     WHERE s.id = ?
     LIMIT 1'
);
$serviceStmt->execute([$serviceId]);
$service = $serviceStmt->fetch();

if (!$service) {
    http_response_code(404);
    echo 'Service not found.';
    exit;
}

$isOwner = $role === 'PROVIDER' && (int) $service['provider_id'] === (int) $user['id'];
$isClient = $role === 'CLIENT';
$isAdmin = $role === 'ADMIN';

$canView = $isAdmin || $isOwner || (string) $service['status'] === 'ACTIVE';
if (!$canView) {
    http_response_code(403);
    echo 'Forbidden: Service is inactive.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isClient) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create_booking') {
        try {
            $bookingId = create_booking(
                (int) $user['id'],
                (int) $service['id'],
                (string) ($_POST['event_date'] ?? ''),
                (int) ($_POST['guest_count'] ?? 0),
                (float) ($_POST['estimated_budget'] ?? 0)
            );
            $message = 'Booking created successfully. Booking ID: ' . $bookingId;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$imagesStmt = $pdo->prepare(
    'SELECT image_url
     FROM service_images
     WHERE service_id = ?
     ORDER BY sort_order ASC, id ASC'
);
$imagesStmt->execute([$serviceId]);
$images = $imagesStmt->fetchAll();

$availabilityStmt = $pdo->prepare(
    'SELECT slot_date, slot_status
     FROM service_availability
     WHERE service_id = ?
     ORDER BY slot_date ASC
     LIMIT 8'
);
$availabilityStmt->execute([$serviceId]);
$availability = $availabilityStmt->fetchAll();

$availableDatesStmt = $pdo->prepare(
    'SELECT slot_date
     FROM service_availability
     WHERE service_id = ? AND slot_status = "AVAILABLE" AND slot_date >= CURDATE()
     ORDER BY slot_date ASC
     LIMIT 60'
);
$availableDatesStmt->execute([$serviceId]);
$availableDates = $availableDatesStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Service Details | WhiteGlove</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        :root {
            --ink: #142233;
            --muted: #617388;
            --bg: #f4f8fb;
            --surface: #fff;
            --primary: #0c6e84;
            --border: #dde7ef;
            --shadow: 0 18px 40px rgba(10, 34, 52, 0.08);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Plus Jakarta Sans", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 15% -10%, #d8eef8 0, transparent 30%),
                radial-gradient(circle at 85% 0%, #fdf1d3 0, transparent 26%),
                var(--bg);
        }
        .nav-wrap {
            position: sticky;
            top: 0;
            z-index: 20;
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--border);
            background: rgba(244,248,251,.9);
        }
        .nav {
            max-width: 1150px;
            margin: 0 auto;
            padding: .85rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: .8rem;
        }
        .brand {
            font-family: "Space Grotesk", sans-serif;
            font-weight: 700;
            text-decoration: none;
            color: var(--ink);
        }
        .actions {
            display: flex;
            gap: .55rem;
            flex-wrap: wrap;
        }
        .btn {
            border: 0;
            border-radius: 999px;
            padding: .55rem .9rem;
            text-decoration: none;
            font-size: .84rem;
            font-weight: 700;
            display: inline-block;
            cursor: pointer;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-line { border: 1px solid #bdd0dd; color: #1f3f5a; background: #fff; }
        .btn-soft { background: #d9edf2; color: #08414e; }
        .btn-warn { background: #ffe0b0; color: #613700; }
        .btn-danger-line { border: 1px solid #efc0bd; color: #a33a33; background: #fff; }
        .alert {
            border-radius: 12px;
            border: 1px solid;
            padding: .58rem .7rem;
            font-size: .86rem;
            margin-bottom: .8rem;
        }
        .ok { color: #1f7a3f; background: #e9f7ee; border-color: #cde9d4; }
        .err { color: #b3261e; background: #fdecea; border-color: #f4c9c4; }
        .svc-wrap { width: min(1150px, 100%); margin: 0 auto; padding: 1rem; }
        .svc-hero {
            background: linear-gradient(135deg, #0f6f84, #13938d);
            color: #fff;
            border-radius: 22px;
            padding: 1.25rem;
            box-shadow: var(--shadow);
        }
        .svc-hero h1 {
            font-family: "Space Grotesk", sans-serif;
            margin: 0 0 .35rem;
            font-size: clamp(1.25rem, 2.5vw, 1.8rem);
        }
        .svc-hero p {
            margin: 0 0 .45rem;
            opacity: .95;
            font-size: .92rem;
        }
        .svc-grid {
            margin-top: 1rem;
            display: grid;
            grid-template-columns: 1.4fr .9fr;
            gap: 1rem;
        }
        .svc-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 1rem;
            box-shadow: var(--shadow);
        }
        .svc-card h2, .svc-card h3 {
            font-family: "Space Grotesk", sans-serif;
        }
        .svc-gallery {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .6rem;
        }
        .svc-gallery img {
            width: 100%;
            height: 132px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        .svc-empty {
            border: 1px dashed var(--border);
            border-radius: 12px;
            color: var(--muted);
            font-size: .85rem;
            text-align: center;
            padding: 1rem;
        }
        .svc-meta { display: grid; grid-template-columns: 1fr 1fr; gap: .55rem; }
        .svc-meta .chip {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: .55rem .65rem;
            background: #fcfdff;
            font-size: .86rem;
        }
        .svc-badge { display: inline-block; border-radius: 999px; padding: .28rem .6rem; font-size: .74rem; font-weight: 700; border: 1px solid transparent; }
        .svc-active { background: #e8f6ec; color: #1f7a3f; border-color: #cde9d4; }
        .svc-inactive { background: #f1f3f5; color: #5b6470; border-color: #d8dde3; }
        .provider-link {
            color: #ffffff;
            text-decoration: none;
            border-bottom: 1px dashed rgba(255,255,255,.55);
        }
        .provider-link:hover {
            color: #f9f3dd;
            border-bottom-color: rgba(249,243,221,.85);
        }
        .svc-form {
            display: grid;
            gap: .5rem;
        }
        .svc-form input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: .58rem .7rem;
            font-size: .88rem;
        }
        .svc-select {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: .58rem .7rem;
            font-size: .88rem;
            background: #fff;
        }
        .svc-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .5rem;
        }
        .svc-list {
            list-style: none;
            margin: 0 0 1rem;
            padding: 0;
            border-top: 1px solid var(--border);
        }
        .svc-list li {
            border-bottom: 1px solid var(--border);
            padding: .5rem 0;
            display: flex;
            justify-content: space-between;
            gap: .5rem;
            font-size: .88rem;
        }
        @media (max-width: 900px) {
            .svc-grid { grid-template-columns: 1fr; }
            .svc-gallery { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 620px) {
            .svc-gallery { grid-template-columns: 1fr; }
            .svc-meta { grid-template-columns: 1fr; }
            .svc-form-grid { grid-template-columns: 1fr; }
        }
        .provider-footer {
            border-top: 1px solid var(--border);
            padding: .95rem 1rem;
            color: var(--muted);
            font-size: .82rem;
            margin-top: 1rem;
            background: rgba(255,255,255,.7);
        }
        .provider-foot {
            max-width: 1150px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            gap: .7rem;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
<header class="nav-wrap">
    <nav class="nav">
        <a class="brand" href="/WhiteGlove/public/index.php">WhiteGlove</a>
        <div class="actions">
            <a class="btn btn-line" href="/WhiteGlove/public/dashboard.php">Dashboard</a>
            <?php if ($role === 'PROVIDER'): ?>
                <a class="btn btn-soft" href="/WhiteGlove/public/provider_services.php">My Services</a>
            <?php elseif ($role === 'CLIENT'): ?>
                <a class="btn btn-soft" href="/WhiteGlove/public/client_bookings.php">Booking Center</a>
            <?php endif; ?>
            <a class="btn btn-warn" href="/WhiteGlove/public/logout.php">Logout</a>
        </div>
    </nav>
</header>

<main class="svc-wrap">
    <section class="svc-hero">
        <h1 class="h4 mb-1"><?php echo htmlspecialchars((string) $service['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="mb-2"><?php echo htmlspecialchars((string) $service['description'], ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="svc-badge <?php echo (string) $service['status'] === 'ACTIVE' ? 'svc-active' : 'svc-inactive'; ?>">
                <?php echo htmlspecialchars((string) $service['status'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <span class="small">
                Provider:
                <strong>
                    <a class="provider-link" href="/WhiteGlove/public/provider_details.php?id=<?php echo (int) $service['provider_id']; ?>">
                        <?php echo htmlspecialchars((string) ($service['business_name'] ?: $service['provider_name']), ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </strong>
            </span>
        </div>
    </section>

    <?php if ($message !== ''): ?>
        <div class="alert ok" style="margin-top:.85rem;"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert err" style="margin-top:.85rem;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="svc-grid">
        <div class="svc-card">
            <h2 class="h5 mb-3" style="margin-top:0;">Gallery</h2>
            <?php if (count($images) > 0): ?>
                <div class="svc-gallery">
                    <?php foreach ($images as $img): ?>
                        <img src="<?php echo htmlspecialchars((string) $img['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Service image">
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="svc-empty">No gallery images uploaded for this service yet.</div>
            <?php endif; ?>
        </div>

        <div class="svc-card">
            <h2 class="h5 mb-3" style="margin-top:0;">Service Overview</h2>
            <div class="svc-meta mb-3">
                <div class="chip"><strong>City:</strong> <?php echo htmlspecialchars((string) $service['city'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="chip"><strong>Event Type:</strong> <?php echo htmlspecialchars((string) $service['event_type'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="chip"><strong>Base Price:</strong> INR <?php echo number_format((float) $service['base_price'], 2); ?></div>
                <div class="chip"><strong>Created:</strong> <?php echo htmlspecialchars((string) $service['created_at'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>

            <h3 class="h6">Upcoming Availability</h3>
            <?php if (count($availability) > 0): ?>
                <ul class="svc-list">
                    <?php foreach ($availability as $slot): ?>
                        <li>
                            <span><?php echo htmlspecialchars((string) $slot['slot_date'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><?php echo htmlspecialchars((string) $slot['slot_status'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-muted small">No availability slots published yet.</p>
            <?php endif; ?>

            <?php if ($isClient && (string) $service['status'] === 'ACTIVE'): ?>
                <h3 class="h6 mt-3">Quick Book</h3>
                <?php if (count($availableDates) > 0): ?>
                    <form method="post" class="svc-form">
                        <input type="hidden" name="action" value="create_booking">
                        <?php
                        $availableDateStrings = array_map(static fn(array $row): string => (string) $row['slot_date'], $availableDates);
                        $datesCsv = implode(',', $availableDateStrings);
                        $minDate = count($availableDateStrings) > 0 ? $availableDateStrings[0] : '';
                        $maxDate = count($availableDateStrings) > 0 ? $availableDateStrings[count($availableDateStrings) - 1] : '';
                        ?>
                        <input
                            type="date"
                            name="event_date"
                            class="svc-select available-date-picker"
                            required
                            <?php echo $minDate !== '' ? 'min="' . htmlspecialchars($minDate, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>
                            <?php echo $maxDate !== '' ? 'max="' . htmlspecialchars($maxDate, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>
                            data-available-dates="<?php echo htmlspecialchars($datesCsv, ENT_QUOTES, 'UTF-8'); ?>"
                            readonly
                        >
                        <div class="svc-form-grid">
                            <input type="number" name="guest_count" placeholder="Guests" required>
                            <input
                                type="number"
                                step="0.01"
                                min="<?php echo htmlspecialchars((string) $service['base_price'], ENT_QUOTES, 'UTF-8'); ?>"
                                name="estimated_budget"
                                placeholder="Budget (min <?php echo number_format((float) $service['base_price'], 2); ?>)"
                                required
                            >
                        </div>
                        <button class="btn btn-primary">Create Booking</button>
                    </form>
                <?php else: ?>
                    <p class="text-muted small">No available dates published yet for this service.</p>
                <?php endif; ?>
            <?php elseif ($isOwner): ?>
                <a class="btn btn-line" style="margin-top:.5rem;" href="/WhiteGlove/public/provider_services.php?edit_id=<?php echo (int) $service['id']; ?>">Edit This Service</a>
            <?php endif; ?>
        </div>
    </section>
</main>
<footer class="provider-footer">
    <div class="provider-foot">
        <span>&copy; <?php echo date('Y'); ?> WhiteGlove. All rights reserved.</span>
        <span>Client Experience Suite</span>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    (function () {
        const input = document.querySelector('.available-date-picker');
        if (!input) {
            return;
        }
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
    })();
</script>
</body>
</html>
