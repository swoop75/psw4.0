<?php
/**
 * Get fresh CSRF token
 */

session_start();
require_once __DIR__ . '/src/utils/Security.php';

header('Content-Type: application/json');

echo json_encode([
    'csrf_token' => Security::generateCSRFToken()
]);
?>