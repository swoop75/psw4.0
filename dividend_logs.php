<?php
/**
 * File: dividend_logs.php
 * Description: Dividend logs interface for PSW 4.0 - displays imported dividend data with filtering and sorting
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
    'sort_by' => $_GET['sort_by'] ?? 'payment_date',
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
        $whereConditions[] = "(ld.isin LIKE ? OR ml.name LIKE ? OR ld.ticker LIKE ?)";
        $searchTerm = '%' . $dbFilters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($dbFilters['date_from'])) {
        $whereConditions[] = "ld.payment_date >= ?";
        $params[] = $dbFilters['date_from'];
    }
    
    if (!empty($dbFilters['date_to'])) {
        $whereConditions[] = "ld.payment_date <= ?";
        $params[] = $dbFilters['date_to'];
    }
    
    if (!empty($dbFilters['broker_id'])) {
        $whereConditions[] = "ld.broker_id = ?";
        $params[] = $dbFilters['broker_id'];
    }
    
    if (!empty($dbFilters['account_group_id'])) {
        $whereConditions[] = "ld.portfolio_account_group_id = ?";
        $params[] = $dbFilters['account_group_id'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Sorting
    $allowedSortColumns = [
        'payment_date' => 'ld.payment_date',
        'isin' => 'ld.isin',
        'company_name' => 'ml.name',
        'ticker' => 'ld.ticker',
        'broker_name' => 'b.broker_name',
        'account_group' => 'pag.portfolio_group_name',
        'shares_held' => 'ld.shares_held',
        'dividend_amount_local' => 'ld.dividend_amount_local',
        'currency_local' => 'ld.currency_local',
        'net_dividend_sek' => 'ld.net_dividend_sek',
        'tax_amount_sek' => 'ld.tax_amount_sek'
    ];
    
    $sortColumn = $allowedSortColumns[$filters['sort_by']] ?? 'ld.payment_date';
    $sortOrder = strtoupper($filters['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
    
    // Get total count
    $countSql = "
        SELECT COUNT(*) as total
        FROM psw_portfolio.log_dividends ld
        LEFT JOIN psw_foundation.masterlist ml ON ld.isin COLLATE utf8mb4_unicode_ci = ml.isin COLLATE utf8mb4_unicode_ci
        LEFT JOIN psw_foundation.brokers b ON ld.broker_id = b.broker_id
        LEFT JOIN psw_foundation.portfolio_account_groups pag ON ld.portfolio_account_group_id = pag.portfolio_account_group_id
        WHERE $whereClause
    ";
    
    $countStmt = $portfolioDb->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch()['total'];
    
    // Get paginated data
    $offset = ($page - 1) * $limit;
    $dataSql = "
        SELECT 
            ld.*,
            ml.name as company_name,
            b.broker_name,
            pag.portfolio_group_name as account_group_name
        FROM psw_portfolio.log_dividends ld
        LEFT JOIN psw_foundation.masterlist ml ON ld.isin COLLATE utf8mb4_unicode_ci = ml.isin COLLATE utf8mb4_unicode_ci
        LEFT JOIN psw_foundation.brokers b ON ld.broker_id = b.broker_id
        LEFT JOIN psw_foundation.portfolio_account_groups pag ON ld.portfolio_account_group_id = pag.portfolio_account_group_id
        WHERE $whereClause
        ORDER BY $sortColumn $sortOrder
        LIMIT $limit OFFSET $offset
    ";
    
    $dataStmt = $portfolioDb->prepare($dataSql);
    $dataStmt->execute($params);
    $dividends = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get filter options
    $brokers = $foundationDb->query("SELECT broker_id, broker_name FROM brokers ORDER BY broker_name")->fetchAll(PDO::FETCH_ASSOC);
    $accountGroups = $foundationDb->query("SELECT portfolio_account_group_id, portfolio_group_name FROM portfolio_account_groups ORDER BY portfolio_group_name")->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $statsSql = "
        SELECT 
            COUNT(*) as total_dividends,
            SUM(ld.dividend_amount_sek) as total_dividend_sek,
            SUM(ld.tax_amount_sek) as total_tax_sek,
            SUM(ld.net_dividend_sek) as total_net_sek,
            COUNT(DISTINCT ld.isin) as unique_companies
        FROM psw_portfolio.log_dividends ld
        LEFT JOIN psw_foundation.masterlist ml ON ld.isin COLLATE utf8mb4_unicode_ci = ml.isin COLLATE utf8mb4_unicode_ci
        LEFT JOIN psw_foundation.brokers b ON ld.broker_id = b.broker_id
        LEFT JOIN psw_foundation.portfolio_account_groups pag ON ld.portfolio_account_group_id = pag.portfolio_account_group_id
        WHERE $whereClause
    ";
    
    $statsStmt = $portfolioDb->prepare($statsSql);
    $statsStmt->execute($params);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Handle CSV export
    if (!empty($_GET['export']) && $_GET['export'] === 'csv') {
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="psw_dividend_logs_export_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, [
            'Payment Date',
            'ISIN',
            'Company',
            'Ticker',
            'Broker',
            'Account Group',
            'Shares',
            'Dividend Amount (Local)',
            'Currency',
            'Net Dividend (SEK)',
            'Tax Amount (SEK)'
        ]);
        
        // Add data rows
        foreach ($dividends as $dividend) {
            fputcsv($output, [
                $dividend['payment_date'],
                $dividend['isin'],
                $dividend['company_name'] ?? 'Unknown Company',
                $dividend['ticker'] ?? '-',
                $dividend['broker_name'] ?? '-',
                $dividend['account_group_name'] ?? '-',
                number_format($dividend['shares_held'], 0, '.', ''),
                number_format($dividend['dividend_amount_local'], 2, '.', ''),
                $dividend['currency_local'],
                number_format($dividend['net_dividend_sek'], 2, '.', ''),
                number_format($dividend['tax_amount_sek'], 2, '.', '')
            ]);
        }
        
        fclose($output);
        exit();
    }
    
    // Get earliest dividend date for 'since start' preset
    $earliestDateSql = "SELECT MIN(payment_date) as earliest_date FROM psw_portfolio.log_dividends";
    $earliestDateStmt = $portfolioDb->prepare($earliestDateSql);
    $earliestDateStmt->execute();
    $earliestDateResult = $earliestDateStmt->fetch(PDO::FETCH_ASSOC);
    $earliestDate = $earliestDateResult['earliest_date'] ?? '2020-01-01'; // Fallback if no data
    
    // Get full year range for enhanced calendar navigation
    $yearRangeSql = "SELECT MIN(YEAR(payment_date)) as min_year, MAX(YEAR(payment_date)) as max_year FROM psw_portfolio.log_dividends";
    $yearRangeStmt = $portfolioDb->prepare($yearRangeSql);
    $yearRangeStmt->execute();
    $yearRangeResult = $yearRangeStmt->fetch(PDO::FETCH_ASSOC);
    $minYear = $yearRangeResult['min_year'] ?? date('Y') - 10;
    $maxYear = $yearRangeResult['max_year'] ?? date('Y');
    
    // Default date range is now applied to filters and will be used in database query
    
} catch (Exception $e) {
    $dividends = [];
    $totalRecords = 0;
    $brokers = [];
    $accountGroups = [];
    $stats = [
        'total_dividends' => 0,
        'total_dividend_sek' => 0,
        'total_tax_sek' => 0,
        'total_net_sek' => 0,
        'unique_companies' => 0
    ];
    $earliestDate = '2020-01-01'; // Fallback date
    $minYear = date('Y') - 10; // Fallback min year
    $maxYear = date('Y'); // Fallback max year
    $errorMessage = $e->getMessage();
}

// Initialize variables for template
$pageTitle = 'Dividend Logs - PSW 4.0';
$pageDescription = 'Track and analyze dividend payments';
$additionalCSS = [];
$additionalJS = [];

// Prepare content for dividend logs page
ob_start();
?>
<div class="psw-content">
    <!-- Page Header -->
    <div class="psw-card psw-mb-6">
        <div class="psw-card-header">
            <h1 class="psw-card-title">
                <i class="fas fa-coins psw-card-title-icon"></i>
                Dividend Logs
            </h1>
            <p class="psw-card-subtitle">Track and analyze dividend payments across the portfolio</p>
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
                        <?php echo Localization::formatNumber($stats['total_dividends']); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Total Dividends</div>
                </div>
            </div>
        </div>
        
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #10B981, #059669); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-coins" style="color: white; font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                        <?php echo Localization::formatCurrency($stats['total_net_sek'], 0, 'SEK'); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Net Income</div>
                </div>
            </div>
        </div>
        
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #F59E0B, #D97706); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-percentage" style="color: white; font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                        <?php echo Localization::formatCurrency($stats['total_tax_sek'], 0, 'SEK'); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Total Tax</div>
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
            <div style="display: grid; grid-template-columns: 1fr 250px 150px 150px 1fr; gap: 1rem; align-items: end;">
                <div class="psw-form-group">
                    <label class="psw-form-label">Search</label>
                    <input type="text" id="search-input" class="psw-form-input" 
                           placeholder="Search by ISIN, company, or ticker..." 
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                
                <!-- Date Range Picker - New Central Component -->
                <div class="psw-form-group">
                    <label class="psw-form-label">Date Range</label>
                    <div id="dividend-date-range" class="date-range-picker">
                        <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                        <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                        
                        <div class="date-range-display" onclick="window.toggleDateRangePicker();" style="cursor: pointer;">
                            <i class="fas fa-calendar-alt"></i>
                            <span class="date-range-text" id="dateRangeText">
                                <?php 
                                // Display the current date range (now always has values)
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
                
                <!-- Filter Action Buttons - Same Row -->
                <div class="psw-form-group" style="display: flex; gap: 0.5rem; align-items: end; justify-content: flex-end;">
                    <a href="<?php echo BASE_URL; ?>/add_dividend.php" class="psw-btn psw-btn-success">
                        <i class="fas fa-plus psw-btn-icon"></i>Add
                    </a>
                    <button type="button" class="psw-btn psw-btn-primary" onclick="applyFilters()">
                        <i class="fas fa-filter psw-btn-icon"></i>Apply
                    </button>
                    <button type="button" class="psw-btn psw-btn-secondary" onclick="clearFilters()">
                        <i class="fas fa-times psw-btn-icon"></i>Clear
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
                    <?php echo Localization::formatNumber($totalRecords); ?> dividend entries
                    <?php if (!empty($filters['search']) || !empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['broker_id']) || !empty($filters['account_group_id'])): ?>
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
            <?php if (!empty($dividends)): ?>
                <table class="psw-table">
                    <thead>
                        <tr>
                            <th style="cursor: pointer;" onclick="sortBy('payment_date')">
                                Payment Date
                                <?php if ($filters['sort_by'] == 'payment_date'): ?>
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
                            <th>Broker</th>
                            <th>Account Group</th>
                            <th style="text-align: right;">Shares</th>
                            <th style="text-align: right;">Dividend (Local)</th>
                            <th>Currency</th>
                            <th style="text-align: right;">Net (SEK)</th>
                            <th style="text-align: right;">Tax (SEK)</th>
                            <th style="text-align: center; width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dividends as $dividend): ?>
                            <tr>
                                <td><?php echo Localization::formatDate($dividend['payment_date']); ?></td>
                                <td style="font-family: var(--font-family-mono); font-size: 0.875rem;"><?php echo htmlspecialchars($dividend['isin']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($dividend['company_name'] ?? 'Unknown Company'); ?>
                                </td>
                                <td style="font-family: var(--font-family-mono); font-size: 0.875rem;"><?php echo htmlspecialchars($dividend['ticker'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($dividend['broker_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($dividend['account_group_name'] ?? '-'); ?></td>
                                <td style="text-align: right;"><?php echo Localization::formatNumber($dividend['shares_held'], 0); ?></td>
                                <td style="text-align: right;"><?php echo Localization::formatNumber($dividend['dividend_amount_local'], 2); ?></td>
                                <td><?php echo htmlspecialchars($dividend['currency_local']); ?></td>
                                <td style="text-align: right; color: var(--success-color); font-weight: 600;">
                                    <?php echo Localization::formatCurrency($dividend['net_dividend_sek'], 2, 'SEK'); ?>
                                </td>
                                <td style="text-align: right; color: var(--error-color);">
                                    -<?php echo Localization::formatCurrency($dividend['tax_amount_sek'], 2, 'SEK'); ?>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 0.25rem; justify-content: center;">
                                        <a href="<?php echo BASE_URL; ?>/edit_dividend.php?id=<?php echo $dividend['log_id']; ?>" 
                                           class="psw-btn psw-btn-sm psw-btn-secondary" 
                                           title="Edit dividend">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                                class="psw-btn psw-btn-sm psw-btn-danger"
                                                onclick="deleteDividend(<?php echo $dividend['log_id']; ?>, '<?php echo htmlspecialchars($dividend['company_name'], ENT_QUOTES); ?>')" 
                                                title="Delete dividend">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                    <i class="fas fa-chart-bar" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <h3 style="margin-bottom: 0.5rem;">No dividend records found</h3>
                    <p>No dividend data matches your current filters. Try adjusting your search criteria.</p>
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
    
    // Broker filter
    const brokerSelect = document.getElementById('broker-filter');
    if (brokerSelect.value) params.set('broker_id', brokerSelect.value);
    
    // Account group filter
    const accountGroupSelect = document.getElementById('account-group-filter');
    if (accountGroupSelect.value) params.set('account_group_id', accountGroupSelect.value);
    
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

// Search functionality with debounce
let searchTimeout;
document.getElementById('search-input').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 500);
});

// Simple test function
window.testFunction = function() {
    alert('Test function called successfully!');
    var overlay = document.getElementById('dateRangeOverlay');
    alert('Overlay found: ' + (overlay ? 'YES' : 'NO'));
};

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
    var picker = document.getElementById('dividend-date-range');
    
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
            
            // Ensure the picker container has relative positioning
            picker.style.position = 'relative';
            
            // Ensure all content is properly styled with 10px padding on all sides
            var overlayContent = overlay.querySelector('.date-range-content');
            if (overlayContent) {
                overlayContent.style.setProperty('display', 'block', 'important');
                overlayContent.style.setProperty('visibility', 'visible', 'important');
                overlayContent.style.setProperty('padding', '10px', 'important'); // 10px on all sides
            }
            
            // Style the panels grid with 5px padding and 40px gap between TO/FROM
            var panelsGrid = overlay.querySelector('.date-range-panels');
            if (panelsGrid) {
                panelsGrid.style.setProperty('display', 'grid', 'important');
                // Calculate exact spacing: 730px - 20px padding = 710px available
                // FROM (240px) + gap (40px) + TO (240px) + gap (40px) + PRESETS (150px) = 710px exactly
                panelsGrid.style.setProperty('grid-template-columns', '240px 240px 150px', 'important');
                panelsGrid.style.setProperty('gap', '0 40px', 'important'); // Exactly 40px between all columns
                panelsGrid.style.setProperty('height', 'calc(100% - 10px)', 'important');
                panelsGrid.style.setProperty('margin', '0', 'important');
                panelsGrid.style.setProperty('justify-content', 'start', 'important'); // No centering needed, perfect fit
                panelsGrid.style.setProperty('align-items', 'start', 'important'); // Align all panels to start
            }
            
            // Style the date panels with aligned calendar positioning
            var panels = overlay.querySelectorAll('.date-panel');
            panels.forEach(function(panel) {
                panel.style.setProperty('background', 'var(--bg-secondary)', 'important');
                panel.style.setProperty('border-radius', 'var(--border-radius)', 'important');
                panel.style.setProperty('padding', '16px', 'important');
                panel.style.setProperty('display', 'flex', 'important');
                panel.style.setProperty('flex-direction', 'column', 'important');
            });
            
            // Style the presets panel
            var presetsPanel = overlay.querySelector('.presets-panel');
            if (presetsPanel) {
                presetsPanel.style.setProperty('background', 'var(--bg-tertiary)', 'important');
                presetsPanel.style.setProperty('border-radius', 'var(--border-radius)', 'important');
                presetsPanel.style.setProperty('padding', '16px', 'important');
            }
            
            // Align calendar containers to start at same row as preset buttons
            var calendarContainers = overlay.querySelectorAll('.calendar-container');
            var presetsGrid = overlay.querySelector('.presets-grid');
            
            if (presetsGrid) {
                // Get the height of date input + header to calculate offset
                var firstDateInput = overlay.querySelector('.date-input');
                var firstHeader = overlay.querySelector('.date-panel h5');
                var inputHeight = firstDateInput ? firstDateInput.offsetHeight : 32;
                var headerHeight = firstHeader ? firstHeader.offsetHeight : 20;
                var spacingHeight = 16; // spacing between input and calendar
                var totalInputArea = headerHeight + inputHeight + spacingHeight;
                
                calendarContainers.forEach(function(container) {
                    var datePanel = container.closest('.date-panel');
                    var dateInput = datePanel.querySelector('.date-input');
                    var panelHeader = datePanel.querySelector('h5');
                    
                    // Ensure date input is visible and properly ordered
                    if (dateInput) {
                        dateInput.style.setProperty('display', 'block', 'important');
                        dateInput.style.setProperty('visibility', 'visible', 'important');
                        dateInput.style.setProperty('order', '2', 'important'); // After header
                        dateInput.style.setProperty('z-index', '1000', 'important');
                        dateInput.style.setProperty('position', 'relative', 'important');
                    }
                    
                    // Ensure header is visible and positioned first
                    if (panelHeader) {
                        panelHeader.style.setProperty('display', 'block', 'important');
                        panelHeader.style.setProperty('visibility', 'visible', 'important');
                        panelHeader.style.setProperty('order', '1', 'important'); // First item
                        panelHeader.style.setProperty('z-index', '1001', 'important');
                    }
                    
                    // Set date panel to flexbox layout for proper sequential positioning
                    datePanel.style.setProperty('display', 'flex', 'important');
                    datePanel.style.setProperty('flex-direction', 'column', 'important');
                    datePanel.style.setProperty('gap', '8px', 'important');
                    
                    // Position calendar as third item in flex layout
                    container.style.setProperty('order', '3', 'important'); // After header and input
                    container.style.setProperty('align-self', 'start', 'important');
                    container.style.setProperty('width', '240px', 'important');
                    container.style.setProperty('max-width', '240px', 'important');
                    container.style.setProperty('margin-top', '0', 'important');
                });
            }
            
            // Style date input fields to be visible and properly positioned with 240px width
            var dateInputs = overlay.querySelectorAll('.date-input');
            dateInputs.forEach(function(input) {
                input.style.setProperty('display', 'block', 'important');
                input.style.setProperty('visibility', 'visible', 'important');  
                input.style.setProperty('margin-bottom', '8px', 'important');
                input.style.setProperty('width', '240px', 'important'); // Match calendar width exactly
                input.style.setProperty('max-width', '240px', 'important');
                input.style.setProperty('box-sizing', 'border-box', 'important');
                input.style.setProperty('padding', '8px 12px', 'important');
                input.style.setProperty('border', '1px solid var(--border-primary)', 'important');
                input.style.setProperty('border-radius', 'var(--border-radius)', 'important');
                input.style.setProperty('font-size', 'var(--font-size-sm)', 'important');
                input.style.setProperty('background', 'var(--bg-card)', 'important');
                input.style.setProperty('color', 'var(--text-primary)', 'important');
            });
            
            // Style the date panel headers (FROM DATE / TO DATE)
            var datePanelHeaders = overlay.querySelectorAll('.date-panel h5');
            datePanelHeaders.forEach(function(header) {
                header.style.setProperty('display', 'block', 'important');
                header.style.setProperty('visibility', 'visible', 'important');
                header.style.setProperty('margin', '0 0 8px 0', 'important');
                header.style.setProperty('font-size', 'var(--font-size-sm)', 'important');
                header.style.setProperty('font-weight', '600', 'important');
                header.style.setProperty('color', 'var(--text-secondary)', 'important');
                header.style.setProperty('text-transform', 'uppercase', 'important');
                header.style.setProperty('letter-spacing', '0.5px', 'important');
            });
            
            // Style the footer with 10px spacing
            var footer = overlay.querySelector('.date-range-footer');
            if (footer) {
                footer.style.setProperty('padding', '10px', 'important'); // 10px on all sides
                footer.style.setProperty('margin-top', 'auto', 'important');
                footer.style.setProperty('margin-bottom', '0', 'important');
            }
            
            // Set current values in inputs
            var fromInput = document.querySelector('input[name="date_from"]');
            var toInput = document.querySelector('input[name="date_to"]');
            
            if (fromInput) document.getElementById('fromDateInput').value = fromInput.value;
            if (toInput) document.getElementById('toDateInput').value = toInput.value;
            
            // Add event listeners for real-time display updates
            var fromDateInput = document.getElementById('fromDateInput');
            var toDateInput = document.getElementById('toDateInput');
            
            if (fromDateInput) {
                fromDateInput.addEventListener('input', function() {
                    tempFromDate = this.value;
                    updateDateRangeDisplay();
                });
            }
            
            if (toDateInput) {
                toDateInput.addEventListener('input', function() {
                    tempToDate = this.value;
                    updateDateRangeDisplay();
                });
            }
            
            // Initialize available years and reset calendar views
            initializeAvailableYears();
            calendarViews.from = 'calendar';
            calendarViews.to = 'calendar';
            yearPageFrom = 0;
            yearPageTo = 0;
            
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
            
            // Set defaults if empty - use user-friendly default range
            if ((!fromInput || !fromInput.value) && (!toInput || !toInput.value)) {
                // Use the user-friendly default range (current month + 3 months back)
                var defaultFromStr = '<?php echo $displayDefaultFrom; ?>';
                var defaultToStr = '<?php echo $displayDefaultTo; ?>';
                
                if (fromInput) fromInput.value = defaultFromStr;
                if (toInput) toInput.value = defaultToStr;
                
                document.getElementById('fromDateInput').value = defaultFromStr;
                document.getElementById('toDateInput').value = defaultToStr;
                
                // Update display
                document.getElementById('dateRangeText').textContent = defaultFromStr + ' - ' + defaultToStr;
            }
        } else {
            // Hide the overlay
            closeDateRangePicker();
        }
    }
};

window.closeDateRangePicker = function(event) {
    // Prevent event bubbling if called from a button click
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    var overlay = document.getElementById('dateRangeOverlay');
    var picker = document.getElementById('dividend-date-range');
    
    overlay.style.display = 'none';
    picker.classList.remove('open');
}

window.applyPreset = function(preset, event) {
    // Prevent event bubbling to avoid closing the date picker
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
    // Prevent event bubbling if called from a button click
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

// Render normal calendar view
function renderCalendarView(type, date) {
    const year = date.getFullYear();
    const month = date.getMonth();
    
    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    
    let html = `
        <div class="calendar-header">
            <button type="button" class="calendar-nav" onclick="navigateYear('${type}', -1, event)">
                <i class="fas fa-angle-double-left"></i>
            </button>
            <button type="button" class="calendar-nav" onclick="navigateMonth('${type}', -1, event)">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button type="button" class="calendar-month-btn" onclick="showMonthsView('${type}', event)">${monthNames[month]}</button>
            <button type="button" class="calendar-year-btn" onclick="showYearsView('${type}', event)">${year}</button>
            <button type="button" class="calendar-nav" onclick="navigateMonth('${type}', 1, event)">
                <i class="fas fa-chevron-right"></i>
            </button>
            <button type="button" class="calendar-nav" onclick="navigateYear('${type}', 1, event)">
                <i class="fas fa-angle-double-right"></i>
            </button>
        </div>
        <div class="calendar-grid">
            <div class="calendar-weekdays">
                <div class="calendar-weekday">Su</div>
                <div class="calendar-weekday">Mo</div>
                <div class="calendar-weekday">Tu</div>
                <div class="calendar-weekday">We</div>
                <div class="calendar-weekday">Th</div>
                <div class="calendar-weekday">Fr</div>
                <div class="calendar-weekday">Sa</div>
            </div>
            <div class="calendar-days">
    `;
    
    // Get first day of month and days in month
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDayOfWeek = firstDay.getDay();
    
    // Previous month's trailing days
    const prevMonth = new Date(year, month - 1, 0);
    const prevMonthDays = prevMonth.getDate();
    
    for (let i = prevMonthDays - startingDayOfWeek + 1; i <= prevMonthDays; i++) {
        html += `<div class="calendar-day other-month">${i}</div>`;
    }
    
    // Current month days
    const today = new Date();
    const selectedDate = type === 'from' ? tempFromDate : tempToDate;
    
    for (let day = 1; day <= daysInMonth; day++) {
        const currentDate = new Date(year, month, day);
        const dateStr = formatDate(currentDate);
        const isToday = dateStr === formatDate(today);
        const isSelected = dateStr === selectedDate;
        
        let classes = 'calendar-day';
        if (isToday) classes += ' today';
        if (isSelected) classes += ' selected';
        
        html += `<div class="${classes}" onclick="selectCalendarDate('${type}', '${dateStr}', event)">${day}</div>`;
    }
    
    // Next month's leading days
    const remainingCells = 42 - (startingDayOfWeek + daysInMonth);
    for (let day = 1; day <= remainingCells && remainingCells < 7; day++) {
        html += `<div class="calendar-day other-month">${day}</div>`;
    }
    
    html += `
            </div>
        </div>
    `;
    
    return html;
}

// Render months selection view
function renderMonthsView(type, year) {
    const monthNames = [
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
    ];
    
    let html = `
        <div class="calendar-header">
            <button type="button" class="calendar-back-btn" onclick="backToCalendarView('${type}', event)">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="calendar-view-title">Select Month ${year}</div>
        </div>
        <div class="calendar-selection-grid">
    `;
    
    monthNames.forEach((monthName, index) => {
        html += `<button type="button" class="calendar-selection-btn" onclick="selectMonth('${type}', ${index}, event)">${monthName}</button>`;
    });
    
    html += `</div>`;
    return html;
}

// Render years selection view
function renderYearsView(type) {
    const currentPage = type === 'from' ? yearPageFrom : yearPageTo;
    const startIndex = currentPage * yearsPerPage;
    const endIndex = Math.min(startIndex + yearsPerPage, availableYears.length);
    const hasNextPage = endIndex < availableYears.length;
    const hasPrevPage = currentPage > 0;
    
    let html = `
        <div class="calendar-header">
            <button type="button" class="calendar-back-btn" onclick="backToCalendarView('${type}', event)">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="calendar-view-title">Select Year</div>
            ${hasPrevPage ? `<button type="button" class="calendar-nav" onclick="navigateYearPage('${type}', -1, event)"><i class="fas fa-chevron-left"></i></button>` : '<span></span>'}
            ${hasNextPage ? `<button type="button" class="calendar-nav" onclick="navigateYearPage('${type}', 1, event)"><i class="fas fa-chevron-right"></i></button>` : '<span></span>'}
        </div>
        <div class="calendar-selection-grid">
    `;
    
    for (let i = startIndex; i < endIndex; i++) {
        const year = availableYears[i];
        html += `<button type="button" class="calendar-selection-btn" onclick="selectYear('${type}', ${year}, event)">${year}</button>`;
    }
    
    html += `</div>`;
    return html;
}

// Enhanced navigation functions
window.navigateMonth = function(type, direction, event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    if (type === 'from') {
        currentFromMonth.setMonth(currentFromMonth.getMonth() + direction);
        renderCalendar('from', currentFromMonth);
    } else {
        currentToMonth.setMonth(currentToMonth.getMonth() + direction);
        renderCalendar('to', currentToMonth);
    }
}

window.navigateYear = function(type, direction, event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    if (type === 'from') {
        currentFromMonth.setFullYear(currentFromMonth.getFullYear() + direction);
        renderCalendar('from', currentFromMonth);
    } else {
        currentToMonth.setFullYear(currentToMonth.getFullYear() + direction);
        renderCalendar('to', currentToMonth);
    }
}

window.showMonthsView = function(type, event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    calendarViews[type] = 'months';
    renderCalendar(type, type === 'from' ? currentFromMonth : currentToMonth);
}

window.showYearsView = function(type, event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    calendarViews[type] = 'years';
    renderCalendar(type, type === 'from' ? currentFromMonth : currentToMonth);
}

window.backToCalendarView = function(type, event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    calendarViews[type] = 'calendar';
    renderCalendar(type, type === 'from' ? currentFromMonth : currentToMonth);
}

window.selectMonth = function(type, monthIndex, event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    if (type === 'from') {
        currentFromMonth.setMonth(monthIndex);
    } else {
        currentToMonth.setMonth(monthIndex);
    }
    
    calendarViews[type] = 'calendar';
    renderCalendar(type, type === 'from' ? currentFromMonth : currentToMonth);
}

window.selectYear = function(type, year, event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    if (type === 'from') {
        currentFromMonth.setFullYear(year);
    } else {
        currentToMonth.setFullYear(year);
    }
    
    calendarViews[type] = 'calendar';
    renderCalendar(type, type === 'from' ? currentFromMonth : currentToMonth);
}

window.navigateYearPage = function(type, direction, event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    if (type === 'from') {
        yearPageFrom = Math.max(0, Math.min(yearPageFrom + direction, Math.ceil(availableYears.length / yearsPerPage) - 1));
    } else {
        yearPageTo = Math.max(0, Math.min(yearPageTo + direction, Math.ceil(availableYears.length / yearsPerPage) - 1));
    }
    
    renderCalendar(type, type === 'from' ? currentFromMonth : currentToMonth);
}

window.selectCalendarDate = function(type, dateStr, event) {
    // Prevent event bubbling to avoid closing the date picker
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    const input = document.getElementById(type + 'DateInput');
    input.value = dateStr;
    
    if (type === 'from') {
        tempFromDate = dateStr;
    } else {
        tempToDate = dateStr;
    }
    
    // Re-render calendar to show selection
    renderCalendar(type, type === 'from' ? currentFromMonth : currentToMonth);
    
    // Update the display immediately when clicking calendar
    updateDateRangeDisplay();
}

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
    const picker = document.getElementById('dividend-date-range');
    const overlay = document.getElementById('dateRangeOverlay');
    
    if (picker && !picker.contains(e.target) && overlay && overlay.style.display === 'block') {
        closeDateRangePicker();
    }
});
document.getElementById('broker-filter').addEventListener('change', applyFilters);
document.getElementById('account-group-filter').addEventListener('change', applyFilters);

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
    
    // Broker filter
    const brokerSelect = document.getElementById('broker-filter');
    if (brokerSelect.value) params.set('broker_id', brokerSelect.value);
    
    // Account group filter
    const accountGroupSelect = document.getElementById('account-group-filter');
    if (accountGroupSelect.value) params.set('account_group_id', accountGroupSelect.value);
    
    // Add export flag
    params.set('export', 'csv');
    
    // Create download link
    const exportUrl = window.location.pathname + '?' + params.toString();
    
    // Trigger download
    const link = document.createElement('a');
    link.href = exportUrl;
    link.download = 'psw_dividend_logs_export.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Debug function to check if JavaScript is loaded
console.log('Date range picker JavaScript loaded');
console.log('toggleDateRangePicker function available:', typeof window.toggleDateRangePicker);

// Initialize calendars when overlay opens
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, setting up date range picker');
    
    // Skip adding event listener since we're using onclick attribute
    
    var fromInput = document.querySelector('input[name="date_from"]');
    var toInput = document.querySelector('input[name="date_to"]');
    
    if (fromInput && toInput && !fromInput.value && !toInput.value) {
        // Use the user-friendly default range (current month + 3 months back)
        var defaultFrom = '<?php echo $displayDefaultFrom; ?>';
        var defaultTo = '<?php echo $displayDefaultTo; ?>';
        
        fromInput.value = defaultFrom;
        toInput.value = defaultTo;
        var dateRangeText = document.getElementById('dateRangeText');
        if (dateRangeText) {
            dateRangeText.textContent = defaultFrom + ' - ' + defaultTo;
        }
    }
    
    // Initialize calendar months
    if (fromInput && fromInput.value) {
        currentFromMonth = new Date(fromInput.value);
    }
    if (toInput && toInput.value) {
        currentToMonth = new Date(toInput.value);
    }
});

// Delete dividend function
function deleteDividend(dividendId, companyName) {
    if (confirm(`Are you sure you want to delete the dividend record for ${companyName}?`)) {
        fetch(`<?php echo BASE_URL; ?>/api/delete_dividend.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                log_id: dividendId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Dividend record deleted successfully!');
                location.reload();
            } else {
                alert('Error deleting dividend: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting dividend: ' + error.message);
        });
    }
}
</script>

<!-- Date Range Picker Styles -->
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

.calendar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--spacing-2) var(--spacing-3);
    background: var(--bg-tertiary);
    border-bottom: 1px solid var(--border-primary);
}

.calendar-nav {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: var(--spacing-1);
    border-radius: var(--border-radius);
    transition: all 0.2s ease;
}

.calendar-nav:hover {
    background: var(--primary-accent-light);
    color: var(--primary-accent);
}

.calendar-month-year {
    font-weight: 600;
    font-size: var(--font-size-sm);
    color: var(--text-primary);
}

.calendar-grid {
    padding: var(--spacing-2) var(--spacing-2) calc(var(--spacing-2) / 2) var(--spacing-2);
}

.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    margin-bottom: var(--spacing-1);
}

.calendar-weekday {
    text-align: center;
    font-size: var(--font-size-xs);
    font-weight: 600;
    color: var(--text-secondary);
    padding: var(--spacing-1);
}

.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
}

.calendar-day {
    text-align: center;
    padding: var(--spacing-1);
    cursor: pointer;
    border-radius: var(--border-radius);
    font-size: var(--font-size-xs);
    transition: all 0.2s ease;
    min-height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.calendar-day:hover {
    background: var(--primary-accent-light);
    color: var(--primary-accent);
}

.calendar-day.selected {
    background: var(--primary-accent);
    color: white;
    font-weight: 600;
}

.calendar-day.other-month {
    color: var(--text-muted);
}

.calendar-day.today {
    background: var(--info-color);
    color: white;
    font-weight: 600;
}

.calendar-day.today.selected {
    background: var(--primary-accent);
}

/* Enhanced Calendar Navigation Styles */
.calendar-month-btn,
.calendar-year-btn {
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: var(--border-radius);
    padding: var(--spacing-1) var(--spacing-2);
    font-size: var(--font-size-sm);
    font-weight: 600;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.2s ease;
    margin: 0 2px;
}

.calendar-month-btn:hover,
.calendar-year-btn:hover {
    background: var(--primary-accent);
    color: white;
    border-color: var(--primary-accent);
}

.calendar-back-btn {
    background: var(--bg-secondary);
    border: 1px solid var(--border-primary);
    border-radius: var(--border-radius);
    padding: var(--spacing-1);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s ease;
}

.calendar-back-btn:hover {
    background: var(--primary-accent-light);
    color: var(--primary-accent);
    border-color: var(--primary-accent);
}

.calendar-view-title {
    font-weight: 600;
    font-size: var(--font-size-sm);
    color: var(--text-primary);
    text-align: center;
    flex: 1;
}

.calendar-selection-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    grid-template-rows: repeat(4, 1fr);
    gap: var(--spacing-1);
    padding: var(--spacing-2);
    min-height: 200px;
}

.calendar-selection-btn {
    padding: var(--spacing-2) var(--spacing-1);
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
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
}

.calendar-selection-btn:hover {
    background: var(--primary-accent);
    color: white;
    border-color: var(--primary-accent);
    transform: translateY(-1px);
    box-shadow: 0 2px 4px var(--primary-accent-light);
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

/* Filter width adjustments - using explicit grid layout instead */
</style>
<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base-redesign.php';
?>