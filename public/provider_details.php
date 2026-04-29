<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';

$user = require_auth();
$pdo = db();
$providerId = (int) ($_GET['id'] ?? 0);

if ($providerId <= 0) {
    http_response_code(400);
    echo 'Invalid provider id.';
    exit;
}

$providerStmt = $pdo->prepare(
    'SELECT
        u.id,
        u.name,
        u.email,
        pp.business_name,
        pp.city,
        pp.description,
        pp.profile_image_url,
        pp.approval_status
     FROM users u
     LEFT JOIN provider_profiles pp ON pp.user_id = u.id
     WHERE u.id = ? AND u.role = "PROVIDER"
     LIMIT 1'
);
$providerStmt->execute([$providerId]);
$provider = $providerStmt->fetch();

if (!$provider) {
    http_response_code(404);
    echo 'Provider not found.';
    exit;
}

$bookingCenterUrl = '/WhiteGlove/public/client_bookings.php';
if ((string) ($user['role'] ?? '') === 'PROVIDER') {
    $bookingCenterUrl = '/WhiteGlove/public/provider_bookings.php';
} elseif ((string) ($user['role'] ?? '') === 'ADMIN') {
    $bookingCenterUrl = '/WhiteGlove/public/admin_bookings.php';
}

$serviceCountStmt = $pdo->prepare(
    'SELECT COUNT(*) AS cnt
     FROM services
     WHERE provider_id = ? AND status = "ACTIVE" AND title NOT LIKE "Custom Bid Booking #%"'
);
$serviceCountStmt->execute([$providerId]);
$serviceCount = (int) (($serviceCountStmt->fetch()['cnt'] ?? 0));

$ratingStmt = $pdo->prepare(
    'SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_count
     FROM reviews
     WHERE provider_id = ?'
);
$ratingStmt->execute([$providerId]);
$rating = $ratingStmt->fetch() ?: ['avg_rating' => 0, 'review_count' => 0];

$servicesStmt = $pdo->prepare(
    'SELECT id, title, city, event_type, base_price
     FROM services
     WHERE provider_id = ? AND status = "ACTIVE" AND title NOT LIKE "Custom Bid Booking #%"
     ORDER BY created_at DESC
     LIMIT 6'
);
$servicesStmt->execute([$providerId]);
$services = $servicesStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Provider Details | WhiteGlove</title>
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
        .actions { display:flex; gap:.55rem; flex-wrap:wrap; }
        .btn {
            border: 0;
            border-radius: 999px;
            padding: .55rem .9rem;
            text-decoration: none;
            font-size: .84rem;
            font-weight: 700;
            display: inline-block;
        }
        .btn-line { border: 1px solid #bdd0dd; color: #1f3f5a; background: #fff; }
        .btn-warn { background: #ffe0b0; color: #613700; }
        .wrap { width:min(1150px,100%); margin:0 auto; padding:1rem; }
        .hero {
            background: linear-gradient(140deg, #0f6f84 0%, #13938d 60%, #49a7a2 100%);
            color:#fff;
            border-radius:24px;
            padding:1.25rem;
            box-shadow:var(--shadow);
            display:flex;
            justify-content:space-between;
            gap:1rem;
            flex-wrap:wrap;
            align-items:center;
        }
        .hero h1 { margin:0 0 .25rem; font-family:"Space Grotesk",sans-serif; }
        .hero p { margin:0; opacity:.94; font-size:.92rem; }
        .avatar {
            width:84px;
            height:84px;
            border-radius:999px;
            object-fit:cover;
            border:2px solid rgba(255,255,255,.65);
            background:#e8f3f8;
        }
        .grid {
            margin-top:1rem;
            display:grid;
            grid-template-columns: 1fr .9fr;
            gap:1rem;
        }
        .card {
            background:var(--surface);
            border:1px solid var(--border);
            border-radius:18px;
            box-shadow:var(--shadow);
            padding:1rem;
        }
        .card h2 { margin:0 0 .7rem; font-family:"Space Grotesk",sans-serif; font-size:1.05rem; }
        .meta {
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:.55rem;
        }
        .chip {
            border:1px solid var(--border);
            border-radius:12px;
            padding:.55rem .65rem;
            background:#fcfdff;
            font-size:.86rem;
        }
        .list {
            margin:0;
            padding:0;
            list-style:none;
            border-top:1px solid var(--border);
        }
        .list li {
            border-bottom:1px solid var(--border);
            padding:.55rem 0;
            display:flex;
            justify-content:space-between;
            gap:.55rem;
            font-size:.88rem;
        }
        .list a { color:var(--ink); text-decoration:none; font-weight:600; }
        .list a:hover { color:var(--primary); }
        .desc {
            color:#3c5163;
            line-height:1.45;
            font-size:.9rem;
            white-space:pre-wrap;
        }
        .empty {
            border:1px dashed var(--border);
            border-radius:12px;
            color:var(--muted);
            font-size:.85rem;
            text-align:center;
            padding:.9rem;
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
        @media (max-width: 900px) {
            .grid { grid-template-columns: 1fr; }
            .meta { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header class="nav-wrap">
    <nav class="nav">
        <a class="brand" href="/WhiteGlove/public/index.php">WhiteGlove</a>
        <div class="actions">
            <a class="btn btn-line" href="<?php echo htmlspecialchars($bookingCenterUrl, ENT_QUOTES, 'UTF-8'); ?>">Booking Center</a>
            <a class="btn btn-line" href="/WhiteGlove/public/dashboard.php">Dashboard</a>
            <a class="btn btn-warn" href="/WhiteGlove/public/logout.php">Logout</a>
        </div>
    </nav>
</header>

<main class="wrap">
    <section class="hero">
        <div>
            <h1><?php echo htmlspecialchars((string) ($provider['business_name'] ?: $provider['name']), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p>Provider Profile Details</p>
        </div>
        <?php if ((string) ($provider['profile_image_url'] ?? '') !== ''): ?>
            <img class="avatar" src="<?php echo htmlspecialchars((string) $provider['profile_image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Provider profile image">
        <?php endif; ?>
    </section>

    <section class="grid">
        <article class="card">
            <h2>Profile Overview</h2>
            <div class="meta">
                <div class="chip"><strong>Provider:</strong> <?php echo htmlspecialchars((string) ($provider['business_name'] ?: $provider['name']), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="chip"><strong>City:</strong> <?php echo htmlspecialchars((string) ($provider['city'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="chip"><strong>Status:</strong> <?php echo htmlspecialchars((string) ($provider['approval_status'] ?: 'PENDING'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="chip"><strong>Active Services:</strong> <?php echo $serviceCount; ?></div>
                <div class="chip"><strong>Avg Rating:</strong> <?php echo number_format((float) ($rating['avg_rating'] ?? 0), 2); ?> (<?php echo (int) ($rating['review_count'] ?? 0); ?>)</div>
            </div>
            <h2 style="margin-top:1rem;">Description</h2>
            <div class="desc"><?php echo htmlspecialchars((string) ($provider['description'] ?: 'No provider description available.'), ENT_QUOTES, 'UTF-8'); ?></div>
        </article>

        <article class="card">
            <h2>Recent Services</h2>
            <?php if (count($services) > 0): ?>
                <ul class="list">
                    <?php foreach ($services as $service): ?>
                        <li>
                            <a href="/WhiteGlove/public/service_view.php?id=<?php echo (int) $service['id']; ?>">
                                <?php echo htmlspecialchars((string) $service['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                            <span>INR <?php echo number_format((float) $service['base_price'], 2); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty">No active services published yet.</div>
            <?php endif; ?>
        </article>
    </section>
</main>
<footer class="provider-footer">
    <div class="provider-foot">
        <span>&copy; <?php echo date('Y'); ?> WhiteGlove. All rights reserved.</span>
        <span>Client Experience Suite</span>
    </div>
</footer>
</body>
</html>
