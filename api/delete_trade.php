<?php
/**
 * File: api/delete_trade.php
 * Description: Delete trade API endpoint
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/middleware/Auth.php';

// Require authentication
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['trade_id']) || !is_numeric($input['trade_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid trade ID']);
        exit;
    }
    
    $tradeId = (int) $input['trade_id'];
    
    $portfolioDb = Database::getConnection('portfolio');
    
    // First check if the trade exists
    $checkSql = "SELECT trade_id, isin FROM log_trades WHERE trade_id = :trade_id";
    $checkStmt = $portfolioDb->prepare($checkSql);
    $checkStmt->execute([':trade_id' => $tradeId]);
    $trade = $checkStmt->fetch();
    
    if (!$trade) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Trade not found']);
        exit;
    }
    
    // Delete the trade
    $deleteSql = "DELETE FROM log_trades WHERE trade_id = :trade_id";
    $deleteStmt = $portfolioDb->prepare($deleteSql);
    $result = $deleteStmt->execute([':trade_id' => $tradeId]);
    
    if ($result && $deleteStmt->rowCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Trade deleted successfully',
            'trade_id' => $tradeId
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete trade']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>