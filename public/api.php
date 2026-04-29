<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/functions.php';

$action = $_GET['action'] ?? '';
$data = read_json_body();

try {
    switch ($action) {
        case 'register_user':
            $id = create_user(
                $data['name'] ?? '',
                $data['email'] ?? '',
                $data['password'] ?? '',
                $data['role'] ?? 'CLIENT'
            );
            json_response(['ok' => true, 'user_id' => $id]);
            break;

        case 'create_service':
            $id = create_service(
                (int) ($data['provider_id'] ?? 0),
                (string) ($data['title'] ?? ''),
                (string) ($data['city'] ?? ''),
                (string) ($data['event_type'] ?? ''),
                (float) ($data['base_price'] ?? 0),
                (string) ($data['description'] ?? '')
            );
            json_response(['ok' => true, 'service_id' => $id]);
            break;

        case 'set_availability':
            set_service_availability(
                (int) ($data['service_id'] ?? 0),
                (string) ($data['date'] ?? ''),
                (string) ($data['slot_status'] ?? 'AVAILABLE')
            );
            json_response(['ok' => true]);
            break;

        case 'create_booking':
            $id = create_booking(
                (int) ($data['client_id'] ?? 0),
                (int) ($data['service_id'] ?? 0),
                (string) ($data['event_date'] ?? ''),
                (int) ($data['guest_count'] ?? 0),
                (float) ($data['estimated_budget'] ?? 0)
            );
            json_response(['ok' => true, 'booking_id' => $id]);
            break;

        case 'simulate_payment':
            $id = simulate_payment(
                (int) ($data['milestone_id'] ?? 0),
                (string) ($data['reference_no'] ?? ('SIM-' . time()))
            );
            json_response(['ok' => true, 'transaction_id' => $id]);
            break;

        case 'create_quote':
            $id = create_quote(
                (int) ($data['booking_id'] ?? 0),
                (float) ($data['subtotal'] ?? 0),
                (float) ($data['tax'] ?? 0),
                (float) ($data['discount'] ?? 0)
            );
            json_response(['ok' => true, 'quote_id' => $id]);
            break;

        case 'generate_invoice':
            $id = generate_invoice((int) ($data['quote_id'] ?? 0));
            json_response(['ok' => true, 'invoice_id' => $id]);
            break;

        case 'create_bid_request':
            $id = create_bid_request(
                (int) ($data['client_id'] ?? 0),
                (string) ($data['event_type'] ?? ''),
                (string) ($data['city'] ?? ''),
                (float) ($data['budget'] ?? 0),
                (string) ($data['event_date'] ?? ''),
                (int) ($data['guest_count'] ?? 0)
            );
            json_response(['ok' => true, 'bid_request_id' => $id]);
            break;

        case 'submit_bid':
            $id = submit_bid(
                (int) ($data['bid_request_id'] ?? 0),
                (int) ($data['provider_id'] ?? 0),
                (float) ($data['quoted_price'] ?? 0),
                (string) ($data['proposal'] ?? '')
            );
            json_response(['ok' => true, 'bid_id' => $id]);
            break;

        case 'compare_bids':
            $bids = compare_bids((int) ($data['bid_request_id'] ?? 0));
            json_response(['ok' => true, 'bids' => $bids]);
            break;

        case 'award_bid':
            award_bid(
                (int) ($data['client_id'] ?? 0),
                (int) ($data['bid_request_id'] ?? 0),
                (int) ($data['bid_id'] ?? 0)
            );
            json_response(['ok' => true]);
            break;

        case 'reject_bid':
            reject_bid(
                (int) ($data['client_id'] ?? 0),
                (int) ($data['bid_request_id'] ?? 0),
                (int) ($data['bid_id'] ?? 0)
            );
            json_response(['ok' => true]);
            break;

        case 'close_bid_request':
            close_bid_request(
                (int) ($data['client_id'] ?? 0),
                (int) ($data['bid_request_id'] ?? 0)
            );
            json_response(['ok' => true]);
            break;

        case 'create_checklist':
            $id = create_checklist(
                (int) ($data['client_id'] ?? 0),
                (int) ($data['booking_id'] ?? 0)
            );
            json_response(['ok' => true, 'checklist_id' => $id]);
            break;

        case 'add_checklist_item':
            $id = add_checklist_item(
                (int) ($data['checklist_id'] ?? 0),
                (string) ($data['task_title'] ?? ''),
                (string) ($data['due_date'] ?? '')
            );
            json_response(['ok' => true, 'item_id' => $id]);
            break;

        case 'update_checklist_item':
            update_checklist_item_status((int) ($data['item_id'] ?? 0), (string) ($data['status'] ?? 'PENDING'));
            json_response(['ok' => true]);
            break;

        case 'notify_user':
            $id = notify_user(
                (int) ($data['user_id'] ?? 0),
                (string) ($data['channel'] ?? 'APP'),
                (string) ($data['title'] ?? ''),
                (string) ($data['message'] ?? '')
            );
            json_response(['ok' => true, 'notification_id' => $id]);
            break;

        case 'request_refund':
            $id = request_refund((int) ($data['booking_id'] ?? 0), (string) ($data['reason'] ?? ''));
            json_response(['ok' => true, 'refund_request_id' => $id]);
            break;

        case 'submit_review':
            $id = submit_review(
                (int) ($data['booking_id'] ?? 0),
                (int) ($data['client_id'] ?? 0),
                (int) ($data['provider_id'] ?? 0),
                (int) ($data['rating'] ?? 5),
                (string) ($data['comment'] ?? '')
            );
            json_response(['ok' => true, 'review_id' => $id]);
            break;

        case 'admin_dashboard':
            json_response(['ok' => true, 'dashboard' => admin_risk_dashboard()]);
            break;

        default:
            json_response(['ok' => false, 'error' => 'Unknown action.'], 404);
    }
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 400);
}

