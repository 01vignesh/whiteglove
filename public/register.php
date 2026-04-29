<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';

$error = '';
$success = '';

function upload_provider_image(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Profile image upload failed.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('Profile image must be 2MB or smaller.');
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string) finfo_file($finfo, $tmp);
            finfo_close($finfo);
        }
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, or WEBP images are allowed.');
    }

    $uploadDir = __DIR__ . '/uploads/providers';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to prepare upload directory.');
    }

    $fileName = 'provider_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $target = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('Failed to save uploaded image.');
    }

    return '/WhiteGlove/public/uploads/providers/' . $fileName;
}

function normalize_security_answer(string $answer): string
{
    $value = trim($answer);
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role = strtoupper(trim((string) ($_POST['role'] ?? 'CLIENT')));
    $securityQuestion = trim((string) ($_POST['security_question'] ?? ''));
    $securityAnswer = (string) ($_POST['security_answer'] ?? '');
    $businessName = trim((string) ($_POST['business_name'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $hasUploadedImage = isset($_FILES['profile_image_file']) && (int) ($_FILES['profile_image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    $allowedRoles = ['CLIENT', 'PROVIDER'];
    if (
        $name === '' || $email === '' || $password === '' || !in_array($role, $allowedRoles, true) ||
        $securityQuestion === '' || trim($securityAnswer) === ''
    ) {
        $error = 'Please fill all required fields with valid values.';
    } else {
        try {
            $storedImagePath = null;
            if ($hasUploadedImage) {
                $storedImagePath = upload_provider_image($_FILES['profile_image_file']);
            }

            $normalizedAnswer = normalize_security_answer($securityAnswer);
            $answerLen = function_exists('mb_strlen') ? mb_strlen($normalizedAnswer) : strlen($normalizedAnswer);
            if ($answerLen < 2) {
                throw new RuntimeException('Security answer must be at least 2 characters.');
            }

            $pdo = db();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, profile_image_url, security_question, security_answer_hash, role)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $storedImagePath,
                $securityQuestion,
                password_hash($normalizedAnswer, PASSWORD_DEFAULT),
                $role
            ]);
            $userId = (int) $pdo->lastInsertId();

            if ($role === 'PROVIDER') {
                $providerStmt = $pdo->prepare(
                    'INSERT INTO provider_profiles (user_id, business_name, city, description, profile_image_url, approval_status)
                     VALUES (?, ?, ?, ?, ?, "PENDING")'
                );
                $providerStmt->execute([
                    $userId,
                    $businessName !== '' ? $businessName : $name . ' Events',
                    $city !== '' ? $city : 'Not set',
                    $description !== '' ? $description : 'New provider onboarding profile',
                    $storedImagePath,
                ]);
            }

            $pdo->commit();
            $success = 'Registration successful. You can now log in.';
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register | WhiteGlove</title>
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
            width: min(1040px, 100%);
            display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;
            background: var(--surface); border: 1px solid var(--border); border-radius: 24px; overflow: hidden;
            box-shadow: 0 18px 40px rgba(10, 34, 52, 0.08);
        }
        .art { position: relative; min-height: 520px; }
        .art img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .note { position: absolute; left: 1rem; bottom: 1rem; background: rgba(255,255,255,.9); padding: .6rem .75rem; border-radius: 12px; font-size: .8rem; font-weight: 700; }
        .form { padding: 1.5rem 1.4rem; }
        h1 { margin: 0 0 .35rem; font-family: "Space Grotesk", sans-serif; font-size: 1.55rem; }
        .sub { margin: 0 0 1rem; color: var(--muted); font-size: .9rem; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; }
        label { display: block; margin: .35rem 0; font-size: .83rem; font-weight: 600; }
        input, select {
            width: 100%; border: 1px solid var(--border); border-radius: 12px; padding: .62rem .72rem; font-size: .9rem;
        }
        .full { grid-column: 1 / -1; }
        textarea {
            width: 100%; border: 1px solid var(--border); border-radius: 12px; padding: .62rem .72rem; font-size: .9rem; resize: vertical; min-height: 86px;
            font-family: inherit;
        }
        .submit { width: 100%; margin-top: .85rem; background: var(--primary); color: #fff; border: 0; border-radius: 999px; padding: .72rem .9rem; font-weight: 700; cursor: pointer; }
        .err, .ok { margin-bottom: .8rem; border-radius: 12px; padding: .55rem .7rem; font-size: .85rem; border: 1px solid; }
        .err { color: #b3261e; background: #fdecea; border-color: #f4c9c4; }
        .ok { color: #1f7a3f; background: #e9f7ee; border-color: #cde9d4; }
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
        @media (max-width: 950px) {
            .auth { grid-template-columns: 1fr; }
            .art { min-height: 260px; }
            .foot { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header class="nav-wrap">
    <nav class="nav">
        <a class="brand" href="/WhiteGlove/public/index.php">WhiteGlove</a>
        <a class="btn btn-line" href="/WhiteGlove/public/login.php">Login</a>
    </nav>
</header>
<main class="shell">
    <section class="auth">
        <div class="art">
            <img src="https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=1200&q=80" alt="Wedding event lighting and decor">
            <div class="note">Create your role-based account and start managing events</div>
        </div>
        <div class="form">
            <h1>Create WhiteGlove Account</h1>
            <p class="sub">Onboard as a client or provider and enter your module instantly.</p>

            <?php if ($error !== ''): ?>
                <div class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <div class="ok"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" novalidate class="grid" enctype="multipart/form-data">
                <div>
                    <label>Full Name</label>
                    <input type="text" name="name" required>
                </div>
                <div>
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div>
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <div>
                    <label>Security Question</label>
                    <select name="security_question" required>
                        <option value="">Select a question</option>
                        <option value="What is your favorite color?">What is your favorite color?</option>
                        <option value="What is your birth city?">What is your birth city?</option>
                        <option value="What is your pet name?">What is your pet name?</option>
                        <option value="What is your best friend name?">What is your best friend name?</option>
                    </select>
                </div>
                <div class="full">
                    <label>Security Answer</label>
                    <input type="text" name="security_answer" required>
                </div>
                <div>
                    <label>Role</label>
                    <select name="role" required>
                        <option value="CLIENT">Client</option>
                        <option value="PROVIDER">Service Provider</option>
                    </select>
                </div>
                <div>
                    <label>Business Name (Provider only)</label>
                    <input type="text" name="business_name">
                </div>
                <div>
                    <label>City (Provider only)</label>
                    <input type="text" name="city">
                </div>
                <div class="full">
                    <label>Description (Provider only)</label>
                    <textarea name="description" placeholder="Tell clients about your services, strengths, and experience."></textarea>
                </div>
                <div class="full">
                    <label>Upload Profile Picture (Client/Provider)</label>
                    <input type="file" name="profile_image_file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                </div>
                <div class="full">
                    <button class="submit" type="submit">Register Account</button>
                </div>
            </form>

            <div class="links">
                <a href="/WhiteGlove/public/login.php">Already have account? Login</a> | <a href="/WhiteGlove/public/index.php">Back to home</a>
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
