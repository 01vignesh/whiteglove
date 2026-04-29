<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/partials/provider_layout.php';

$user = require_role(['PROVIDER']);
$pdo = db();
$message = '';
$error = '';

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
    try {
        $securityQuestion = trim((string) ($_POST['security_question'] ?? ''));
        $securityAnswer = trim((string) ($_POST['security_answer'] ?? ''));

        $hasUploadedImage = isset($_FILES['profile_image_file']) && (int) ($_FILES['profile_image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $currentStmt = $pdo->prepare('SELECT profile_image_url FROM provider_profiles WHERE user_id = ? LIMIT 1');
        $currentStmt->execute([(int) $user['id']]);
        $current = $currentStmt->fetch();
        $profileImageUrl = (string) ($current['profile_image_url'] ?? '');
        if ($hasUploadedImage) {
            $profileImageUrl = upload_provider_image($_FILES['profile_image_file']);
        }

        $stmt = $pdo->prepare(
            'UPDATE provider_profiles
             SET business_name = ?, city = ?, description = ?, profile_image_url = ?
             WHERE user_id = ?'
        );
        $stmt->execute([
            trim((string) ($_POST['business_name'] ?? '')),
            trim((string) ($_POST['city'] ?? '')),
            trim((string) ($_POST['description'] ?? '')),
            $profileImageUrl !== '' ? $profileImageUrl : null,
            (int) $user['id']
        ]);

        $userStmt = $pdo->prepare('UPDATE users SET profile_image_url = ? WHERE id = ?');
        $userStmt->execute([$profileImageUrl !== '' ? $profileImageUrl : null, (int) $user['id']]);

        $userCurrentStmt = $pdo->prepare('SELECT security_answer_hash FROM users WHERE id = ? LIMIT 1');
        $userCurrentStmt->execute([(int) $user['id']]);
        $userCurrent = $userCurrentStmt->fetch() ?: ['security_answer_hash' => null];
        $securityAnswerHash = (string) ($userCurrent['security_answer_hash'] ?? '');

        if ($securityAnswer !== '') {
            $normalizedAnswer = normalize_security_answer($securityAnswer);
            $answerLen = function_exists('mb_strlen') ? mb_strlen($normalizedAnswer) : strlen($normalizedAnswer);
            if ($answerLen < 2) {
                throw new RuntimeException('Security answer must be at least 2 characters.');
            }
            $securityAnswerHash = password_hash($normalizedAnswer, PASSWORD_DEFAULT);
        }

        if ($securityQuestion === '' && $securityAnswer !== '') {
            throw new RuntimeException('Please choose a security question when setting a new answer.');
        }

        $securityStmt = $pdo->prepare(
            'UPDATE users
             SET security_question = ?, security_answer_hash = ?
             WHERE id = ?'
        );
        $securityStmt->execute([
            $securityQuestion !== '' ? $securityQuestion : null,
            $securityAnswerHash !== '' ? $securityAnswerHash : null,
            (int) $user['id']
        ]);

        $message = 'Provider profile updated successfully.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$profileStmt = $pdo->prepare(
    'SELECT business_name, city, description, profile_image_url, approval_status
     FROM provider_profiles WHERE user_id = ? LIMIT 1'
);
$profileStmt->execute([$user['id']]);
$providerProfile = $profileStmt->fetch() ?: [
    'business_name' => '',
    'city' => '',
    'description' => '',
    'profile_image_url' => '',
    'approval_status' => 'PENDING',
];

$securityStmt = $pdo->prepare('SELECT security_question FROM users WHERE id = ? LIMIT 1');
$securityStmt->execute([(int) $user['id']]);
$security = $securityStmt->fetch() ?: ['security_question' => ''];
?>
<?php render_provider_page_start('Provider Profile'); ?>
    <div class="provider-hero">
        <h1 class="h4 mb-1">Provider Profile</h1>
        <p class="mb-0">Approval status: <strong><?php echo htmlspecialchars((string) $providerProfile['approval_status'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="provider-card">
            <?php if ((string) ($providerProfile['profile_image_url'] ?? '') !== ''): ?>
                <div class="provider-avatar-wrap">
                    <img class="provider-avatar" src="<?php echo htmlspecialchars((string) $providerProfile['profile_image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Provider profile picture">
                </div>
            <?php endif; ?>
            <form method="post" class="row g-2" enctype="multipart/form-data">
                <div class="col-md-6">
                    <label class="form-label">Business Name</label>
                    <input class="form-control" name="business_name" value="<?php echo htmlspecialchars((string) $providerProfile['business_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">City</label>
                    <input class="form-control" name="city" value="<?php echo htmlspecialchars((string) $providerProfile['city'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="4" required><?php echo htmlspecialchars((string) $providerProfile['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Upload Profile Picture</label>
                    <input class="form-control" type="file" name="profile_image_file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Security Question</label>
                    <select class="form-select" name="security_question">
                        <option value="">Select a question</option>
                        <?php
                        $questions = [
                            'What is your favorite color?',
                            'What is your birth city?',
                            'What is your pet name?',
                            'What is your best friend name?',
                        ];
                        foreach ($questions as $q):
                            ?>
                            <option value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ((string) ($security['security_question'] ?? '') === $q) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Security Answer (leave blank to keep existing)</label>
                    <input class="form-control" type="text" name="security_answer" placeholder="Enter new answer only if updating">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary w-100" type="submit">Save Profile</button>
                </div>
            </form>
    </section>
<?php render_provider_page_end(); ?>


