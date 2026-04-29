<?php

declare(strict_types=1);

if (!function_exists('render_client_page_start')) {
    function render_client_page_start(string $title): void
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '    <meta charset="utf-8">';
        echo '    <meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '    <title>' . $safeTitle . ' | WhiteGlove</title>';
        echo '    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '    <link href="/WhiteGlove/public/assets/style.css" rel="stylesheet">';
        echo '</head>';
        echo '<body class="bg-light">';
        echo '<nav class="navbar navbar-expand-lg wg-navbar mb-3">';
        echo '    <div class="container">';
        echo '        <a class="navbar-brand fw-semibold" href="/WhiteGlove/public/index.php">WhiteGlove</a>';
        echo '        <div class="d-flex gap-2">';
        echo '            <a class="btn btn-sm btn-warning" href="/WhiteGlove/public/logout.php">Logout</a>';
        echo '        </div>';
        echo '    </div>';
        echo '</nav>';
        echo '<main class="container py-4">';
    }
}

if (!function_exists('render_client_page_end')) {
    function render_client_page_end(string $sectionLabel = 'Client Experience Suite'): void
    {
        $safeSectionLabel = htmlspecialchars($sectionLabel, ENT_QUOTES, 'UTF-8');
        echo '</main>';
        echo '<footer class="wg-page-footer">';
        echo '    <div class="container wg-page-footer-inner">';
        echo '        <span>&copy; ' . date('Y') . ' WhiteGlove. All rights reserved.</span>';
        echo '        <span>' . $safeSectionLabel . '</span>';
        echo '    </div>';
        echo '</footer>';
        echo '</body>';
        echo '</html>';
    }
}
