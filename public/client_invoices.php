<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/partials/client_module_layout.php';

$user = require_role(['CLIENT']);
$pdo = db();

$invoiceStmt = $pdo->prepare(
    'SELECT i.id, i.invoice_no, i.total_amount, i.invoice_status, i.created_at, s.title
     FROM invoices i
     INNER JOIN bookings b ON b.id = i.booking_id
     INNER JOIN services s ON s.id = b.service_id
     WHERE b.client_id = ?
     ORDER BY i.created_at DESC'
);
$invoiceStmt->execute([$user['id']]);
$invoices = $invoiceStmt->fetchAll();
?>
<?php render_client_module_page_start('Client Invoices'); ?>
    <section class="provider-hero">
        <h1 class="h4 mb-1">Invoices</h1>
        <p class="mb-0">View issued invoices and payment states for your bookings.</p>
    </section>

    <section class="provider-card">
        <h2 class="h5">My Invoices</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>Invoice</th><th>Service</th><th>Total</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($invoices as $i): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $i['invoice_no'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $i['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo number_format((float) $i['total_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars((string) $i['invoice_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $i['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <div class="d-flex gap-2">
                                <a class="btn btn-sm btn-outline-primary" href="/WhiteGlove/public/invoice_view.php?id=<?php echo (int) $i['id']; ?>">View</a>
                                <a class="btn btn-sm btn-outline-secondary" href="/WhiteGlove/public/invoice_view.php?id=<?php echo (int) $i['id']; ?>&download=1">Download</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php render_client_module_page_end(); ?>
