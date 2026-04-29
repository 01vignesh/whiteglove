<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';

$user = require_auth();
$pdo = db();

function metric(PDO $pdo, string $sql, array $params = [], string $field = 'value'): string
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    if (!$row) {
        return '0';
    }
    return (string) ($row[$field] ?? '0');
}

function status_badge_class(string $status): string
{
    switch (strtoupper($status)) {
        case 'PENDING':
            return 'status-pending';
        case 'APPROVED':
            return 'status-approved';
        case 'REJECTED':
            return 'status-rejected';
        case 'COMPLETED':
            return 'status-completed';
        case 'CANCELLED':
            return 'status-cancelled';
        case 'OPEN':
            return 'status-open';
        default:
            return 'status-open';
    }
}

$role = $user['role'];
$profileImageUrl = '';
$avatarStmt = $pdo->prepare('SELECT profile_image_url FROM users WHERE id = ? LIMIT 1');
$avatarStmt->execute([(int) $user['id']]);
$avatarRow = $avatarStmt->fetch();
$profileImageUrl = (string) ($avatarRow['profile_image_url'] ?? '');
if ($role === 'PROVIDER' && $profileImageUrl === '') {
    $providerAvatarStmt = $pdo->prepare('SELECT profile_image_url FROM provider_profiles WHERE user_id = ? LIMIT 1');
    $providerAvatarStmt->execute([(int) $user['id']]);
    $providerAvatarRow = $providerAvatarStmt->fetch();
    $profileImageUrl = (string) ($providerAvatarRow['profile_image_url'] ?? '');
}
$cards = [];
$tableRows = [];
$tableTitle = '';

if ($role === 'ADMIN') {
    $cards = [
        ['Total Users', metric($pdo, 'SELECT COUNT(*) AS value FROM users')],
        ['Total Bookings', metric($pdo, 'SELECT COUNT(*) AS value FROM bookings')],
        ['Cancelled Bookings', metric($pdo, 'SELECT COUNT(*) AS value FROM bookings WHERE booking_status = "CANCELLED"')],
        ['Pending Provider Approvals', metric($pdo, 'SELECT COUNT(*) AS value FROM provider_profiles WHERE approval_status = "PENDING"')],
        ['Revenue', 'INR ' . number_format((float) metric($pdo, 'SELECT COALESCE(SUM(amount), 0) AS value FROM transactions WHERE payment_status = "SUCCESS"'), 2)],
    ];

    $tableTitle = 'Latest Provider Approval Queue';
    $stmt = $pdo->query(
        'SELECT pp.id, u.name, pp.business_name, pp.city, pp.approval_status
         FROM provider_profiles pp
         INNER JOIN users u ON u.id = pp.user_id
         ORDER BY pp.created_at DESC
         LIMIT 8'
    );
    $tableRows = $stmt->fetchAll();
} elseif ($role === 'CLIENT') {
    $cards = [
        ['My Bookings', metric($pdo, 'SELECT COUNT(*) AS value FROM bookings WHERE client_id = ?', [$user['id']])],
        ['Pending Bookings', metric($pdo, 'SELECT COUNT(*) AS value FROM bookings WHERE client_id = ? AND booking_status = "PENDING"', [$user['id']])],
        ['Completed Bookings', metric($pdo, 'SELECT COUNT(*) AS value FROM bookings WHERE client_id = ? AND booking_status = "COMPLETED"', [$user['id']])],
        ['Refund Requests', metric($pdo, 'SELECT COUNT(*) AS value FROM refund_requests rr INNER JOIN bookings b ON b.id = rr.booking_id WHERE b.client_id = ?', [$user['id']])],
    ];

    $tableTitle = 'Latest Services';
    $stmt = $pdo->prepare(
        'SELECT id, title, city, event_type, base_price
         FROM services
         WHERE status = "ACTIVE"
         ORDER BY created_at DESC
         LIMIT 8'
    );
    $stmt->execute();
    $tableRows = $stmt->fetchAll();
} else {
    $cards = [
        ['My Services', metric($pdo, 'SELECT COUNT(*) AS value FROM services WHERE provider_id = ? AND title NOT LIKE "Custom Bid Booking #%"', [$user['id']])],
        ['Pending Bookings', metric($pdo,
            'SELECT COUNT(*) AS value
             FROM bookings b
             INNER JOIN services s ON s.id = b.service_id
             WHERE s.provider_id = ? AND b.booking_status = "PENDING"',
            [$user['id']]
        )],
        ['Total Bids Submitted', metric($pdo, 'SELECT COUNT(*) AS value FROM bids WHERE provider_id = ?', [$user['id']])],
        ['Average Rating', number_format((float) metric($pdo, 'SELECT COALESCE(AVG(rating), 0) AS value FROM reviews WHERE provider_id = ?', [$user['id']]), 2)],
        ['Net Revenue', 'INR ' . number_format((float) metric($pdo,
            'SELECT
                (
                    COALESCE((
                        SELECT SUM(t.amount)
                        FROM transactions t
                        INNER JOIN bookings b1 ON b1.id = t.booking_id
                        INNER JOIN services s1 ON s1.id = b1.service_id
                        WHERE s1.provider_id = ? AND t.payment_status = "SUCCESS"
                    ), 0)
                    -
                    COALESCE((
                        SELECT SUM(rr.refund_amount)
                        FROM refund_requests rr
                        INNER JOIN bookings b2 ON b2.id = rr.booking_id
                        INNER JOIN services s2 ON s2.id = b2.service_id
                        WHERE s2.provider_id = ? AND rr.refund_status IN ("APPROVED", "PAID")
                    ), 0)
                ) AS value',
            [(int) $user['id'], (int) $user['id']]
        ), 2)],
    ];

    $tableTitle = 'My Services';
    $stmt = $pdo->prepare(
        'SELECT id, title, city, event_type, base_price, status
         FROM services
         WHERE provider_id = ? AND title NOT LIKE "Custom Bid Booking #%"
         ORDER BY created_at DESC
         LIMIT 8'
    );
    $stmt->execute([$user['id']]);
    $tableRows = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | WhiteGlove</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
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
            display: flex;
            flex-direction: column;
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
        }

        .btn-primary { background: var(--primary); color: #fff; }
        .btn-soft { background: #d9edf2; color: #08414e; }
        .btn-line { border: 1px solid #bdd0dd; color: #1f3f5a; background: #fff; }
        .btn-warn { background: #ffe0b0; color: #613700; }

        main {
            width: min(1150px, 100%);
            margin: 0 auto;
            padding: 1rem;
            flex: 1;
        }

        .hero {
            background: linear-gradient(140deg, #0f6f84 0%, #13938d 60%, #49a7a2 100%);
            color: #fff;
            border-radius: 24px;
            padding: 1.45rem;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: .8rem;
            flex-wrap: wrap;
        }

        .hero h1 {
            font-family: "Space Grotesk", sans-serif;
            margin: 0 0 .35rem;
            font-size: clamp(1.35rem, 2.4vw, 1.9rem);
        }

        .hero p { margin: 0; opacity: .95; font-size: .92rem; }

        .hero-user {
            display: flex;
            align-items: center;
            gap: .8rem;
        }

        .hero-avatar {
            width: 72px;
            height: 72px;
            border-radius: 999px;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,.9);
            box-shadow: 0 10px 20px rgba(20,34,51,.22);
            background: #fff;
        }

        .metric-grid {
            margin-top: 1rem;
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: .7rem;
        }

        .metric {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: .85rem;
            box-shadow: 0 8px 18px rgba(20,34,51,.05);
        }

        .metric small {
            display: block;
            color: var(--muted);
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .metric strong {
            display: block;
            margin-top: .3rem;
            font-family: "Space Grotesk", sans-serif;
            font-size: 1.2rem;
            line-height: 1.2;
        }

        .panel {
            margin-top: 1rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 1rem;
        }

        .head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: .7rem;
            flex-wrap: wrap;
            margin-bottom: .8rem;
        }

        .head h2 {
            margin: 0;
            font-family: "Space Grotesk", sans-serif;
            font-size: 1.15rem;
        }

        .table-wrap { overflow: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        th, td {
            padding: .66rem .55rem;
            border-bottom: 1px solid #edf0f4;
            text-align: left;
            vertical-align: middle;
            font-size: .86rem;
        }

        th {
            color: var(--muted);
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        tr:nth-child(odd) td { background: #fcfdff; }

        .badge {
            display: inline-flex;
            border-radius: 999px;
            padding: .24rem .58rem;
            font-size: .74rem;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .status-pending { background: #fff4e5; color: #a05a00; border-color: #f4ddbd; }
        .status-approved { background: #e8f6ec; color: #1f7a3f; border-color: #cde9d4; }
        .status-rejected { background: #fdecea; color: #b3261e; border-color: #f4c9c4; }
        .status-completed { background: #e8f1ff; color: #0f4b8a; border-color: #cddff7; }
        .status-cancelled { background: #f1f3f5; color: #5b6470; border-color: #d8dde3; }
        .status-open { background: #eef2ff; color: #3347a6; border-color: #d8defd; }

        footer {
            border-top: 1px solid var(--border);
            padding: .95rem 1rem;
            color: var(--muted);
            font-size: .82rem;
            margin-top: 1rem;
        }

        .foot {
            max-width: 1150px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            gap: .7rem;
            flex-wrap: wrap;
        }

        @media (max-width: 980px) {
            .metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 560px) {
            .metric-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header class="nav-wrap">
    <nav class="nav">
        <a class="brand" href="/WhiteGlove/public/index.php">WhiteGlove</a>
        <div class="actions">
            <a class="btn btn-line" href="/WhiteGlove/public/index.php">Home</a>
            <a class="btn btn-warn" href="/WhiteGlove/public/logout.php">Logout</a>
        </div>
    </nav>
</header>
<main>
    <section class="hero">
        <div class="hero-user">
            <?php if ($profileImageUrl !== ''): ?>
                <img class="hero-avatar" src="<?php echo htmlspecialchars($profileImageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile picture">
            <?php endif; ?>
            <div>
                <h1>Welcome, <?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <p>Role: <strong><?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?></strong> | Centralized dashboard for managing your operations</p>
            </div>
        </div>
        <div class="actions">
            <?php if ($role === 'ADMIN'): ?>
                <a class="btn btn-soft" href="/WhiteGlove/public/admin_hub.php">Admin Hub</a>
                <a class="btn btn-line" href="/WhiteGlove/public/admin_profile.php">Profile</a>
                <a class="btn btn-line" href="/WhiteGlove/public/admin_providers.php">Provider Approvals</a>
                <a class="btn btn-line" href="/WhiteGlove/public/admin_users.php">Users</a>
                <a class="btn btn-line" href="/WhiteGlove/public/admin_bookings.php">Bookings</a>
                <a class="btn btn-line" href="/WhiteGlove/public/admin_payments.php">Payments</a>
                <a class="btn btn-line" href="/WhiteGlove/public/admin_analytics.php">Analytics</a>
                <a class="btn btn-line" href="/WhiteGlove/public/admin_reports.php">Reports</a>
            <?php elseif ($role === 'CLIENT'): ?>
                <a class="btn btn-soft" href="/WhiteGlove/public/client_hub.php">Client Hub</a>
                <a class="btn btn-line" href="/WhiteGlove/public/client_profile.php">Profile</a>
                <a class="btn btn-line" href="/WhiteGlove/public/client_bookings.php">Booking Center</a>
                <a class="btn btn-line" href="/WhiteGlove/public/client_bids.php">Bids</a>
                <a class="btn btn-line" href="/WhiteGlove/public/client_milestones.php">Milestones</a>
                <a class="btn btn-line" href="/WhiteGlove/public/client_quotes.php">Quotes</a>
                <a class="btn btn-line" href="/WhiteGlove/public/client_invoices.php">Invoices</a>
                <a class="btn btn-line" href="/WhiteGlove/public/client_checklists.php">Checklists</a>
                <a class="btn btn-line" href="/WhiteGlove/public/client_reviews.php">Reviews</a>
                <a class="btn btn-line" href="/WhiteGlove/public/client_notifications.php">Notifications</a>
            <?php else: ?>
                <a class="btn btn-soft" href="/WhiteGlove/public/provider_hub.php">Provider Hub</a>
                <a class="btn btn-line" href="/WhiteGlove/public/provider_profile.php">Profile</a>
                <a class="btn btn-line" href="/WhiteGlove/public/provider_services.php">Services</a>
                <a class="btn btn-line" href="/WhiteGlove/public/provider_availability.php">Availability</a>
                <a class="btn btn-line" href="/WhiteGlove/public/provider_bookings.php">Bookings</a>
                <a class="btn btn-line" href="/WhiteGlove/public/provider_payments.php">Payments</a>
                <a class="btn btn-line" href="/WhiteGlove/public/provider_bids.php">Bids</a>
                <a class="btn btn-line" href="/WhiteGlove/public/provider_quotes.php">Quotes</a>
                <a class="btn btn-line" href="/WhiteGlove/public/provider_invoices.php">Invoices</a>
                <a class="btn btn-line" href="/WhiteGlove/public/provider_notifications.php">Notifications</a>
                <a class="btn btn-line" href="/WhiteGlove/public/provider_reviews.php">Reviews</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="metric-grid">
        <?php foreach ($cards as $card): ?>
            <article class="metric">
                <small><?php echo htmlspecialchars($card[0], ENT_QUOTES, 'UTF-8'); ?></small>
                <strong><?php echo htmlspecialchars($card[1], ENT_QUOTES, 'UTF-8'); ?></strong>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="panel">
        <div class="head">
            <h2><?php echo htmlspecialchars($tableTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
        </div>
        <div class="table-wrap">
            <?php if (count($tableRows) === 0): ?>
                <p style="margin:0;color:#617388;">No records yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                    <tr>
                        <?php foreach (array_keys($tableRows[0]) as $col): ?>
                            <th><?php echo htmlspecialchars((string) $col, ENT_QUOTES, 'UTF-8'); ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tableRows as $row): ?>
                        <tr>
                            <?php foreach ($row as $col => $val): ?>
                                <td>
                                    <?php if (str_contains(strtolower((string) $col), 'status')): ?>
                                        <span class="badge <?php echo status_badge_class((string) $val); ?>">
                                            <?php echo htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>
</main>
<footer>
    <div class="foot">
        <span>&copy; <?php echo date('Y'); ?> WhiteGlove. All rights reserved.</span>
        <span>Event Management System</span>
    </div>
</footer>
</body>
</html>
