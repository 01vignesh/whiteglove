<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';

function pdf_escape(string $text): string
{
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace('(', '\\(', $text);
    $text = str_replace(')', '\\)', $text);
    $text = str_replace(["\r", "\n"], ' ', $text);
    return $text;
}

function build_invoice_pdf(array $invoice): string
{
    $lines = [
        'WhiteGlove Invoice',
        'Invoice No: ' . (string) $invoice['invoice_no'],
        'Invoice Status: ' . (string) $invoice['invoice_status'],
        'Invoice Date: ' . (string) $invoice['created_at'],
        'Booking ID: #' . (int) $invoice['booking_id'],
        'Service: ' . (string) $invoice['service_title'],
        'Event: ' . (string) $invoice['event_type'] . ' / ' . (string) $invoice['city'],
        'Event Date: ' . (string) $invoice['event_date'],
        'Guest Count: ' . (int) $invoice['guest_count'],
        'Client: ' . (string) $invoice['client_name'] . ' (' . (string) $invoice['client_email'] . ')',
        'Provider: ' . (string) $invoice['provider_name'] . ' (' . (string) $invoice['provider_email'] . ')',
        '--- Quote Breakdown ---',
        'Quote ID: #' . (int) $invoice['quote_id'] . ' | Status: ' . (string) $invoice['quote_status'],
        'Subtotal: ' . number_format((float) $invoice['subtotal'], 2),
        'Tax: ' . number_format((float) $invoice['tax'], 2),
        'Discount: ' . number_format((float) $invoice['discount'], 2),
        'Quote Total: ' . number_format((float) $invoice['quote_total'], 2),
        'Invoice Payable Amount (INR): ' . number_format((float) $invoice['total_amount'], 2),
    ];

    $stream = "BT\n/F1 11 Tf\n";
    $y = 800;
    foreach ($lines as $line) {
        $stream .= '1 0 0 1 50 ' . $y . ' Tm (' . pdf_escape($line) . ") Tj\n";
        $y -= 16;
    }
    $stream .= "ET\n";

    $objects = [];
    $objects[] = '1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj';
    $objects[] = '2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj';
    $objects[] = '3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>endobj';
    $objects[] = '4 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj';
    $objects[] = '5 0 obj<< /Length ' . strlen($stream) . " >>stream\n" . $stream . "endstream\nendobj";

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    foreach ($objects as $obj) {
        $offsets[] = strlen($pdf);
        $pdf .= $obj . "\n";
    }

    $xrefPos = strlen($pdf);
    $count = count($objects) + 1;
    $pdf .= "xref\n0 " . $count . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i < $count; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer<< /Size " . $count . " /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";

    return $pdf;
}

$user = require_auth();
$pdo = db();
$invoiceId = (int) ($_GET['id'] ?? 0);
$download = (string) ($_GET['download'] ?? '') === '1';

if ($invoiceId <= 0) {
    http_response_code(400);
    echo 'Invalid invoice id.';
    exit;
}

$stmt = $pdo->prepare(
    'SELECT
        i.id,
        i.invoice_no,
        i.total_amount,
        i.invoice_status,
        i.created_at,
        i.quote_id,
        i.booking_id,
        q.subtotal,
        q.tax,
        q.discount,
        q.total AS quote_total,
        q.quote_status,
        b.client_id,
        b.event_date,
        b.guest_count,
        b.city,
        b.event_type,
        b.booking_status,
        s.id AS service_id,
        s.title AS service_title,
        s.provider_id,
        c.name AS client_name,
        c.email AS client_email,
        p.name AS provider_name,
        p.email AS provider_email
     FROM invoices i
     INNER JOIN quotes q ON q.id = i.quote_id
     INNER JOIN bookings b ON b.id = i.booking_id
     INNER JOIN services s ON s.id = b.service_id
     INNER JOIN users c ON c.id = b.client_id
     INNER JOIN users p ON p.id = s.provider_id
     WHERE i.id = ?
     LIMIT 1'
);
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    http_response_code(404);
    echo 'Invoice not found.';
    exit;
}

$role = (string) ($user['role'] ?? '');
$allowed = $role === 'ADMIN'
    || ($role === 'CLIENT' && (int) $invoice['client_id'] === (int) $user['id'])
    || ($role === 'PROVIDER' && (int) $invoice['provider_id'] === (int) $user['id']);

if (!$allowed) {
    http_response_code(403);
    echo 'Forbidden: You cannot access this invoice.';
    exit;
}

if ($download) {
    $fileName = preg_replace('/[^A-Za-z0-9\\-_.]/', '_', (string) $invoice['invoice_no']) . '.pdf';
    $pdfData = build_invoice_pdf($invoice);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . strlen($pdfData));
    echo $pdfData;
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice <?php echo htmlspecialchars((string) $invoice['invoice_no'], ENT_QUOTES, 'UTF-8'); ?> | WhiteGlove</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root { --ink:#132337; --muted:#5d7188; --bg:#f4f8fb; --surface:#fff; --border:#dde7ef; --primary:#0c6e84; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:"Plus Jakarta Sans",sans-serif; color:var(--ink); background:var(--bg); }
        .wrap { width:min(980px,100%); margin:0 auto; padding:1rem; }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:18px; padding:1rem; margin-top:1rem; }
        .hero { background:linear-gradient(140deg,#0f6f84,#13938d); color:#fff; border-radius:20px; padding:1rem; }
        .hero h1 { margin:0 0 .3rem; font-family:"Space Grotesk",sans-serif; }
        .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.8rem; }
        .label { color:var(--muted); font-size:.82rem; }
        .value { font-weight:700; margin-top:.15rem; word-break:break-word; }
        .actions { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:1rem; }
        .btn { border:0; border-radius:999px; padding:.55rem .9rem; text-decoration:none; font-weight:700; font-size:.84rem; display:inline-block; }
        .btn-primary { background:var(--primary); color:#fff; }
        .btn-line { border:1px solid #bdd0dd; color:#1f3f5a; background:#fff; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:.6rem .5rem; border-bottom:1px solid #edf0f4; text-align:left; font-size:.86rem; }
        th { color:var(--muted); font-size:.76rem; text-transform:uppercase; }
        @media (max-width: 700px) { .grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<main class="wrap">
    <section class="hero">
        <h1>Invoice <?php echo htmlspecialchars((string) $invoice['invoice_no'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p style="margin:0;">Detailed invoice document with quote breakdown and booking details.</p>
    </section>

    <section class="card">
        <div class="grid">
            <div><div class="label">Invoice No</div><div class="value"><?php echo htmlspecialchars((string) $invoice['invoice_no'], ENT_QUOTES, 'UTF-8'); ?></div></div>
            <div><div class="label">Invoice Status</div><div class="value"><?php echo htmlspecialchars((string) $invoice['invoice_status'], ENT_QUOTES, 'UTF-8'); ?></div></div>
            <div><div class="label">Invoice Date</div><div class="value"><?php echo htmlspecialchars((string) $invoice['created_at'], ENT_QUOTES, 'UTF-8'); ?></div></div>
            <div><div class="label">Booking ID</div><div class="value">#<?php echo (int) $invoice['booking_id']; ?></div></div>
            <div><div class="label">Service</div><div class="value"><?php echo htmlspecialchars((string) $invoice['service_title'], ENT_QUOTES, 'UTF-8'); ?></div></div>
            <div><div class="label">Event</div><div class="value"><?php echo htmlspecialchars((string) $invoice['event_type'] . ' / ' . (string) $invoice['city'], ENT_QUOTES, 'UTF-8'); ?></div></div>
            <div><div class="label">Event Date</div><div class="value"><?php echo htmlspecialchars((string) $invoice['event_date'], ENT_QUOTES, 'UTF-8'); ?></div></div>
            <div><div class="label">Guest Count</div><div class="value"><?php echo (int) $invoice['guest_count']; ?></div></div>
            <div><div class="label">Client</div><div class="value"><?php echo htmlspecialchars((string) $invoice['client_name'] . ' (' . (string) $invoice['client_email'] . ')', ENT_QUOTES, 'UTF-8'); ?></div></div>
            <div><div class="label">Provider</div><div class="value"><?php echo htmlspecialchars((string) $invoice['provider_name'] . ' (' . (string) $invoice['provider_email'] . ')', ENT_QUOTES, 'UTF-8'); ?></div></div>
        </div>
    </section>

    <section class="card">
        <h2 style="margin:0 0 .7rem;font-family:'Space Grotesk',sans-serif;">Quote Breakdown</h2>
        <table>
            <thead><tr><th>Quote</th><th>Subtotal</th><th>Tax</th><th>Discount</th><th>Total</th><th>Quote Status</th></tr></thead>
            <tbody>
            <tr>
                <td>#<?php echo (int) $invoice['quote_id']; ?></td>
                <td><?php echo number_format((float) $invoice['subtotal'], 2); ?></td>
                <td><?php echo number_format((float) $invoice['tax'], 2); ?></td>
                <td><?php echo number_format((float) $invoice['discount'], 2); ?></td>
                <td><?php echo number_format((float) $invoice['quote_total'], 2); ?></td>
                <td><?php echo htmlspecialchars((string) $invoice['quote_status'], ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
            </tbody>
        </table>
    </section>

    <section class="card">
        <div class="label">Invoice Amount Payable</div>
        <div class="value" style="font-size:1.3rem;">INR <?php echo number_format((float) $invoice['total_amount'], 2); ?></div>
        <?php if (!$download): ?>
            <div class="actions">
                <a class="btn btn-primary" href="/WhiteGlove/public/invoice_view.php?id=<?php echo (int) $invoice['id']; ?>&download=1">Download</a>
                <a class="btn btn-line" href="javascript:window.print();">Print</a>
                <a class="btn btn-line" href="/WhiteGlove/public/dashboard.php">Back to Dashboard</a>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
