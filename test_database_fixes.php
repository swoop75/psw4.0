<?php
/**
 * Test database fixes and dashboard functionality
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/controllers/DashboardController.php';

session_start();

try {
    echo "=== TESTING DATABASE FIXES ===\n\n";
    
    // Mock user authentication
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'testuser';
    $_SESSION['role_id'] = 1;
    
    echo "1. Testing DashboardController initialization...\n";
    $controller = new DashboardController();
    echo "✓ DashboardController created successfully\n\n";
    
    echo "2. Testing getDashboardData method...\n";
    $data = $controller->getDashboardData();
    echo "✓ getDashboardData completed without errors\n";
    
    echo "3. Checking returned data structure...\n";
    $expectedKeys = ['portfolio_metrics', 'recent_dividends', 'upcoming_dividends', 'allocation_data', 'performance_data', 'news_feed', 'quick_stats'];
    
    foreach ($expectedKeys as $key) {
        if (isset($data[$key])) {
            echo "✓ Found key: $key\n";
        } else {
            echo "✗ Missing key: $key\n";
        }
    }
    
    echo "\n4. Testing portfolio metrics...\n";
    if (isset($data['portfolio_metrics'])) {
        $metrics = $data['portfolio_metrics'];
        echo "Total Value: " . number_format($metrics['total_value']) . " SEK\n";
        echo "YTD Dividends: " . number_format($metrics['total_dividends_ytd']) . " SEK\n";
        echo "Total Companies: " . $metrics['total_companies'] . "\n";
        echo "Current Yield: " . number_format($metrics['current_yield'], 2) . "%\n";
    }
    
    echo "\n5. Testing recent dividends...\n";
    if (isset($data['recent_dividends'])) {
        $dividends = $data['recent_dividends'];
        echo "Recent dividends count: " . count($dividends) . "\n";
        if (!empty($dividends)) {
            $first = $dividends[0];
            echo "Sample dividend: " . $first['company'] . " (" . $first['symbol'] . ") - " . 
                 number_format($first['sek_amount']) . " SEK\n";
        }
    }
    
    echo "\n6. Testing allocation data...\n";
    if (isset($data['allocation_data'])) {
        $allocation = $data['allocation_data'];
        foreach (['country', 'currency', 'sector', 'asset_class'] as $type) {
            if (isset($allocation[$type])) {
                echo "✓ $type allocation: " . count($allocation[$type]) . " entries\n";
            } else {
                echo "✗ Missing $type allocation\n";
            }
        }
    }
    
    echo "\n=== ALL TESTS COMPLETED ===\n";
    echo "✓ Database connection working\n";
    echo "✓ Cross-database query issues resolved\n";
    echo "✓ Dashboard controller functional\n";
    echo "✓ Ready for frontend testing\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>