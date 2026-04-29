<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $db = $config['db'];

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['name'],
        $db['charset']
    );

    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    ensure_schema_updates($pdo);
    run_runtime_maintenance($pdo);

    return $pdo;
}

function ensure_schema_updates(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    // Backfill older databases with new provider profile photo support.
    $column = $pdo->query("SHOW COLUMNS FROM provider_profiles LIKE 'profile_image_url'")->fetch();
    if (!$column) {
        $pdo->exec('ALTER TABLE provider_profiles ADD COLUMN profile_image_url VARCHAR(500) NULL AFTER description');
    }

    $userColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_image_url'")->fetch();
    if (!$userColumn) {
        $pdo->exec('ALTER TABLE users ADD COLUMN profile_image_url VARCHAR(500) NULL AFTER password_hash');
    }

    $securityQuestionColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'security_question'")->fetch();
    if (!$securityQuestionColumn) {
        $pdo->exec('ALTER TABLE users ADD COLUMN security_question VARCHAR(255) NULL AFTER profile_image_url');
    }

    $securityAnswerHashColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'security_answer_hash'")->fetch();
    if (!$securityAnswerHashColumn) {
        $pdo->exec('ALTER TABLE users ADD COLUMN security_answer_hash VARCHAR(255) NULL AFTER security_question');
    }

    $serviceDescriptionColumn = $pdo->query("SHOW COLUMNS FROM services LIKE 'description'")->fetch();
    if (!$serviceDescriptionColumn) {
        $pdo->exec('ALTER TABLE services ADD COLUMN description TEXT NULL AFTER event_type');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS service_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_id INT NOT NULL,
            image_url VARCHAR(500) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
        )'
    );

    $bidGuestColumn = $pdo->query("SHOW COLUMNS FROM bid_requests LIKE 'guest_count'")->fetch();
    if (!$bidGuestColumn) {
        $pdo->exec('ALTER TABLE bid_requests ADD COLUMN guest_count INT NOT NULL DEFAULT 0 AFTER event_date');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            actor_user_id INT NULL,
            actor_role VARCHAR(40) NOT NULL DEFAULT "SYSTEM",
            action_key VARCHAR(120) NOT NULL,
            entity_type VARCHAR(80) NOT NULL,
            entity_id INT NULL,
            details TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
             FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
        )'
    );

    $refundAmountColumn = $pdo->query("SHOW COLUMNS FROM refund_requests LIKE 'refund_amount'")->fetch();
    if (!$refundAmountColumn) {
        $pdo->exec('ALTER TABLE refund_requests ADD COLUMN refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER refund_percentage');
    }

    $providerNoteColumn = $pdo->query("SHOW COLUMNS FROM refund_requests LIKE 'provider_note'")->fetch();
    if (!$providerNoteColumn) {
        $pdo->exec('ALTER TABLE refund_requests ADD COLUMN provider_note TEXT NULL AFTER reason');
    }

    $refundPaidAtColumn = $pdo->query("SHOW COLUMNS FROM refund_requests LIKE 'paid_at'")->fetch();
    if (!$refundPaidAtColumn) {
        $pdo->exec('ALTER TABLE refund_requests ADD COLUMN paid_at DATETIME NULL AFTER refund_status');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS cancellation_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id INT NOT NULL,
            client_id INT NOT NULL,
            provider_id INT NOT NULL,
            reason TEXT NOT NULL,
            request_status ENUM("REQUESTED", "APPROVED", "REJECTED") NOT NULL DEFAULT "REQUESTED",
            provider_note TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME NULL,
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE
        )'
    );

}

function run_runtime_maintenance(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    // Auto-mark due milestones as overdue once per request.
    $stmt = $pdo->prepare(
        'UPDATE payment_milestones
         SET milestone_status = "OVERDUE"
         WHERE milestone_status = "DUE" AND due_date < CURDATE()'
    );
    $stmt->execute();

    // Auto-close bid requests whose event date has already passed.
    $closeBids = $pdo->prepare(
        'UPDATE bid_requests
         SET request_status = "CLOSED"
         WHERE request_status = "OPEN" AND event_date < CURDATE()'
    );
    $closeBids->execute();

    // Auto-block service slots whose date is in the past.
    $blockSlots = $pdo->prepare(
        'UPDATE service_availability
         SET slot_status = "BLOCKED"
         WHERE slot_status = "AVAILABLE" AND slot_date < CURDATE()'
    );
    $blockSlots->execute();

    // Backfill paid timestamp for historical refund rows already marked as paid.
    $refundPaidBackfill = $pdo->prepare(
        'UPDATE refund_requests
         SET paid_at = COALESCE(paid_at, created_at)
         WHERE refund_status = "PAID" AND paid_at IS NULL'
    );
    $refundPaidBackfill->execute();
}

function log_activity(
    ?int $actorUserId,
    string $actorRole,
    string $actionKey,
    string $entityType,
    ?int $entityId = null,
    array $details = []
): void {
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs (actor_user_id, actor_role, action_key, entity_type, entity_id, details)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $actorUserId,
            $actorRole,
            $actionKey,
            $entityType,
            $entityId,
            count($details) > 0 ? json_encode($details) : null,
        ]);
    } catch (Throwable $e) {
        // Do not block core flows if audit logging fails.
    }
}
