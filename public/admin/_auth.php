<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
try {
    $adminUser = rh_auth()->requireAdmin()->toArray();
} catch (Throwable $e) {
    error_log('ResourceHub admin check failed: ' . $e->getMessage());
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('403 Forbidden: Administrator access required.');
}
header('Cache-Control: no-store, private');
