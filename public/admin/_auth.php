<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$adminUser = rh_current_user();
if (!$adminUser || $adminUser['role'] !== 'admin') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store, private');
    exit('403 Forbidden: Administrator access required.');
}
header('Cache-Control: no-store, private');
