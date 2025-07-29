<?php
/**
 * File: admin.php
 * Description: Simple administration interface for PSW 4.0
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/controllers/NewCompaniesController.php';
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

$controller = new NewCompaniesController();
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
    <div class="psw-card psw-mb-6">
        <div class="psw-card-header">
            <h1 class="psw-card-title">
                <i class="fas fa-cogs psw-card-title-icon"></i>
                Administration
            </h1>
            <p class="psw-card-subtitle">System configuration and management</p>
        </div>
        <div style="padding: 0 var(--spacing-6) var(--spacing-4) var(--spacing-6); display: flex; align-items: center; color: var(--text-secondary); font-size: var(--font-size-sm);">
            <i class="fas fa-user-shield" style="margin-right: var(--spacing-2);"></i>
            <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['role_name']) ?>)
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

    <!-- Admin Sections -->
    <div style="display: grid; grid-template-columns: 1fr; gap: var(--spacing-6);">
        <!-- Default Filter Settings -->
        <div class="psw-card">
            <div class="psw-card-header">
                <h2 class="psw-card-title">
                    <i class="fas fa-filter psw-card-title-icon"></i>
                    Default Filter Settings
                </h2>
                <p class="psw-card-subtitle">Configure which filter options should be selected by default when users first load the buylist page.</p>
            </div>
            <div class="psw-card-content">
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_defaults">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-6);">
                        <!-- Status Defaults -->
                        <div>
                            <h3 style="font-size: var(--font-size-lg); font-weight: 600; color: var(--text-primary); margin-bottom: var(--spacing-2);">Default Status Filters</h3>
                            <p style="color: var(--text-secondary); font-size: var(--font-size-sm); margin-bottom: var(--spacing-4);">Select which statuses should be shown by default:</p>
                            
                            <div style="display: flex; gap: var(--spacing-2); margin-bottom: var(--spacing-3);">
                                <button type="button" onclick="selectAllCheckboxes('status_defaults')" class="psw-btn psw-btn-secondary" style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">Select All</button>
                                <button type="button" onclick="deselectAllCheckboxes('status_defaults')" class="psw-btn psw-btn-secondary" style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">Deselect All</button>
                            </div>
                            
                            <div data-group="status_defaults" style="display: flex; flex-direction: column; gap: var(--spacing-2);">
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" name="status_defaults[]" value="null" 
                                           <?= in_array('null', $currentDefaults['status_defaults'] ?? ['null']) ? 'checked' : '' ?>
                                           style="margin-right: var(--spacing-2);">
                                    <span style="color: var(--text-primary); font-size: var(--font-size-sm);">not bought</span>
                                </label>
                                
                                <?php foreach ($filterOptions['statuses'] ?? [] as $status): ?>
                                    <label style="display: flex; align-items: center; cursor: pointer;">
                                        <input type="checkbox" name="status_defaults[]" value="<?= $status['id'] ?>"
                                               <?= in_array($status['id'], $currentDefaults['status_defaults'] ?? []) ? 'checked' : '' ?>
                                               style="margin-right: var(--spacing-2);">
                                        <span style="color: var(--text-primary); font-size: var(--font-size-sm);"><?= htmlspecialchars($status['status']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Country Defaults -->
                        <div>
                            <h3 style="font-size: var(--font-size-lg); font-weight: 600; color: var(--text-primary); margin-bottom: var(--spacing-2);">Default Country Filters</h3>
                            <p style="color: var(--text-secondary); font-size: var(--font-size-sm); margin-bottom: var(--spacing-4);">Select which countries should be shown by default:</p>
                            
                            <div style="display: flex; gap: var(--spacing-2); margin-bottom: var(--spacing-3);">
                                <button type="button" onclick="selectAllCheckboxes('country_defaults')" class="psw-btn psw-btn-secondary" style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">Select All</button>
                                <button type="button" onclick="deselectAllCheckboxes('country_defaults')" class="psw-btn psw-btn-secondary" style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">Deselect All</button>
                            </div>
                            
                            <div data-group="country_defaults" style="display: flex; flex-direction: column; gap: var(--spacing-2);">
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" name="country_defaults[]" value="null"
                                           <?= in_array('null', $currentDefaults['country_defaults'] ?? []) ? 'checked' : '' ?>
                                           style="margin-right: var(--spacing-2);">
                                    <span style="color: var(--text-primary); font-size: var(--font-size-sm);">No Country (NULL)</span>
                                </label>
                                
                                <?php foreach ($filterOptions['countries'] ?? [] as $country): ?>
                                    <label style="display: flex; align-items: center; cursor: pointer;">
                                        <input type="checkbox" name="country_defaults[]" value="<?= htmlspecialchars($country) ?>"
                                               <?= in_array($country, $currentDefaults['country_defaults'] ?? []) ? 'checked' : '' ?>
                                               style="margin-right: var(--spacing-2);">
                                        <span style="color: var(--text-primary); font-size: var(--font-size-sm);"><?= htmlspecialchars($country) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Strategy Defaults -->
                        <div>
                            <h3 style="font-size: var(--font-size-lg); font-weight: 600; color: var(--text-primary); margin-bottom: var(--spacing-2);">Default Strategy Group Filters</h3>
                            <p style="color: var(--text-secondary); font-size: var(--font-size-sm); margin-bottom: var(--spacing-4);">Select which strategy groups should be shown by default:</p>
                            
                            <div style="display: flex; gap: var(--spacing-2); margin-bottom: var(--spacing-3);">
                                <button type="button" onclick="selectAllCheckboxes('strategy_defaults')" class="psw-btn psw-btn-secondary" style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">Select All</button>
                                <button type="button" onclick="deselectAllCheckboxes('strategy_defaults')" class="psw-btn psw-btn-secondary" style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">Deselect All</button>
                            </div>
                            
                            <div data-group="strategy_defaults" style="display: flex; flex-direction: column; gap: var(--spacing-2);">
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" name="strategy_defaults[]" value="null"
                                           <?= in_array('null', $currentDefaults['strategy_defaults'] ?? []) ? 'checked' : '' ?>
                                           style="margin-right: var(--spacing-2);">
                                    <span style="color: var(--text-primary); font-size: var(--font-size-sm);">No Strategy Group (NULL)</span>
                                </label>
                                
                                <?php foreach ($filterOptions['strategies'] ?? [] as $strategy): ?>
                                    <label style="display: flex; align-items: center; cursor: pointer;">
                                        <input type="checkbox" name="strategy_defaults[]" value="<?= $strategy['strategy_group_id'] ?>"
                                               <?= in_array($strategy['strategy_group_id'], $currentDefaults['strategy_defaults'] ?? []) ? 'checked' : '' ?>
                                               style="margin-right: var(--spacing-2);">
                                        <span style="color: var(--text-primary); font-size: var(--font-size-sm);">Group <?= $strategy['strategy_group_id'] ?>: <?= htmlspecialchars($strategy['strategy_name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Broker Defaults -->
                        <div>
                            <h3 style="font-size: var(--font-size-lg); font-weight: 600; color: var(--text-primary); margin-bottom: var(--spacing-2);">Default Broker Filters</h3>
                            <p style="color: var(--text-secondary); font-size: var(--font-size-sm); margin-bottom: var(--spacing-4);">Select which brokers should be shown by default:</p>
                            
                            <div style="display: flex; gap: var(--spacing-2); margin-bottom: var(--spacing-3);">
                                <button type="button" onclick="selectAllCheckboxes('broker_defaults')" class="psw-btn psw-btn-secondary" style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">Select All</button>
                                <button type="button" onclick="deselectAllCheckboxes('broker_defaults')" class="psw-btn psw-btn-secondary" style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">Deselect All</button>
                            </div>
                            
                            <div data-group="broker_defaults" style="display: flex; flex-direction: column; gap: var(--spacing-2);">
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" name="broker_defaults[]" value="null"
                                           <?= in_array('null', $currentDefaults['broker_defaults'] ?? []) ? 'checked' : '' ?>
                                           style="margin-right: var(--spacing-2);">
                                    <span style="color: var(--text-primary); font-size: var(--font-size-sm);">No Broker (NULL)</span>
                                </label>
                                
                                <?php foreach ($filterOptions['brokers'] ?? [] as $broker): ?>
                                    <label style="display: flex; align-items: center; cursor: pointer;">
                                        <input type="checkbox" name="broker_defaults[]" value="<?= $broker['broker_id'] ?>"
                                               <?= in_array($broker['broker_id'], $currentDefaults['broker_defaults'] ?? []) ? 'checked' : '' ?>
                                               style="margin-right: var(--spacing-2);">
                                        <span style="color: var(--text-primary); font-size: var(--font-size-sm);"><?= htmlspecialchars($broker['broker_name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: var(--spacing-6); padding-top: var(--spacing-4); border-top: 1px solid var(--border-primary);">
                        <button type="submit" class="psw-btn psw-btn-primary">
                            <i class="fas fa-save psw-btn-icon"></i> Save Default Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- System Information -->
        <div class="psw-card">
            <div class="psw-card-header">
                <h2 class="psw-card-title">
                    <i class="fas fa-info-circle psw-card-title-icon"></i>
                    System Information
                </h2>
            </div>
            <div class="psw-card-content">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-4);">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--spacing-3); background: var(--bg-secondary); border-radius: var(--radius-md);">
                        <span style="font-weight: 500; color: var(--text-secondary);">Application:</span>
                        <span style="color: var(--text-primary);"><?= APP_FULL_NAME ?> v<?= APP_VERSION ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--spacing-3); background: var(--bg-secondary); border-radius: var(--radius-md);">
                        <span style="font-weight: 500; color: var(--text-secondary);">PHP Version:</span>
                        <span style="color: var(--text-primary);"><?= PHP_VERSION ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--spacing-3); background: var(--bg-secondary); border-radius: var(--radius-md);">
                        <span style="font-weight: 500; color: var(--text-secondary);">Current User:</span>
                        <span style="color: var(--text-primary);"><?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['role_name']) ?>)</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--spacing-3); background: var(--bg-secondary); border-radius: var(--radius-md);">
                        <span style="font-weight: 500; color: var(--text-secondary);">Config File:</span>
                        <span style="color: var(--text-primary);"><?= file_exists($defaultsPath) ? 'Found' : 'Not found' ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="psw-card">
            <div class="psw-card-header">
                <h2 class="psw-card-title">
                    <i class="fas fa-bolt psw-card-title-icon"></i>
                    Quick Actions
                </h2>
            </div>
            <div class="psw-card-content">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-4);">
                    <a href="<?= BASE_URL ?>/buylist_management.php" style="display: flex; flex-direction: column; align-items: center; padding: var(--spacing-6); background: var(--bg-secondary); border-radius: var(--radius-lg); text-decoration: none; color: var(--text-primary); transition: all var(--transition-fast);" onmouseover="this.style.background='var(--primary-accent-light)'; this.style.color='var(--primary-accent)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.background='var(--bg-secondary)'; this.style.color='var(--text-primary)'; this.style.transform='translateY(0)';">
                        <i class="fas fa-star" style="font-size: var(--font-size-2xl); margin-bottom: var(--spacing-3);"></i>
                        <span style="font-weight: 500; text-align: center;">New Companies Management</span>
                    </a>
                    <a href="<?= BASE_URL ?>/masterlist_management.php" style="display: flex; flex-direction: column; align-items: center; padding: var(--spacing-6); background: var(--bg-secondary); border-radius: var(--radius-lg); text-decoration: none; color: var(--text-primary); transition: all var(--transition-fast);" onmouseover="this.style.background='var(--primary-accent-light)'; this.style.color='var(--primary-accent)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.background='var(--bg-secondary)'; this.style.color='var(--text-primary)'; this.style.transform='translateY(0)';">
                        <i class="fas fa-list" style="font-size: var(--font-size-2xl); margin-bottom: var(--spacing-3);"></i>
                        <span style="font-weight: 500; text-align: center;">Masterlist Management</span>
                    </a>
                    <a href="<?= BASE_URL ?>/user_management.php" style="display: flex; flex-direction: column; align-items: center; padding: var(--spacing-6); background: var(--bg-secondary); border-radius: var(--radius-lg); text-decoration: none; color: var(--text-primary); transition: all var(--transition-fast);" onmouseover="this.style.background='var(--primary-accent-light)'; this.style.color='var(--primary-accent)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.background='var(--bg-secondary)'; this.style.color='var(--text-primary)'; this.style.transform='translateY(0)';">
                        <i class="fas fa-users" style="font-size: var(--font-size-2xl); margin-bottom: var(--spacing-3);"></i>
                        <span style="font-weight: 500; text-align: center;">User Management</span>
                    </a>
                    <a href="<?= BASE_URL ?>/dashboard.php" style="display: flex; flex-direction: column; align-items: center; padding: var(--spacing-6); background: var(--bg-secondary); border-radius: var(--radius-lg); text-decoration: none; color: var(--text-primary); transition: all var(--transition-fast);" onmouseover="this.style.background='var(--primary-accent-light)'; this.style.color='var(--primary-accent)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.background='var(--bg-secondary)'; this.style.color='var(--text-primary)'; this.style.transform='translateY(0)';">
                        <i class="fas fa-tachometer-alt" style="font-size: var(--font-size-2xl); margin-bottom: var(--spacing-3);"></i>
                        <span style="font-weight: 500; text-align: center;">Dashboard</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base-redesign.php';
?>