<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function notify_safe(int $userId, string $title, string $message, string $channel = 'APP'): void
{
    try {
        notify_user($userId, $channel, $title, $message);
    } catch (Throwable $e) {
        // Notifications should not block primary business flow.
    }
}

function create_user(string $name, string $email, string $password, string $role): int
{
    $pdo = db();

    if ($role === 'ADMIN') {
        $check = $pdo->query('SELECT COUNT(*) AS cnt FROM users WHERE role = "ADMIN"')->fetch();
        if ($check && (int) $check['cnt'] > 0) {
            throw new RuntimeException('Only one admin account is allowed.');
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
    return (int) $pdo->lastInsertId();
}

function create_service(
    int $providerId,
    string $title,
    string $city,
    string $eventType,
    float $basePrice,
    string $description = ''
): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO services (provider_id, title, city, event_type, description, base_price, status)
         VALUES (?, ?, ?, ?, ?, ?, "ACTIVE")'
    );
    $stmt->execute([$providerId, $title, $city, $eventType, $description, $basePrice]);
    return (int) $pdo->lastInsertId();
}

function add_service_image(int $serviceId, string $imageUrl, int $sortOrder = 0): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO service_images (service_id, image_url, sort_order)
         VALUES (?, ?, ?)'
    );
    $stmt->execute([$serviceId, $imageUrl, $sortOrder]);
    return (int) $pdo->lastInsertId();
}

function set_service_availability(int $serviceId, string $date, string $slotStatus): void
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO service_availability (service_id, slot_date, slot_status)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE slot_status = VALUES(slot_status)'
    );
    $stmt->execute([$serviceId, $date, $slotStatus]);
}

function create_booking(
    int $clientId,
    int $serviceId,
    string $eventDate,
    int $guestCount,
    float $estimatedBudget
): int {
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $serviceStmt = $pdo->prepare('SELECT provider_id, city, event_type, base_price FROM services WHERE id = ?');
        $serviceStmt->execute([$serviceId]);
        $service = $serviceStmt->fetch();
        if (!$service) {
            throw new RuntimeException('Service not found.');
        }

        $basePrice = (float) ($service['base_price'] ?? 0);
        if ($estimatedBudget < $basePrice) {
            throw new RuntimeException('Estimated budget must be at least the service base price.');
        }

        $check = $pdo->prepare(
            'SELECT id, slot_status
             FROM service_availability
             WHERE service_id = ? AND slot_date = ?
             FOR UPDATE'
        );
        $check->execute([$serviceId, $eventDate]);
        $row = $check->fetch();
        if (!$row || (string) $row['slot_status'] !== 'AVAILABLE') {
            throw new RuntimeException('Booking allowed only on dates marked as AVAILABLE by provider.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO bookings (client_id, service_id, city, event_type, event_date, guest_count, estimated_budget, booking_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, "PENDING")'
        );
        $stmt->execute([
            $clientId,
            $serviceId,
            $service['city'],
            $service['event_type'],
            $eventDate,
            $guestCount,
            $estimatedBudget
        ]);
        $bookingId = (int) $pdo->lastInsertId();

        $lockSlot = $pdo->prepare('UPDATE service_availability SET slot_status = "BLOCKED" WHERE id = ?');
        $lockSlot->execute([(int) $row['id']]);

        $pdo->commit();
        log_activity($clientId, 'CLIENT', 'booking_created', 'booking', $bookingId, [
            'service_id' => $serviceId,
            'event_date' => $eventDate,
            'guest_count' => $guestCount,
            'estimated_budget' => $estimatedBudget,
        ]);
        notify_safe(
            (int) $service['provider_id'],
            'New Booking Request',
            'A new booking #' . $bookingId . ' was created for your service. Please review and update status.'
        );
        notify_safe(
            $clientId,
            'Booking Created',
            'Your booking #' . $bookingId . ' has been created and is pending provider review.'
        );
        return $bookingId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function simulate_payment(int $milestoneId, string $reference): int
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT booking_id, amount, milestone_status FROM payment_milestones WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([$milestoneId]);
        $milestone = $stmt->fetch();

        if (!$milestone) {
            throw new RuntimeException('Milestone not found.');
        }
        if ($milestone['milestone_status'] === 'PAID') {
            throw new RuntimeException('Milestone already paid.');
        }

        $insert = $pdo->prepare(
            'INSERT INTO transactions (booking_id, milestone_id, amount, payment_mode, payment_status, reference_no)
             VALUES (?, ?, ?, "SIMULATED", "SUCCESS", ?)'
        );
        $insert->execute([
            (int) $milestone['booking_id'],
            $milestoneId,
            (float) $milestone['amount'],
            $reference
        ]);
        $transactionId = (int) $pdo->lastInsertId();

        $update = $pdo->prepare(
            'UPDATE payment_milestones SET milestone_status = "PAID", paid_at = NOW() WHERE id = ?'
        );
        $update->execute([$milestoneId]);

        $pendingStmt = $pdo->prepare(
            'SELECT COUNT(*) AS cnt
             FROM payment_milestones
             WHERE booking_id = ? AND milestone_status <> "PAID"'
        );
        $pendingStmt->execute([(int) $milestone['booking_id']]);
        $pending = $pendingStmt->fetch();
        if ((int) ($pending['cnt'] ?? 0) === 0) {
            $invoiceUpdate = $pdo->prepare(
                'UPDATE invoices
                 SET invoice_status = "PAID"
                 WHERE booking_id = ? AND invoice_status = "ISSUED"'
            );
            $invoiceUpdate->execute([(int) $milestone['booking_id']]);
            log_activity(null, 'SYSTEM', 'invoice_marked_paid', 'booking', (int) $milestone['booking_id'], [
                'trigger' => 'all_milestones_paid',
            ]);
        }

        $pdo->commit();
        $ownerStmt = $pdo->prepare(
            'SELECT s.provider_id, b.client_id
             FROM bookings b
             INNER JOIN services s ON s.id = b.service_id
             WHERE b.id = ? LIMIT 1'
        );
        $ownerStmt->execute([(int) $milestone['booking_id']]);
        $owner = $ownerStmt->fetch();
        if ($owner) {
            notify_safe(
                (int) $owner['provider_id'],
                'Milestone Payment Received',
                'Milestone payment recorded for booking #' . (int) $milestone['booking_id'] . '.'
            );
            notify_safe(
                (int) $owner['client_id'],
                'Milestone Payment Successful',
                'Your payment for booking #' . (int) $milestone['booking_id'] . ' is successful.'
            );
        }
        return $transactionId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function create_quote(int $bookingId, float $subtotal, float $tax, float $discount): int
{
    $total = max(0, ($subtotal + $tax) - $discount);
    $pdo = db();

    $acceptedQuoteStmt = $pdo->prepare(
        'SELECT id
         FROM quotes
         WHERE booking_id = ? AND quote_status = "ACCEPTED"
         LIMIT 1'
    );
    $acceptedQuoteStmt->execute([$bookingId]);
    if ($acceptedQuoteStmt->fetch()) {
        throw new RuntimeException('Cannot create a new quote after an ACCEPTED quote exists for this booking.');
    }

    $budgetStmt = $pdo->prepare('SELECT estimated_budget, booking_status FROM bookings WHERE id = ? LIMIT 1');
    $budgetStmt->execute([$bookingId]);
    $booking = $budgetStmt->fetch();
    if (!$booking) {
        throw new RuntimeException('Booking not found.');
    }
    if ((string) $booking['booking_status'] !== 'APPROVED') {
        throw new RuntimeException('Quote can be created only for APPROVED bookings.');
    }
    if ($total > (float) $booking['estimated_budget']) {
        throw new RuntimeException('Quote total cannot exceed client booking budget.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO quotes (booking_id, subtotal, tax, discount, total, quote_status)
         VALUES (?, ?, ?, ?, ?, "SENT")'
    );
    $stmt->execute([$bookingId, $subtotal, $tax, $discount, $total]);
    $id = (int) $pdo->lastInsertId();
    $ownerStmt = $pdo->prepare(
        'SELECT b.client_id
         FROM bookings b
         WHERE b.id = ? LIMIT 1'
    );
    $ownerStmt->execute([$bookingId]);
    $owner = $ownerStmt->fetch();
    if ($owner) {
        notify_safe(
            (int) $owner['client_id'],
            'New Quote Received',
            'A new quote #' . $id . ' has been created for your booking #' . $bookingId . '.'
        );
    }
    return $id;
}

function generate_invoice(int $quoteId): int
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT booking_id, total, quote_status FROM quotes WHERE id = ? FOR UPDATE');
        $stmt->execute([$quoteId]);
        $quote = $stmt->fetch();
        if (!$quote) {
            throw new RuntimeException('Quote not found.');
        }
        if ((string) $quote['quote_status'] !== 'ACCEPTED') {
            throw new RuntimeException('Invoice can be generated only after client accepts the quote.');
        }

        $existsStmt = $pdo->prepare('SELECT id FROM invoices WHERE quote_id = ? LIMIT 1');
        $existsStmt->execute([$quoteId]);
        if ($existsStmt->fetch()) {
            throw new RuntimeException('Invoice already exists for this quote.');
        }

        $invoiceNo = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $insert = $pdo->prepare(
            'INSERT INTO invoices (quote_id, booking_id, invoice_no, total_amount, invoice_status)
             VALUES (?, ?, ?, ?, "ISSUED")'
        );
        $insert->execute([$quoteId, (int) $quote['booking_id'], $invoiceNo, (float) $quote['total']]);
        $invoiceId = (int) $pdo->lastInsertId();

        // Create milestone schedule only after invoice issuance, once per booking.
        $milestoneExists = $pdo->prepare('SELECT id FROM payment_milestones WHERE booking_id = ? LIMIT 1');
        $milestoneExists->execute([(int) $quote['booking_id']]);
        if (!$milestoneExists->fetch()) {
            $bookingStmt = $pdo->prepare('SELECT event_date FROM bookings WHERE id = ? LIMIT 1');
            $bookingStmt->execute([(int) $quote['booking_id']]);
            $booking = $bookingStmt->fetch();
            if (!$booking) {
                throw new RuntimeException('Booking not found for milestone generation.');
            }

            $total = (float) $quote['total'];
            $advance = round($total * 0.30, 2);
            $mid = round($total * 0.40, 2);
            $final = round($total * 0.30, 2);

            $milestoneStmt = $pdo->prepare(
                'INSERT INTO payment_milestones (booking_id, milestone_name, amount, due_date, milestone_status)
                 VALUES (?, ?, ?, ?, "DUE")'
            );
            $dueDate = (string) $booking['event_date'];
            $milestoneStmt->execute([(int) $quote['booking_id'], 'Advance Payment', $advance, $dueDate]);
            $milestoneStmt->execute([(int) $quote['booking_id'], 'Midway Payment', $mid, $dueDate]);
            $milestoneStmt->execute([(int) $quote['booking_id'], 'Final Payment', $final, $dueDate]);
            log_activity(null, 'SYSTEM', 'milestones_generated', 'booking', (int) $quote['booking_id'], [
                'invoice_id' => $invoiceId,
                'total' => $total,
            ]);
        }

        $pdo->commit();
        $ownerStmt = $pdo->prepare(
            'SELECT b.client_id
             FROM bookings b
             WHERE b.id = ? LIMIT 1'
        );
        $ownerStmt->execute([(int) $quote['booking_id']]);
        $owner = $ownerStmt->fetch();
        if ($owner) {
            notify_safe(
                (int) $owner['client_id'],
                'Invoice Issued',
                'Invoice ' . $invoiceNo . ' has been issued for booking #' . (int) $quote['booking_id'] . '.'
            );
        }
        return $invoiceId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function create_bid_request(
    int $clientId,
    string $eventType,
    string $city,
    float $budget,
    string $eventDate,
    int $guestCount = 0
): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO bid_requests (client_id, event_type, city, budget, event_date, guest_count, request_status)
         VALUES (?, ?, ?, ?, ?, ?, "OPEN")'
    );
    $stmt->execute([$clientId, $eventType, $city, $budget, $eventDate, max(0, $guestCount)]);
    $id = (int) $pdo->lastInsertId();
    log_activity($clientId, 'CLIENT', 'bid_request_created', 'bid_request', $id, [
        'event_type' => $eventType,
        'city' => $city,
        'budget' => $budget,
        'event_date' => $eventDate,
        'guest_count' => $guestCount,
    ]);
    notify_safe(
        $clientId,
        'Bid Request Published',
        'Your bid request #' . $id . ' is now open for providers.'
    );
    return $id;
}

function convert_awarded_bid_to_booking(int $clientId, int $bidRequestId): int
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $requestStmt = $pdo->prepare(
            'SELECT id, event_type, city, budget, event_date, guest_count, request_status
             FROM bid_requests
             WHERE id = ? AND client_id = ?
             FOR UPDATE'
        );
        $requestStmt->execute([$bidRequestId, $clientId]);
        $request = $requestStmt->fetch();
        if (!$request) {
            throw new RuntimeException('Bid request not found.');
        }
        if ((string) $request['request_status'] !== 'AWARDED') {
            throw new RuntimeException('Only AWARDED requests can be converted to booking.');
        }

        $acceptedStmt = $pdo->prepare(
            'SELECT id, provider_id, quoted_price
             FROM bids
             WHERE bid_request_id = ? AND bid_status = "ACCEPTED"
             LIMIT 1
             FOR UPDATE'
        );
        $acceptedStmt->execute([$bidRequestId]);
        $accepted = $acceptedStmt->fetch();
        if (!$accepted) {
            throw new RuntimeException('No accepted bid found for this request.');
        }

        $customServiceStmt = $pdo->prepare(
            'INSERT INTO services (provider_id, title, city, event_type, description, base_price, status)
             VALUES (?, ?, ?, ?, ?, ?, "INACTIVE")'
        );
        $customServiceStmt->execute([
            (int) $accepted['provider_id'],
            'Custom Bid Booking #' . (int) $bidRequestId,
            (string) $request['city'],
            (string) $request['event_type'],
            'Auto-generated custom service from accepted bid request #' . (int) $bidRequestId,
            (float) $accepted['quoted_price'],
        ]);
        $customServiceId = (int) $pdo->lastInsertId();

        $estimatedBudget = (float) $accepted['quoted_price'];
        $insertBooking = $pdo->prepare(
            'INSERT INTO bookings (client_id, service_id, city, event_type, event_date, guest_count, estimated_budget, booking_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, "PENDING")'
        );
        $insertBooking->execute([
            $clientId,
            (int) $customServiceId,
            (string) $request['city'],
            (string) $request['event_type'],
            (string) $request['event_date'],
            max(0, (int) $request['guest_count']),
            $estimatedBudget,
        ]);
        $bookingId = (int) $pdo->lastInsertId();

        $closeRequest = $pdo->prepare('UPDATE bid_requests SET request_status = "CLOSED" WHERE id = ? AND client_id = ?');
        $closeRequest->execute([$bidRequestId, $clientId]);

        $pdo->commit();
        log_activity($clientId, 'CLIENT', 'awarded_bid_converted', 'booking', $bookingId, [
            'bid_request_id' => $bidRequestId,
            'accepted_provider_id' => (int) $accepted['provider_id'],
            'quoted_price' => (float) $accepted['quoted_price'],
        ]);
        return $bookingId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function submit_bid(int $bidRequestId, int $providerId, float $quotedPrice, string $proposal): int
{
    $pdo = db();

    $requestStmt = $pdo->prepare(
        'SELECT budget, request_status
         FROM bid_requests
         WHERE id = ?
         LIMIT 1'
    );
    $requestStmt->execute([$bidRequestId]);
    $request = $requestStmt->fetch();
    if (!$request) {
        throw new RuntimeException('Bid request not found.');
    }
    if ((string) $request['request_status'] !== 'OPEN') {
        throw new RuntimeException('You can submit bids only for OPEN requests.');
    }
    if ($quotedPrice > (float) $request['budget']) {
        throw new RuntimeException('Quoted price cannot exceed client budget.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO bids (bid_request_id, provider_id, quoted_price, proposal, bid_status)
         VALUES (?, ?, ?, ?, "SUBMITTED")'
    );
    $stmt->execute([$bidRequestId, $providerId, $quotedPrice, $proposal]);
    $id = (int) $pdo->lastInsertId();
    log_activity($providerId, 'PROVIDER', 'bid_submitted', 'bid', $id, [
        'bid_request_id' => $bidRequestId,
        'quoted_price' => $quotedPrice,
    ]);
    $clientStmt = $pdo->prepare('SELECT client_id FROM bid_requests WHERE id = ? LIMIT 1');
    $clientStmt->execute([$bidRequestId]);
    $client = $clientStmt->fetch();
    if ($client) {
        notify_safe(
            (int) $client['client_id'],
            'New Bid Submitted',
            'A provider submitted a bid for your request #' . $bidRequestId . '.'
        );
    }
    return $id;
}

function compare_bids(int $bidRequestId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT b.id, u.name AS provider_name, b.quoted_price, b.proposal, b.bid_status, b.created_at
         FROM bids b
         INNER JOIN users u ON u.id = b.provider_id
         WHERE b.bid_request_id = ?
         ORDER BY b.quoted_price ASC, b.created_at ASC'
    );
    $stmt->execute([$bidRequestId]);
    return $stmt->fetchAll();
}

function award_bid(int $clientId, int $bidRequestId, int $bidId): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $requestStmt = $pdo->prepare(
            'SELECT id, request_status
             FROM bid_requests
             WHERE id = ? AND client_id = ?
             FOR UPDATE'
        );
        $requestStmt->execute([$bidRequestId, $clientId]);
        $request = $requestStmt->fetch();
        if (!$request) {
            throw new RuntimeException('Bid request not found.');
        }
        if ((string) $request['request_status'] !== 'OPEN') {
            throw new RuntimeException('Only OPEN requests can be awarded.');
        }

        $bidStmt = $pdo->prepare(
            'SELECT id, provider_id
             FROM bids
             WHERE id = ? AND bid_request_id = ?
             FOR UPDATE'
        );
        $bidStmt->execute([$bidId, $bidRequestId]);
        $selectedBid = $bidStmt->fetch();
        if (!$selectedBid) {
            throw new RuntimeException('Selected bid does not belong to this request.');
        }

        $rejectAll = $pdo->prepare('UPDATE bids SET bid_status = "REJECTED" WHERE bid_request_id = ?');
        $rejectAll->execute([$bidRequestId]);

        $acceptOne = $pdo->prepare('UPDATE bids SET bid_status = "ACCEPTED" WHERE id = ? AND bid_request_id = ?');
        $acceptOne->execute([$bidId, $bidRequestId]);

        $awardRequest = $pdo->prepare('UPDATE bid_requests SET request_status = "AWARDED" WHERE id = ?');
        $awardRequest->execute([$bidRequestId]);

        $pdo->commit();
        log_activity($clientId, 'CLIENT', 'bid_awarded', 'bid_request', $bidRequestId, [
            'accepted_bid_id' => $bidId,
        ]);
        notify_safe(
            (int) $selectedBid['provider_id'],
            'Bid Awarded',
            'Congratulations. Your bid #' . $bidId . ' was awarded for request #' . $bidRequestId . '.'
        );
        notify_safe(
            $clientId,
            'Bid Awarded Successfully',
            'You awarded bid #' . $bidId . ' for request #' . $bidRequestId . '.'
        );
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function reject_bid(int $clientId, int $bidRequestId, int $bidId): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $requestStmt = $pdo->prepare(
            'SELECT id, request_status
             FROM bid_requests
             WHERE id = ? AND client_id = ?
             FOR UPDATE'
        );
        $requestStmt->execute([$bidRequestId, $clientId]);
        $request = $requestStmt->fetch();
        if (!$request) {
            throw new RuntimeException('Bid request not found.');
        }
        if ((string) $request['request_status'] !== 'OPEN') {
            throw new RuntimeException('Only OPEN requests can be edited.');
        }

        $providerStmt = $pdo->prepare('SELECT provider_id FROM bids WHERE id = ? AND bid_request_id = ? LIMIT 1');
        $providerStmt->execute([$bidId, $bidRequestId]);
        $providerBid = $providerStmt->fetch();

        $reject = $pdo->prepare(
            'UPDATE bids
             SET bid_status = "REJECTED"
             WHERE id = ? AND bid_request_id = ?'
        );
        $reject->execute([$bidId, $bidRequestId]);
        if ($reject->rowCount() === 0) {
            throw new RuntimeException('Bid not found.');
        }

        $pdo->commit();
        log_activity($clientId, 'CLIENT', 'bid_rejected', 'bid', $bidId, [
            'bid_request_id' => $bidRequestId,
        ]);
        if ($providerBid) {
            notify_safe(
                (int) $providerBid['provider_id'],
                'Bid Rejected',
                'Your bid #' . $bidId . ' was rejected for request #' . $bidRequestId . '.'
            );
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function close_bid_request(int $clientId, int $bidRequestId): void
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'UPDATE bid_requests
         SET request_status = "CLOSED"
         WHERE id = ? AND client_id = ? AND request_status = "OPEN"'
    );
    $stmt->execute([$bidRequestId, $clientId]);
    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Only OPEN requests can be closed.');
    }
    log_activity($clientId, 'CLIENT', 'bid_request_closed', 'bid_request', $bidRequestId);
    notify_safe(
        $clientId,
        'Bid Request Closed',
        'Your bid request #' . $bidRequestId . ' has been closed.'
    );
}

function create_checklist(int $clientId, int $bookingId): int
{
    $pdo = db();
    $bookingStmt = $pdo->prepare(
        'SELECT b.booking_status, b.client_id, s.title AS service_title
         FROM bookings b
         INNER JOIN services s ON s.id = b.service_id
         WHERE b.id = ?
         LIMIT 1'
    );
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch();
    if (!$booking) {
        throw new RuntimeException('Booking not found.');
    }
    if (strtoupper((string) ($booking['booking_status'] ?? '')) !== 'APPROVED') {
        throw new RuntimeException('Checklist can be created only for APPROVED bookings.');
    }
    if ((int) ($booking['client_id'] ?? 0) !== $clientId) {
        throw new RuntimeException('You can create checklist only for your own booking.');
    }

    $existingStmt = $pdo->prepare(
        'SELECT id
         FROM planning_checklists
         WHERE booking_id = ?
         LIMIT 1'
    );
    $existingStmt->execute([$bookingId]);
    $existing = $existingStmt->fetch();
    if ($existing) {
        throw new RuntimeException('Checklist already exists for this booking.');
    }

    $checklistTitle = 'Booking #' . $bookingId . ' - ' . (string) ($booking['service_title'] ?? 'Event');
    $stmt = $pdo->prepare(
        'INSERT INTO planning_checklists (booking_id, title) VALUES (?, ?)'
    );
    $stmt->execute([$bookingId, $checklistTitle]);
    return (int) $pdo->lastInsertId();
}

function add_checklist_item(int $checklistId, string $task, string $dueDate): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO checklist_items (checklist_id, task_title, due_date, item_status)
         VALUES (?, ?, ?, "PENDING")'
    );
    $stmt->execute([$checklistId, $task, $dueDate]);
    return (int) $pdo->lastInsertId();
}

function update_checklist_item_status(int $itemId, string $status): void
{
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE checklist_items SET item_status = ? WHERE id = ?');
    $stmt->execute([$status, $itemId]);
}

function normalize_notification_channel(string $channel): string
{
    // WhiteGlove currently supports APP notifications only.
    return 'APP';
}

function notify_user(int $userId, string $channel, string $title, string $message): int
{
    $pdo = db();
    $channel = 'APP';
    $title = trim($title);
    $message = trim($message);
    if ($title === '' || $message === '') {
        throw new RuntimeException('Notification title and message are required.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO notifications (user_id, channel, title, message, delivery_status)
         VALUES (?, ?, ?, ?, "QUEUED")'
    );
    $stmt->execute([$userId, $channel, $title, $message]);
    $notificationId = (int) $pdo->lastInsertId();
    $statusStmt = $pdo->prepare('UPDATE notifications SET delivery_status = "SENT" WHERE id = ?');
    $statusStmt->execute([$notificationId]);
    return $notificationId;
}

function refund_policy_percentage_for_event_date(string $eventDate): float
{
    $todayTs = strtotime(date('Y-m-d'));
    $eventTs = strtotime($eventDate);
    if ($eventTs === false || $todayTs === false) {
        return 0.0;
    }

    $daysBefore = (int) floor(($eventTs - $todayTs) / 86400);
    if ($daysBefore >= 30) {
        return 80.0;
    }
    if ($daysBefore >= 15) {
        return 50.0;
    }
    if ($daysBefore >= 7) {
        return 20.0;
    }
    return 0.0;
}

function booking_total_paid(PDO $pdo, int $bookingId): float
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0) AS paid_total
         FROM transactions
         WHERE booking_id = ? AND payment_status = "SUCCESS"'
    );
    $stmt->execute([$bookingId]);
    $row = $stmt->fetch();
    return (float) ($row['paid_total'] ?? 0);
}

function booking_total_refunded(PDO $pdo, int $bookingId): float
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(refund_amount), 0) AS refunded_total
         FROM refund_requests
         WHERE booking_id = ? AND refund_status IN ("APPROVED", "PAID")'
    );
    $stmt->execute([$bookingId]);
    $row = $stmt->fetch();
    return (float) ($row['refunded_total'] ?? 0);
}

function create_refund_request_record(
    PDO $pdo,
    int $bookingId,
    int $clientId,
    string $reason,
    string $refundStatus = 'REQUESTED',
    ?string $providerNote = null
): int {
    $status = strtoupper(trim($refundStatus));
    if (!in_array($status, ['REQUESTED', 'APPROVED', 'REJECTED', 'PAID'], true)) {
        throw new RuntimeException('Invalid refund status.');
    }

    $activeStmt = $pdo->prepare(
        'SELECT id
         FROM refund_requests
         WHERE booking_id = ? AND refund_status IN ("REQUESTED", "APPROVED", "PAID")
         LIMIT 1'
    );
    $activeStmt->execute([$bookingId]);
    if ($activeStmt->fetch()) {
        throw new RuntimeException('An active refund already exists for this booking.');
    }

    $bookingStmt = $pdo->prepare('SELECT event_date FROM bookings WHERE id = ? LIMIT 1');
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch();
    if (!$booking) {
        throw new RuntimeException('Booking not found.');
    }

    $percentage = refund_policy_percentage_for_event_date((string) $booking['event_date']);
    $paidTotal = booking_total_paid($pdo, $bookingId);
    $alreadyRefunded = booking_total_refunded($pdo, $bookingId);
    $policyAmount = round(($paidTotal * $percentage) / 100, 2);
    $availableAmount = max(0, $paidTotal - $alreadyRefunded);
    $refundAmount = min($policyAmount, $availableAmount);

    $insert = $pdo->prepare(
        'INSERT INTO refund_requests (booking_id, reason, provider_note, refund_percentage, refund_amount, refund_status, paid_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $paidAt = $status === 'PAID' ? date('Y-m-d H:i:s') : null;
    $insert->execute([
        $bookingId,
        trim($reason) !== '' ? trim($reason) : 'Cancellation refund initiated.',
        $providerNote,
        $percentage,
        $refundAmount,
        $status,
        $paidAt,
    ]);
    $refundId = (int) $pdo->lastInsertId();

    log_activity($clientId, 'CLIENT', 'refund_requested', 'refund_request', $refundId, [
        'booking_id' => $bookingId,
        'refund_percentage' => $percentage,
        'refund_amount' => $refundAmount,
        'refund_status' => $status,
    ]);

    return $refundId;
}

function request_refund(int $bookingId, string $reason): int
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT event_date, client_id, booking_status FROM bookings WHERE id = ?');
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();
    if (!$booking) {
        throw new RuntimeException('Booking not found.');
    }
    if (strtoupper((string) ($booking['booking_status'] ?? '')) !== 'CANCELLED') {
        throw new RuntimeException('Refund can be initiated only for CANCELLED bookings.');
    }

    $id = create_refund_request_record(
        $pdo,
        $bookingId,
        (int) $booking['client_id'],
        $reason,
        'REQUESTED'
    );
    notify_safe(
        (int) $booking['client_id'],
        'Refund Requested',
        'Refund request #' . $id . ' has been created for booking #' . $bookingId . '.'
    );
    return $id;
}

function request_cancellation(int $bookingId, int $clientId, string $reason): int
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $bookingStmt = $pdo->prepare(
            'SELECT b.id, b.booking_status, b.client_id, s.provider_id
             FROM bookings b
             INNER JOIN services s ON s.id = b.service_id
             WHERE b.id = ? AND b.client_id = ?
             FOR UPDATE'
        );
        $bookingStmt->execute([$bookingId, $clientId]);
        $booking = $bookingStmt->fetch();
        if (!$booking) {
            throw new RuntimeException('Booking not found.');
        }
        if ((string) $booking['booking_status'] !== 'APPROVED') {
            throw new RuntimeException('Cancellation request is allowed only for APPROVED bookings.');
        }

        $existingStmt = $pdo->prepare(
            'SELECT id
             FROM cancellation_requests
             WHERE booking_id = ? AND request_status = "REQUESTED"
             LIMIT 1'
        );
        $existingStmt->execute([$bookingId]);
        if ($existingStmt->fetch()) {
            throw new RuntimeException('A cancellation request is already pending for this booking.');
        }

        $insert = $pdo->prepare(
            'INSERT INTO cancellation_requests (booking_id, client_id, provider_id, reason, request_status)
             VALUES (?, ?, ?, ?, "REQUESTED")'
        );
        $insert->execute([
            $bookingId,
            $clientId,
            (int) $booking['provider_id'],
            trim($reason) !== '' ? trim($reason) : 'Client requested cancellation.',
        ]);
        $requestId = (int) $pdo->lastInsertId();

        $pdo->commit();
        log_activity($clientId, 'CLIENT', 'cancellation_requested', 'cancellation_request', $requestId, [
            'booking_id' => $bookingId,
        ]);
        notify_safe(
            (int) $booking['provider_id'],
            'Cancellation Request Received',
            'Client requested cancellation for booking #' . $bookingId . '.'
        );
        notify_safe(
            $clientId,
            'Cancellation Request Submitted',
            'Your cancellation request #' . $requestId . ' for booking #' . $bookingId . ' is pending provider decision.'
        );
        return $requestId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function provider_decide_cancellation_request(int $requestId, int $providerId, string $decision, string $providerNote = ''): array
{
    $pdo = db();
    $decision = strtoupper(trim($decision));
    if (!in_array($decision, ['APPROVED', 'REJECTED'], true)) {
        throw new RuntimeException('Invalid cancellation decision.');
    }

    $pdo->beginTransaction();
    try {
        $requestStmt = $pdo->prepare(
            'SELECT cr.id, cr.booking_id, cr.client_id, cr.provider_id, cr.reason, cr.request_status, b.booking_status
             FROM cancellation_requests cr
             INNER JOIN bookings b ON b.id = cr.booking_id
             WHERE cr.id = ? AND cr.provider_id = ?
             FOR UPDATE'
        );
        $requestStmt->execute([$requestId, $providerId]);
        $request = $requestStmt->fetch();
        if (!$request) {
            throw new RuntimeException('Cancellation request not found.');
        }
        if ((string) $request['request_status'] !== 'REQUESTED') {
            throw new RuntimeException('Cancellation request is already processed.');
        }

        $refundId = null;
        if ($decision === 'APPROVED') {
            if ((string) $request['booking_status'] !== 'APPROVED') {
                throw new RuntimeException('Only APPROVED bookings can be cancelled through this request.');
            }

            $cancelBooking = $pdo->prepare('UPDATE bookings SET booking_status = "CANCELLED" WHERE id = ?');
            $cancelBooking->execute([(int) $request['booking_id']]);

            $refundId = create_refund_request_record(
                $pdo,
                (int) $request['booking_id'],
                (int) $request['client_id'],
                'Auto-initiated after provider-approved cancellation.',
                'APPROVED',
                trim($providerNote) !== '' ? trim($providerNote) : null
            );
        }

        $updateRequest = $pdo->prepare(
            'UPDATE cancellation_requests
             SET request_status = ?, provider_note = ?, resolved_at = NOW()
             WHERE id = ?'
        );
        $updateRequest->execute([
            $decision,
            trim($providerNote) !== '' ? trim($providerNote) : null,
            $requestId,
        ]);

        $pdo->commit();

        log_activity($providerId, 'PROVIDER', 'cancellation_request_decided', 'cancellation_request', $requestId, [
            'booking_id' => (int) $request['booking_id'],
            'decision' => $decision,
            'refund_id' => $refundId,
        ]);

        if ($decision === 'APPROVED') {
            notify_safe(
                (int) $request['client_id'],
                'Cancellation Approved',
                'Your cancellation for booking #' . (int) $request['booking_id'] . ' was approved. Booking is cancelled and refund #' . (int) $refundId . ' has been initiated.'
            );
        } else {
            notify_safe(
                (int) $request['client_id'],
                'Cancellation Rejected',
                'Your cancellation request for booking #' . (int) $request['booking_id'] . ' was rejected by the provider.'
            );
        }

        return [
            'decision' => $decision,
            'booking_id' => (int) $request['booking_id'],
            'refund_id' => $refundId,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function provider_mark_refund_paid(int $refundId, int $providerId): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT rr.id, rr.booking_id, rr.refund_status, rr.refund_amount, b.client_id, s.provider_id
             FROM refund_requests rr
             INNER JOIN bookings b ON b.id = rr.booking_id
             INNER JOIN services s ON s.id = b.service_id
             WHERE rr.id = ?
             FOR UPDATE'
        );
        $stmt->execute([$refundId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Refund request not found.');
        }
        if ((int) $row['provider_id'] !== $providerId) {
            throw new RuntimeException('You are not allowed to settle this refund.');
        }
        if ((string) $row['refund_status'] !== 'APPROVED') {
            throw new RuntimeException('Only APPROVED refunds can be marked as PAID.');
        }

        $update = $pdo->prepare(
            'UPDATE refund_requests
             SET refund_status = "PAID", paid_at = NOW()
             WHERE id = ?'
        );
        $update->execute([$refundId]);

        $pdo->commit();

        log_activity($providerId, 'PROVIDER', 'refund_marked_paid', 'refund_request', $refundId, [
            'booking_id' => (int) $row['booking_id'],
            'refund_amount' => (float) $row['refund_amount'],
        ]);
        notify_safe(
            (int) $row['client_id'],
            'Refund Paid',
            'Refund #' . $refundId . ' for booking #' . (int) $row['booking_id'] . ' has been marked as paid by the provider.'
        );
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function submit_review(int $bookingId, int $clientId, int $providerId, int $rating, string $comment): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT b.booking_status, s.provider_id
         FROM bookings b
         INNER JOIN services s ON s.id = b.service_id
         WHERE b.id = ? AND b.client_id = ?'
    );
    $stmt->execute([$bookingId, $clientId]);
    $booking = $stmt->fetch();
    if (!$booking || $booking['booking_status'] !== 'COMPLETED') {
        throw new RuntimeException('Review allowed only for completed bookings.');
    }
    if ((int) ($booking['provider_id'] ?? 0) !== $providerId) {
        throw new RuntimeException('Invalid provider selected for this booking.');
    }

    $existsStmt = $pdo->prepare(
        'SELECT id FROM reviews WHERE booking_id = ? AND client_id = ? LIMIT 1'
    );
    $existsStmt->execute([$bookingId, $clientId]);
    if ($existsStmt->fetch()) {
        throw new RuntimeException('You have already submitted a review for this booking.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO reviews (booking_id, client_id, provider_id, rating, comment, is_verified)
         VALUES (?, ?, ?, ?, ?, 1)'
    );
    $insert->execute([$bookingId, $clientId, $providerId, $rating, $comment]);
    $id = (int) $pdo->lastInsertId();
    log_activity($clientId, 'CLIENT', 'review_submitted', 'review', $id, [
        'booking_id' => $bookingId,
        'provider_id' => $providerId,
        'rating' => $rating,
    ]);
    return $id;
}

function admin_risk_dashboard(): array
{
    $pdo = db();

    $totals = $pdo->query(
        'SELECT
            (SELECT COUNT(*) FROM users) AS total_users,
            (SELECT COUNT(*) FROM bookings) AS total_bookings,
            (SELECT COUNT(*) FROM bookings WHERE booking_status = "CANCELLED") AS cancelled_bookings,
            (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE payment_status = "SUCCESS") AS total_revenue'
    )->fetch();

    $topCategories = $pdo->query(
        'SELECT event_type, COUNT(*) AS cnt
         FROM services
         GROUP BY event_type
         ORDER BY cnt DESC
         LIMIT 5'
    )->fetchAll();

    $pendingProviders = $pdo->query(
        'SELECT COUNT(*) AS pending_approvals FROM provider_profiles WHERE approval_status = "PENDING"'
    )->fetch();

    $cancelRate = 0.0;
    if ((int) $totals['total_bookings'] > 0) {
        $cancelRate = ((int) $totals['cancelled_bookings'] / (int) $totals['total_bookings']) * 100;
    }

    return [
        'summary' => [
            'total_users' => (int) $totals['total_users'],
            'total_bookings' => (int) $totals['total_bookings'],
            'cancelled_bookings' => (int) $totals['cancelled_bookings'],
            'cancellation_rate_percent' => round($cancelRate, 2),
            'total_revenue' => (float) $totals['total_revenue'],
            'pending_provider_approvals' => (int) $pendingProviders['pending_approvals'],
        ],
        'top_event_categories' => $topCategories,
    ];
}
