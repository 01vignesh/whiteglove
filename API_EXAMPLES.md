# API Examples (JSON)

Base URL:

`http://localhost/WhiteGlove/public/api.php?action=<action_name>`

Use `POST` with `Content-Type: application/json`.

## 1) Register user

Action: `register_user`

```json
{
  "name": "Aarav Client",
  "email": "aarav@example.com",
  "password": "test1234",
  "role": "CLIENT"
}
```

## 2) Create service (provider)

Action: `create_service`

```json
{
  "provider_id": 3,
  "title": "Premium Wedding Decor",
  "city": "Kolkata",
  "event_type": "Wedding",
  "base_price": 120000
}
```

## 3) Set availability

Action: `set_availability`

```json
{
  "service_id": 1,
  "date": "2026-12-15",
  "slot_status": "BLOCKED"
}
```

## 4) Create booking

Action: `create_booking`

```json
{
  "client_id": 2,
  "service_id": 1,
  "event_date": "2026-12-20",
  "guest_count": 300,
  "estimated_budget": 150000
}
```

## 5) Simulate payment (milestone)

Action: `simulate_payment`

```json
{
  "milestone_id": 1,
  "reference_no": "SIM-TXN-0001"
}
```

## 6) Create quote

Action: `create_quote`

```json
{
  "booking_id": 1,
  "subtotal": 140000,
  "tax": 7000,
  "discount": 5000
}
```

## 7) Generate invoice

Action: `generate_invoice`

```json
{
  "quote_id": 1
}
```

## 8) Create bid request

Action: `create_bid_request`

```json
{
  "client_id": 2,
  "event_type": "Corporate",
  "city": "Kolkata",
  "budget": 200000,
  "event_date": "2026-10-10"
}
```

## 9) Submit bid

Action: `submit_bid`

```json
{
  "bid_request_id": 1,
  "provider_id": 3,
  "quoted_price": 190000,
  "proposal": "Includes lighting, stage, catering and AV setup."
}
```

## 10) Compare bids

Action: `compare_bids`

```json
{
  "bid_request_id": 1
}
```

## 11) Checklist create + items

Action: `create_checklist`

```json
{
  "booking_id": 1,
  "title": "Wedding Master Checklist"
}
```

Action: `add_checklist_item`

```json
{
  "checklist_id": 1,
  "task_title": "Finalize menu tasting",
  "due_date": "2026-11-15"
}
```

Action: `update_checklist_item`

```json
{
  "item_id": 1,
  "status": "DONE"
}
```

## 12) Notification

Action: `notify_user`

```json
{
  "user_id": 2,
  "channel": "APP",
  "title": "Booking Approved",
  "message": "Your booking #1 has been approved."
}
```

## 13) Refund request

Action: `request_refund`

```json
{
  "booking_id": 1,
  "reason": "Date conflict due to venue issue."
}
```

## 14) Verified review

Action: `submit_review`

```json
{
  "booking_id": 1,
  "client_id": 2,
  "provider_id": 3,
  "rating": 5,
  "comment": "Excellent execution and coordination."
}
```

## 15) Recommendations

Action: `recommendations`

```json
{
  "client_id": 2
}
```

## 16) Admin risk dashboard

Action: `admin_dashboard`

```json
{}
```

