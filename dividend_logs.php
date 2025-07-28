<?php
/**
 * File: dividend_logs.php
 * Description: Dividend logs interface for PSW 4.0 - displays imported dividend data with filtering and sorting
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/Auth.php';

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
    
    // Valid sort columns
    $validSortColumns = [
        'payment_date' => 'ld.payment_date',
        'isin' => 'ld.isin',
        'company_name' => 'ml.name',
        'ticker' => 'ld.ticker',
        'broker_name' => 'b.broker_name',
        'account_group' => 'pag.portfolio_group_name',
        'shares_held' => 'ld.shares_held',
        'dividend_amount_local' => 'ld.dividend_amount_local',
        'currency_local' => 'ld.currency_local',
        'dividend_amount_sek' => 'ld.dividend_amount_sek',
        'tax_amount_sek' => 'ld.tax_amount_sek',
        'net_dividend_sek' => 'ld.net_dividend_sek'
    ];
    
    $sortColumn = $validSortColumns[$dbFilters['sort_by']] ?? 'ld.payment_date';
    $sortOrder = strtoupper($dbFilters['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
    
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

$pageTitle = 'Dividend Logs - PSW 4.0';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/improved-main.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/new-companies-management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Beautiful Date Range Filter Styles */
        .date-range-filter {
            display: flex;
            gap: var(--space-4);
            align-items: flex-end;
        }
        
        .date-input-group {
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
            min-width: 140px;
        }
        
        .date-label {
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }
        
        .date-label i {
            color: var(--primary-color);
            font-size: var(--text-xs);
        }
        
        .date-input {
            width: 100%;
            padding: var(--space-3) var(--space-4);
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: var(--text-sm);
            transition: all var(--transition-normal);
            background: var(--bg-white-solid);
            color: var(--text-primary);
            font-family: inherit;
        }
        
        .date-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 200, 150, 0.1);
            transform: translateY(-1px);
        }
        
        .date-input:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-sm);
        }
        
        /* Page size selector styling */
        .page-size-selector select {
            padding: var(--space-2) var(--space-4);
            border: 2px solid var(--border-light);
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
            transition: all var(--transition-normal);
            background: var(--bg-white-solid);
            color: var(--text-primary);
            font-family: inherit;
        }
        
        .page-size-selector select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 200, 150, 0.1);
        }
        
        .page-size-selector select:hover {
            border-color: var(--primary-color);
        }
        
        /* Filter dropdown styling */
        .filter-dropdown {
            min-width: 140px;
        }
        
        .filter-dropdown .form-label {
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: var(--space-2);
            margin-bottom: var(--space-2);
        }
        
        .filter-dropdown .form-label i {
            color: var(--primary-color);
            font-size: var(--text-xs);
        }
        
        .filter-dropdown .form-control {
            width: 100%;
            padding: var(--space-3) var(--space-4);
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: var(--text-sm);
            transition: all var(--transition-normal);
            background: var(--bg-white-solid);
            color: var(--text-primary);
            font-family: inherit;
        }
        
        .filter-dropdown .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 200, 150, 0.1);
            transform: translateY(-1px);
        }
        
        .filter-dropdown .form-control:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-sm);
        }
        
        /* Statistics cards layout */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-6);
            margin-bottom: var(--space-8);
        }
        
        .stats-grid .stat-card {
            padding: 0;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
            background: var(--bg-white-solid);
            transition: all var(--transition-normal);
        }
        
        .stats-grid .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .stats-grid .stat-card .card-content {
            padding: var(--space-6);
            display: flex;
            align-items: center;
            gap: var(--space-4);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--text-xl);
            color: white;
            flex-shrink: 0;
        }
        
        .stat-icon.primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        }
        
        .stat-icon.success {
            background: linear-gradient(135deg, #10B981, #059669);
        }
        
        .stat-icon.warning {
            background: linear-gradient(135deg, #F59E0B, #D97706);
        }
        
        .stat-icon.info {
            background: linear-gradient(135deg, #3B82F6, #2563EB);
        }
        
        .stat-info {
            flex: 1;
        }
        
        .stat-number {
            font-size: var(--text-2xl);
            font-weight: var(--font-bold);
            color: var(--text-primary);
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: var(--text-sm);
            color: var(--text-secondary);
            font-weight: var(--font-medium);
            margin-top: var(--space-1);
        }
        
        /* Toolbar improvements */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: var(--space-6);
            margin-bottom: var(--space-6);
            flex-wrap: wrap;
        }
        
        .toolbar-right {
            display: flex;
            align-items: flex-end;
            gap: var(--space-4);
            flex-wrap: wrap;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .date-range-filter {
                flex-direction: column;
                gap: var(--space-3);
            }
            
            .date-input-group {
                min-width: auto;
                width: 100%;
            }
            
            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .toolbar-right {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-dropdown {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <header class="unified-header">
        <div class="header-container">
            <a href="<?php echo BASE_URL; ?>" class="logo-header">
                <div class="logo-mini">
                    <i class="fas fa-chart-line"></i>
                </div>
                <span class="logo-text">PSW 4.0</span>
            </a>
            
            <div class="nav-links">
                <div class="nav-item">
                    <a href="javascript:void(0)" class="nav-link nav-dropdown-only">
                        <i class="fas fa-cogs"></i>
                        Functions
                        <i class="fas fa-chevron-down nav-arrow"></i>
                    </a>
                    <div class="submenu">
                        <a href="<?php echo BASE_URL; ?>/dashboard.php" class="submenu-link">Dashboard</a>
                        <a href="<?php echo BASE_URL; ?>/dividend_import.php" class="submenu-link">Dividend Import</a>
                        <a href="<?php echo BASE_URL; ?>/masterlist_management.php" class="submenu-link">Masterlist Management</a>
                        <a href="<?php echo BASE_URL; ?>/new_companies_management.php" class="submenu-link">New Companies</a>
                        <a href="<?php echo BASE_URL; ?>/user_management.php" class="submenu-link">User Management</a>
                    </div>
                </div>
                
                <div class="nav-item">
                    <a href="javascript:void(0)" class="nav-link nav-dropdown-only">
                        <i class="fas fa-chart-bar"></i>
                        Logs
                        <i class="fas fa-chevron-down nav-arrow"></i>
                    </a>
                    <div class="submenu">
                        <a href="<?php echo BASE_URL; ?>/dividend_logs.php" class="submenu-link">Dividends</a>
                    </div>
                </div>
                
                <div class="nav-item">
                    <a href="javascript:void(0)" class="nav-link nav-dropdown-only">
                        <i class="fas fa-book"></i>
                        Rules
                        <i class="fas fa-chevron-down nav-arrow"></i>
                    </a>
                    <div class="submenu">
                        <a href="<?php echo BASE_URL; ?>/philosophy.php" class="submenu-link">Philosophy</a>
                        <a href="#" class="submenu-link">Rulebook</a>
                    </div>
                </div>
                
                <div class="user-menu">
                    <button class="login-toggle" onclick="toggleUserMenu()">
                        <i class="fas fa-user"></i>
                        <?php echo Auth::getUsername(); ?>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="login-dropdown" id="userMenu">
                        <div class="user-info">
                            <p><strong><?php echo Auth::getUsername(); ?></strong></p>
                        </div>
                        <hr style="margin: 12px 0; border: none; border-top: 1px solid #e9ecef;">
                        <a href="<?php echo BASE_URL; ?>/logout.php" class="dropdown-link text-danger">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="main-container">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-content">
                    <div class="header-left">
                        <h1><i class="fas fa-chart-bar"></i> Dividend Logs</h1>
                    </div>
                </div>
            </div>

            <?php if (isset($errorMessage)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($errorMessage) ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="dashboard-card stat-card">
                    <div class="card-content">
                        <div class="stat-icon primary">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo number_format($stats['total_dividends']); ?></div>
                            <div class="stat-label">Total Dividends</div>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card stat-card">
                    <div class="card-content">
                        <div class="stat-icon success">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo number_format($stats['total_net_sek'], 2); ?> SEK</div>
                            <div class="stat-label">Net Dividend</div>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card stat-card">
                    <div class="card-content">
                        <div class="stat-icon warning">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo number_format($stats['total_tax_sek'], 2); ?> SEK</div>
                            <div class="stat-label">Total Tax</div>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card stat-card">
                    <div class="card-content">
                        <div class="stat-icon info">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo number_format($stats['unique_companies']); ?></div>
                            <div class="stat-label">Companies</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="content-wrapper">
                <!-- Toolbar -->
                <div class="toolbar">
                    <div class="toolbar-left">
                        <div class="page-size-selector">
                            <label>Show:</label>
                            <select onchange="changePageSize(this.value)">
                                <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                            <span>entries</span>
                        </div>
                    </div>
                    
                    <div class="toolbar-right">
                        <!-- Search Box -->
                        <div class="search-box">
                            <input type="text" id="search-input" placeholder="Search by ISIN, company, or ticker..." 
                                   value="<?php echo htmlspecialchars($filters['search']); ?>">
                            <i class="fas fa-search"></i>
                        </div>
                        
                        <!-- Date Range Filter -->
                        <div class="date-range-filter">
                            <div class="date-input-group">
                                <label for="date-from" class="date-label">
                                    <i class="fas fa-calendar-alt"></i>
                                    From
                                </label>
                                <input type="date" id="date-from" class="form-control date-input" 
                                       value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                            </div>
                            <div class="date-input-group">
                                <label for="date-to" class="date-label">
                                    <i class="fas fa-calendar-alt"></i>
                                    To
                                </label>
                                <input type="date" id="date-to" class="form-control date-input" 
                                       value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                            </div>
                        </div>
                        
                        <!-- Broker Filter -->
                        <div class="form-group filter-dropdown">
                            <label class="form-label">
                                <i class="fas fa-building"></i>
                                Broker
                            </label>
                            <select id="broker-filter" class="form-control">
                                <option value="">All Brokers</option>
                                <?php foreach ($brokers as $broker): ?>
                                    <option value="<?php echo $broker['broker_id']; ?>"
                                            <?php echo $filters['broker_id'] == $broker['broker_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($broker['broker_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Account Group Filter -->
                        <div class="form-group filter-dropdown">
                            <label class="form-label">
                                <i class="fas fa-folder"></i>
                                Account Group
                            </label>
                            <select id="account-group-filter" class="form-control">
                                <option value="">All Account Groups</option>
                                <?php foreach ($accountGroups as $group): ?>
                                    <option value="<?php echo $group['portfolio_account_group_id']; ?>"
                                            <?php echo $filters['account_group_id'] == $group['portfolio_account_group_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($group['portfolio_group_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Filter Controls -->
                        <div class="filter-controls">
                            <button type="button" class="btn btn-primary" onclick="applyFilters()">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Results Info -->
                <div class="search-results-indicator">
                    <div class="search-info">
                        Showing <?php echo number_format(min($limit, $totalRecords - ($page - 1) * $limit)); ?> of 
                        <?php echo number_format($totalRecords); ?> dividend entries
                        <?php if (!empty($filters['search']) || !empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['broker_id']) || !empty($filters['account_group_id'])): ?>
                            (filtered)
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="table-container">
                    <?php if (!empty($dividends)): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="sortable" data-sort="payment_date">
                                        Payment Date
                                        <?php if ($filters['sort_by'] == 'payment_date'): ?>
                                            <i class="fas fa-sort-<?php echo $filters['sort_order'] == 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" data-sort="isin">
                                        ISIN
                                        <?php if ($filters['sort_by'] == 'isin'): ?>
                                            <i class="fas fa-sort-<?php echo $filters['sort_order'] == 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" data-sort="company_name">
                                        Company
                                        <?php if ($filters['sort_by'] == 'company_name'): ?>
                                            <i class="fas fa-sort-<?php echo $filters['sort_order'] == 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" data-sort="ticker">
                                        Ticker
                                        <?php if ($filters['sort_by'] == 'ticker'): ?>
                                            <i class="fas fa-sort-<?php echo $filters['sort_order'] == 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" data-sort="broker_name">
                                        Broker
                                        <?php if ($filters['sort_by'] == 'broker_name'): ?>
                                            <i class="fas fa-sort-<?php echo $filters['sort_order'] == 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" data-sort="account_group">
                                        Account Group
                                        <?php if ($filters['sort_by'] == 'account_group'): ?>
                                            <i class="fas fa-sort-<?php echo $filters['sort_order'] == 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable numeric" data-sort="shares_held">
                                        Shares
                                        <?php if ($filters['sort_by'] == 'shares_held'): ?>
                                            <i class="fas fa-sort-<?php echo $filters['sort_order'] == 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable numeric" data-sort="dividend_amount_local">
                                        Dividend (Local)
                                        <?php if ($filters['sort_by'] == 'dividend_amount_local'): ?>
                                            <i class="fas fa-sort-<?php echo $filters['sort_order'] == 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" data-sort="currency_local">
                                        Currency
                                        <?php if ($filters['sort_by'] == 'currency_local'): ?>
                                            <i class="fas fa-sort-<?php echo $filters['sort_order'] == 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable numeric" data-sort="net_dividend_sek">
                                        Net (SEK)
                                        <?php if ($filters['sort_by'] == 'net_dividend_sek'): ?>
                                            <i class="fas fa-sort-<?php echo $filters['sort_order'] == 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable numeric" data-sort="tax_amount_sek">
                                        Tax (SEK)
                                        <?php if ($filters['sort_by'] == 'tax_amount_sek'): ?>
                                            <i class="fas fa-sort-<?php echo $filters['sort_order'] == 'asc' ? 'up' : 'down'; ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dividends as $dividend): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dividend['payment_date']); ?></td>
                                        <td class="font-mono"><?php echo htmlspecialchars($dividend['isin']); ?></td>
                                        <td>
                                            <div class="company-info">
                                                <div class="company-name">
                                                    <?php echo htmlspecialchars($dividend['company_name'] ?? 'Unknown Company'); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="font-mono"><?php echo htmlspecialchars($dividend['ticker'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($dividend['broker_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($dividend['account_group_name'] ?? '-'); ?></td>
                                        <td class="numeric"><?php echo number_format($dividend['shares_held'], 4); ?></td>
                                        <td class="numeric">
                                            <?php echo number_format($dividend['dividend_amount_local'], 4); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($dividend['currency_local']); ?></td>
                                        <td class="numeric positive">
                                            <?php echo number_format($dividend['net_dividend_sek'], 2); ?>
                                        </td>
                                        <td class="numeric negative">
                                            -<?php echo number_format($dividend['tax_amount_sek'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h3>No dividend records found</h3>
                            <p>No dividend data matches your current filters. Try adjusting your search criteria.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalRecords > $limit): ?>
                    <div class="pagination">
                        <div class="pagination-info">
                            Showing <?php echo ($page - 1) * $limit + 1; ?>-<?php echo min($page * $limit, $totalRecords); ?> of <?php echo number_format($totalRecords); ?> entries
                        </div>
                        <div class="pagination-controls">
                            <?php
                            $totalPages = ceil($totalRecords / $limit);
                            $showPages = 5;
                            $startPage = max(1, $page - floor($showPages / 2));
                            $endPage = min($totalPages, $startPage + $showPages - 1);
                            
                            if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> PSW 4.0 | Built with PHP</p>
        </div>
    </footer>

    <script src="<?php echo ASSETS_URL; ?>/js/improved-main.js"></script>
    
    <script>
        console.log('Dividend logs page loaded');
        
        // Search functionality
        let searchTimeout;
        document.getElementById('search-input').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                applyFilters();
            }, 500);
        });
        
        // Date range change handlers
        document.getElementById('date-from').addEventListener('change', applyFilters);
        document.getElementById('date-to').addEventListener('change', applyFilters);
        
        // Dropdown filter change handlers
        document.getElementById('broker-filter').addEventListener('change', applyFilters);
        document.getElementById('account-group-filter').addEventListener('change', applyFilters);
        
        // Column sorting
        document.querySelectorAll('.sortable').forEach(header => {
            header.addEventListener('click', function() {
                const sortBy = this.dataset.sort;
                const currentSort = new URLSearchParams(window.location.search).get('sort_by');
                const currentOrder = new URLSearchParams(window.location.search).get('sort_order') || 'desc';
                
                let newOrder = 'desc';
                if (currentSort === sortBy && currentOrder === 'desc') {
                    newOrder = 'asc';
                }
                
                const params = new URLSearchParams(window.location.search);
                params.set('sort_by', sortBy);
                params.set('sort_order', newOrder);
                params.delete('page'); // Reset to first page
                
                window.location.href = '?' + params.toString();
            });
        });
        
        function applyFilters() {
            const params = new URLSearchParams();
            
            // Search
            const search = document.getElementById('search-input').value.trim();
            if (search) params.set('search', search);
            
            // Date range
            const dateFrom = document.getElementById('date-from').value;
            const dateTo = document.getElementById('date-to').value;
            if (dateFrom) params.set('date_from', dateFrom);
            if (dateTo) params.set('date_to', dateTo);
            
            // Broker filter
            const brokerSelect = document.getElementById('broker-filter');
            if (brokerSelect.value) {
                params.set('broker_id', brokerSelect.value);
            }
            
            // Account group filter
            const accountGroupSelect = document.getElementById('account-group-filter');
            if (accountGroupSelect.value) {
                params.set('account_group_id', accountGroupSelect.value);
            }
            
            // Preserve current sorting
            const currentParams = new URLSearchParams(window.location.search);
            if (currentParams.get('sort_by')) params.set('sort_by', currentParams.get('sort_by'));
            if (currentParams.get('sort_order')) params.set('sort_order', currentParams.get('sort_order'));
            
            // Preserve page size
            if (currentParams.get('limit')) params.set('limit', currentParams.get('limit'));
            
            window.location.href = '?' + params.toString();
        }
        
        function clearFilters() {
            const params = new URLSearchParams();
            
            // Preserve only sorting and page size
            const currentParams = new URLSearchParams(window.location.search);
            if (currentParams.get('sort_by')) params.set('sort_by', currentParams.get('sort_by'));
            if (currentParams.get('sort_order')) params.set('sort_order', currentParams.get('sort_order'));
            if (currentParams.get('limit')) params.set('limit', currentParams.get('limit'));
            
            window.location.href = params.toString() ? '?' + params.toString() : window.location.pathname;
        }
        
        function changePageSize(newSize) {
            const params = new URLSearchParams(window.location.search);
            params.set('limit', newSize);
            params.delete('page'); // Reset to first page
            window.location.href = '?' + params.toString();
        }
        
        
        // User menu toggle
        function toggleUserMenu() {
            const menu = document.getElementById('userMenu');
            menu.classList.toggle('active');
        }
    </script>
</body>
</html>