<?php
/**
 * File: update_theme.php
 * Description: Handle theme preference updates via AJAX
 */

session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/middleware/Auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Require authentication
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['theme'])) {
    http_response_code(400);
    exit('Invalid input');
}

$theme = $input['theme'];

// Validate theme
if (!in_array($theme, ['light', 'dark'])) {
    http_response_code(400);
    exit('Invalid theme');
}

// Update session
$_SESSION['user_theme'] = $theme;

// TODO: Update database user preferences when user settings table is available
// For now, we just store in session

header('Content-Type: application/json');
echo json_encode(['success' => true, 'theme' => $theme]);
?>