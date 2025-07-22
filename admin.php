<?php
/**
 * File: admin.php
 * Description: Simple administration interface for PSW 4.0
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/controllers/PortfolioBuylistController.php';
require_once __DIR__ . '/src/utils/Security.php';

if (!Auth::isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Check if user has admin privileges
$adminRoles = ['Admin', 'admin', 'Administrator', 'administrator'];
if (!isset($_SESSION['role_name']) || !in_array($_SESSION['role_name'], $adminRoles)) {
    $_SESSION['flash_error'] = 'Access denied. Admin privileges required.';
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$controller = new PortfolioBuylistController();
$successMessage = '';
$errorMessage = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_defaults':
                // Save default filter settings to a simple config file
                $defaults = [
                    'status_defaults' => $_POST['status_defaults'] ?? [],
                    'country_defaults' => $_POST['country_defaults'] ?? [],
                    'strategy_defaults' => $_POST['strategy_defaults'] ?? [],
                    'broker_defaults' => $_POST['broker_defaults'] ?? []
                ];
                
                $configPath = __DIR__ . '/config/filter_defaults.json';
                if (file_put_contents($configPath, json_encode($defaults, JSON_PRETTY_PRINT))) {
                    $successMessage = 'Default filter settings updated successfully!';
                } else {
                    $errorMessage = 'Failed to save default filter settings.';
                }
                break;
                
            default:
                $errorMessage = 'Invalid action';
        }
    }
}

// Load current defaults
$defaultsPath = __DIR__ . '/config/filter_defaults.json';
$currentDefaults = [];
if (file_exists($defaultsPath)) {
    $currentDefaults = json_decode(file_get_contents($defaultsPath), true) ?? [];
}

// Get filter options for the form
try {
    $filterOptions = $controller->getFilterOptions();
} catch (Exception $e) {
    $errorMessage = 'Error loading filter options: ' . $e->getMessage();
    $filterOptions = [];
}

// Initialize variables for template
$pageTitle = 'Administration - PSW 4.0';
$pageDescription = 'System administration and configuration';
$additionalCSS = [
    BASE_URL . '/assets/css/admin.css?v=' . time()
];
$additionalJS = [
    BASE_URL . '/assets/js/admin.js?v=' . time()
];

$user = [
    'username' => Auth::getUsername(),
    'user_id' => Auth::getUserId(),
    'role_name' => $_SESSION['role_name'] ?? 'User'
];
$csrfToken = Security::generateCSRFToken();

// Prepare content for admin page
ob_start();
?>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1><i class="fas fa-cogs"></i> Administration</h1>
                    <p>System configuration and management</p>
                </div>
                <div class="header-right">
                    <div class="user-info">
                        <i class="fas fa-user-shield"></i>
                        <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['role_name']) ?>)
                    </div>
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

        <!-- Admin Sections -->
        <div class="admin-grid">
            <!-- Default Filter Settings -->
            <div class="admin-section">
                <div class="section-header">
                    <h2><i class="fas fa-filter"></i> Default Filter Settings</h2>
                    <p>Configure which filter options should be selected by default when users first load the buylist page.</p>
                </div>
                
                <form method="POST" class="admin-form">
                    <input type="hidden" name="action" value="update_defaults">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <div class="form-grid">
                        <!-- Status Defaults -->
                        <div class="form-section">
                            <h3>Default Status Filters</h3>
                            <p class="form-help">Select which statuses should be shown by default:</p>
                            
                            <div class="select-all-controls">
                                <button type="button" onclick="selectAllCheckboxes('status_defaults')" class="btn-select-all">Select All</button>
                                <button type="button" onclick="deselectAllCheckboxes('status_defaults')" class="btn-select-all">Deselect All</button>
                            </div>
                            
                            <div class="checkbox-list" data-group="status_defaults">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="status_defaults[]" value="null" 
                                           <?= in_array('null', $currentDefaults['status_defaults'] ?? ['null']) ? 'checked' : '' ?>>
                                    <span>No Status (NULL)</span>
                                </label>
                                
                                <?php foreach ($filterOptions['statuses'] ?? [] as $status): ?>
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="status_defaults[]" value="<?= $status['id'] ?>"
                                               <?= in_array($status['id'], $currentDefaults['status_defaults'] ?? []) ? 'checked' : '' ?>>
                                        <span><?= htmlspecialchars($status['status']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Country Defaults -->
                        <div class="form-section">
                            <h3>Default Country Filters</h3>
                            <p class="form-help">Select which countries should be shown by default:</p>
                            
                            <div class="select-all-controls">
                                <button type="button" onclick="selectAllCheckboxes('country_defaults')" class="btn-select-all">Select All</button>
                                <button type="button" onclick="deselectAllCheckboxes('country_defaults')" class="btn-select-all">Deselect All</button>
                            </div>
                            
                            <div class="checkbox-list" data-group="country_defaults">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="country_defaults[]" value="null"
                                           <?= in_array('null', $currentDefaults['country_defaults'] ?? []) ? 'checked' : '' ?>>
                                    <span>No Country (NULL)</span>
                                </label>
                                
                                <?php foreach ($filterOptions['countries'] ?? [] as $country): ?>
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="country_defaults[]" value="<?= htmlspecialchars($country) ?>"
                                               <?= in_array($country, $currentDefaults['country_defaults'] ?? []) ? 'checked' : '' ?>>
                                        <span><?= htmlspecialchars($country) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Strategy Defaults -->
                        <div class="form-section">
                            <h3>Default Strategy Group Filters</h3>
                            <p class="form-help">Select which strategy groups should be shown by default:</p>
                            
                            <div class="select-all-controls">
                                <button type="button" onclick="selectAllCheckboxes('strategy_defaults')" class="btn-select-all">Select All</button>
                                <button type="button" onclick="deselectAllCheckboxes('strategy_defaults')" class="btn-select-all">Deselect All</button>
                            </div>
                            
                            <div class="checkbox-list" data-group="strategy_defaults">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="strategy_defaults[]" value="null"
                                           <?= in_array('null', $currentDefaults['strategy_defaults'] ?? []) ? 'checked' : '' ?>>
                                    <span>No Strategy Group (NULL)</span>
                                </label>
                                
                                <?php foreach ($filterOptions['strategies'] ?? [] as $strategy): ?>
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="strategy_defaults[]" value="<?= $strategy['strategy_group_id'] ?>"
                                               <?= in_array($strategy['strategy_group_id'], $currentDefaults['strategy_defaults'] ?? []) ? 'checked' : '' ?>>
                                        <span>Group <?= $strategy['strategy_group_id'] ?>: <?= htmlspecialchars($strategy['strategy_name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Broker Defaults -->
                        <div class="form-section">
                            <h3>Default Broker Filters</h3>
                            <p class="form-help">Select which brokers should be shown by default:</p>
                            
                            <div class="select-all-controls">
                                <button type="button" onclick="selectAllCheckboxes('broker_defaults')" class="btn-select-all">Select All</button>
                                <button type="button" onclick="deselectAllCheckboxes('broker_defaults')" class="btn-select-all">Deselect All</button>
                            </div>
                            
                            <div class="checkbox-list" data-group="broker_defaults">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="broker_defaults[]" value="null"
                                           <?= in_array('null', $currentDefaults['broker_defaults'] ?? []) ? 'checked' : '' ?>>
                                    <span>No Broker (NULL)</span>
                                </label>
                                
                                <?php foreach ($filterOptions['brokers'] ?? [] as $broker): ?>
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="broker_defaults[]" value="<?= $broker['broker_id'] ?>"
                                               <?= in_array($broker['broker_id'], $currentDefaults['broker_defaults'] ?? []) ? 'checked' : '' ?>>
                                        <span><?= htmlspecialchars($broker['broker_name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Default Settings
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- System Information -->
            <div class="admin-section">
                <div class="section-header">
                    <h2><i class="fas fa-info-circle"></i> System Information</h2>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Application:</span>
                        <span class="info-value"><?= APP_FULL_NAME ?> v<?= APP_VERSION ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">PHP Version:</span>
                        <span class="info-value"><?= PHP_VERSION ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Current User:</span>
                        <span class="info-value"><?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['role_name']) ?>)</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Config File:</span>
                        <span class="info-value"><?= file_exists($defaultsPath) ? 'Found' : 'Not found' ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="admin-section">
                <div class="section-header">
                    <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                </div>
                
                <div class="action-grid">
                    <a href="<?= BASE_URL ?>/buylist_management.php" class="action-card">
                        <i class="fas fa-star"></i>
                        <span>Buylist Management</span>
                    </a>
                    <a href="<?= BASE_URL ?>/masterlist_management.php" class="action-card">
                        <i class="fas fa-list"></i>
                        <span>Masterlist Management</span>
                    </a>
                    <a href="<?= BASE_URL ?>/user_management.php" class="action-card">
                        <i class="fas fa-users"></i>
                        <span>User Management</span>
                    </a>
                    <a href="<?= BASE_URL ?>/dashboard.php" class="action-card">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base.php';
?>