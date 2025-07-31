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

// Get filter parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
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
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
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
                                if ($filters['date_from'] && $filters['date_to']) {
                                    echo $filters['date_from'] . ' - ' . $filters['date_to'];
                                } else {
                                    $defaultFrom = date('Y-m-01', strtotime('-3 months'));
                                    $defaultTo = date('Y-m-t');
                                    echo $defaultFrom . ' - ' . $defaultTo;
                                }
                                ?>
                            </span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        
                        <div class="date-range-overlay" id="dateRangeOverlay">
                            <div class="date-range-content">
                                <div class="date-range-panels">
                                    <div class="date-panel from-panel">
                                        <h5>From Date</h5>
                                        <input type="text" class="date-input" id="fromDateInput" placeholder="YYYY-MM-DD" onchange="updateCalendar('from')">
                                        <div class="calendar-container" id="fromCalendar"></div>
                                    </div>
                                    <div class="date-panel to-panel">
                                        <h5>To Date</h5>
                                        <input type="text" class="date-input" id="toDateInput" placeholder="YYYY-MM-DD" onchange="updateCalendar('to')">
                                        <div class="calendar-container" id="toCalendar"></div>
                                    </div>
                                    <div class="presets-panel">
                                        <div class="presets-grid">
                                            <button type="button" class="preset-btn" onclick="applyPreset('today')">Today</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('yesterday')">Yesterday</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('thisWeek')">This Week</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('prevWeek')">Previous Week</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('thisMonth')">This Month</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('prevMonth')">Previous Month</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('thisYear')">This Year</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('prevYear')">Previous Year</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('sinceStart')">Since Start</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="date-range-footer">
                                <button type="button" class="psw-btn psw-btn-secondary" onclick="closeDateRangePicker()">Cancel</button>
                                <button type="button" class="psw-btn psw-btn-primary" onclick="applyDateRange()">Apply</button>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dividends as $dividend): ?>
                            <tr>
                                <td><?php echo Localization::formatDate($dividend['payment_date']); ?></td>
                                <td style="font-family: var(--font-family-mono); font-size: 0.875rem;"><?php echo htmlspecialchars($dividend['isin']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($dividend['company_name'] ?? 'Unknown Company'); ?></strong>
                                </td>
                                <td style="font-family: var(--font-family-mono); font-size: 0.875rem;"><?php echo htmlspecialchars($dividend['ticker'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($dividend['broker_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($dividend['account_group_name'] ?? '-'); ?></td>
                                <td style="text-align: right;"><?php echo Localization::formatNumber($dividend['shares_held'], 4); ?></td>
                                <td style="text-align: right;"><?php echo Localization::formatNumber($dividend['dividend_amount_local'], 4); ?></td>
                                <td><?php echo htmlspecialchars($dividend['currency_local']); ?></td>
                                <td style="text-align: right; color: var(--success-color); font-weight: 600;">
                                    <?php echo Localization::formatCurrency($dividend['net_dividend_sek'], 2, 'SEK'); ?>
                                </td>
                                <td style="text-align: right; color: var(--error-color);">
                                    -<?php echo Localization::formatCurrency($dividend['tax_amount_sek'], 2, 'SEK'); ?>
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

// Make function global - proper implementation
window.toggleDateRangePicker = function() {
    var overlay = document.getElementById('dateRangeOverlay');
    var picker = document.getElementById('dividend-date-range');
    
    if (overlay && picker) {
        if (overlay.style.display === 'none' || overlay.style.display === '') {
            // Show the overlay with proper positioning
            overlay.style.setProperty('display', 'block', 'important');
            overlay.style.setProperty('position', 'absolute', 'important');
            overlay.style.top = '100%';
            overlay.style.left = '0';
            overlay.style.right = 'auto';
            overlay.style.setProperty('z-index', '9999999', 'important');
            overlay.style.minHeight = '400px';
            overlay.style.backgroundColor = 'white';
            overlay.style.border = '1px solid #ccc';
            overlay.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
            overlay.style.borderRadius = '8px';
            overlay.style.minWidth = '800px';
            picker.classList.add('open');
            
            // Ensure the picker container has relative positioning
            picker.style.position = 'relative';
            
            // Ensure content is visible
            var overlayContent = overlay.querySelector('.date-range-content');
            if (overlayContent) {
                overlayContent.style.display = 'block';
                overlayContent.style.visibility = 'visible';
            }
            
            // Set current values in inputs
            var fromInput = document.querySelector('input[name="date_from"]');
            var toInput = document.querySelector('input[name="date_to"]');
            
            if (fromInput) document.getElementById('fromDateInput').value = fromInput.value;
            if (toInput) document.getElementById('toDateInput').value = toInput.value;
            
            // Set defaults if empty
            if ((!fromInput || !fromInput.value) && (!toInput || !toInput.value)) {
                // Set default range: current month + 3 months back
                var today = new Date();
                var defaultFrom = new Date(today.getFullYear(), today.getMonth() - 3, 1);
                var defaultTo = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                
                var defaultFromStr = formatDate(defaultFrom);
                var defaultToStr = formatDate(defaultTo);
                
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

window.closeDateRangePicker = function() {
    var overlay = document.getElementById('dateRangeOverlay');
    var picker = document.getElementById('dividend-date-range');
    
    overlay.style.display = 'none';
    picker.classList.remove('open');
}

window.applyPreset = function(preset) {
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
            fromDate = new Date(2020, 0, 1);
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
}

window.applyDateRange = function() {
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
    }
}

window.renderCalendar = function(type, date) {
    const calendarContainer = document.getElementById(type + 'Calendar');
    const year = date.getFullYear();
    const month = date.getMonth();
    
    // Calendar header
    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    
    let html = `
        <div class="calendar-header">
            <button type="button" class="calendar-nav" onclick="navigateMonth('${type}', -1)">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="calendar-month-year">${monthNames[month]} ${year}</div>
            <button type="button" class="calendar-nav" onclick="navigateMonth('${type}', 1)">
                <i class="fas fa-chevron-right"></i>
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
        
        html += `<div class="${classes}" onclick="selectCalendarDate('${type}', '${dateStr}')">${day}</div>`;
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
    
    calendarContainer.innerHTML = html;
}

window.navigateMonth = function(type, direction) {
    if (type === 'from') {
        currentFromMonth.setMonth(currentFromMonth.getMonth() + direction);
        renderCalendar('from', currentFromMonth);
    } else {
        currentToMonth.setMonth(currentToMonth.getMonth() + direction);
        renderCalendar('to', currentToMonth);
    }
}

window.selectCalendarDate = function(type, dateStr) {
    const input = document.getElementById(type + 'DateInput');
    input.value = dateStr;
    
    if (type === 'from') {
        tempFromDate = dateStr;
    } else {
        tempToDate = dateStr;
    }
    
    // Re-render calendar to show selection
    renderCalendar(type, type === 'from' ? currentFromMonth : currentToMonth);
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
        var today = new Date();
        var defaultFrom = formatDate(new Date(today.getFullYear(), today.getMonth() - 3, 1));
        var defaultTo = formatDate(new Date(today.getFullYear(), today.getMonth() + 1, 0));
        
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