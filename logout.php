<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/auth.php';

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Method not allowed.';
    exit;
}

if (!sfc_verify_csrf_request()) {
    http_response_code(419);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid or expired security token. Refresh the page and try again.';
    exit;
}

sfc_logout();
header('Location: ./index.php');
exit;
