<?php
/**
 * File: public/masterlist_management.php
 * Description: Masterlist management interface for PSW 4.0
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/middleware/Auth.php';
require_once __DIR__ . '/../src/controllers/MasterlistController.php';
require_once __DIR__ . '/../src/utils/Security.php';

if (!Auth::isLoggedIn()) {
    header('Location: ' . BASE_URL . '/public/login.php');
    exit;
}

$controller = new MasterlistController();
$errorMessage = '';
$successMessage = '';

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $filters = [
            'search' => $_GET['search'] ?? '',
            'country' => $_GET['country'] ?? '',
            'market' => $_GET['market'] ?? '',
            'share_type' => $_GET['share_type'] ?? '',
            'delisted' => isset($_GET['delisted']) ? (int)$_GET['delisted'] : null
        ];
        
        $csvContent = $controller->exportToCSV(array_filter($filters));
        
        // Set CSV headers
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="masterlist_export_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Content-Length: ' . strlen($csvContent));
        
        echo $csvContent;
        exit;
        
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo 'Error exporting CSV: ' . $e->getMessage();
        exit;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    try {
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token');
        }
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $result = $controller->createCompany($_POST);
                echo json_encode(['success' => $result, 'message' => $result ? 'Company created successfully' : 'Failed to create company']);
                break;
                
            case 'update':
                $isin = $_POST['isin'] ?? '';
                unset($_POST['action'], $_POST['csrf_token'], $_POST['isin']);
                $result = $controller->updateCompany($isin, $_POST);
                echo json_encode(['success' => $result, 'message' => $result ? 'Company updated successfully' : 'Failed to update company']);
                break;
                
            case 'delete':
                $isin = $_POST['isin'] ?? '';
                $result = $controller->deleteCompany($isin);
                echo json_encode(['success' => $result, 'message' => $result ? 'Company deleted successfully' : 'Failed to delete company']);
                break;
                
            case 'get_company':
                $isin = $_POST['isin'] ?? '';
                $company = $controller->getCompanyByIsin($isin);
                echo json_encode(['success' => (bool)$company, 'company' => $company]);
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
    'country' => $_GET['country'] ?? '',
    'market' => $_GET['market'] ?? '',
    'share_type' => $_GET['share_type'] ?? '',
    'delisted' => isset($_GET['delisted']) ? (int)$_GET['delisted'] : null
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(10, min(200, (int)($_GET['limit'] ?? 50)));

// Get data
try {
    $companiesData = $controller->getAllCompanies(array_filter($filters), $page, $limit);
    $shareTypes = $controller->getShareTypes();
    $filterOptions = $controller->getFilterOptions();
    $statistics = $controller->getStatistics();
} catch (Exception $e) {
    $errorMessage = 'Error loading data: ' . $e->getMessage();
    $companiesData = ['companies' => [], 'pagination' => []];
    $shareTypes = [];
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
    <title>Masterlist Management - PSW 4.0</title>
    
    <!-- Beautiful Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/masterlist-management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1><i class="fas fa-building"></i> Masterlist Management</h1>
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
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $statistics['total_companies'] ?></div>
                    <div class="stat-label">Total Companies</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon active">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $statistics['active_companies'] ?></div>
                    <div class="stat-label">Active</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon delisted">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $statistics['delisted_companies'] ?></div>
                    <div class="stat-label">Delisted</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-globe"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $statistics['total_countries'] ?></div>
                    <div class="stat-label">Countries</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="content-wrapper">
            <!-- Filters and Actions -->
            <div class="toolbar">
                <div class="toolbar-left">
                    <button class="btn btn-primary" onclick="showCreateModal()">
                        <i class="fas fa-plus"></i> Add Company
                    </button>
                    <button class="btn btn-secondary" onclick="exportToCSV()">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>
                <div class="toolbar-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search companies..." value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                    <select id="countryFilter" onchange="applyFilters()">
                        <option value="">All Countries</option>
                        <?php foreach ($filterOptions['countries'] ?? [] as $country): ?>
                            <option value="<?= htmlspecialchars($country) ?>" <?= $filters['country'] === $country ? 'selected' : '' ?>>
                                <?= htmlspecialchars($country) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="marketFilter" onchange="applyFilters()">
                        <option value="">All Markets</option>
                        <?php foreach ($filterOptions['markets'] ?? [] as $market): ?>
                            <option value="<?= htmlspecialchars($market) ?>" <?= $filters['market'] === $market ? 'selected' : '' ?>>
                                <?= htmlspecialchars($market) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="delistedFilter" onchange="applyFilters()">
                        <option value="">All Status</option>
                        <option value="0" <?= $filters['delisted'] === 0 ? 'selected' : '' ?>>Active</option>
                        <option value="1" <?= $filters['delisted'] === 1 ? 'selected' : '' ?>>Delisted</option>
                    </select>
                </div>
            </div>

            <!-- Companies Table -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ISIN</th>
                            <th>Ticker</th>
                            <th>Company Name</th>
                            <th>Country</th>
                            <th>Market</th>
                            <th>Share Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($companiesData['companies'])): ?>
                            <?php foreach ($companiesData['companies'] as $company): ?>
                                <tr>
                                    <td class="mono"><?= htmlspecialchars($company['isin']) ?></td>
                                    <td class="mono font-bold"><?= htmlspecialchars($company['ticker']) ?></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/public/company_detail.php?isin=<?= urlencode($company['isin']) ?>" class="company-link">
                                            <?= htmlspecialchars($company['name']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($company['country']) ?></td>
                                    <td><?= htmlspecialchars($company['market'] ?? '-') ?></td>
                                    <td>
                                        <span class="share-type-badge">
                                            <?= htmlspecialchars($company['share_type_code'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($company['delisted']): ?>
                                            <span class="status-badge delisted">
                                                <i class="fas fa-times-circle"></i> Delisted
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge active">
                                                <i class="fas fa-check-circle"></i> Active
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" onclick="editCompany('<?= htmlspecialchars($company['isin']) ?>')" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon btn-danger" onclick="deleteCompany('<?= htmlspecialchars($company['isin']) ?>', '<?= htmlspecialchars($company['name']) ?>')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No companies found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if (!empty($companiesData['pagination'])): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <?= count($companiesData['companies']) ?> of <?= $companiesData['pagination']['total_records'] ?> companies
                    </div>
                    <div class="pagination-controls">
                        <?php if ($companiesData['pagination']['has_prev']): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $companiesData['pagination']['current_page'] - 1])) ?>" class="btn btn-sm">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <span class="page-info">
                            Page <?= $companiesData['pagination']['current_page'] ?> of <?= $companiesData['pagination']['total_pages'] ?>
                        </span>
                        
                        <?php if ($companiesData['pagination']['has_next']): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $companiesData['pagination']['current_page'] + 1])) ?>" class="btn btn-sm">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div id="companyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add Company</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="companyForm">
                <input type="hidden" id="modalAction" name="action" value="create">
                <input type="hidden" id="originalIsin" name="original_isin" value="">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="isin">ISIN *</label>
                        <input type="text" id="isin" name="isin" required maxlength="12" placeholder="e.g., SE0000108656">
                    </div>
                    <div class="form-group">
                        <label for="ticker">Ticker *</label>
                        <input type="text" id="ticker" name="ticker" required maxlength="20" placeholder="e.g., AAPL">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="name">Company Name *</label>
                    <input type="text" id="name" name="name" required maxlength="200" placeholder="e.g., Apple Inc.">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="country">Country *</label>
                        <select id="country" name="country" required>
                            <option value="">Select Country</option>
                            <option value="SE">Sweden</option>
                            <option value="US">United States</option>
                            <option value="FI">Finland</option>
                            <option value="NO">Norway</option>
                            <option value="DK">Denmark</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="market">Market</label>
                        <select id="market" name="market">
                            <option value="">Select Market</option>
                            <option value="Large Cap">Large Cap</option>
                            <option value="Mid Cap">Mid Cap</option>
                            <option value="Small Cap">Small Cap</option>
                            <option value="NYSE">NYSE</option>
                            <option value="NASDAQ">NASDAQ</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="share_type_id">Share Type</label>
                        <select id="share_type_id" name="share_type_id">
                            <?php foreach ($shareTypes as $type): ?>
                                <option value="<?= $type['share_type_id'] ?>">
                                    <?= htmlspecialchars($type['code']) ?> - <?= htmlspecialchars($type['description']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="delisted">Status</label>
                        <select id="delisted" name="delisted">
                            <option value="0">Active</option>
                            <option value="1">Delisted</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group" id="delistedDateGroup" style="display: none;">
                    <label for="delisted_date">Delisted Date</label>
                    <input type="date" id="delisted_date" name="delisted_date">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span id="submitText">Add Company</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteCompanyName"></strong>?</p>
                <p>This will mark the company as delisted and cannot be easily undone.</p>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Delete Company
                </button>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/masterlist-management.js"></script>
</body>
</html>