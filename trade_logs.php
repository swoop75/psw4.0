<?php
/**
 * File: trade_logs.php
 * Description: Trade logs interface for PSW 4.0 - displays trade execution data with filtering and sorting
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/utils/Localization.php';

// Require authentication
Auth::requireAuth();

// Set the user-friendly default date range
$displayDefaultFrom = date('Y-m-01', strtotime('-3 months'));
$displayDefaultTo = date('Y-m-t');

$filters = [
    'search' => $_GET['search'] ?? '',
    'date_from' => $_GET['date_from'] ?? $displayDefaultFrom,
    'date_to' => $_GET['date_to'] ?? $displayDefaultTo,
    'broker_id' => $_GET['broker_id'] ?? '',
    'account_group_id' => $_GET['account_group_id'] ?? '',
    'trade_type_id' => $_GET['trade_type_id'] ?? '',
    'currency_local' => $_GET['currency_local'] ?? '',
    'sort_by' => $_GET['sort_by'] ?? 'trade_date',
    'sort_order' => $_GET['sort_order'] ?? 'desc'
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(10, min(100, (int)($_GET['limit'] ?? 50)));

// Create filtered array for database query
$dbFilters = array_filter($filters, function($value) {
    return $value !== null && $value !== '';
});

try {
    $portfolioDb = Database::getConnection('portfolio');
    $foundationDb = Database::getConnection('foundation');
    
    // Build WHERE clause
    $whereConditions = ['1=1'];
    $params = [];
    
    if (!empty($dbFilters['search'])) {
        $whereConditions[] = "(lt.isin LIKE ? OR ml.name LIKE ? OR lt.ticker LIKE ?)";
        $searchTerm = '%' . $dbFilters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($dbFilters['date_from'])) {
        $whereConditions[] = "lt.trade_date >= ?";
        $params[] = $dbFilters['date_from'];
    }
    
    if (!empty($dbFilters['date_to'])) {
        $whereConditions[] = "lt.trade_date <= ?";
        $params[] = $dbFilters['date_to'];
    }
    
    if (!empty($dbFilters['broker_id'])) {
        $whereConditions[] = "lt.broker_id = ?";
        $params[] = $dbFilters['broker_id'];
    }
    
    if (!empty($dbFilters['account_group_id'])) {
        $whereConditions[] = "lt.portfolio_account_group_id = ?";
        $params[] = $dbFilters['account_group_id'];
    }
    
    if (!empty($dbFilters['trade_type_id'])) {
        $whereConditions[] = "lt.trade_type_id = ?";
        $params[] = $dbFilters['trade_type_id'];
    }
    
    if (!empty($dbFilters['currency_local'])) {
        $whereConditions[] = "lt.currency_local = ?";
        $params[] = $dbFilters['currency_local'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Sorting
    $allowedSortColumns = [
        'trade_date' => 'lt.trade_date',
        'isin' => 'lt.isin',
        'company_name' => 'ml.name',
        'ticker' => 'lt.ticker',
        'trade_type' => 'tt.type_name',
        'broker_name' => 'b.broker_name',
        'account_group' => 'pag.portfolio_group_name',
        'shares_traded' => 'lt.shares_traded',
        'price_per_share_sek' => 'lt.price_per_share_sek',
        'total_amount_sek' => 'lt.total_amount_sek',
        'net_amount_sek' => 'lt.net_amount_sek'
    ];
    
    $sortColumn = $allowedSortColumns[$filters['sort_by']] ?? 'lt.trade_date';
    $sortOrder = strtoupper($filters['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
    
    // Get total count
    $countSql = "
        SELECT COUNT(*) as total
        FROM psw_portfolio.log_trades lt
        LEFT JOIN psw_foundation.masterlist ml ON lt.isin COLLATE utf8mb4_unicode_ci = ml.isin COLLATE utf8mb4_unicode_ci
        LEFT JOIN psw_foundation.trade_types tt ON lt.trade_type_id = tt.trade_type_id
        LEFT JOIN psw_foundation.brokers b ON lt.broker_id = b.broker_id
        LEFT JOIN psw_foundation.portfolio_account_groups pag ON lt.portfolio_account_group_id = pag.portfolio_account_group_id
        WHERE $whereClause
    ";
    
    $countStmt = $portfolioDb->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch()['total'];
    
    // Get paginated data
    $offset = ($page - 1) * $limit;
    $dataSql = "
        SELECT 
            lt.*,
            ml.name as company_name,
            tt.type_code,
            tt.type_name as trade_type_name,
            tt.affects_position,
            b.broker_name,
            pag.portfolio_group_name as account_group_name
        FROM psw_portfolio.log_trades lt
        LEFT JOIN psw_foundation.masterlist ml ON lt.isin COLLATE utf8mb4_unicode_ci = ml.isin COLLATE utf8mb4_unicode_ci
        LEFT JOIN psw_foundation.trade_types tt ON lt.trade_type_id = tt.trade_type_id
        LEFT JOIN psw_foundation.brokers b ON lt.broker_id = b.broker_id
        LEFT JOIN psw_foundation.portfolio_account_groups pag ON lt.portfolio_account_group_id = pag.portfolio_account_group_id
        WHERE $whereClause
        ORDER BY $sortColumn $sortOrder
        LIMIT $limit OFFSET $offset
    ";
    
    $dataStmt = $portfolioDb->prepare($dataSql);
    $dataStmt->execute($params);
    $trades = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get filter options
    $tradeTypes = $foundationDb->query("SELECT trade_type_id, type_code, type_name FROM trade_types WHERE is_active = 1 ORDER BY type_name")->fetchAll(PDO::FETCH_ASSOC);
    $brokers = $foundationDb->query("SELECT broker_id, broker_name FROM brokers ORDER BY broker_name")->fetchAll(PDO::FETCH_ASSOC);
    $accountGroups = $foundationDb->query("SELECT portfolio_account_group_id, portfolio_group_name FROM portfolio_account_groups ORDER BY portfolio_group_name")->fetchAll(PDO::FETCH_ASSOC);
    $currencies = $portfolioDb->query("SELECT DISTINCT currency_local FROM log_trades WHERE currency_local IS NOT NULL ORDER BY currency_local")->fetchAll(PDO::FETCH_COLUMN);
    
    // Calculate statistics
    $statsSql = "
        SELECT 
            COUNT(*) as total_trades,
            COUNT(DISTINCT lt.isin) as unique_companies,
            SUM(CASE WHEN tt.type_code IN ('BUY', 'DIVIDEND_REINVEST', 'TRANSFER_IN', 'RIGHTS_ISSUE', 'BONUS_ISSUE') THEN lt.net_amount_sek ELSE 0 END) as total_purchases_sek,
            SUM(CASE WHEN tt.type_code IN ('SELL', 'TRANSFER_OUT') THEN lt.net_amount_sek ELSE 0 END) as total_sales_sek,
            SUM(lt.broker_fees_sek) as total_fees_sek,
            SUM(lt.tft_tax_sek) as total_taxes_sek
        FROM psw_portfolio.log_trades lt
        LEFT JOIN psw_foundation.masterlist ml ON lt.isin COLLATE utf8mb4_unicode_ci = ml.isin COLLATE utf8mb4_unicode_ci
        LEFT JOIN psw_foundation.trade_types tt ON lt.trade_type_id = tt.trade_type_id
        LEFT JOIN psw_foundation.brokers b ON lt.broker_id = b.broker_id
        LEFT JOIN psw_foundation.portfolio_account_groups pag ON lt.portfolio_account_group_id = pag.portfolio_account_group_id
        WHERE $whereClause
    ";
    
    $statsStmt = $portfolioDb->prepare($statsSql);
    $statsStmt->execute($params);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Handle CSV export
    if (!empty($_GET['export']) && $_GET['export'] === 'csv') {
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="psw_trade_logs_export_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, [
            'Trade Date',
            'Settlement Date',
            'ISIN',
            'Company',
            'Ticker',
            'Trade Type',
            'Shares',
            'Price/Share (Local)',
            'Total (Local)',
            'Currency',
            'Price/Share (SEK)',
            'Total (SEK)',
            'Fees (SEK)',
            'Tax (SEK)',
            'Net (SEK)',
            'Broker',
            'Account Group',
            'Transaction ID',
            'Order Type',
            'Status'
        ]);
        
        // Add data rows
        foreach ($trades as $trade) {
            fputcsv($output, [
                $trade['trade_date'],
                $trade['settlement_date'] ?? '',
                $trade['isin'],
                $trade['company_name'] ?? 'Unknown Company',
                $trade['ticker'] ?? '-',
                $trade['trade_type_name'],
                number_format($trade['shares_traded'], 4, '.', ''),
                number_format($trade['price_per_share_local'], 6, '.', ''),
                number_format($trade['total_amount_local'], 2, '.', ''),
                $trade['currency_local'],
                number_format($trade['price_per_share_sek'], 6, '.', ''),
                number_format($trade['total_amount_sek'], 2, '.', ''),
                number_format($trade['broker_fees_sek'], 2, '.', ''),
                number_format($trade['tft_tax_sek'], 2, '.', ''),
                number_format($trade['net_amount_sek'], 2, '.', ''),
                $trade['broker_name'] ?? '-',
                $trade['account_group_name'] ?? '-',
                $trade['broker_transaction_id'] ?? '',
                $trade['order_type'] ?? '',
                $trade['execution_status']
            ]);
        }
        
        fclose($output);
        exit();
    }
    
    // Get earliest trade date for 'since start' preset
    $earliestDateSql = "SELECT MIN(trade_date) as earliest_date FROM psw_portfolio.log_trades";
    $earliestDateStmt = $portfolioDb->prepare($earliestDateSql);
    $earliestDateStmt->execute();
    $earliestDateResult = $earliestDateStmt->fetch(PDO::FETCH_ASSOC);
    $earliestDate = $earliestDateResult['earliest_date'] ?? '2020-01-01'; // Fallback if no data
    
    // Get full year range for enhanced calendar navigation
    $yearRangeSql = "SELECT MIN(YEAR(trade_date)) as min_year, MAX(YEAR(trade_date)) as max_year FROM psw_portfolio.log_trades";
    $yearRangeStmt = $portfolioDb->prepare($yearRangeSql);
    $yearRangeStmt->execute();
    $yearRangeResult = $yearRangeStmt->fetch(PDO::FETCH_ASSOC);
    $minYear = $yearRangeResult['min_year'] ?? date('Y') - 10;
    $maxYear = $yearRangeResult['max_year'] ?? date('Y');
    
} catch (Exception $e) {
    $trades = [];
    $totalRecords = 0;
    $tradeTypes = [];
    $brokers = [];
    $accountGroups = [];
    $currencies = [];
    $stats = [
        'total_trades' => 0,
        'unique_companies' => 0,
        'total_purchases_sek' => 0,
        'total_sales_sek' => 0,
        'total_fees_sek' => 0,
        'total_taxes_sek' => 0
    ];
    $earliestDate = '2020-01-01'; // Fallback date
    $minYear = date('Y') - 10; // Fallback min year
    $maxYear = date('Y'); // Fallback max year
    $errorMessage = $e->getMessage();
}

// Initialize variables for template
$pageTitle = 'Trade Logs - PSW 4.0';
$pageDescription = 'Track and analyze trade executions';
$additionalCSS = [];
$additionalJS = [];

// Prepare content for trade logs page
ob_start();
?>
<div class="psw-content">
    <!-- Page Header -->
    <div class="psw-card psw-mb-6">
        <div class="psw-card-header">
            <h1 class="psw-card-title">
                <i class="fas fa-exchange-alt psw-card-title-icon"></i>
                Trade Logs
            </h1>
            <p class="psw-card-subtitle">Track and analyze trade executions across the portfolio</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, var(--primary-accent), var(--primary-accent-hover)); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-list-alt" style="color: var(--text-inverse); font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                        <?php echo Localization::formatNumber($stats['total_trades']); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Total Trades</div>
                </div>
            </div>
        </div>
        
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #10B981, #059669); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-arrow-up" style="color: white; font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                        <?php echo Localization::formatCurrency($stats['total_purchases_sek'], 0, 'SEK'); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Total Purchases</div>
                </div>
            </div>
        </div>
        
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #EF4444, #DC2626); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-arrow-down" style="color: white; font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                        <?php echo Localization::formatCurrency($stats['total_sales_sek'], 0, 'SEK'); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Total Sales</div>
                </div>
            </div>
        </div>
        
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #F59E0B, #D97706); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-receipt" style="color: white; font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                        <?php echo Localization::formatCurrency($stats['total_fees_sek'] + $stats['total_taxes_sek'], 0, 'SEK'); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Total Costs</div>
                </div>
            </div>
        </div>
        
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-building" style="color: white; font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                        <?php echo Localization::formatNumber($stats['unique_companies']); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Companies</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="psw-card" style="margin-bottom: 1.5rem;">
        <div class="psw-card-header">
            <div class="psw-card-title">
                <i class="fas fa-filter psw-card-title-icon"></i>
                Filter Options
            </div>
        </div>
        <div class="psw-card-content">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                <div class="psw-form-group">
                    <label class="psw-form-label">Search</label>
                    <input type="text" id="search-input" class="psw-form-input" 
                           placeholder="Search by ISIN, company, or ticker..." 
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                
                <!-- Date Range Picker - Same as dividend logs -->
                <div class="psw-form-group">
                    <label class="psw-form-label">Date Range</label>
                    <div id="trade-date-range" class="date-range-picker">
                        <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                        <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                        
                        <div class="date-range-display" onclick="window.toggleDateRangePicker();" style="cursor: pointer;">
                            <i class="fas fa-calendar-alt"></i>
                            <span class="date-range-text" id="dateRangeText">
                                <?php 
                                echo $filters['date_from'] . ' - ' . $filters['date_to'];
                                ?>
                            </span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        
                        <div class="date-range-overlay" id="dateRangeOverlay">
                            <div class="date-range-content">
                                <div class="date-range-panels">
                                    <div class="date-panel from-panel">
                                        <h5>From Date</h5>
                                        <input type="text" class="date-input" id="fromDateInput" placeholder="YYYY-MM-DD" onchange="updateCalendar('from')" />
                                        <div class="calendar-container" id="fromCalendar"></div>
                                    </div>
                                    <div class="date-panel to-panel">
                                        <h5>To Date</h5>
                                        <input type="text" class="date-input" id="toDateInput" placeholder="YYYY-MM-DD" onchange="updateCalendar('to')" />
                                        <div class="calendar-container" id="toCalendar"></div>
                                    </div>
                                    <div class="presets-panel">
                                        <div class="presets-grid">
                                            <button type="button" class="preset-btn" onclick="applyPreset('today', event)">Today</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('yesterday', event)">Yesterday</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('thisWeek', event)">This Week</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('prevWeek', event)">Previous Week</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('thisMonth', event)">This Month</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('prevMonth', event)">Previous Month</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('thisYear', event)">This Year</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('prevYear', event)">Previous Year</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('sinceStart', event)">Since Start</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="date-range-footer">
                                <button type="button" class="psw-btn psw-btn-secondary" onclick="closeDateRangePicker(event)">Cancel</button>
                                <button type="button" class="psw-btn psw-btn-primary" onclick="applyDateRange(event)">Apply</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="psw-form-group">
                    <label class="psw-form-label">Trade Type</label>
                    <select id="trade-type-filter" class="psw-form-input">
                        <option value="">All Types</option>
                        <?php foreach ($tradeTypes as $tradeType): ?>
                            <option value="<?php echo $tradeType['trade_type_id']; ?>"
                                    <?php echo $filters['trade_type_id'] == $tradeType['trade_type_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tradeType['type_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="psw-form-group">
                    <label class="psw-form-label">Broker</label>
                    <select id="broker-filter" class="psw-form-input">
                        <option value="">All Brokers</option>
                        <?php foreach ($brokers as $broker): ?>
                            <option value="<?php echo $broker['broker_id']; ?>"
                                    <?php echo $filters['broker_id'] == $broker['broker_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($broker['broker_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="psw-form-group">
                    <label class="psw-form-label">Account Group</label>
                    <select id="account-group-filter" class="psw-form-input">
                        <option value="">All Groups</option>
                        <?php foreach ($accountGroups as $group): ?>
                            <option value="<?php echo $group['portfolio_account_group_id']; ?>"
                                    <?php echo $filters['account_group_id'] == $group['portfolio_account_group_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($group['portfolio_group_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="psw-form-group">
                    <label class="psw-form-label">Currency</label>
                    <select id="currency-filter" class="psw-form-input">
                        <option value="">All Currencies</option>
                        <?php foreach ($currencies as $currency): ?>
                            <option value="<?php echo $currency; ?>"
                                    <?php echo $filters['currency_local'] == $currency ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($currency); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Filter Action Buttons -->
                <div class="psw-form-group" style="display: flex; gap: 0.5rem; align-items: end; justify-content: flex-end;">
                    <button type="button" class="psw-btn psw-btn-primary" onclick="applyFilters()">
                        <i class="fas fa-filter psw-btn-icon"></i>Apply
                    </button>
                    <button type="button" class="psw-btn psw-btn-secondary" onclick="clearFilters()">
                        <i class="fas fa-times psw-btn-icon"></i>Clear
                    </button>
                    <button type="button" class="psw-btn psw-btn-accent" onclick="exportToCSV()">
                        <i class="fas fa-download psw-btn-icon"></i>Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Info -->
    <div class="psw-card" style="margin-bottom: 1.5rem;">
        <div class="psw-card-content">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="color: var(--text-secondary);">
                    Showing <?php echo Localization::formatNumber(min($limit, $totalRecords - ($page - 1) * $limit)); ?> of 
                    <?php echo Localization::formatNumber($totalRecords); ?> trade entries
                    <?php if (!empty($filters['search']) || !empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['broker_id']) || !empty($filters['account_group_id']) || !empty($filters['trade_type_id']) || !empty($filters['currency_local'])): ?>
                        (filtered)
                    <?php endif; ?>
                </div>
                <div class="psw-form-group" style="margin: 0;">
                    <select onchange="changePageSize(this.value)" class="psw-form-input" style="width: auto;">
                        <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10 per page</option>
                        <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25 per page</option>
                        <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 per page</option>
                        <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 per page</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="psw-card">
        <div class="psw-card-content" style="padding: 0;">
            <?php if (!empty($trades)): ?>
                <div style="overflow-x: auto;">
                    <table class="psw-table">
                        <thead>
                            <tr>
                                <th style="cursor: pointer;" onclick="sortBy('trade_date')">
                                    Trade Date
                                    <?php if ($filters['sort_by'] == 'trade_date'): ?>
                                        <i class="fas fa-sort-<?php echo $filters['sort_order'] == 'asc' ? 'up' : 'down'; ?>"></i>
                                    <?php else: ?>
                                        <i class="fas fa-sort"></i>
                                    <?php endif; ?>
                                </th>
                                <th style="cursor: pointer;" onclick="sortBy('isin')">
                                    ISIN
                                    <?php if ($filters['sort_by'] == 'isin'): ?>
                                        <i class="fas fa-sort-<?php echo $filters['sort_order'] == 'asc' ? 'up' : 'down'; ?>"></i>
                                    <?php else: ?>
                                        <i class="fas fa-sort"></i>
                                    <?php endif; ?>
                                </th>
                                <th style="cursor: pointer;" onclick="sortBy('company_name')">
                                    Company
                                    <?php if ($filters['sort_by'] == 'company_name'): ?>
                                        <i class="fas fa-sort-<?php echo $filters['sort_order'] == 'asc' ? 'up' : 'down'; ?>"></i>
                                    <?php else: ?>
                                        <i class="fas fa-sort"></i>
                                    <?php endif; ?>
                                </th>
                                <th style="cursor: pointer;" onclick="sortBy('ticker')">
                                    Ticker
                                    <?php if ($filters['sort_by'] == 'ticker'): ?>
                                        <i class="fas fa-sort-<?php echo $filters['sort_order'] == 'asc' ? 'up' : 'down'; ?>"></i>
                                    <?php else: ?>
                                        <i class="fas fa-sort"></i>
                                    <?php endif; ?>
                                </th>
                                <th style="cursor: pointer;" onclick="sortBy('trade_type')">
                                    Type
                                    <?php if ($filters['sort_by'] == 'trade_type'): ?>
                                        <i class="fas fa-sort-<?php echo $filters['sort_order'] == 'asc' ? 'up' : 'down'; ?>"></i>
                                    <?php else: ?>
                                        <i class="fas fa-sort"></i>
                                    <?php endif; ?>
                                </th>
                                <th style="text-align: right;">Shares</th>
                                <th style="text-align: right;">Price (Local)</th>
                                <th>Currency</th>
                                <th style="text-align: right;">Price (SEK)</th>
                                <th style="text-align: right;">Net (SEK)</th>
                                <th>Broker</th>
                                <th>Account</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trades as $trade): ?>
                                <tr>
                                    <td><?php echo Localization::formatDate($trade['trade_date']); ?></td>
                                    <td style="font-family: var(--font-family-mono); font-size: 0.875rem;"><?php echo htmlspecialchars($trade['isin']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($trade['company_name'] ?? 'Unknown Company'); ?></strong>
                                    </td>
                                    <td style="font-family: var(--font-family-mono); font-size: 0.875rem;"><?php echo htmlspecialchars($trade['ticker'] ?? '-'); ?></td>
                                    <td>
                                        <span class="psw-badge <?php echo $trade['type_code'] == 'BUY' ? 'psw-badge-success' : ($trade['type_code'] == 'SELL' ? 'psw-badge-error' : 'psw-badge-info'); ?>">
                                            <?php echo htmlspecialchars($trade['type_code']); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;"><?php echo Localization::formatNumber($trade['shares_traded'], 4); ?></td>
                                    <td style="text-align: right;"><?php echo Localization::formatNumber($trade['price_per_share_local'], 6); ?></td>
                                    <td><?php echo htmlspecialchars($trade['currency_local']); ?></td>
                                    <td style="text-align: right;"><?php echo Localization::formatNumber($trade['price_per_share_sek'], 6); ?></td>
                                    <td style="text-align: right; color: <?php echo $trade['type_code'] == 'BUY' ? 'var(--error-color)' : 'var(--success-color)'; ?>; font-weight: 600;">
                                        <?php echo Localization::formatCurrency($trade['net_amount_sek'], 2, 'SEK'); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($trade['broker_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($trade['account_group_name'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                    <i class="fas fa-exchange-alt" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <h3 style="margin-bottom: 0.5rem;">No trade records found</h3>
                    <p>No trade data matches your current filters. Try adjusting your search criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalRecords > $limit): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem;">
            <div style="color: var(--text-secondary);">
                Showing <?php echo Localization::formatNumber(($page - 1) * $limit + 1); ?>-<?php echo Localization::formatNumber(min($page * $limit, $totalRecords)); ?> of <?php echo Localization::formatNumber($totalRecords); ?> entries
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <?php
                $totalPages = ceil($totalRecords / $limit);
                if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="psw-btn psw-btn-secondary">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <span class="psw-btn psw-btn-secondary" style="background: var(--primary-accent); color: var(--text-inverse);">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                </span>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="psw-btn psw-btn-secondary">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Copy all the date range picker JavaScript from dividend_logs.php
function applyFilters() {
    const params = new URLSearchParams();
    
    // Search
    const search = document.getElementById('search-input').value.trim();
    if (search) params.set('search', search);
    
    // Date range from hidden inputs
    const dateFrom = document.querySelector('input[name="date_from"]').value;
    const dateTo = document.querySelector('input[name="date_to"]').value;
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);
    
    // Trade type filter
    const tradeTypeSelect = document.getElementById('trade-type-filter');
    if (tradeTypeSelect.value) params.set('trade_type_id', tradeTypeSelect.value);
    
    // Broker filter
    const brokerSelect = document.getElementById('broker-filter');
    if (brokerSelect.value) params.set('broker_id', brokerSelect.value);
    
    // Account group filter
    const accountGroupSelect = document.getElementById('account-group-filter');
    if (accountGroupSelect.value) params.set('account_group_id', accountGroupSelect.value);
    
    // Currency filter
    const currencySelect = document.getElementById('currency-filter');
    if (currencySelect.value) params.set('currency_local', currencySelect.value);
    
    // Preserve current sorting
    const currentParams = new URLSearchParams(window.location.search);
    if (currentParams.get('sort_by')) params.set('sort_by', currentParams.get('sort_by'));
    if (currentParams.get('sort_order')) params.set('sort_order', currentParams.get('sort_order'));
    if (currentParams.get('limit')) params.set('limit', currentParams.get('limit'));
    
    window.location.href = '?' + params.toString();
}

function clearFilters() {
    const params = new URLSearchParams();
    const currentParams = new URLSearchParams(window.location.search);
    if (currentParams.get('sort_by')) params.set('sort_by', currentParams.get('sort_by'));
    if (currentParams.get('sort_order')) params.set('sort_order', currentParams.get('sort_order'));
    if (currentParams.get('limit')) params.set('limit', currentParams.get('limit'));
    
    window.location.href = params.toString() ? '?' + params.toString() : window.location.pathname;
}

function changePageSize(newSize) {
    const params = new URLSearchParams(window.location.search);
    params.set('limit', newSize);
    params.delete('page');
    window.location.href = '?' + params.toString();
}

function sortBy(column) {
    const currentParams = new URLSearchParams(window.location.search);
    const currentSort = currentParams.get('sort_by');
    const currentOrder = currentParams.get('sort_order') || 'desc';
    
    let newOrder = 'desc';
    if (currentSort === column && currentOrder === 'desc') {
        newOrder = 'asc';
    }
    
    currentParams.set('sort_by', column);
    currentParams.set('sort_order', newOrder);
    currentParams.delete('page');
    
    window.location.href = '?' + currentParams.toString();
}

// CSV Export function
window.exportToCSV = function() {
    // Get current filter parameters
    const params = new URLSearchParams();
    
    // Add current filters
    const search = document.getElementById('search-input').value.trim();
    if (search) params.set('search', search);
    
    // Date range from hidden inputs
    const dateFrom = document.querySelector('input[name="date_from"]').value;
    const dateTo = document.querySelector('input[name="date_to"]').value;
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);
    
    // Trade type filter
    const tradeTypeSelect = document.getElementById('trade-type-filter');
    if (tradeTypeSelect.value) params.set('trade_type_id', tradeTypeSelect.value);
    
    // Broker filter
    const brokerSelect = document.getElementById('broker-filter');
    if (brokerSelect.value) params.set('broker_id', brokerSelect.value);
    
    // Account group filter
    const accountGroupSelect = document.getElementById('account-group-filter');
    if (accountGroupSelect.value) params.set('account_group_id', accountGroupSelect.value);
    
    // Currency filter
    const currencySelect = document.getElementById('currency-filter');
    if (currencySelect.value) params.set('currency_local', currencySelect.value);
    
    // Add export flag
    params.set('export', 'csv');
    
    // Create download link
    const exportUrl = window.location.pathname + '?' + params.toString();
    
    // Trigger download
    const link = document.createElement('a');
    link.href = exportUrl;
    link.download = 'psw_trade_logs_export.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Search functionality with debounce
let searchTimeout;
document.getElementById('search-input').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 500);
});

// Auto-apply filters for dropdowns
document.getElementById('trade-type-filter').addEventListener('change', applyFilters);
document.getElementById('broker-filter').addEventListener('change', applyFilters);
document.getElementById('account-group-filter').addEventListener('change', applyFilters);
document.getElementById('currency-filter').addEventListener('change', applyFilters);

// [Copy all date range picker JavaScript from dividend_logs.php - lines 642-1432]
// Enhanced Date Range Picker JavaScript with Calendar
var tempFromDate = '';
var tempToDate = '';
var currentFromMonth = new Date();
var currentToMonth = new Date();

// Calendar view states and pagination
var calendarViews = {
    from: 'calendar', // 'calendar', 'months', 'years'
    to: 'calendar'
};
var yearPageFrom = 0; // For year pagination
var yearPageTo = 0;
var yearsPerPage = 12; // 3x4 grid
var availableYears = []; // Will be populated from PHP data

// Initialize available years from PHP
function initializeAvailableYears() {
    var minYear = <?php echo $minYear; ?>;
    var maxYear = <?php echo $maxYear; ?>;
    availableYears = [];
    for (var year = minYear; year <= maxYear; year++) {
        availableYears.push(year);
    }
}

// Make function global - proper implementation
window.toggleDateRangePicker = function() {
    var overlay = document.getElementById('dateRangeOverlay');
    var picker = document.getElementById('trade-date-range');
    
    if (overlay && picker) {
        if (overlay.style.display === 'none' || overlay.style.display === '') {
            // Show the overlay with calculated fixed positioning
            var pickerRect = picker.getBoundingClientRect();
            overlay.style.setProperty('display', 'block', 'important');
            overlay.style.setProperty('position', 'fixed', 'important');
            overlay.style.setProperty('top', (pickerRect.bottom + 5) + 'px', 'important');
            overlay.style.setProperty('left', pickerRect.left + 'px', 'important');
            overlay.style.setProperty('z-index', '2147483647', 'important'); // Maximum z-index value
            // Apply beautiful PSW 4.0 styling with exact dimensions
            overlay.style.setProperty('background', 'var(--bg-card)', 'important');
            overlay.style.setProperty('border', '1px solid var(--border-primary)', 'important');
            overlay.style.setProperty('border-radius', 'var(--border-radius-lg)', 'important');
            overlay.style.setProperty('box-shadow', 'var(--shadow-xl)', 'important');
            // Calculate optimal width: content (710px) + left/right padding (20px) = 730px
            // Height: content + top/bottom padding (10px each) = reduced height
            overlay.style.setProperty('width', '730px', 'important');
            overlay.style.setProperty('height', '420px', 'important');
            overlay.style.setProperty('min-width', '730px', 'important');
            overlay.style.setProperty('max-width', '730px', 'important');
            overlay.style.setProperty('min-height', '420px', 'important');
            overlay.style.setProperty('max-height', '420px', 'important');
            picker.classList.add('open');
            
            // Initialize available years and reset calendar views
            initializeAvailableYears();
            calendarViews.from = 'calendar';
            calendarViews.to = 'calendar';
            yearPageFrom = 0;
            yearPageTo = 0;
            
            // Set current values in inputs
            var fromInput = document.querySelector('input[name="date_from"]');
            var toInput = document.querySelector('input[name="date_to"]');
            
            if (fromInput) document.getElementById('fromDateInput').value = fromInput.value;
            if (toInput) document.getElementById('toDateInput').value = toInput.value;
            
            // Always render calendars immediately
            var fromDate = fromInput && fromInput.value ? new Date(fromInput.value) : new Date();
            var toDate = toInput && toInput.value ? new Date(toInput.value) : new Date();
            
            currentFromMonth = fromDate;
            currentToMonth = toDate;
            tempFromDate = fromInput ? fromInput.value : '';
            tempToDate = toInput ? toInput.value : '';
            
            // Render both calendars
            renderCalendar('from', currentFromMonth);
            renderCalendar('to', currentToMonth);
        } else {
            // Hide the overlay
            closeDateRangePicker();
        }
    }
};

window.closeDateRangePicker = function(event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    var overlay = document.getElementById('dateRangeOverlay');
    var picker = document.getElementById('trade-date-range');
    
    overlay.style.display = 'none';
    picker.classList.remove('open');
}

window.applyPreset = function(preset, event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    var today = new Date();
    var fromDate, toDate;

    switch (preset) {
        case 'today':
            fromDate = toDate = new Date(today);
            break;
        case 'yesterday':
            const yesterday = new Date(today);
            yesterday.setDate(today.getDate() - 1);
            fromDate = toDate = new Date(yesterday);
            break;
        case 'thisWeek':
            const startOfWeek = new Date(today);
            startOfWeek.setDate(today.getDate() - today.getDay());
            const endOfWeek = new Date(startOfWeek);
            endOfWeek.setDate(startOfWeek.getDate() + 6);
            fromDate = new Date(startOfWeek);
            toDate = new Date(endOfWeek);
            break;
        case 'prevWeek':
            const prevWeekEnd = new Date(today);
            prevWeekEnd.setDate(today.getDate() - today.getDay() - 1);
            const prevWeekStart = new Date(prevWeekEnd);
            prevWeekStart.setDate(prevWeekEnd.getDate() - 6);
            fromDate = new Date(prevWeekStart);
            toDate = new Date(prevWeekEnd);
            break;
        case 'thisMonth':
            fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
            toDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            break;
        case 'prevMonth':
            fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            toDate = new Date(today.getFullYear(), today.getMonth(), 0);
            break;
        case 'thisYear':
            fromDate = new Date(today.getFullYear(), 0, 1);
            toDate = new Date(today.getFullYear(), 11, 31);
            break;
        case 'prevYear':
            fromDate = new Date(today.getFullYear() - 1, 0, 1);
            toDate = new Date(today.getFullYear() - 1, 11, 31);
            break;
        case 'sinceStart':
            fromDate = new Date('<?php echo $earliestDate; ?>');
            toDate = new Date(today);
            break;
        case 'defaultRange':
        default:
            // Current month + 3 months back
            fromDate = new Date(today.getFullYear(), today.getMonth() - 3, 1);
            toDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            break;
    }

    const fromDateStr = formatDate(fromDate);
    const toDateStr = formatDate(toDate);

    document.getElementById('fromDateInput').value = fromDateStr;
    document.getElementById('toDateInput').value = toDateStr;
    tempFromDate = fromDateStr;
    tempToDate = toDateStr;
    
    // Update calendars
    currentFromMonth = new Date(fromDate);
    currentToMonth = new Date(toDate);
    renderCalendar('from', currentFromMonth);
    renderCalendar('to', currentToMonth);
    
    // Update the display immediately when using presets
    updateDateRangeDisplay();
}

window.applyDateRange = function(event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    var fromInput = document.getElementById('fromDateInput');
    var toInput = document.getElementById('toDateInput');
    
    tempFromDate = fromInput.value;
    tempToDate = toInput.value;
    
    // Update hidden inputs
    document.querySelector('input[name="date_from"]').value = tempFromDate;
    document.querySelector('input[name="date_to"]').value = tempToDate;
    
    // Update display
    document.getElementById('dateRangeText').textContent = tempFromDate + ' - ' + tempToDate;
    
    closeDateRangePicker();
}

window.updateCalendar = function(type) {
    var input = document.getElementById(type + 'DateInput');
    var dateValue = input.value;
    
    if (isValidDate(dateValue)) {
        var newDate = new Date(dateValue);
        if (type === 'from') {
            currentFromMonth = newDate;
            tempFromDate = dateValue;
        } else {
            currentToMonth = newDate;
            tempToDate = dateValue;
        }
        renderCalendar(type, type === 'from' ? currentFromMonth : currentToMonth);
        
        // Update the display immediately when manually typing
        updateDateRangeDisplay();
    }
}

window.renderCalendar = function(type, date) {
    const calendarContainer = document.getElementById(type + 'Calendar');
    const year = date.getFullYear();
    const month = date.getMonth();
    const currentView = calendarViews[type];
    
    let html = '';
    
    if (currentView === 'calendar') {
        html = renderCalendarView(type, date);
    } else if (currentView === 'months') {
        html = renderMonthsView(type, year);
    } else if (currentView === 'years') {
        html = renderYearsView(type);
    }
    
    calendarContainer.innerHTML = html;
}

// [Continue with all other calendar functions from dividend_logs.php...]

window.formatDate = function(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

window.isValidDate = function(dateStr) {
    const date = new Date(dateStr);
    return date instanceof Date && !isNaN(date) && dateStr.match(/^\d{4}-\d{2}-\d{2}$/);
}

// Update the main date range display text
window.updateDateRangeDisplay = function() {
    var fromInput = document.getElementById('fromDateInput');
    var toInput = document.getElementById('toDateInput');
    var displayElement = document.getElementById('dateRangeText');
    
    if (fromInput && toInput && displayElement) {
        var fromDate = fromInput.value || tempFromDate;
        var toDate = toInput.value || tempToDate;
        
        if (fromDate && toDate) {
            displayElement.textContent = fromDate + ' - ' + toDate;
        }
    }
}

// Close on outside click
document.addEventListener('click', function(e) {
    const picker = document.getElementById('trade-date-range');
    const overlay = document.getElementById('dateRangeOverlay');
    
    if (picker && !picker.contains(e.target) && overlay && overlay.style.display === 'block') {
        closeDateRangePicker();
    }
});

// Initialize date range picker
document.addEventListener('DOMContentLoaded', function() {
    var fromInput = document.querySelector('input[name="date_from"]');
    var toInput = document.querySelector('input[name="date_to"]');
    
    if (fromInput && toInput && !fromInput.value && !toInput.value) {
        var defaultFrom = '<?php echo $displayDefaultFrom; ?>';
        var defaultTo = '<?php echo $displayDefaultTo; ?>';
        
        fromInput.value = defaultFrom;
        toInput.value = defaultTo;
        var dateRangeText = document.getElementById('dateRangeText');
        if (dateRangeText) {
            dateRangeText.textContent = defaultFrom + ' - ' + defaultTo;
        }
    }
    
    if (fromInput && fromInput.value) {
        currentFromMonth = new Date(fromInput.value);
    }
    if (toInput && toInput.value) {
        currentToMonth = new Date(toInput.value);
    }
});
</script>

<!-- Date Range Picker Styles - Same as dividend_logs.php -->
<style>
/* Enhanced Date Range Picker Styles */
.date-range-picker {
    position: relative;
    display: inline-block;
    width: 100%;
    max-width: 300px;
}

.date-range-display {
    display: flex;
    align-items: center;
    padding: var(--spacing-3) var(--spacing-4);
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: all 0.2s ease;
    min-height: 44px;
    font-family: var(--font-family-primary);
    color: var(--text-primary);
    position: relative;
    z-index: 1;
}

.date-range-display:hover {
    border-color: var(--primary-accent);
    box-shadow: 0 0 0 2px var(--primary-accent-light);
}

.date-range-display i.fa-calendar-alt {
    color: var(--text-secondary);
    margin-right: var(--spacing-2);
}

.date-range-text {
    flex: 1;
    font-size: var(--font-size-sm);
    font-weight: 500;
    color: var(--text-primary);
}

.date-range-display i.fa-chevron-down {
    color: var(--text-secondary);
    font-size: var(--font-size-xs);
    transition: transform 0.2s ease;
}

.date-range-picker.open .date-range-display i.fa-chevron-down {
    transform: rotate(180deg);
}

.date-range-overlay {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-xl);
    z-index: 9999;
    min-width: 900px;
    margin-top: var(--spacing-1);
    display: none;
}

.date-range-content {
    padding: var(--spacing-5);
}

.date-range-panels {
    display: grid;
    grid-template-columns: 1fr 1fr 150px;
    gap: var(--spacing-5);
}

.date-panel {
    background: var(--bg-secondary);
    border-radius: var(--border-radius);
    padding: var(--spacing-4);
}

.date-panel h5 {
    margin: 0 0 calc(var(--spacing-3) / 2) 0;
    font-size: var(--font-size-sm);
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.date-input {
    width: 100%;
    padding: var(--spacing-2) var(--spacing-3);
    border: 1px solid var(--border-primary);
    border-radius: var(--border-radius);
    font-size: var(--font-size-sm);
    margin-bottom: var(--spacing-3);
    background: var(--bg-card);
    color: var(--text-primary);
    transition: border-color 0.2s ease;
}

.date-input:focus {
    outline: none;
    border-color: var(--primary-accent);
    box-shadow: 0 0 0 2px var(--primary-accent-light);
}

.calendar-container {
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: var(--border-radius);
    overflow: hidden;
}

.presets-panel {
    background: var(--bg-tertiary);
    border-radius: var(--border-radius);
    padding: var(--spacing-4);
    min-width: 150px;
}

.presets-grid {
    display: flex;
    flex-direction: column;
    gap: calc(var(--spacing-1) + 2px);
}

.preset-btn {
    padding: var(--spacing-1) var(--spacing-2);
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: var(--border-radius);
    font-size: var(--font-size-xs);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    color: var(--text-primary);
    white-space: nowrap;
    width: 100%;
}

.preset-btn:hover {
    background: var(--primary-accent);
    color: white;
    border-color: var(--primary-accent);
    transform: translateY(-1px);
    box-shadow: 0 2px 4px var(--primary-accent-light);
}

.date-range-footer {
    display: flex;
    justify-content: flex-end;
    gap: var(--spacing-3);
    padding: var(--spacing-4) var(--spacing-5);
    border-top: 1px solid var(--border-primary);
    background: var(--bg-secondary);
}

@media (max-width: 1024px) {
    .date-range-overlay {
        min-width: 100%;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        margin: 0;
        border-radius: 0;
        height: 100vh;
        overflow-y: auto;
    }
    
    .date-range-panels {
        grid-template-columns: 1fr;
        gap: var(--spacing-4);
    }
    
    .presets-grid {
        gap: var(--spacing-1);
    }
}
</style>
<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base-redesign.php';
?>