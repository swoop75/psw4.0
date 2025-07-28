<?php
header('Content-Type: application/json');
session_start();

require_once 'config/config.php';
require_once 'config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    // Get the parsed data from session (stored during upload)
    if (!isset($_SESSION['dividend_import_data'])) {
        throw new Exception('No dividend data found. Please upload and preview first.');
    }
    
    $importData = $_SESSION['dividend_import_data'];
    $ignoreDuplicates = $_POST['ignore_duplicates'] ?? false;
    
    $portfolioDb = Database::getConnection('portfolio');
    $foundationDb = Database::getConnection('foundation');
    
    $imported = 0;
    $skipped = 0;
    $errors = [];
    
    $portfolioDb->beginTransaction();
    
    try {
        foreach ($importData as $dividend) {
            // Check for duplicates
            $stmt = $portfolioDb->prepare("
                SELECT COUNT(*) as count FROM log_dividends 
                WHERE isin = ? AND payment_date = ? AND shares_held = ? AND dividend_amount_local = ?
            ");
            $stmt->execute([
                $dividend['isin'],
                $dividend['payment_date'],
                $dividend['shares_held'],
                $dividend['dividend_amount_local']
            ]);
            
            $duplicate = $stmt->fetch()['count'] > 0;
            
            if ($duplicate && !$ignoreDuplicates) {
                $skipped++;
                continue;
            }
            
            if ($duplicate && $ignoreDuplicates) {
                $skipped++;
                continue;
            }
            
            // Handle portfolio account group
            $portfolioAccountGroupId = null;
            if (!empty($dividend['portfolio_account_group'])) {
                // Check if account group exists
                $stmt = $foundationDb->prepare("
                    SELECT portfolio_account_group_id 
                    FROM portfolio_account_groups 
                    WHERE portfolio_group_name = ?
                ");
                $stmt->execute([$dividend['portfolio_account_group']]);
                $accountGroup = $stmt->fetch();
                
                if (!$accountGroup) {
                    // Create new account group
                    $stmt = $foundationDb->prepare("
                        INSERT INTO portfolio_account_groups (portfolio_group_name, portfolio_group_description) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([
                        $dividend['portfolio_account_group'],
                        "Auto-created during dividend import"
                    ]);
                    $portfolioAccountGroupId = $foundationDb->lastInsertId();
                } else {
                    $portfolioAccountGroupId = $accountGroup['portfolio_account_group_id'];
                }
            }
            
            // Insert dividend record
            $stmt = $portfolioDb->prepare("
                INSERT INTO log_dividends (
                    payment_date, isin, ticker, shares_held, 
                    dividend_amount_local, tax_amount_local, currency_local,
                    dividend_amount_sek, tax_amount_sek, net_dividend_sek, 
                    exchange_rate_used, portfolio_account_group_id,
                    broker_id, dividend_type_id, distribution_classification_id, currency_id,
                    is_complete, incomplete_fields, created_at
                ) VALUES (
                    ?, ?, ?, ?, 
                    ?, ?, ?, 
                    ?, ?, ?, 
                    ?, ?, 
                    ?, NULL, NULL, NULL,
                    ?, NULL, NOW()
                )
            ");
            
            $stmt->execute([
                $dividend['payment_date'],
                $dividend['isin'],
                $dividend['ticker'] ?? '',
                $dividend['shares_held'],
                $dividend['dividend_amount_local'],
                $dividend['tax_amount_local'],
                $dividend['currency_local'],
                $dividend['dividend_amount_sek'],
                $dividend['tax_amount_sek'],
                $dividend['net_dividend_sek'],
                $dividend['exchange_rate_used'],
                $portfolioAccountGroupId,
                'minimal', // broker_id - could be dynamic
                $dividend['is_complete']
            ]);
            
            $imported++;
        }
        
        $portfolioDb->commit();
        
        // Clear session data
        unset($_SESSION['dividend_import_data']);
        
        echo json_encode([
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'total_processed' => $imported + $skipped,
            'message' => "Successfully imported $imported dividends" . ($skipped > 0 ? " (skipped $skipped duplicates)" : "")
        ]);
        
    } catch (Exception $e) {
        $portfolioDb->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>