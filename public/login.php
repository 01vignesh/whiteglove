<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';

$error = '';
if (isset($_GET['inactive']) && (string) $_GET['inactive'] === '1') {
    $error = 'Your account is inactive. Please contact admin.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $user = auth_login($email, $password);
        if ($user) {
            header('Location: /WhiteGlove/public/dashboard.php');
            exit;
        }
        $error = 'Invalid credentials or inactive account.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | WhiteGlove</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #142233;
            --muted: #617388;
            --bg: #f4f8fb;
            --surface: #fff;
            --primary: #0c6e84;
            --border: #dde7ef;
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
        .nav-wrap { border-bottom: 1px solid var(--border); background: rgba(244,248,251,.9); }
        .nav { max-width: 1150px; margin: 0 auto; padding: .85rem 1rem; display: flex; justify-content: space-between; align-items: center; }
        .brand { font-family: "Space Grotesk", sans-serif; font-weight: 700; text-decoration: none; color: var(--ink); }
        .btn {
            border: 0; border-radius: 999px; padding: .55rem .9rem; font-weight: 700; text-decoration: none; font-size: .84rem;
            display: inline-block;
        }
        .btn-line { border: 1px solid #bdd0dd; color: #1f3f5a; background: #fff; }
        .shell { width: min(1120px, 100%); margin: 0 auto; padding: 1rem; flex: 1; display: grid; place-items: center; }
        .auth {
            width: min(980px, 100%);
            display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;
            background: var(--surface); border: 1px solid var(--border); border-radius: 24px; overflow: hidden;
            box-shadow: 0 18px 40px rgba(10, 34, 52, 0.08);
        }
        .art { position: relative; min-height: 450px; }
        .art img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .note { position: absolute; left: 1rem; bottom: 1rem; background: rgba(255,255,255,.9); padding: .6rem .75rem; border-radius: 12px; font-size: .8rem; font-weight: 700; }
        .form { padding: 2rem 1.4rem; }
        h1 { margin: 0 0 .4rem; font-family: "Space Grotesk", sans-serif; }
        .sub { margin: 0 0 1.1rem; color: var(--muted); font-size: .9rem; }
        label { display: block; margin: .4rem 0; font-size: .85rem; font-weight: 600; }
        input {
            width: 100%; border: 1px solid var(--border); border-radius: 12px; padding: .65rem .75rem; font-size: .9rem;
        }
        .submit { width: 100%; margin-top: .8rem; background: var(--primary); color: #fff; border: 0; border-radius: 999px; padding: .72rem .9rem; font-weight: 700; cursor: pointer; }
        .err { margin-bottom: .8rem; color: #b3261e; background: #fdecea; border: 1px solid #f4c9c4; border-radius: 12px; padding: .55rem .7rem; font-size: .85rem; }
        .links { margin-top: .9rem; font-size: .86rem; color: var(--muted); }
        .links a { color: #14557e; text-decoration: none; font-weight: 700; }
        footer { border-top: 1px solid var(--border); padding: .95rem 1rem; color: var(--muted); font-size: .82rem; }
        .foot {
            max-width: 1150px; margin: 0 auto;
            display: grid; grid-template-columns: 1.1fr .9fr; gap: .9rem; align-items: start;
        }
        .foot-left strong { color: var(--ink); display: block; margin-bottom: .2rem; }
        .foot-links, .foot-social { display: flex; gap: .55rem; flex-wrap: wrap; align-items: center; }
        .foot-links a, .foot-social a { color: #1b557d; text-decoration: none; font-weight: 700; font-size: .8rem; }
        .ico {
            width: 28px; height: 28px; border-radius: 999px; border: 1px solid var(--border);
            display: inline-flex; align-items: center; justify-content: center; background: #fff;
        }
        .ico svg { width: 14px; height: 14px; fill: #1b557d; }
        .handles { margin-top: .35rem; font-size: .78rem; color: var(--muted); }
        @media (max-width: 900px) {
            .auth { grid-template-columns: 1fr; }
            .art { min-height: 240px; }
            .foot { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header class="nav-wrap">
    <nav class="nav">
        <a class="brand" href="/WhiteGlove/public/index.php">WhiteGlove</a>
        <a class="btn btn-line" href="/WhiteGlove/public/register.php">Register</a>
    </nav>
</header>
<main class="shell">
    <section class="auth">
        <div class="art">
            <img src="https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?auto=format&fit=crop&w=1200&q=80" alt="Event night setup">
            <div class="note">Secure access to client, provider, and admin modules</div>
        </div>
        <div class="form">
            <h1>Welcome Back</h1>
            <p class="sub">Sign in to continue managing bookings, vendors, and event operations.</p>

            <?php if ($error !== ''): ?>
                <div class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <label>Email</label>
                <input type="email" name="email" required>
                <label>Password</label>
                <input type="password" name="password" required>
                <button class="submit" type="submit">Login to Dashboard</button>
            </form>

            <div class="links">
                <a href="/WhiteGlove/public/forgot_password.php?fresh=1">Forgot Password?</a> | <a href="/WhiteGlove/public/register.php">Create account</a> | <a href="/WhiteGlove/public/index.php">Back to home</a>
            </div>
        </div>
    </section>
</main>
<footer>
    <div class="foot">
        <div class="foot-left">
            <strong>&copy; <?php echo date('Y'); ?> WhiteGlove. All rights reserved.</strong>
            <div>Event Management System</div>
            <div class="handles">@whitegloveevents | @whitegloveofficial</div>
        </div>
        <div>
            <div class="foot-links">
                <a href="/WhiteGlove/public/about.php">About Us</a>
                <a href="/WhiteGlove/public/privacy.php">Privacy Policy</a>
                <a href="/WhiteGlove/public/terms.php">Terms of Service</a>
                <a href="/WhiteGlove/public/contact.php">Contact Us</a>
                <a href="/WhiteGlove/public/blog.php">Blog</a>
            </div>
            <div class="foot-social" style="margin-top:.45rem;">
                <a class="ico" href="#" aria-label="Instagram">
                    <svg viewBox="0 0 24 24"><path d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2zm0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8zm9.65 1.5a1.05 1.05 0 1 1 0 2.1 1.05 1.05 0 0 1 0-2.1zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>
                </a>
                <a class="ico" href="#" aria-label="Facebook">
                    <svg viewBox="0 0 24 24"><path d="M13.5 21v-8h2.7l.4-3h-3.1V8.1c0-.9.3-1.6 1.7-1.6h1.5V3.8c-.3 0-1.1-.1-2.2-.1-2.2 0-3.7 1.3-3.7 3.9V10H8v3h2.8v8h2.7z"/></svg>
                </a>
                <a class="ico" href="#" aria-label="LinkedIn">
                    <svg viewBox="0 0 24 24"><path d="M6.5 8.2A1.7 1.7 0 1 1 6.5 4.8a1.7 1.7 0 0 1 0 3.4zM5 9.6h3V20H5V9.6zm5 0h2.8V11h.1c.4-.8 1.4-1.6 2.9-1.6 3.1 0 3.7 2 3.7 4.7V20h-3v-5.2c0-1.2 0-2.8-1.8-2.8s-2 1.3-2 2.7V20h-3V9.6z"/></svg>
                </a>
                <a class="ico" href="#" aria-label="YouTube">
                    <svg viewBox="0 0 24 24"><path d="M23 12s0-3.5-.4-5.2a2.9 2.9 0 0 0-2-2C18.8 4.3 12 4.3 12 4.3s-6.8 0-8.6.5a2.9 2.9 0 0 0-2 2C1 8.5 1 12 1 12s0 3.5.4 5.2a2.9 2.9 0 0 0 2 2c1.8.5 8.6.5 8.6.5s6.8 0 8.6-.5a2.9 2.9 0 0 0 2-2C23 15.5 23 12 23 12zM10 15.5v-7l6 3.5-6 3.5z"/></svg>
                </a>
                <a class="ico" href="#" aria-label="X">
                    <svg viewBox="0 0 24 24"><path d="M18.9 2H22l-6.8 7.7L23 22h-6.1l-4.8-6.3L6.5 22H3.4l7.3-8.3L1 2h6.2l4.3 5.7L18.9 2zm-1.1 18h1.7L6.2 3.9H4.3L17.8 20z"/></svg>
                </a>
            </div>
        </div>
    </div>
</footer>
</body>
</html>
