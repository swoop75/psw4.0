<?php
/**
 * File: buylist_management.php
 * Description: Buylist management interface for PSW 4.0 - integrated with unified navigation
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/controllers/PortfolioBuylistController.php';
require_once __DIR__ . '/src/controllers/MasterlistController.php';
require_once __DIR__ . '/src/utils/Security.php';

if (!Auth::isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$controller = new PortfolioBuylistController();
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
                $result = $controller->addBuylistEntry($_POST);
                echo json_encode(['success' => $result, 'message' => $result ? 'Entry added to buylist successfully' : 'Failed to add entry']);
                break;
                
            case 'add_to_masterlist':
                $buylistId = $_POST['buy_list_id'] ?? '';
                $masterlistData = [
                    'market' => $_POST['market'] ?? null,
                    'share_type_id' => $_POST['share_type_id'] ?? 1
                ];
                $result = $controller->addToMasterlist($buylistId, $masterlistData);
                echo json_encode(['success' => $result, 'message' => $result ? 'Company added to masterlist successfully' : 'Failed to add to masterlist']);
                break;
                
            case 'update':
                $buylistId = $_POST['buy_list_id'] ?? '';
                unset($_POST['action'], $_POST['csrf_token'], $_POST['buy_list_id']);
                $result = $controller->updateBuylistEntry($buylistId, $_POST);
                echo json_encode(['success' => $result, 'message' => $result ? 'Entry updated successfully' : 'Failed to update entry']);
                break;
                
            case 'delete':
                $buylistId = $_POST['buy_list_id'] ?? '';
                $result = $controller->deleteBuylistEntry($buylistId);
                echo json_encode(['success' => $result, 'message' => $result ? 'Entry removed from buylist' : 'Failed to remove entry']);
                break;
                
            case 'get_entry':
                $buylistId = $_POST['buy_list_id'] ?? '';
                $entry = $controller->getBuylistEntry($buylistId);
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

// Get filter parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'buylist_status_id' => $_GET['status_id'] ?? '',
    'country_name' => $_GET['country'] ?? '',
    'strategy_group_id' => $_GET['strategy_group_id'] ?? '',
    'broker_id' => $_GET['broker_id'] ?? '',
    'yield_min' => $_GET['yield_min'] ?? '',
    'yield_max' => $_GET['yield_max'] ?? ''
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(10, min(100, (int)($_GET['limit'] ?? 25)));

// Get data
try {
    $buylistData = $controller->getBuylist(array_filter($filters), $page, $limit);
    $filterOptions = $controller->getFilterOptions();
    $statistics = $controller->getBuylistStatistics();
} catch (Exception $e) {
    $errorMessage = 'Error loading data: ' . $e->getMessage();
    $buylistData = ['entries' => [], 'pagination' => []];
    $filterOptions = [];
    $statistics = [];
}

// Initialize variables for template
$pageTitle = 'Buylist Management - PSW 4.0';
$pageDescription = 'Manage your watchlist and buy targets';
$additionalCSS = [
    BASE_URL . '/assets/css/improved-buylist-management.css?v=' . time(),
    BASE_URL . '/assets/css/tooltip.css?v=' . time()
];
$additionalJS = [BASE_URL . '/assets/js/buylist-management.js?v=' . time()];

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
                    <h1><i class="fas fa-star"></i> Buylist Management</h1>
                    <p>Manage your watchlist and buy targets</p>
                    <p class="header-hint"><i class="fas fa-info-circle"></i> Hover over company names for detailed information</p>
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

        <!-- Statistics Cards -->
        <?php if (!empty($statistics)): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-list"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $statistics['total_entries'] ?></div>
                    <div class="stat-label">Total Entries</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon target">
                    <i class="fas fa-bullseye"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $statistics['unique_countries'] ?></div>
                    <div class="stat-label">Countries</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon price">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= number_format($statistics['avg_yield'], 2) ?>%</div>
                    <div class="stat-label">Avg Yield</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon entries">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= number_format($statistics['max_yield'], 2) ?>%</div>
                    <div class="stat-label">Max Yield</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="content-wrapper">
            <!-- Toolbar -->
            <div class="toolbar">
                <div class="toolbar-left">
                    <button class="btn btn-primary" onclick="showAddModal()">
                        <i class="fas fa-plus"></i> Add to Buylist
                    </button>
                    <button class="btn btn-secondary" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <div class="toolbar-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search companies, notes..." value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                    <select id="statusFilter" onchange="applyFilters()">
                        <option value="">All Statuses</option>
                        <?php foreach ($filterOptions['statuses'] ?? [] as $status): ?>
                            <option value="<?= $status['id'] ?>" <?= $filters['buylist_status_id'] == $status['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($status['status']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="countryFilter" onchange="applyFilters()">
                        <option value="">All Countries</option>
                        <?php foreach ($filterOptions['countries'] ?? [] as $country): ?>
                            <option value="<?= htmlspecialchars($country) ?>" <?= $filters['country_name'] === $country ? 'selected' : '' ?>>
                                <?= htmlspecialchars($country) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="strategyFilter" onchange="applyFilters()">
                        <option value="">All Strategies</option>
                        <?php foreach ($filterOptions['strategies'] ?? [] as $strategy): ?>
                            <option value="<?= $strategy['strategy_group_id'] ?>" <?= $filters['strategy_group_id'] == $strategy['strategy_group_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($strategy['strategy_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

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
                        <?php if (!empty($buylistData['entries'])): ?>
                            <?php foreach ($buylistData['entries'] as $entry): ?>
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
                                        <div class="action-buttons">
                                                <button class="btn-icon btn-success" onclick="addToMasterlist(<?= $entry['buy_list_id'] ?>, '<?= htmlspecialchars($entry['company']) ?>')" title="Add to Masterlist">
                                                    <i class="fas fa-plus-circle"></i>
                                                </button>
                                            <button class="btn-icon" onclick="editEntry(<?= $entry['buy_list_id'] ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon btn-danger" onclick="deleteEntry(<?= $entry['buy_list_id'] ?>, '<?= htmlspecialchars($entry['company']) ?>')" title="Remove">
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
                                        <p>Your buylist is empty</p>
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
            <?php if (!empty($buylistData['pagination'])): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <?= count($buylistData['entries']) ?> of <?= $buylistData['pagination']['total_records'] ?> entries
                    </div>
                    <div class="pagination-controls">
                        <?php if ($buylistData['pagination']['has_prev']): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $buylistData['pagination']['current_page'] - 1])) ?>" class="btn btn-sm">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <span class="page-info">
                            Page <?= $buylistData['pagination']['current_page'] ?> of <?= $buylistData['pagination']['total_pages'] ?>
                        </span>
                        
                        <?php if ($buylistData['pagination']['has_next']): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $buylistData['pagination']['current_page'] + 1])) ?>" class="btn btn-sm">
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
                <h3 id="modalTitle">Add to Buylist</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="entryForm">
                <input type="hidden" id="modalAction" name="action" value="add">
                <input type="hidden" id="buylistId" name="buy_list_id" value="">
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
                            <label for="buylist_status_id">Status</label>
                            <select id="buylist_status_id" name="buylist_status_id">
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
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span id="submitText">Add to Buylist</span>
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
                <input type="hidden" id="masterlistBuylistId" name="buy_list_id" value="">
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
                <p>Are you sure you want to remove <strong id="deleteCompanyName"></strong> from your buylist?</p>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Remove from Buylist
                </button>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/buylist-management.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/tooltip.js"></script>
    </div>

<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base.php';
?>