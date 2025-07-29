<?php
/**
 * File: buylist_management.php
 * Description: Buylist management interface for PSW 4.0 - integrated with unified navigation
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
    header('Content-Type: application/json');
    
    try {
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token');
        }
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add':
                $result = $controller->addNewCompanyEntry($_POST);
                echo json_encode(['success' => $result, 'message' => $result ? 'Entry added to new companies successfully' : 'Failed to add entry']);
                break;
                
            case 'add_to_masterlist':
                $companyId = $_POST['new_companies_id'] ?? '';
                $masterlistData = [
                    'market' => $_POST['market'] ?? null,
                    'share_type_id' => $_POST['share_type_id'] ?? 1
                ];
                $result = $controller->addToMasterlist($companyId, $masterlistData);
                echo json_encode(['success' => $result, 'message' => $result ? 'Company added to masterlist successfully' : 'Failed to add to masterlist']);
                break;
                
            case 'update':
                $companyId = $_POST['new_companies_id'] ?? '';
                unset($_POST['action'], $_POST['csrf_token'], $_POST['new_companies_id']);
                $result = $controller->updateNewCompanyEntry($companyId, $_POST);
                echo json_encode(['success' => $result, 'message' => $result ? 'Entry updated successfully' : 'Failed to update entry']);
                break;
                
            case 'delete':
                $companyId = $_POST['new_companies_id'] ?? '';
                $result = $controller->deleteNewCompanyEntry($companyId);
                echo json_encode(['success' => $result, 'message' => $result ? 'Entry removed from new companies' : 'Failed to remove entry']);
                break;
                
            case 'get_entry':
                $companyId = $_POST['new_companies_id'] ?? '';
                $entry = $controller->getNewCompanyEntry($companyId);
                echo json_encode(['success' => (bool)$entry, 'entry' => $entry]);
                break;
                
            case 'search_companies':
                $search = $_POST['search'] ?? '';
                if (strlen($search) >= 2) {
                    $companies = $masterlistController->getAllCompanies(['search' => $search], 1, 10);
                    echo json_encode(['success' => true, 'companies' => $companies['companies']]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Search term too short']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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

// Get filter parameters, using admin defaults when no explicit filter is set
$filters = [
    'search' => $_GET['search'] ?? '',
    'new_companies_status_id' => $_GET['status_id'] ?? (!empty($defaultStatusIds) ? implode(',', $defaultStatusIds) : ''),
    'country_name' => $_GET['country'] ?? (!empty($defaultCountries) ? implode(',', $defaultCountries) : ''),
    'strategy_group_id' => $_GET['strategy_group_id'] ?? (!empty($defaultStrategies) ? implode(',', $defaultStrategies) : ''),
    'broker_id' => $_GET['broker_id'] ?? (!empty($defaultBrokers) ? implode(',', $defaultBrokers) : ''),
    'yield_min' => $_GET['yield_min'] ?? '',
    'yield_max' => $_GET['yield_max'] ?? ''
];

// Remove empty filters to avoid parameter binding issues
$filters = array_filter($filters, function($value) {
    return $value !== null && $value !== '';
});


$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(10, min(100, (int)($_GET['limit'] ?? 25)));

// Get data
try {
    $newCompaniesData = $controller->getNewCompanies($filters, $page, $limit);
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
    BASE_URL . '/assets/css/improved-buylist-management.css?v=' . time(),
    BASE_URL . '/assets/css/tooltip.css?v=' . time()
];
$additionalJS = [
    BASE_URL . '/assets/js/buylist-management.js?v=' . time(),
    BASE_URL . '/assets/js/tooltip.js?v=' . time()
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
    <div class="psw-card psw-mb-6">
        <div class="psw-card-header">
            <h1 class="psw-card-title">
                <i class="fas fa-star psw-card-title-icon"></i>
                New Companies Management
            </h1>
            <p class="psw-card-subtitle">Manage your watchlist and buy targets</p>
        </div>
    </div>

    <?php if ($errorMessage): ?>
        <div class="psw-alert psw-alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="psw-alert psw-alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($successMessage) ?>
        </div>
    <?php endif; ?>

        <!-- Statistics removed for cleaner interface -->

    <!-- Main Content -->
    <div class="psw-card">
        <div class="psw-card-content">
            <!-- Toolbar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-6);">
                <div style="display: flex; gap: var(--spacing-3);">
                    <button class="psw-btn psw-btn-primary" onclick="showAddModal()">
                        <i class="fas fa-plus psw-btn-icon"></i> Add New Company
                    </button>
                    <button class="psw-btn psw-btn-secondary" onclick="refreshData()">
                        <i class="fas fa-sync-alt psw-btn-icon"></i> Refresh
                    </button>
                </div>
                <div style="display: flex; gap: var(--spacing-3); align-items: center;">
                    <div style="position: relative; display: flex; align-items: center;">
                        <i class="fas fa-search" style="position: absolute; left: var(--spacing-3); color: var(--text-muted);"></i>
                        <input type="text" id="searchInput" placeholder="Search companies, notes..." value="<?= htmlspecialchars($filters['search']) ?>" class="psw-form-input" style="padding-left: var(--spacing-10); min-width: 300px;">
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
                                    <label for="status_null">not bought</label>
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
            $hasSearch = !empty($filters['search']);
            $activeFilters = array_filter([
                !empty($filters['new_companies_status_id']) ? 'status' : null,
                !empty($filters['country_name']) ? 'country' : null,
                !empty($filters['strategy_group_id']) ? 'strategy' : null,
                !empty($filters['broker_id']) ? 'broker' : null,
                !empty($filters['yield_min']) || !empty($filters['yield_max']) ? 'yield' : null
            ]);
            $activeFilterCount = count($activeFilters);
            $totalRecords = $newCompaniesData['pagination']['total_records'] ?? 0;
            
            if ($hasSearch || $activeFilterCount > 0): ?>
                <div class="search-results-indicator">
                    <div class="search-info">
                        <?php if ($hasSearch): ?>
                            <span class="search-icon">🔍</span>
                            <span class="search-text">
                                Search results for "<strong><?= htmlspecialchars($filters['search']) ?></strong>"
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
                            <?php if ($activeFilterCount > 0): ?>
                                <button type="button" class="psw-btn psw-btn-secondary" style="padding: var(--spacing-2) var(--spacing-3); font-size: var(--font-size-sm);" onclick="resetToDefaults()">
                                    <i class="fas fa-undo" style="margin-right: var(--spacing-2);"></i> Reset to defaults
                                </button>
                            <?php endif; ?>
                            <?php if ($hasSearch): ?>
                                <button type="button" class="psw-btn psw-btn-secondary" style="padding: var(--spacing-2) var(--spacing-3); font-size: var(--font-size-sm);" onclick="searchAllItems()">
                                    <i class="fas fa-globe" style="margin-right: var(--spacing-2);"></i> Search all items
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Buylist Table -->
            <div style="overflow-x: auto;">
                <table class="psw-table">
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
                                             data-tooltip="true"
                                             data-company="<?= htmlspecialchars($entry['company']) ?>"
                                             data-ticker="<?= htmlspecialchars($entry['ticker']) ?>"
                                             data-isin="<?= htmlspecialchars($entry['isin'] ?: 'N/A') ?>"
                                             data-strategy-group="<?= htmlspecialchars($entry['strategy_name'] ?: 'No Strategy') ?>"
                                             data-strategy-id="<?= $entry['strategy_group_id'] ?: 'N/A' ?>"
                                             data-new-group="<?= $entry['new_group_id'] ?: 'No Group' ?>"
                                             data-broker="<?= htmlspecialchars($entry['broker_name'] ?: 'No Broker') ?>"
                                             data-yield="<?= $entry['yield'] ? number_format($entry['yield'], 2) . '%' : 'N/A' ?>"
                                             data-country="<?= htmlspecialchars($entry['country_name'] ?: 'N/A') ?>"
                                             data-status="<?= htmlspecialchars($entry['status_name'] ?: 'No Status') ?>"
                                             data-comments="<?= htmlspecialchars($entry['comments'] ?: 'No comments') ?>"
                                             data-inspiration="<?= htmlspecialchars($entry['inspiration'] ?: 'No inspiration noted') ?>">
                                            <div class="company-name">
                                                <strong><?= htmlspecialchars($entry['company']) ?></strong>
                                                <span class="ticker"><?= htmlspecialchars($entry['ticker']) ?></span>
                                                <i class="fas fa-info-circle tooltip-icon" title="Hover for details"></i>
                                            </div>
                                            <div class="company-details">
                                                <?php if ($entry['isin']): ?>
                                                    <span class="isin"><?= htmlspecialchars($entry['isin']) ?></span>
                                                <?php endif; ?>
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
                                            <?= htmlspecialchars($entry['status_name'] ?: 'No Status') ?>
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
                                        <div style="display: flex; gap: var(--spacing-1);">
                                            <button style="display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: var(--success-bg); border: 1px solid var(--success-color); border-radius: var(--radius-md); color: var(--success-color); cursor: pointer; transition: all var(--transition-fast);" onclick="addToMasterlist(<?= $entry['new_companies_id'] ?>, '<?= htmlspecialchars($entry['company']) ?>')" title="Add to Masterlist" onmouseover="this.style.background='var(--success-color)'; this.style.color='var(--text-inverse)';" onmouseout="this.style.background='var(--success-bg)'; this.style.color='var(--success-color)';">
                                                <i class="fas fa-plus-circle"></i>
                                            </button>
                                            <button style="display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: var(--radius-md); color: var(--text-secondary); cursor: pointer; transition: all var(--transition-fast);" onclick="editEntry(<?= $entry['new_companies_id'] ?>)" title="Edit" onmouseover="this.style.background='var(--primary-accent-light)'; this.style.color='var(--primary-accent)';" onmouseout="this.style.background='var(--bg-secondary)'; this.style.color='var(--text-secondary)';">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button style="display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: var(--radius-md); color: var(--text-secondary); cursor: pointer; transition: all var(--transition-fast);" onclick="deleteEntry(<?= $entry['new_companies_id'] ?>, '<?= htmlspecialchars($entry['company']) ?>')" title="Remove" onmouseover="this.style.background='var(--error-bg)'; this.style.color='var(--error-color)';" onmouseout="this.style.background='var(--bg-secondary)'; this.style.color='var(--text-secondary)';">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: var(--spacing-8); color: var(--text-muted);">
                                    <div class="empty-state">
                                        <i class="fas fa-star"></i>
                                        <p>Your new companies list is empty</p>
                                        <button class="psw-btn psw-btn-primary" onclick="showAddModal()">
                                            <i class="fas fa-plus psw-btn-icon"></i> Add First Entry
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
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: var(--spacing-6); padding-top: var(--spacing-4); border-top: 1px solid var(--border-primary);">
                    <div style="color: var(--text-secondary); font-size: var(--font-size-sm);">
                        Showing <?= count($newCompaniesData['entries']) ?> of <?= $newCompaniesData['pagination']['total_records'] ?> entries
                    </div>
                    <div style="display: flex; align-items: center; gap: var(--spacing-3);">
                        <?php if ($newCompaniesData['pagination']['has_previous']): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $newCompaniesData['pagination']['current_page'] - 1])) ?>" class="psw-btn psw-btn-secondary" style="padding: var(--spacing-2) var(--spacing-3); font-size: var(--font-size-sm);">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <span style="color: var(--text-secondary); font-size: var(--font-size-sm);">
                            Page <?= $newCompaniesData['pagination']['current_page'] ?> of <?= $newCompaniesData['pagination']['total_pages'] ?>
                        </span>
                        
                        <?php if ($newCompaniesData['pagination']['has_next']): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $newCompaniesData['pagination']['current_page'] + 1])) ?>" class="psw-btn psw-btn-secondary" style="padding: var(--spacing-2) var(--spacing-3); font-size: var(--font-size-sm);">
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
                    <div class="form-row">
                        <div class="form-group">
                            <label for="company">Company Name *</label>
                            <input type="text" id="company" name="company" required maxlength="200" placeholder="e.g., Tesla Inc">
                        </div>
                        <div class="form-group">
                            <label for="ticker">Ticker *</label>
                            <input type="text" id="ticker" name="ticker" required maxlength="20" placeholder="e.g., TSLA">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="country_name">Country</label>
                            <select id="country_name" name="country_name">
                                <option value="">Select Country</option>
                                <option value="SE">Sweden</option>
                                <option value="US">United States</option>
                                <option value="FI">Finland</option>
                                <option value="NO">Norway</option>
                                <option value="DK">Denmark</option>
                                <option value="NL">Netherlands</option>
                                <option value="DE">Germany</option>
                                <option value="GB">United Kingdom</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="yield">Yield (%)</label>
                            <input type="number" id="yield" name="yield" step="0.01" min="0" max="100" placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="inspiration">Inspiration</label>
                            <input type="text" id="inspiration" name="inspiration" maxlength="255" placeholder="What inspired this pick?">
                        </div>
                        <div class="form-group">
                            <label for="isin">ISIN (Optional)</label>
                            <input type="text" id="isin" name="isin" maxlength="12" placeholder="e.g., US88160R1014">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="comments">Comments</label>
                        <textarea id="comments" name="comments" rows="3" placeholder="Add your notes about this investment..."></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_companies_status_id">Status</label>
                            <select id="new_companies_status_id" name="new_companies_status_id">
                                <option value="">Select Status</option>
                                <?php foreach ($filterOptions['statuses'] ?? [] as $status): ?>
                                    <option value="<?= $status['id'] ?>">
                                        <?= htmlspecialchars($status['status']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="strategy_group_id">Strategy Group</label>
                            <select id="strategy_group_id" name="strategy_group_id">
                                <option value="">Select Strategy</option>
                                <?php foreach ($filterOptions['strategies'] ?? [] as $strategy): ?>
                                    <option value="<?= $strategy['strategy_group_id'] ?>">
                                        <?= htmlspecialchars($strategy['strategy_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
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
                        <div class="form-group">
                            <label for="new_group_id">Group ID</label>
                            <input type="number" id="new_group_id" name="new_group_id" min="0" placeholder="Group ID">
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="psw-btn psw-btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="psw-btn psw-btn-primary">
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
                    <button type="button" class="psw-btn psw-btn-secondary" onclick="closeMasterlistModal()">Cancel</button>
                    <button type="submit" class="psw-btn psw-btn-primary">
                        <i class="fas fa-plus-circle psw-btn-icon"></i> Add to Masterlist
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
                <button type="button" class="psw-btn psw-btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" style="background: var(--error-color); color: var(--text-inverse); border: none; padding: var(--spacing-3) var(--spacing-4); border-radius: var(--radius-lg); font-size: var(--font-size-base); font-weight: 500; cursor: pointer; transition: all var(--transition-fast);" onclick="confirmDelete()" onmouseover="this.style.background='var(--error-color)'; this.style.transform='translateY(-1px)';" onmouseout="this.style.background='var(--error-color)'; this.style.transform='translateY(0)';">
                    <i class="fas fa-trash" style="margin-right: var(--spacing-2);"></i> Remove from List
                </button>
            </div>
        </div>
        </div>
    </div>

<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base-redesign.php';
?>