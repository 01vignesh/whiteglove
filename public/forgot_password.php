<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';

start_session_if_needed();
$pdo = db();

$error = '';
$success = '';
$step = 'identify';

function normalize_security_answer(string $answer): string
{
    $value = trim($answer);
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
}

function clear_reset_session(): void
{
    unset($_SESSION['security_reset_user_id'], $_SESSION['security_reset_email'], $_SESSION['security_reset_question'], $_SESSION['security_reset_attempts']);
}

if (isset($_SESSION['security_reset_user_id'], $_SESSION['security_reset_email'], $_SESSION['security_reset_question'])) {
    $step = 'verify';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['fresh'] ?? '') === '1') {
    clear_reset_session();
    $step = 'identify';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'identify');
    try {
        if ($action === 'identify') {
            $email = trim((string) ($_POST['email'] ?? ''));
            if ($email === '') {
                throw new RuntimeException('Please enter your account email.');
            }

            $stmt = $pdo->prepare(
                'SELECT id, email, security_question, security_answer_hash, is_active
                 FROM users
                 WHERE email = ?
                 LIMIT 1'
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (
                !$user ||
                (int) ($user['is_active'] ?? 0) !== 1 ||
                trim((string) ($user['security_question'] ?? '')) === '' ||
                trim((string) ($user['security_answer_hash'] ?? '')) === ''
            ) {
                throw new RuntimeException('Unable to start reset for this account. Contact admin.');
            }

            $_SESSION['security_reset_user_id'] = (int) $user['id'];
            $_SESSION['security_reset_email'] = (string) $user['email'];
            $_SESSION['security_reset_question'] = (string) $user['security_question'];
            $_SESSION['security_reset_attempts'] = 0;
            $step = 'verify';
        } elseif ($action === 'reset_password') {
            if (!isset($_SESSION['security_reset_user_id'], $_SESSION['security_reset_question'])) {
                clear_reset_session();
                $step = 'identify';
                $success = '';
            } else {
                $answer = (string) ($_POST['security_answer'] ?? '');
                $newPassword = (string) ($_POST['new_password'] ?? '');
                $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

                if (trim($answer) === '' || $newPassword === '' || $confirmPassword === '') {
                    throw new RuntimeException('Please fill all required fields.');
                }
                if ($newPassword !== $confirmPassword) {
                    throw new RuntimeException('New password and confirm password do not match.');
                }
                if (strlen($newPassword) < 6) {
                    throw new RuntimeException('Password must be at least 6 characters.');
                }

                $attempts = (int) ($_SESSION['security_reset_attempts'] ?? 0);
                if ($attempts >= 5) {
                    throw new RuntimeException('Too many failed attempts. Please try again later.');
                }

                $stmt = $pdo->prepare(
                    'SELECT id, security_answer_hash
                     FROM users
                     WHERE id = ?
                     LIMIT 1'
                );
                $stmt->execute([(int) $_SESSION['security_reset_user_id']]);
                $user = $stmt->fetch();
                if (!$user) {
                    clear_reset_session();
                    throw new RuntimeException('Account not found. Please start again.');
                }

                $normalizedAnswer = normalize_security_answer($answer);
                $answerHash = (string) ($user['security_answer_hash'] ?? '');
                if ($answerHash === '' || !password_verify($normalizedAnswer, $answerHash)) {
                    $_SESSION['security_reset_attempts'] = $attempts + 1;
                    $remaining = max(0, 5 - (int) $_SESSION['security_reset_attempts']);
                    throw new RuntimeException('Security answer is incorrect. Remaining attempts: ' . $remaining);
                }

                $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $user['id']]);

                clear_reset_session();
                $step = 'identify';
                $success = 'Password reset successful. You can now login.';
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | WhiteGlove</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root { --ink:#142233; --muted:#617388; --bg:#f4f8fb; --surface:#fff; --primary:#0c6e84; --border:#dde7ef; }
        * { box-sizing:border-box; }
        body {
            margin:0; min-height:100vh; display:flex; flex-direction:column; font-family:"Plus Jakarta Sans",sans-serif; color:var(--ink);
            background: radial-gradient(circle at 15% -10%, #d8eef8 0, transparent 30%), radial-gradient(circle at 85% 0%, #fdf1d3 0, transparent 26%), var(--bg);
        }
        .nav-wrap { border-bottom:1px solid var(--border); background:rgba(244,248,251,.9); }
        .nav { max-width:1150px; margin:0 auto; padding:.85rem 1rem; display:flex; justify-content:space-between; align-items:center; }
        .brand { font-family:"Space Grotesk",sans-serif; font-weight:700; text-decoration:none; color:var(--ink); }
        .btn { border:0; border-radius:999px; padding:.55rem .9rem; font-weight:700; text-decoration:none; font-size:.84rem; display:inline-block; }
        .btn-line { border:1px solid #bdd0dd; color:#1f3f5a; background:#fff; }
        .shell { width:min(760px,100%); margin:0 auto; padding:1rem; flex:1; display:grid; place-items:center; }
        .card { width:100%; background:var(--surface); border:1px solid var(--border); border-radius:24px; box-shadow:0 18px 40px rgba(10,34,52,.08); padding:1.4rem; }
        h1 { margin:0 0 .35rem; font-family:"Space Grotesk",sans-serif; }
        .sub { margin:0 0 1rem; color:var(--muted); font-size:.9rem; }
        .err,.ok { margin-bottom:.8rem; border-radius:12px; padding:.55rem .7rem; font-size:.85rem; border:1px solid; }
        .err { color:#b3261e; background:#fdecea; border-color:#f4c9c4; }
        .ok { color:#1f7a3f; background:#e9f7ee; border-color:#cde9d4; }
        label { display:block; margin:.4rem 0; font-size:.85rem; font-weight:600; }
        input {
            width:100%; border:1px solid var(--border); border-radius:12px; padding:.65rem .75rem; font-size:.9rem;
        }
        .submit { width:100%; margin-top:.85rem; background:var(--primary); color:#fff; border:0; border-radius:999px; padding:.72rem .9rem; font-weight:700; cursor:pointer; }
        .links { margin-top:.9rem; font-size:.86rem; color:var(--muted); }
        .links a { color:#14557e; text-decoration:none; font-weight:700; }
        .question {
            padding:.65rem .75rem; border:1px solid var(--border); border-radius:12px; background:#f8fbfd; font-size:.9rem;
        }
    </style>
</head>
<body>
<header class="nav-wrap">
    <nav class="nav">
        <a class="brand" href="/WhiteGlove/public/index.php">WhiteGlove</a>
        <a class="btn btn-line" href="/WhiteGlove/public/login.php">Back to Login</a>
    </nav>
</header>
<main class="shell">
    <section class="card">
        <h1>Reset Password</h1>
        <p class="sub">Use your security question to reset your password.</p>

        <?php if ($error !== ''): ?>
            <div class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="ok"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($step === 'verify'): ?>
            <form method="post" novalidate>
                <input type="hidden" name="action" value="reset_password">
                <label>Account Email</label>
                <input type="email" value="<?php echo htmlspecialchars((string) ($_SESSION['security_reset_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly>
                <label>Security Question</label>
                <div class="question"><?php echo htmlspecialchars((string) ($_SESSION['security_reset_question'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <label>Security Answer</label>
                <input type="text" name="security_answer" required>
                <label>New Password</label>
                <input type="password" name="new_password" required>
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required>
                <button class="submit" type="submit">Update Password</button>
            </form>
        <?php else: ?>
            <form method="post" novalidate>
                <input type="hidden" name="action" value="identify">
                <label>Registered Email</label>
                <input type="email" name="email" required>
                <button class="submit" type="submit">Continue</button>
            </form>
        <?php endif; ?>

        <div class="links">
            <a href="/WhiteGlove/public/login.php">Login</a> | <a href="/WhiteGlove/public/register.php">Create account</a>
        </div>
    </section>
</main>
</body>
</html>
