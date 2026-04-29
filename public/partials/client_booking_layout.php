<?php

declare(strict_types=1);

if (!function_exists('render_client_booking_page_start')) {
    function render_client_booking_page_start(string $title): void
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        echo '<!doctype html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '    <meta charset="utf-8">';
        echo '    <meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '    <title>' . $safeTitle . ' | WhiteGlove</title>';
        echo '    <link rel="preconnect" href="https://fonts.googleapis.com">';
        echo '    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        echo '    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">';
        $cssPath = __DIR__ . '/../assets/client-bookings.css';
        $cssVersion = file_exists($cssPath) ? (string) filemtime($cssPath) : (string) time();
        echo '    <link href="/WhiteGlove/public/assets/client-bookings.css?v=' . rawurlencode($cssVersion) . '" rel="stylesheet">';
        echo '</head>';
        echo '<body>';
        echo '<header class="nav-wrap">';
        echo '    <nav class="nav">';
        echo '        <a class="brand" href="/WhiteGlove/public/index.php">WhiteGlove</a>';
        echo '        <div class="actions">';
        echo '            <a class="btn btn-warn" href="/WhiteGlove/public/logout.php">Logout</a>';
        echo '        </div>';
        echo '    </nav>';
        echo '</header>';
        echo '<main>';
    }
}

if (!function_exists('render_client_booking_page_end')) {
    function render_client_booking_page_end(string $suiteLabel = 'Event Management System'): void
    {
        $safeSuiteLabel = htmlspecialchars($suiteLabel, ENT_QUOTES, 'UTF-8');

        echo '</main>';
        echo '<footer>';
        echo '    <div class="foot">';
        echo '        <span>&copy; ' . date('Y') . ' WhiteGlove. All rights reserved.</span>';
        echo '        <span>' . $safeSuiteLabel . '</span>';
        echo '    </div>';
        echo '</footer>';
        echo '</body>';
        echo '</html>';
    }
}
