<?php
/**
 * File: api/delete_dividend.php
 * Description: API endpoint to delete dividend records
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/middleware/Auth.php';
require_once __DIR__ . '/../src/utils/Logger.php';

// Set JSON response header
header('Content-Type: application/json');

// Require authentication
if (!Auth::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['log_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing log_id parameter']);
        exit();
    }
    
    $logId = (int)$input['log_id'];
    
    if ($logId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid log_id']);
        exit();
    }
    
    $portfolioDb = Database::getConnection('portfolio');
    
    // First, check if the dividend record exists
    $checkSql = "SELECT log_id, isin, payment_date FROM log_dividends WHERE log_id = :log_id";
    $checkStmt = $portfolioDb->prepare($checkSql);
    $checkStmt->execute([':log_id' => $logId]);
    $dividend = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dividend) {
        http_response_code(404);
        echo json_encode(['error' => 'Dividend record not found']);
        exit();
    }
    
    // Delete the dividend record
    $deleteSql = "DELETE FROM log_dividends WHERE log_id = :log_id";
    $deleteStmt = $portfolioDb->prepare($deleteSql);
    $deleteStmt->execute([':log_id' => $logId]);
    
    if ($deleteStmt->rowCount() === 0) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete dividend record']);
        exit();
    }
    
    // Log the deletion
    Logger::logUserAction('dividend_deleted', 'Dividend record deleted', [
        'log_id' => $logId,
        'isin' => $dividend['isin'],
        'payment_date' => $dividend['payment_date']
    ]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Dividend record deleted successfully'
    ]);
    
} catch (Exception $e) {
    Logger::error('Delete dividend error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>