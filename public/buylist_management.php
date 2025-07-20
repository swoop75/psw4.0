<?php
/**
 * File: public/buylist_management.php
 * Description: Buylist management interface for PSW 4.0
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/middleware/Auth.php';
require_once __DIR__ . '/../src/controllers/BuylistController.php';
require_once __DIR__ . '/../src/controllers/MasterlistController.php';
require_once __DIR__ . '/../src/utils/Security.php';

if (!Auth::isLoggedIn()) {
    header('Location: ' . BASE_URL . '/public/login.php');
    exit;
}

$controller = new BuylistController();
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
                $buylistId = $_POST['buylist_id'] ?? '';
                $masterlistData = [
                    'market' => $_POST['market'] ?? null,
                    'share_type_id' => $_POST['share_type_id'] ?? 1
                ];
                $result = $controller->addToMasterlist($buylistId, $masterlistData);
                echo json_encode(['success' => $result, 'message' => $result ? 'Company added to masterlist successfully' : 'Failed to add to masterlist']);
                break;
                
            case 'update':
                $buylistId = $_POST['buylist_id'] ?? '';
                unset($_POST['action'], $_POST['csrf_token'], $_POST['buylist_id']);
                $result = $controller->updateBuylistEntry($buylistId, $_POST);
                echo json_encode(['success' => $result, 'message' => $result ? 'Entry updated successfully' : 'Failed to update entry']);
                break;
                
            case 'delete':
                $buylistId = $_POST['buylist_id'] ?? '';
                $result = $controller->deleteBuylistEntry($buylistId);
                echo json_encode(['success' => $result, 'message' => $result ? 'Entry removed from buylist' : 'Failed to remove entry']);
                break;
                
            case 'get_entry':
                $buylistId = $_POST['buylist_id'] ?? '';
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
    'status_id' => $_GET['status_id'] ?? '',
    'priority_level' => $_GET['priority_level'] ?? '',
    'risk_level' => $_GET['risk_level'] ?? '',
    'sector' => $_GET['sector'] ?? '',
    'market_cap_category' => $_GET['market_cap_category'] ?? '',
    'price_min' => $_GET['price_min'] ?? '',
    'price_max' => $_GET['price_max'] ?? ''
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(10, min(100, (int)($_GET['limit'] ?? 25)));

// Get data
try {
    $buylistData = $controller->getUserBuylist(array_filter($filters), $page, $limit);
    $statuses = $controller->getBuylistStatuses();
    $filterOptions = $controller->getFilterOptions();
    $statistics = $controller->getBuylistStatistics();
} catch (Exception $e) {
    $errorMessage = 'Error loading data: ' . $e->getMessage();
    $buylistData = ['entries' => [], 'pagination' => []];
    $statuses = [];
    $filterOptions = [];
    $statistics = [];
}

$user = [
    'username' => Auth::getUsername(),
    'user_id' => Auth::getUserId(),
    'role_name' => $_SESSION['role_name'] ?? 'User'
];
$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buylist Management - PSW 4.0</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/improved-buylist-management.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1><i class="fas fa-star"></i> Buylist Management</h1>
                    <p>Manage your watchlist and buy targets</p>
                </div>
                <div class="header-right">
                    <span class="user-info">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($user['username']) ?>
                    </span>
                    <a href="<?= BASE_URL ?>/public/index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </header>

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
                    <div class="stat-number"><?= number_format($statistics['target_value'], 0) ?> SEK</div>
                    <div class="stat-label">Target Value</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon price">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= number_format($statistics['avg_target_price'], 0) ?> SEK</div>
                    <div class="stat-label">Avg Target Price</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon entries">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $statistics['entries_with_price'] ?></div>
                    <div class="stat-label">With Price Targets</div>
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
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= $status['status_id'] ?>" <?= $filters['status_id'] == $status['status_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($status['status_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="priorityFilter" onchange="applyFilters()">
                        <option value="">All Priorities</option>
                        <option value="4" <?= $filters['priority_level'] == '4' ? 'selected' : '' ?>>Critical</option>
                        <option value="3" <?= $filters['priority_level'] == '3' ? 'selected' : '' ?>>High</option>
                        <option value="2" <?= $filters['priority_level'] == '2' ? 'selected' : '' ?>>Medium</option>
                        <option value="1" <?= $filters['priority_level'] == '1' ? 'selected' : '' ?>>Low</option>
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
                            <th>Priority</th>
                            <th>Target Price</th>
                            <th>Quantity</th>
                            <th>Target Value</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($buylistData['entries'])): ?>
                            <?php foreach ($buylistData['entries'] as $entry): ?>
                                <tr>
                                    <td>
                                        <div class="company-info">
                                            <div class="company-name">
                                                <strong><?= htmlspecialchars($entry['company_name']) ?></strong>
                                                <span class="ticker"><?= htmlspecialchars($entry['ticker']) ?></span>
                                                <?php if ($entry['added_to_masterlist']): ?>
                                                    <span class="masterlist-badge">
                                                        <i class="fas fa-check"></i> In Masterlist
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="company-details">
                                                <?php if ($entry['isin']): ?>
                                                    <span class="isin"><?= htmlspecialchars($entry['isin']) ?></span>
                                                <?php endif; ?>
                                                <span class="country"><?= htmlspecialchars($entry['country'] ?: 'N/A') ?></span>
                                                <?php if ($entry['exchange']): ?>
                                                    <span class="exchange"><?= htmlspecialchars($entry['exchange']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge" style="background-color: <?= htmlspecialchars($entry['status_color']) ?>">
                                            <?= htmlspecialchars($entry['status_name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="priority-badge priority-<?= $entry['priority_level'] ?>">
                                            <?= ['', 'Low', 'Medium', 'High', 'Critical'][$entry['priority_level']] ?? 'Unknown' ?>
                                        </span>
                                    </td>
                                    <td class="price">
                                        <?= $entry['target_price'] ? number_format($entry['target_price'], 2) . ' SEK' : '-' ?>
                                    </td>
                                    <td class="quantity">
                                        <?= $entry['target_quantity'] ? number_format($entry['target_quantity']) : '-' ?>
                                    </td>
                                    <td class="value">
                                        <?= ($entry['target_price'] && $entry['target_quantity']) ? 
                                            number_format($entry['target_price'] * $entry['target_quantity'], 0) . ' SEK' : '-' ?>
                                    </td>
                                    <td class="date">
                                        <?= date('M j, Y', strtotime($entry['updated_at'])) ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if (!$entry['added_to_masterlist']): ?>
                                                <button class="btn-icon btn-success" onclick="addToMasterlist(<?= $entry['buylist_id'] ?>, '<?= htmlspecialchars($entry['company_name']) ?>')" title="Add to Masterlist">
                                                    <i class="fas fa-plus-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn-icon" onclick="editEntry(<?= $entry['buylist_id'] ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon btn-danger" onclick="deleteEntry(<?= $entry['buylist_id'] ?>, '<?= htmlspecialchars($entry['company_name']) ?>')" title="Remove">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">
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
                <input type="hidden" id="buylistId" name="buylist_id" value="">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                
                <div class="form-tabs">
                    <button type="button" class="tab-button active" onclick="showTab('basic')">Basic Info</button>
                    <button type="button" class="tab-button" onclick="showTab('analysis')">Analysis</button>
                    <button type="button" class="tab-button" onclick="showTab('strategy')">Strategy</button>
                </div>
                
                <!-- Basic Info Tab -->
                <div id="basicTab" class="tab-content active">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="company_name">Company Name *</label>
                            <input type="text" id="company_name" name="company_name" required maxlength="200" placeholder="e.g., Tesla Inc">
                        </div>
                        <div class="form-group">
                            <label for="ticker">Ticker *</label>
                            <input type="text" id="ticker" name="ticker" required maxlength="20" placeholder="e.g., TSLA">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="country">Country</label>
                            <select id="country" name="country">
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
                            <label for="currency">Currency</label>
                            <select id="currency" name="currency">
                                <option value="SEK">SEK</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                                <option value="NOK">NOK</option>
                                <option value="DKK">DKK</option>
                                <option value="GBP">GBP</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="exchange">Exchange</label>
                            <input type="text" id="exchange" name="exchange" maxlength="50" placeholder="e.g., NASDAQ, NYSE, OMX">
                        </div>
                        <div class="form-group">
                            <label for="isin">ISIN (Optional)</label>
                            <input type="text" id="isin" name="isin" maxlength="12" placeholder="e.g., US88160R1014">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="business_description">Business Description</label>
                        <textarea id="business_description" name="business_description" rows="2" placeholder="Brief description of what the company does..."></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status_id">Status *</label>
                            <select id="status_id" name="status_id" required>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= $status['status_id'] ?>" data-color="<?= $status['status_color'] ?>">
                                        <?= htmlspecialchars($status['status_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="priority_level">Priority</label>
                            <select id="priority_level" name="priority_level">
                                <option value="1">Low</option>
                                <option value="2">Medium</option>
                                <option value="3" selected>High</option>
                                <option value="4">Critical</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="target_price">Target Price (SEK)</label>
                            <input type="number" id="target_price" name="target_price" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="target_quantity">Target Quantity</label>
                            <input type="number" id="target_quantity" name="target_quantity" min="1" placeholder="0">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Add your notes about this investment..."></textarea>
                    </div>
                </div>
                
                <!-- Analysis Tab -->
                <div id="analysisTab" class="tab-content">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="expected_dividend_yield">Expected Dividend Yield (%)</label>
                            <input type="number" id="expected_dividend_yield" name="expected_dividend_yield" step="0.01" min="0" max="100" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="pe_ratio">P/E Ratio</label>
                            <input type="number" id="pe_ratio" name="pe_ratio" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="price_to_book">Price-to-Book</label>
                            <input type="number" id="price_to_book" name="price_to_book" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="debt_to_equity">Debt-to-Equity</label>
                            <input type="number" id="debt_to_equity" name="debt_to_equity" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="roe">ROE (%)</label>
                            <input type="number" id="roe" name="roe" step="0.01" min="0" max="100" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="risk_level">Risk Level</label>
                            <select id="risk_level" name="risk_level">
                                <option value="1">Low Risk</option>
                                <option value="2">Medium Risk</option>
                                <option value="3" selected>High Risk</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="research_notes">Research Notes</label>
                        <textarea id="research_notes" name="research_notes" rows="4" placeholder="Detailed research findings and analysis..."></textarea>
                    </div>
                </div>
                
                <!-- Strategy Tab -->
                <div id="strategyTab" class="tab-content">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="stop_loss_price">Stop Loss (SEK)</label>
                            <input type="number" id="stop_loss_price" name="stop_loss_price" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="take_profit_price">Take Profit (SEK)</label>
                            <input type="number" id="take_profit_price" name="take_profit_price" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="target_allocation_percent">Target Allocation (%)</label>
                        <input type="number" id="target_allocation_percent" name="target_allocation_percent" step="0.01" min="0" max="100" placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label for="entry_strategy">Entry Strategy</label>
                        <textarea id="entry_strategy" name="entry_strategy" rows="3" placeholder="When and how to enter this position..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="exit_strategy">Exit Strategy</label>
                        <textarea id="exit_strategy" name="exit_strategy" rows="3" placeholder="When and how to exit this position..."></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="next_review_date">Next Review Date</label>
                            <input type="date" id="next_review_date" name="next_review_date">
                        </div>
                        <div class="form-group">
                            <label for="price_alert_enabled">Price Alerts</label>
                            <div class="checkbox-group">
                                <input type="checkbox" id="price_alert_enabled" name="price_alert_enabled" value="1" checked>
                                <label for="price_alert_enabled">Enable price alerts</label>
                            </div>
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
                <input type="hidden" id="masterlistBuylistId" name="buylist_id" value="">
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
</body>
</html>