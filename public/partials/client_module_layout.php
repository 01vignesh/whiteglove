<?php

declare(strict_types=1);

if (!function_exists('render_client_module_page_start')) {
    function render_client_module_page_start(
        string $title,
        string $backHref = '/WhiteGlove/public/client_hub.php',
        string $backLabel = 'Client Hub'
    ): void {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeBackHref = htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8');
        $safeBackLabel = htmlspecialchars($backLabel, ENT_QUOTES, 'UTF-8');

        echo '<!doctype html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '    <meta charset="utf-8">';
        echo '    <meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '    <title>' . $safeTitle . ' | WhiteGlove</title>';
        echo '    <link rel="preconnect" href="https://fonts.googleapis.com">';
        echo '    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        echo '    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">';
        echo '    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '    <link href="/WhiteGlove/public/assets/provider-modern.css" rel="stylesheet">';
        echo '</head>';
        echo '<body>';
        echo '<header class="nav-wrap">';
        echo '    <nav class="nav-modern">';
        echo '        <a class="brand-modern" href="/WhiteGlove/public/index.php">WhiteGlove</a>';
        echo '        <div class="actions-modern">';
        echo '            <a class="btn-modern btn-modern-line" href="' . $safeBackHref . '">' . $safeBackLabel . '</a>';
        echo '            <a class="btn-modern btn-modern-warn" href="/WhiteGlove/public/logout.php">Logout</a>';
        echo '        </div>';
        echo '    </nav>';
        echo '</header>';
        echo '<main class="provider-main">';
    }
}

if (!function_exists('render_client_module_page_end')) {
    function render_client_module_page_end(string $suiteLabel = 'Client Experience Suite'): void
    {
        $safeSuiteLabel = htmlspecialchars($suiteLabel, ENT_QUOTES, 'UTF-8');
        echo '</main>';
        echo '<footer class="provider-footer">';
        echo '    <div class="provider-foot">';
        echo '        <span>&copy; ' . date('Y') . ' WhiteGlove. All rights reserved.</span>';
        echo '        <span>' . $safeSuiteLabel . '</span>';
        echo '    </div>';
        echo '</footer>';
        echo '</body>';
        echo '</html>';
    }
}
