<?php
/**
 * File: new_companies_management.php
 * Description: New Companies (watchlist) management interface for PSW 4.0 - integrated with unified navigation
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/controllers/NewCompaniesController.php';
require_once __DIR__ . '/src/controllers/MasterlistController.php';
require_once __DIR__ . '/src/utils/Security.php';

if (!Auth::isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$controller = new NewCompaniesController();
$masterlistController = new MasterlistController();
$errorMessage = '';
$successMessage = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    // Clean output buffer and prevent any HTML output
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token');
        }
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add':
                $result = $controller->addNewCompanyEntry($_POST);
                if ($result === true) {
                    echo json_encode(['success' => true, 'message' => 'Entry added to new companies successfully']);
                } else if (is_string($result)) {
                    // If result is a string, it's an error message
                    echo json_encode(['success' => false, 'message' => $result]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add entry']);
                }
                exit;
                break;
                
            case 'add_to_masterlist':
                $companyId = $_POST['new_companies_id'] ?? '';
                $masterlistData = [
                    'market' => $_POST['market'] ?? null,
                    'share_type_id' => $_POST['share_type_id'] ?? 1
                ];
                $result = $controller->addToMasterlist($companyId, $masterlistData);
                echo json_encode(['success' => $result, 'message' => $result ? 'Company added to masterlist successfully' : 'Failed to add to masterlist']);
                exit;
                
            case 'update':
                $companyId = $_POST['new_companies_id'] ?? '';
                unset($_POST['action'], $_POST['csrf_token'], $_POST['new_companies_id']);
                $result = $controller->updateNewCompanyEntry($companyId, $_POST);
                echo json_encode(['success' => $result, 'message' => $result ? 'Entry updated successfully' : 'Failed to update entry']);
                exit;
                
            case 'delete':
                $companyId = $_POST['new_companies_id'] ?? '';
                $result = $controller->deleteNewCompanyEntry($companyId);
                echo json_encode(['success' => $result, 'message' => $result ? 'Entry removed from new companies' : 'Failed to remove entry']);
                exit;
                
            case 'get_entry':
                $companyId = $_POST['new_companies_id'] ?? '';
                $entry = $controller->getNewCompanyEntry($companyId);
                echo json_encode(['success' => (bool)$entry, 'entry' => $entry]);
                exit;
                
            case 'search_companies':
                $search = $_POST['search'] ?? '';
                if (strlen($search) >= 2) {
                    $companies = $masterlistController->getAllCompanies(['search' => $search], 1, 10);
                    echo json_encode(['success' => true, 'companies' => $companies['companies']]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Search term too short']);
                }
                exit;
                
            case 'preview_borsdata':
                $isin = $_POST['isin'] ?? '';
                if (empty($isin)) {
                    echo json_encode(['success' => false, 'message' => 'ISIN is required']);
                    exit;
                }
                
                try {
                    $marketdataDb = Database::getConnection('marketdata');
                    
                    // Try global_instruments first
                    $stmt = $marketdataDb->prepare("
                        SELECT gi.name, gi.yahoo, c.nameEN as country_name, c.id as country_id
                        FROM global_instruments gi 
                        LEFT JOIN countries c ON gi.countryId = c.id 
                        WHERE gi.isin = ? 
                        LIMIT 1
                    ");
                    $stmt->execute([$isin]);
                    $data = $stmt->fetch();
                    
                    // If not found in global, try nordic
                    if (!$data) {
                        $stmt = $marketdataDb->prepare("
                            SELECT ni.name, ni.yahoo, c.nameEN as country_name, c.id as country_id
                            FROM nordic_instruments ni 
                            LEFT JOIN countries c ON ni.countryId = c.id 
                            WHERE ni.isin = ? 
                            LIMIT 1
                        ");
                        $stmt->execute([$isin]);
                        $data = $stmt->fetch();
                    }
                    
                    if ($data) {
                        echo json_encode([
                            'success' => true, 
                            'company_data' => [
                                'company' => $data['name'],
                                'ticker' => $data['yahoo'],
                                'country' => $data['country_name'],
                                'country_id' => $data['country_id']
                            ]
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'ISIN not found in Börsdata']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    
    exit;
}

// Load admin-configured default filter settings
$defaultsPath = __DIR__ . '/config/filter_defaults.json';
$adminDefaults = [];
if (file_exists($defaultsPath)) {
    $adminDefaults = json_decode(file_get_contents($defaultsPath), true) ?? [];
}

// Set fallback defaults if admin hasn't configured them yet
$defaultStatusIds = $adminDefaults['status_defaults'] ?? ['null'];
$defaultCountries = $adminDefaults['country_defaults'] ?? [];
$defaultStrategies = $adminDefaults['strategy_defaults'] ?? [];
$defaultBrokers = $adminDefaults['broker_defaults'] ?? [];

// Detect if this is "search all items" mode (search with no explicit filters)
$isSearchAllMode = !empty($_GET['search']) && empty($_GET['status_id']) && empty($_GET['country']) && 
                   empty($_GET['strategy_group_id']) && empty($_GET['broker_id']) && 
                   empty($_GET['yield_min']) && empty($_GET['yield_max']);


// Get filter parameters, using admin defaults when no explicit filter is set (unless in search all mode)
$filters = [
    'search' => $_GET['search'] ?? '',
    'new_companies_status_id' => $_GET['status_id'] ?? ($isSearchAllMode ? '' : (!empty($defaultStatusIds) ? implode(',', $defaultStatusIds) : '')),
    'country_name' => $_GET['country'] ?? ($isSearchAllMode ? '' : (!empty($defaultCountries) ? implode(',', $defaultCountries) : '')),
    'strategy_group_id' => $_GET['strategy_group_id'] ?? ($isSearchAllMode ? '' : (!empty($defaultStrategies) ? implode(',', $defaultStrategies) : '')),
    'broker_id' => $_GET['broker_id'] ?? ($isSearchAllMode ? '' : (!empty($defaultBrokers) ? implode(',', $defaultBrokers) : '')),
    'yield_min' => $_GET['yield_min'] ?? '',
    'yield_max' => $_GET['yield_max'] ?? ''
];

// Create filtered array for database query (remove empty values to avoid parameter binding issues)
$dbFilters = array_filter($filters, function($value) {
    return $value !== null && $value !== '';
});

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(10, min(100, (int)($_GET['limit'] ?? 25)));

// Get data
try {
    $newCompaniesData = $controller->getNewCompanies($dbFilters, $page, $limit);
    $filterOptions = $controller->getFilterOptions();
    $statistics = $controller->getNewCompaniesStatistics();
} catch (Exception $e) {
    $errorMessage = 'Error loading data: ' . $e->getMessage();
    $newCompaniesData = ['entries' => [], 'pagination' => []];
    $filterOptions = [];
    $statistics = [];
}

// Initialize variables for template
$pageTitle = 'New Companies Management - PSW 4.0';
$pageDescription = 'Manage your watchlist and buy targets';
$additionalCSS = [
    BASE_URL . '/assets/css/new-companies-management.css?v=' . time()
];
$additionalJS = [
    BASE_URL . '/assets/js/new-companies-management.js?v=' . time()
];

$user = [
    'username' => Auth::getUsername(),
    'user_id' => Auth::getUserId(),
    'role_name' => $_SESSION['role_name'] ?? 'User'
];
$csrfToken = Security::generateCSRFToken();

// Prepare content for buylist page
ob_start();
?>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1><i class="fas fa-star"></i> New Companies</h1>
                </div>
            </div>
        </div>

        <?php if ($errorMessage): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics removed for cleaner interface -->

        <!-- Main Content -->
        <div class="content-wrapper">
            <!-- Toolbar -->
            <div class="toolbar">
                <div class="toolbar-left">
                    <button class="btn btn-primary" onclick="showAddModal()">
                        <i class="fas fa-plus"></i> Add New Company
                    </button>
                    <button class="btn btn-secondary" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <div class="toolbar-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search companies, notes..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                    </div>
                    <div class="checkbox-dropdown" data-filter="status">
                        <button type="button" class="dropdown-button" id="statusFilter">
                            <span class="dropdown-text">All Statuses</span>
                            <i class="fas fa-chevron-down arrow"></i>
                        </button>
                        <div class="dropdown-content">
                            <?php 
                            $selectedStatusIds = !empty($filters['new_companies_status_id']) ? explode(',', $filters['new_companies_status_id']) : [];
                            
                            // Add null option first - check if it's in selected values or admin defaults
                            $isNullSelected = in_array('null', $selectedStatusIds);
                            ?>
                                <div class="dropdown-option">
                                    <input type="checkbox" id="status_null" value="null" <?= $isNullSelected ? 'checked' : '' ?>>
                                    <label for="status_null">Watchlist</label>
                                </div>
                            <?php
                            
                            foreach ($filterOptions['statuses'] ?? [] as $status): 
                                $isChecked = in_array($status['id'], $selectedStatusIds);
                            ?>
                                <div class="dropdown-option">
                                    <input type="checkbox" id="status_<?= $status['id'] ?>" value="<?= $status['id'] ?>" 
                                           <?= $isChecked ? 'checked' : '' ?>>
                                    <label for="status_<?= $status['id'] ?>"><?= htmlspecialchars($status['status']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="checkbox-dropdown" data-filter="country">
                        <button type="button" class="dropdown-button" id="countryFilter">
                            <span class="dropdown-text">All Countries</span>
                            <i class="fas fa-chevron-down arrow"></i>
                        </button>
                        <div class="dropdown-content">
                            <?php 
                            $selectedCountries = !empty($filters['country_name']) ? explode(',', $filters['country_name']) : [];
                            
                            // Add null option first - check if it's in admin defaults
                            $isNullSelected = in_array('null', $selectedCountries);
                            ?>
                                <div class="dropdown-option">
                                    <input type="checkbox" id="country_null" value="null" <?= $isNullSelected ? 'checked' : '' ?>>
                                    <label for="country_null">No Country (NULL)</label>
                                </div>
                            <?php
                            
                            foreach ($filterOptions['countries'] ?? [] as $country): 
                            ?>
                                <div class="dropdown-option">
                                    <input type="checkbox" id="country_<?= htmlspecialchars($country) ?>" value="<?= htmlspecialchars($country) ?>" 
                                           <?= in_array($country, $selectedCountries) ? 'checked' : '' ?>>
                                    <label for="country_<?= htmlspecialchars($country) ?>"><?= htmlspecialchars($country) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="checkbox-dropdown" data-filter="strategy">
                        <button type="button" class="dropdown-button" id="strategyFilter">
                            <span class="dropdown-text">All Strategy Groups</span>
                            <i class="fas fa-chevron-down arrow"></i>
                        </button>
                        <div class="dropdown-content">
                            <?php 
                            $selectedStrategyIds = !empty($filters['strategy_group_id']) ? explode(',', $filters['strategy_group_id']) : [];
                            
                            // Add null option first - check if it's in admin defaults  
                            $isNullSelected = in_array('null', $selectedStrategyIds);
                            ?>
                                <div class="dropdown-option">
                                    <input type="checkbox" id="strategy_null" value="null" <?= $isNullSelected ? 'checked' : '' ?>>
                                    <label for="strategy_null">No Strategy Group (NULL)</label>
                                </div>
                            <?php
                            
                            foreach ($filterOptions['strategies'] ?? [] as $strategy): 
                            ?>
                                <div class="dropdown-option">
                                    <input type="checkbox" id="strategy_<?= $strategy['strategy_group_id'] ?>" value="<?= $strategy['strategy_group_id'] ?>" 
                                           <?= in_array($strategy['strategy_group_id'], $selectedStrategyIds) ? 'checked' : '' ?>>
                                    <label for="strategy_<?= $strategy['strategy_group_id'] ?>">Group <?= $strategy['strategy_group_id'] ?>: <?= htmlspecialchars($strategy['strategy_name']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="checkbox-dropdown" data-filter="broker">
                        <button type="button" class="dropdown-button" id="brokerFilter">
                            <span class="dropdown-text">All Brokers</span>
                            <i class="fas fa-chevron-down arrow"></i>
                        </button>
                        <div class="dropdown-content">
                            <?php 
                            $selectedBrokerIds = !empty($filters['broker_id']) ? explode(',', $filters['broker_id']) : [];
                            
                            // Add null option first - check if it's in admin defaults
                            $isNullSelected = in_array('null', $selectedBrokerIds);
                            ?>
                                <div class="dropdown-option">
                                    <input type="checkbox" id="broker_null" value="null" <?= $isNullSelected ? 'checked' : '' ?>>
                                    <label for="broker_null">No Broker (NULL)</label>
                                </div>
                            <?php
                            
                            foreach ($filterOptions['brokers'] ?? [] as $broker): 
                            ?>
                                <div class="dropdown-option">
                                    <input type="checkbox" id="broker_<?= $broker['broker_id'] ?>" value="<?= $broker['broker_id'] ?>" 
                                           <?= in_array($broker['broker_id'], $selectedBrokerIds) ? 'checked' : '' ?>>
                                    <label for="broker_<?= $broker['broker_id'] ?>"><?= htmlspecialchars($broker['broker_name']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search Results Indicator -->
            <?php 
            $hasSearch = !empty($_GET['search']);
            
            // Check for active filters (either explicit URL parameters OR applied defaults)
            $hasExplicitFilters = !empty($_GET['status_id']) || !empty($_GET['country']) || 
                                !empty($_GET['strategy_group_id']) || !empty($_GET['broker_id']) || 
                                !empty($_GET['yield_min']) || !empty($_GET['yield_max']);
            
            $hasDefaultFilters = (!$isSearchAllMode && (
                !empty($defaultStatusIds) || !empty($defaultCountries) || 
                !empty($defaultStrategies) || !empty($defaultBrokers)
            ));
            
            $activeFilters = array_filter([
                ($hasExplicitFilters && !empty($_GET['status_id'])) || ($hasDefaultFilters && !empty($defaultStatusIds)) ? 'status' : null,
                ($hasExplicitFilters && !empty($_GET['country'])) || ($hasDefaultFilters && !empty($defaultCountries)) ? 'country' : null,
                ($hasExplicitFilters && !empty($_GET['strategy_group_id'])) || ($hasDefaultFilters && !empty($defaultStrategies)) ? 'strategy' : null,
                ($hasExplicitFilters && !empty($_GET['broker_id'])) || ($hasDefaultFilters && !empty($defaultBrokers)) ? 'broker' : null,
                !empty($_GET['yield_min']) || !empty($_GET['yield_max']) ? 'yield' : null
            ]);
            $activeFilterCount = count($activeFilters);
            $totalRecords = $newCompaniesData['pagination']['total_records'] ?? 0;
            
            if ($hasSearch || $activeFilterCount > 0): ?>
                <div class="search-results-indicator">
                    <div class="search-info">
                        <?php if ($hasSearch): ?>
                            <span class="search-icon">🔍</span>
                            <span class="search-text">
                                Search results for "<strong><?= htmlspecialchars($_GET['search']) ?></strong>"
                            </span>
                        <?php else: ?>
                            <span class="filter-icon">📊</span>
                            <span class="filter-text">Filtered results</span>
                        <?php endif; ?>
                        
                        <span class="results-summary">
                            (showing <?= count($newCompaniesData['entries']) ?> of <?= $totalRecords ?> total
                            <?php if ($activeFilterCount > 0): ?>
                                • <?= $activeFilterCount ?> filter<?= $activeFilterCount > 1 ? 's' : '' ?> active
                            <?php endif; ?>)
                        </span>
                    </div>
                    
                    <?php if ($hasSearch || $activeFilterCount > 0): ?>
                        <div class="search-actions">
                            <?php if ($hasSearch || $hasExplicitFilters || $hasDefaultFilters): ?>
                                <button type="button" class="btn btn-sm btn-outline" onclick="resetToDefaults()">
                                    <i class="fas fa-undo"></i> Reset to defaults
                                </button>
                            <?php endif; ?>
                            <?php if ($hasSearch): ?>
                                <button type="button" class="btn btn-sm btn-outline" onclick="searchAllItems()">
                                    <i class="fas fa-globe"></i> Search all items
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Buylist Table -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Status</th>
                            <th>Broker</th>
                            <th>Yield (%)</th>
                            <th>Country</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($newCompaniesData['entries'])): ?>
                            <?php foreach ($newCompaniesData['entries'] as $entry): ?>
                                <tr>
                                    <td>
                                        <div class="company-info" 
                                             data-company-id="<?= $entry['new_companies_id'] ?>"
                                             data-company="<?= htmlspecialchars($entry['company']) ?>"
                                             data-ticker="<?= htmlspecialchars($entry['ticker']) ?>"
                                             data-isin="<?= htmlspecialchars($entry['isin'] ?: 'N/A') ?>"
                                             data-strategy-group="<?= htmlspecialchars($entry['strategy_name'] ?: 'No Strategy') ?>"
                                             data-strategy-id="<?= $entry['strategy_group_id'] ?: 'N/A' ?>"
                                             data-new-group="<?= $entry['new_group_id'] ?: 'No Group' ?>"
                                             data-broker="<?= htmlspecialchars($entry['broker_name'] ?: 'No Broker') ?>"
                                             data-yield="<?= $entry['yield'] ? number_format($entry['yield'], 2) . '%' : 'N/A' ?>"
                                             data-country="<?= htmlspecialchars($entry['country_name'] ?: 'N/A') ?>"
                                             data-status="<?= htmlspecialchars($entry['status_name'] ?: 'Watchlist') ?>"
                                             data-comments="<?= htmlspecialchars($entry['comments'] ?: 'No comments') ?>"
                                             data-inspiration="<?= htmlspecialchars($entry['inspiration'] ?: 'No inspiration noted') ?>">
                                            <div class="company-name">
                                                <a href="#" class="company-name-link" onclick="openCompanyPage(<?= $entry['new_companies_id'] ?>); return false;">
                                                    <strong><?= htmlspecialchars($entry['company']) ?></strong>
                                                </a>
                                                <button class="company-details-btn" onclick="toggleCompanyPanel(this)" title="View details">
                                                    <i class="fas fa-ellipsis-h"></i>
                                                </button>
                                            </div>
                                            <div class="company-details">
                                                <div class="company-identifiers">
                                                    <?php if ($entry['isin'] || $entry['ticker']): ?>
                                                        <?= htmlspecialchars($entry['isin'] ?: 'No ISIN') ?> | <?= htmlspecialchars($entry['ticker'] ?: 'No Ticker') ?>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($entry['comments']): ?>
                                                    <div class="comments-preview">
                                                        <?= htmlspecialchars(substr($entry['comments'], 0, 80)) ?><?= strlen($entry['comments']) > 80 ? '...' : '' ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge">
                                            <?= htmlspecialchars($entry['status_name'] ?: 'Watchlist') ?>
                                        </span>
                                    </td>
                                    <td class="broker">
                                        <?= htmlspecialchars($entry['broker_name'] ?: '-') ?>
                                    </td>
                                    <td class="yield">
                                        <?= $entry['yield'] ? number_format($entry['yield'], 2) . '%' : '-' ?>
                                    </td>
                                    <td class="country">
                                        <?= htmlspecialchars($entry['country_name'] ?: '-') ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" onclick="toggleCompanyPanel(this)" title="View Details">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                            <button class="btn-icon btn-success" onclick="addToMasterlist(<?= $entry['new_companies_id'] ?>, '<?= htmlspecialchars($entry['company']) ?>')" title="Add to Masterlist">
                                                <i class="fas fa-plus-circle"></i>
                                            </button>
                                            <button class="btn-icon" onclick="editEntry(<?= $entry['new_companies_id'] ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon btn-danger" onclick="deleteEntry(<?= $entry['new_companies_id'] ?>, '<?= htmlspecialchars($entry['company']) ?>')" title="Remove">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-star"></i>
                                        <p>Your new companies list is empty</p>
                                        <button class="btn btn-primary" onclick="showAddModal()">
                                            <i class="fas fa-plus"></i> Add First Entry
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if (!empty($newCompaniesData['pagination'])): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <?= count($newCompaniesData['entries']) ?> of <?= $newCompaniesData['pagination']['total_records'] ?> entries
                    </div>
                    <div class="pagination-controls">
                        <?php if ($newCompaniesData['pagination']['has_previous']): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $newCompaniesData['pagination']['current_page'] - 1])) ?>" class="btn btn-sm">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <span class="page-info">
                            Page <?= $newCompaniesData['pagination']['current_page'] ?> of <?= $newCompaniesData['pagination']['total_pages'] ?>
                        </span>
                        
                        <?php if ($newCompaniesData['pagination']['has_next']): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $newCompaniesData['pagination']['current_page'] + 1])) ?>" class="btn btn-sm">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="entryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Company</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="entryForm">
                <input type="hidden" id="modalAction" name="action" value="add">
                <input type="hidden" id="companyId" name="new_companies_id" value="">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                
                
                <div>
                    <!-- Row 1: Data Source (full width) -->
                    <div class="form-group borsdata-toggle-section">
                        <label for="borsdata_available">Data Source</label>
                        <select id="borsdata_available" name="borsdata_available" onchange="toggleBorsdataFields(); previewBorsdataData();">
                            <option value="0">Manual Entry</option>
                            <option value="1" selected>Auto-populate from Börsdata</option>
                        </select>
                        <small class="form-help">Select "Auto-populate" to fetch company data automatically using ISIN</small>
                    </div>
                    
                    <!-- Row 2: ISIN | Company Name | Ticker -->
                    <div class="form-row form-row-three">
                        <div class="form-group">
                            <label for="isin">ISIN <span id="isinRequired" style="display: none; color: red;">*</span></label>
                            <input type="text" id="isin" name="isin" maxlength="12" placeholder="e.g., US88160R1014" onblur="previewBorsdataData()">
                            <small class="form-help" id="isinHelp">International Securities Identification Number</small>
                        </div>
                        <div class="form-group">
                            <label for="company">Company Name <span id="companyRequired">*</span></label>
                            <input type="text" id="company" name="company" required maxlength="200" placeholder="e.g., Tesla Inc">
                            <small class="form-help" id="companyHelp" style="display: none;">This will be auto-filled when using Börsdata</small>
                        </div>
                        <div class="form-group">
                            <label for="ticker">Ticker <span id="tickerRequired">*</span></label>
                            <input type="text" id="ticker" name="ticker" required maxlength="20" placeholder="e.g., TSLA">
                            <small class="form-help" id="tickerHelp" style="display: none;">This will be auto-filled when using Börsdata</small>
                        </div>
                    </div>
                    
                    <!-- Row 3: Strategy Group | New Group ID | Broker -->
                    <div class="form-row form-row-three">
                        <div class="form-group">
                            <label for="strategy_group_id">Strategy Group</label>
                            <select id="strategy_group_id" name="strategy_group_id">
                                <option value="">Select Strategy</option>
                                <?php foreach ($filterOptions['strategies'] ?? [] as $strategy): ?>
                                    <option value="<?= $strategy['strategy_group_id'] ?>">
                                        Group <?= $strategy['strategy_group_id'] ?> - <?= htmlspecialchars($strategy['strategy_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="new_group_id">New Group ID</label>
                            <input type="number" id="new_group_id" name="new_group_id" min="0" placeholder="Group ID">
                        </div>
                        <div class="form-group">
                            <label for="broker_id">Broker</label>
                            <select id="broker_id" name="broker_id">
                                <option value="">Select Broker</option>
                                <?php foreach ($filterOptions['brokers'] ?? [] as $broker): ?>
                                    <option value="<?= $broker['broker_id'] ?>">
                                        <?= htmlspecialchars($broker['broker_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Row 4: Status (full width) -->
                    <div class="form-group">
                        <label for="new_companies_status_id">Status</label>
                        <select id="new_companies_status_id" name="new_companies_status_id">
                            <option value="">Default (Add to watchlist)</option>
                            <?php foreach ($filterOptions['statuses'] ?? [] as $status): ?>
                                <option value="<?= $status['id'] ?>">
                                    <?= htmlspecialchars($status['status']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Row 5: Inspiration (full width) -->
                    <div class="form-group">
                        <label for="inspiration">Inspiration</label>
                        <input type="text" id="inspiration" name="inspiration" maxlength="255" placeholder="What inspired this pick?">
                    </div>
                    
                    <!-- Row 6: Comments (full width) -->
                    <div class="form-group">
                        <label for="comments">Comments</label>
                        <textarea id="comments" name="comments" rows="3" placeholder="Add your notes about this investment..."></textarea>
                    </div>
                    
                    <!-- Hidden fields for optional data -->
                    <input type="hidden" id="country_name" name="country_name">
                    <input type="hidden" id="country_id" name="country_id">
                    <input type="hidden" id="yield" name="yield">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span id="submitText">Add New Company</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add to Masterlist Modal -->
    <div id="masterlistModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add to Masterlist</h3>
                <button class="modal-close" onclick="closeMasterlistModal()">&times;</button>
            </div>
            <form id="masterlistForm">
                <input type="hidden" name="action" value="add_to_masterlist">
                <input type="hidden" id="masterlistCompanyId" name="new_companies_id" value="">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                
                <div class="modal-body">
                    <p>Add <strong id="masterlistCompanyName"></strong> to your masterlist?</p>
                    <p class="text-muted">This will make the company available in your portfolio tracking.</p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="masterlist_market">Market Category</label>
                            <select id="masterlist_market" name="market">
                                <option value="">Select Market</option>
                                <option value="Large Cap">Large Cap</option>
                                <option value="Mid Cap">Mid Cap</option>
                                <option value="Small Cap">Small Cap</option>
                                <option value="NYSE">NYSE</option>
                                <option value="NASDAQ">NASDAQ</option>
                                <option value="Private">Private/Unlisted</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="masterlist_share_type">Share Type</label>
                            <select id="masterlist_share_type" name="share_type_id">
                                <option value="1">A - Ordinary A Share</option>
                                <option value="2">B - Ordinary B Share</option>
                                <option value="3">C - Ordinary C Share</option>
                                <option value="4">Pref - Preference Share</option>
                                <option value="5">D - Ordinary D Share</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeMasterlistModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Add to Masterlist
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Removal</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove <strong id="deleteCompanyName"></strong> from your new companies list?</p>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Remove from List
                </button>
            </div>
        </div>
    </div>

    <!-- Company Details Right Panel -->
    <div id="companyPanel" class="company-panel">
        <div class="company-panel-header">
            <h3 id="companyPanelTitle">Company Details</h3>
            <button class="panel-close-btn" onclick="closeCompanyPanel()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="company-panel-content">
            <div class="company-panel-loading">
                <i class="fas fa-spinner fa-spin"></i>
                Loading company details...
            </div>
            <div class="company-panel-data" style="display: none;">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Panel Backdrop -->
    <div id="companyPanelBackdrop" class="company-panel-backdrop" onclick="closeCompanyPanel()"></div>

    </div>

<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base.php';
?>