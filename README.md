# WhiteGlove Event Management System

WhiteGlove is a PHP + MySQL academic project for end-to-end event management with realistic modules:

- Role-based access (`Admin`, `Client`, `Service Provider`)
- Event/service browsing and booking
- Simulated milestone payments
- Vendor bidding and comparison
- Quotes + invoices
- Availability calendar
- Planning checklist
- Notifications
- Cancellation and refunds
- Verified reviews
- Admin analytics dashboard

## Tech Stack

- Frontend: HTML, CSS, Bootstrap
- Backend: PHP
- Database: MySQL

## Project Structure

```
WhiteGlove/
  app/
    config.php
    db.php
    functions.php
  database/
    schema.sql
  public/
    api.php
    index.php
```

## Setup

1. Create a MySQL database named `whiteglove`.
2. Import [`database/schema.sql`](./database/schema.sql).
3. Import [`database/seed_demo.sql`](./database/seed_demo.sql).
4. Import [`database/seed_dump_realistic.sql`](./database/seed_dump_realistic.sql).
5. Import [`database/seed_dump_premium.sql`](./database/seed_dump_premium.sql).
6. Create Client Account
7. Update DB credentials in [`app/config.php`](./app/config.php) or set env vars (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, optional `DB_CHARSET`).
8. Open `http://localhost/WhiteGlove/public/`.

## Notes

- Payments are simulated intentionally for academic use.
- API endpoints are available at `public/api.php` with JSON requests.
- This is a clean baseline intended for iterative expansion (auth hardening, full UI pages, test coverage).

## Auth Pages

- Register: `http://localhost/WhiteGlove/public/register.php`
- Login: `http://localhost/WhiteGlove/public/login.php`
- Role Dashboard: `http://localhost/WhiteGlove/public/dashboard.php`
- Admin Hub: `http://localhost/WhiteGlove/public/admin_hub.php`
- Admin Provider Approvals: `http://localhost/WhiteGlove/public/admin_providers.php`
- Admin User Management: `http://localhost/WhiteGlove/public/admin_users.php`
- Admin Booking Oversight: `http://localhost/WhiteGlove/public/admin_bookings.php`
- Admin Payments & Refunds: `http://localhost/WhiteGlove/public/admin_payments.php`
- Admin Reports: `http://localhost/WhiteGlove/public/admin_reports.php`
- Client Booking Center: `http://localhost/WhiteGlove/public/client_bookings.php`
- Client Hub: `http://localhost/WhiteGlove/public/client_hub.php`
- Client Bids: `http://localhost/WhiteGlove/public/client_bids.php`
- Client Milestones: `http://localhost/WhiteGlove/public/client_milestones.php`
- Client Quotes: `http://localhost/WhiteGlove/public/client_quotes.php`
- Client Invoices: `http://localhost/WhiteGlove/public/client_invoices.php`
- Client Checklists: `http://localhost/WhiteGlove/public/client_checklists.php`
- Client Reviews: `http://localhost/WhiteGlove/public/client_reviews.php`
- Client Notifications: `http://localhost/WhiteGlove/public/client_notifications.php`
- Provider Management Panel: `http://localhost/WhiteGlove/public/provider_manage.php`
- Provider Hub: `http://localhost/WhiteGlove/public/provider_hub.php`
- Provider Profile: `http://localhost/WhiteGlove/public/provider_profile.php`
- Provider Services: `http://localhost/WhiteGlove/public/provider_services.php`
- Provider Availability: `http://localhost/WhiteGlove/public/provider_availability.php`
- Provider Bookings: `http://localhost/WhiteGlove/public/provider_bookings.php`
- Provider Bids: `http://localhost/WhiteGlove/public/provider_bids.php`
- Provider Quotes: `http://localhost/WhiteGlove/public/provider_quotes.php`
- Provider Invoices: `http://localhost/WhiteGlove/public/provider_invoices.php`
- Provider Payments: `http://localhost/WhiteGlove/public/provider_payments.php`
- Provider Notifications: `http://localhost/WhiteGlove/public/provider_notifications.php`
- Admin Analytics Center: `http://localhost/WhiteGlove/public/admin_analytics.php`
- Client Experience Hub (redirects to Client Hub): `http://localhost/WhiteGlove/public/client_experience.php`
- Provider Workbench: `http://localhost/WhiteGlove/public/provider_workbench.php`

## Demo Credentials (after seed import)

- Admin: `admin@whiteglove.test` / `admin123`
- Client: `client@whiteglove.test` / `client123`
- Provider (approved): `provider1@whiteglove.test` / `provider123`
- Provider (pending): `provider2@whiteglove.test` / `provider234`
