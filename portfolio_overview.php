<?php
/**
 * File: portfolio_overview.php
 * Description: Portfolio overview interface for PSW 4.0 - displays current holdings with real-time valuations
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/utils/Localization.php';

// Require authentication
Auth::requireAuth();

// Define filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'sector' => $_GET['sector'] ?? '',
    'min_value' => $_GET['min_value'] ?? '',
    'max_value' => $_GET['max_value'] ?? '',
    'sort_by' => $_GET['sort_by'] ?? 'current_value_sek',
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
    $marketDb = Database::getConnection('marketdata');
    
    // Build WHERE clause
    $whereConditions = ['p.is_active = 1', 'p.shares_held > 0'];
    $params = [];
    
    if (!empty($dbFilters['search'])) {
        $whereConditions[] = "(p.isin LIKE ? OR p.company_name LIKE ? OR p.ticker LIKE ?)";
        $searchTerm = '%' . $dbFilters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($dbFilters['sector'])) {
        $whereConditions[] = "(COALESCE(s1.nameEn, s2.nameEn, 'Unknown') = ?)";
        $params[] = $dbFilters['sector'];
    }
    
    if (!empty($dbFilters['min_value'])) {
        $whereConditions[] = "p.current_value_sek >= ?";
        $params[] = $dbFilters['min_value'];
    }
    
    if (!empty($dbFilters['max_value'])) {
        $whereConditions[] = "p.current_value_sek <= ?";
        $params[] = $dbFilters['max_value'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Sorting
    $allowedSortColumns = [
        'isin' => 'p.isin',
        'company_name' => 'p.company_name',
        'ticker' => 'p.ticker',
        'sector' => 'COALESCE(s1.nameEn, s2.nameEn, "Unknown")',
        'shares_held' => 'p.shares_held',
        'average_cost_price_sek' => 'p.average_cost_price_sek',
        'latest_price' => 'p.latest_price_local',
        'current_value_sek' => 'p.current_value_sek',
        'unrealized_gain_loss_sek' => '(p.current_value_sek - p.total_cost_sek)',
        'unrealized_gain_loss_percent' => '((p.current_value_sek - p.total_cost_sek) / p.total_cost_sek * 100)'
    ];
    
    $sortColumn = $allowedSortColumns[$filters['sort_by']] ?? 'p.current_value_sek';
    $sortOrder = strtoupper($filters['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total
                FROM psw_portfolio.portfolio p
                LEFT JOIN psw_marketdata.nordic_instruments ni ON p.isin COLLATE utf8mb4_unicode_ci = ni.isin COLLATE utf8mb4_unicode_ci
                LEFT JOIN psw_marketdata.global_instruments gi ON p.isin COLLATE utf8mb4_unicode_ci = gi.isin COLLATE utf8mb4_unicode_ci
                LEFT JOIN psw_marketdata.sectors s1 ON ni.sectorID = s1.id
                LEFT JOIN psw_marketdata.sectors s2 ON gi.sectorId = s2.id
                WHERE {$whereClause}";
    
    $countStmt = $portfolioDb->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calculate pagination
    $totalPages = ceil($totalRecords / $limit);
    $offset = ($page - 1) * $limit;
    
    // Get portfolio holdings with filters, sorting, and pagination
    $sql = "SELECT 
                p.portfolio_id,
                p.isin, 
                p.ticker,
                p.company_name,
                p.shares_held,
                p.average_cost_price_sek,
                p.total_cost_sek,
                p.latest_price_local as latest_price,
                p.currency_local as price_currency,
                p.updated_at as price_updated,
                NULL as fx_rate,
                NULL as fx_updated,
                p.current_value_local as calculated_value_local,
                p.current_value_sek as calculated_value_sek,
                p.currency_local as base_currency,
                'Sweden' as country,
                COALESCE(s1.nameEn, s2.nameEn, 'Unknown') as sector
            FROM psw_portfolio.portfolio p
            LEFT JOIN psw_marketdata.nordic_instruments ni ON p.isin COLLATE utf8mb4_unicode_ci = ni.isin COLLATE utf8mb4_unicode_ci
            LEFT JOIN psw_marketdata.global_instruments gi ON p.isin COLLATE utf8mb4_unicode_ci = gi.isin COLLATE utf8mb4_unicode_ci
            LEFT JOIN psw_marketdata.sectors s1 ON ni.sectorID = s1.id
            LEFT JOIN psw_marketdata.sectors s2 ON gi.sectorId = s2.id
            WHERE {$whereClause}
            ORDER BY {$sortColumn} {$sortOrder}
            LIMIT {$limit} OFFSET {$offset}";
    
    $stmt = $portfolioDb->prepare($sql);
    $stmt->execute($params);
    $holdings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available sectors for filter dropdown
    $sectorsSql = "SELECT DISTINCT COALESCE(s1.nameEn, s2.nameEn, 'Unknown') as sector
                   FROM psw_portfolio.portfolio p
                   LEFT JOIN psw_marketdata.nordic_instruments ni ON p.isin COLLATE utf8mb4_unicode_ci = ni.isin COLLATE utf8mb4_unicode_ci
                   LEFT JOIN psw_marketdata.global_instruments gi ON p.isin COLLATE utf8mb4_unicode_ci = gi.isin COLLATE utf8mb4_unicode_ci
                   LEFT JOIN psw_marketdata.sectors s1 ON ni.sectorID = s1.id
                   LEFT JOIN psw_marketdata.sectors s2 ON gi.sectorId = s2.id
                   WHERE p.is_active = 1 AND p.shares_held > 0
                   ORDER BY sector";
    
    $sectorsStmt = $portfolioDb->prepare($sectorsSql);
    $sectorsStmt->execute();
    $availableSectors = $sectorsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Portfolio data loaded successfully
    
    
    // Calculate portfolio totals
    $totalValueSek = 0;
    $totalCostSek = 0;
    $totalPositions = count($holdings);
    
    foreach ($holdings as $i => $holding) {
        $currentValueSek = $holding['calculated_value_sek'] ?: $holding['current_value_sek'] ?: 0;
        $costSek = $holding['total_cost_sek'] ?: 0;
        
        // Update the holding with calculated values
        $holdings[$i]['display_value_sek'] = $currentValueSek;
        $holdings[$i]['unrealized_gain_loss_sek'] = $currentValueSek - $costSek;
        $holdings[$i]['unrealized_gain_loss_percent'] = $costSek > 0 ? (($currentValueSek - $costSek) / $costSek) * 100 : 0;
        
        $totalValueSek += $currentValueSek;
        $totalCostSek += $costSek;
    }
    
    // Calculate portfolio weight percentages
    foreach ($holdings as $i => $holding) {
        $holdings[$i]['portfolio_weight_percent'] = $totalValueSek > 0 ? ($holdings[$i]['display_value_sek'] / $totalValueSek) * 100 : 0;
    }
    
    $totalUnrealizedGainLoss = $totalValueSek - $totalCostSek;
    $totalUnrealizedPercent = $totalCostSek > 0 ? ($totalUnrealizedGainLoss / $totalCostSek) * 100 : 0;
    
    // Get sector allocation
    $sectorSql = "SELECT 
                    COALESCE(s1.nameEn, s2.nameEn, 'Unknown') as sector,
                    COUNT(*) as positions,
                    SUM(COALESCE(p.current_value_sek, 0)) as sector_value_sek
                  FROM psw_portfolio.portfolio p
                  LEFT JOIN psw_marketdata.nordic_instruments ni ON p.isin COLLATE utf8mb4_unicode_ci = ni.isin COLLATE utf8mb4_unicode_ci
                  LEFT JOIN psw_marketdata.global_instruments gi ON p.isin COLLATE utf8mb4_unicode_ci = gi.isin COLLATE utf8mb4_unicode_ci
                  LEFT JOIN psw_marketdata.sectors s1 ON ni.sectorID = s1.id
                  LEFT JOIN psw_marketdata.sectors s2 ON gi.sectorId = s2.id
                  WHERE p.is_active = 1 AND p.shares_held > 0
                  GROUP BY COALESCE(s1.nameEn, s2.nameEn, 'Unknown')
                  ORDER BY sector_value_sek DESC";
    
    $sectorStmt = $portfolioDb->prepare($sectorSql);
    $sectorStmt->execute();
    $sectorAllocations = $sectorStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error loading portfolio data: " . $e->getMessage();
    $holdings = [];
    $sectorAllocations = [];
    $availableSectors = [];
    $totalValueSek = 0;
    $totalCostSek = 0;
    $totalPositions = 0;
    $totalUnrealizedGainLoss = 0;
    $totalUnrealizedPercent = 0;
    $totalRecords = 0;
    $totalPages = 0;
}

// Helper function for sort icons
function getSortIcon($column, $currentSort, $currentOrder) {
    if ($column !== $currentSort) {
        return '<i class="fas fa-sort text-muted"></i>';
    }
    return $currentOrder === 'asc' 
        ? '<i class="fas fa-sort-up text-primary"></i>' 
        : '<i class="fas fa-sort-down text-primary"></i>';
}

// Initialize variables for template
$pageTitle = 'Portfolio Overview - PSW 4.0';
$pageDescription = 'Current portfolio holdings and performance';
$additionalCSS = [];
$additionalJS = [];

// Prepare content
ob_start();
?>
<div class="psw-content">
    <!-- Page Header -->
    <div class="psw-card psw-mb-4">
        <div class="psw-card-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <h1 class="psw-card-title">
                    <i class="fas fa-chart-pie psw-card-title-icon"></i>
                    Portfolio Overview
                </h1>
                <p class="psw-card-subtitle">Current holdings with real-time valuations and performance metrics</p>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button type="button" class="psw-btn psw-btn-primary" onclick="refreshPrices()">
                    <i class="fas fa-sync psw-btn-icon"></i>Refresh Prices
                </button>
                <button type="button" class="psw-btn psw-btn-secondary" onclick="exportToCSV()">
                    <i class="fas fa-download psw-btn-icon"></i>Export
                </button>
            </div>
        </div>
    </div>

    <!-- Portfolio Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1rem;">
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, var(--primary-accent), var(--primary-accent-hover)); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-wallet" style="color: var(--text-inverse); font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                        <?php echo Localization::formatCurrency($totalValueSek, 0, 'SEK'); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Total Value</div>
                </div>
            </div>
        </div>
        
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #10B981, #059669); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-chart-bar" style="color: white; font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: <?php echo $totalUnrealizedGainLoss >= 0 ? 'var(--success-color)' : 'var(--error-color)'; ?>;">
                        <?php echo ($totalUnrealizedGainLoss >= 0 ? '+' : '') . Localization::formatCurrency($totalUnrealizedGainLoss, 0, 'SEK'); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">
                        Unrealized P&L (<?php echo number_format($totalUnrealizedPercent, 1); ?>%)
                    </div>
                </div>
            </div>
        </div>
        
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #F59E0B, #D97706); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-building" style="color: white; font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                        <?php echo Localization::formatNumber($totalPositions); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Active Positions</div>
                </div>
            </div>
        </div>
        
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-coins" style="color: white; font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                        <?php echo Localization::formatCurrency($totalCostSek, 0, 'SEK'); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Total Cost Basis</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="psw-card" style="margin-bottom: 1.5rem;">
        <div class="psw-card-header">
            <div class="psw-card-title">
                <i class="fas fa-filter psw-card-title-icon"></i>
                Filter & Search
            </div>
        </div>
        <div class="psw-card-content">
            <div style="display: grid; grid-template-columns: 1fr 200px 150px 150px 120px 120px; gap: 1rem; align-items: end;">
                <div class="psw-form-group">
                    <label class="psw-form-label">Search</label>
                    <input type="text" id="search-input" class="psw-form-input" 
                           placeholder="Search by ISIN, company, or ticker..." 
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                
                <div class="psw-form-group">
                    <label class="psw-form-label">Sector</label>
                    <select id="sector-filter" class="psw-form-input">
                        <option value="">All Sectors</option>
                        <?php foreach ($availableSectors as $sector): ?>
                            <option value="<?php echo htmlspecialchars($sector['sector']); ?>"
                                    <?php echo $filters['sector'] == $sector['sector'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sector['sector']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="psw-form-group">
                    <label class="psw-form-label">Min Value (SEK)</label>
                    <input type="number" id="min-value-filter" class="psw-form-input" 
                           placeholder="0" min="0" step="1000"
                           value="<?php echo htmlspecialchars($filters['min_value']); ?>">
                </div>
                
                <div class="psw-form-group">
                    <label class="psw-form-label">Max Value (SEK)</label>
                    <input type="number" id="max-value-filter" class="psw-form-input" 
                           placeholder="∞" min="0" step="1000"
                           value="<?php echo htmlspecialchars($filters['max_value']); ?>">
                </div>
                
                <button type="button" class="psw-btn psw-btn-primary" onclick="applyFilters()" style="height: 44px;">
                    <i class="fas fa-search psw-btn-icon"></i>Apply
                </button>
                
                <button type="button" class="psw-btn psw-btn-secondary" onclick="clearFilters()" style="height: 44px;">
                    <i class="fas fa-times psw-btn-icon"></i>Clear
                </button>
            </div>
        </div>
    </div>

    <!-- Holdings Table -->
    <div class="psw-card">
        <div class="psw-card-header">
            <div class="psw-card-title">
                <i class="fas fa-table psw-card-title-icon"></i>
                Current Holdings
            </div>
        </div>
        <div class="psw-card-content" style="padding: 0;">
            <?php if (!empty($holdings) && !isset($error)): ?>
                <table class="psw-table">
                    <thead>
                        <tr>
                            <th onclick="sortTable('isin')" style="cursor: pointer;">
                                ISIN <?php echo getSortIcon('isin', $filters['sort_by'], $filters['sort_order']); ?>
                            </th>
                            <th onclick="sortTable('company_name')" style="cursor: pointer;">
                                Company <?php echo getSortIcon('company_name', $filters['sort_by'], $filters['sort_order']); ?>
                            </th>
                            <th onclick="sortTable('ticker')" style="cursor: pointer;">
                                Ticker <?php echo getSortIcon('ticker', $filters['sort_by'], $filters['sort_order']); ?>
                            </th>
                            <th>Country</th>
                            <th onclick="sortTable('sector')" style="cursor: pointer;">
                                Sector <?php echo getSortIcon('sector', $filters['sort_by'], $filters['sort_order']); ?>
                            </th>
                            <th onclick="sortTable('shares_held')" style="text-align: right; cursor: pointer;">
                                Shares <?php echo getSortIcon('shares_held', $filters['sort_by'], $filters['sort_order']); ?>
                            </th>
                            <th onclick="sortTable('average_cost_price_sek')" style="text-align: right; cursor: pointer;">
                                Avg Cost (SEK) <?php echo getSortIcon('average_cost_price_sek', $filters['sort_by'], $filters['sort_order']); ?>
                            </th>
                            <th onclick="sortTable('latest_price')" style="text-align: right; cursor: pointer;">
                                Latest Price <?php echo getSortIcon('latest_price', $filters['sort_by'], $filters['sort_order']); ?>
                            </th>
                            <th onclick="sortTable('current_value_sek')" style="text-align: right; cursor: pointer;">
                                Market Value (SEK) <?php echo getSortIcon('current_value_sek', $filters['sort_by'], $filters['sort_order']); ?>
                            </th>
                            <th style="text-align: right;">Weight %</th>
                            <th onclick="sortTable('unrealized_gain_loss_sek')" style="text-align: right; cursor: pointer;">
                                Unrealized P&L <?php echo getSortIcon('unrealized_gain_loss_sek', $filters['sort_by'], $filters['sort_order']); ?>
                            </th>
                            <th onclick="sortTable('unrealized_gain_loss_percent')" style="text-align: right; cursor: pointer;">
                                Return % <?php echo getSortIcon('unrealized_gain_loss_percent', $filters['sort_by'], $filters['sort_order']); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($holdings as $holding): ?>
                            <tr>
                                <td style="font-family: var(--font-family-mono); font-size: 0.875rem;">
                                    <?php echo htmlspecialchars($holding['isin']); ?>
                                </td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>/company_detail_new.php?isin=<?php echo urlencode($holding['isin']); ?>" 
                                       style="color: var(--primary-accent); text-decoration: none; font-weight: 600;">
                                        <?php echo htmlspecialchars($holding['company_name'] ?? 'Unknown Company'); ?>
                                    </a>
                                </td>
                                <td style="font-family: var(--font-family-mono); font-size: 0.875rem;">
                                    <?php echo htmlspecialchars($holding['ticker'] ?? '-'); ?>
                                </td>
                                <td><?php echo htmlspecialchars($holding['country'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($holding['sector'] ?? '-'); ?></td>
                                <td style="text-align: right;">
                                    <?php echo Localization::formatNumber($holding['shares_held'], 0); ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php echo $holding['average_cost_price_sek'] ? Localization::formatNumber($holding['average_cost_price_sek'], 2) : '-'; ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php if ($holding['latest_price']): ?>
                                        <?php echo Localization::formatNumber($holding['latest_price'], 2) . ' ' . htmlspecialchars($holding['price_currency']); ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-weight: 600;">
                                    <?php echo Localization::formatCurrency($holding['display_value_sek'], 0, 'SEK'); ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php echo number_format($holding['portfolio_weight_percent'], 1); ?>%
                                </td>
                                <td style="text-align: right; color: <?php echo $holding['unrealized_gain_loss_sek'] >= 0 ? 'var(--success-color)' : 'var(--error-color)'; ?>;">
                                    <?php echo ($holding['unrealized_gain_loss_sek'] >= 0 ? '+' : '') . Localization::formatCurrency($holding['unrealized_gain_loss_sek'], 0, 'SEK'); ?>
                                </td>
                                <td style="text-align: right; color: <?php echo $holding['unrealized_gain_loss_percent'] >= 0 ? 'var(--success-color)' : 'var(--error-color)'; ?>; font-weight: 600;">
                                    <?php echo ($holding['unrealized_gain_loss_percent'] >= 0 ? '+' : '') . number_format($holding['unrealized_gain_loss_percent'], 1); ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif (isset($error)): ?>
                <div style="text-align: center; padding: 3rem; color: var(--error-color);">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <h3 style="margin-bottom: 0.5rem;">Error Loading Portfolio</h3>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                    <i class="fas fa-chart-pie" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <h3 style="margin-bottom: 0.5rem;">No Holdings Found</h3>
                    <p>Your portfolio appears to be empty. Start by making some trades to build your portfolio.</p>
                    <a href="<?php echo BASE_URL; ?>/add_trade.php" class="psw-btn psw-btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-plus psw-btn-icon"></i>Add Trade
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="psw-card psw-mt-4">
            <div class="psw-card-content" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="color: var(--text-secondary); font-size: 0.875rem;">
                    Showing <?php echo number_format(($page - 1) * $limit + 1); ?> to 
                    <?php echo number_format(min($page * $limit, $totalRecords)); ?> of 
                    <?php echo number_format($totalRecords); ?> holdings
                </div>
                
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <?php if ($page > 1): ?>
                        <button type="button" class="psw-btn psw-btn-secondary psw-btn-sm" onclick="changePage(<?php echo $page - 1; ?>)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <button type="button" 
                                class="psw-btn <?php echo $i === $page ? 'psw-btn-primary' : 'psw-btn-secondary'; ?> psw-btn-sm" 
                                onclick="changePage(<?php echo $i; ?>)">
                            <?php echo $i; ?>
                        </button>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <button type="button" class="psw-btn psw-btn-secondary psw-btn-sm" onclick="changePage(<?php echo $page + 1; ?>)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function applyFilters() {
    const params = new URLSearchParams();
    
    const search = document.getElementById('search-input').value.trim();
    if (search) params.set('search', search);
    
    const sector = document.getElementById('sector-filter').value;
    if (sector) params.set('sector', sector);
    
    const minValue = document.getElementById('min-value-filter').value;
    if (minValue) params.set('min_value', minValue);
    
    const maxValue = document.getElementById('max-value-filter').value;
    if (maxValue) params.set('max_value', maxValue);
    
    // Reset to first page when applying filters
    params.set('page', '1');
    
    window.location.href = '?' + params.toString();
}

function clearFilters() {
    window.location.href = window.location.pathname;
}

function sortTable(column) {
    const params = new URLSearchParams(window.location.search);
    
    let newOrder = 'desc';
    if (params.get('sort_by') === column && params.get('sort_order') === 'desc') {
        newOrder = 'asc';
    }
    
    params.set('sort_by', column);
    params.set('sort_order', newOrder);
    params.set('page', '1'); // Reset to first page when sorting
    
    window.location.href = '?' + params.toString();
}

function changePage(page) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', page);
    window.location.href = '?' + params.toString();
}

function refreshPrices() {
    // TODO: Implement price refresh functionality
    alert('Price refresh functionality will be implemented in a future update.');
}

function exportToCSV() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '?' + params.toString();
}

// Enable Enter key for search input
document.getElementById('search-input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        applyFilters();
    }
});
</script>

<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base-redesign.php';
?>