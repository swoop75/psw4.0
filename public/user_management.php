<?php
/**
 * File: public/user_management.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\public\user_management.php
 * Description: User management page for PSW 4.0
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/middleware/Auth.php';
require_once __DIR__ . '/../src/controllers/UserManagementController.php';
require_once __DIR__ . '/../src/utils/Logger.php';
require_once __DIR__ . '/../src/utils/Security.php';

// Check authentication
Auth::requireAuth();

// Initialize controller
$controller = new UserManagementController();
$error = '';
$success = '';
$activeTab = $_GET['tab'] ?? 'profile';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        try {
            $action = $_POST['action'] ?? '';
            
            switch ($action) {
                case 'update_profile':
                    $result = $controller->updateProfile($_POST);
                    $success = 'Profile updated successfully!';
                    $activeTab = 'profile';
                    break;
                    
                case 'change_password':
                    $result = $controller->changePassword($_POST);
                    $success = 'Password changed successfully!';
                    $activeTab = 'security';
                    break;
                    
                case 'update_preferences':
                    $result = $controller->updatePreferences($_POST);
                    $success = 'Preferences updated successfully!';
                    $activeTab = 'preferences';
                    break;
                    
                default:
                    $error = 'Invalid action.';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get user profile data
try {
    $profileData = $controller->getUserProfile();
    $user = $profileData['user'];
    $stats = $profileData['profile_stats'];
    $preferences = $profileData['preferences'];
    $activityLog = $profileData['activity_log'];
} catch (Exception $e) {
    Logger::error('User management page error: ' . $e->getMessage());
    $error = 'Failed to load profile data.';
    $user = [];
    $stats = [];
    $preferences = [];
    $activityLog = [];
}

// Page title and navigation
$pageTitle = 'User Management';
$currentPage = 'user_management';

include __DIR__ . '/../templates/header.php';
?>

<link rel="stylesheet" href="../assets/css/user-management.css">

<div class="user-management-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>
                <i class="fas fa-user-cog"></i>
                User Management
            </h1>
            <p class="page-subtitle">
                Manage your profile, security settings, and preferences
            </p>
        </div>
        <div class="header-info">
            <div class="user-badge">
                <i class="fas fa-user-circle"></i>
                <div>
                    <div class="user-name"><?php echo htmlspecialchars($user['username'] ?? 'Unknown'); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($user['role_name'] ?? 'User'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Account Statistics -->
    <div class="account-stats">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($stats['account_age_days'] ?? 0); ?> days</div>
                <div class="stat-label">Account Age</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-sign-in-alt"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($stats['login_count'] ?? 0); ?></div>
                <div class="stat-label">Total Logins</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($stats['dividend_payments_count'] ?? 0); ?></div>
                <div class="stat-label">Dividend Payments</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($stats['total_dividend_amount'] ?? 0, 0); ?> SEK</div>
                <div class="stat-label">Total Dividends</div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tabs-container">
        <div class="tab-nav">
            <button class="tab-button <?php echo $activeTab === 'profile' ? 'active' : ''; ?>" 
                    onclick="showTab('profile')">
                <i class="fas fa-user"></i> Profile Information
            </button>
            <button class="tab-button <?php echo $activeTab === 'security' ? 'active' : ''; ?>" 
                    onclick="showTab('security')">
                <i class="fas fa-shield-alt"></i> Security Settings
            </button>
            <button class="tab-button <?php echo $activeTab === 'preferences' ? 'active' : ''; ?>" 
                    onclick="showTab('preferences')">
                <i class="fas fa-cog"></i> Preferences
            </button>
            <button class="tab-button <?php echo $activeTab === 'activity' ? 'active' : ''; ?>" 
                    onclick="showTab('activity')">
                <i class="fas fa-history"></i> Activity Log
            </button>
        </div>

        <!-- Profile Information Tab -->
        <div id="profile-tab" class="tab-content <?php echo $activeTab === 'profile' ? 'active' : ''; ?>">
            <div class="form-section">
                <h2>Profile Information</h2>
                <form method="POST" class="profile-form">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" 
                                   class="form-control" readonly>
                            <small class="form-help">Username cannot be changed</small>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                   class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                                   class="form-control" maxlength="100">
                        </div>

                        <div class="form-group">
                            <label for="role">Account Role</label>
                            <input type="text" id="role" value="<?php echo htmlspecialchars($user['role_name'] ?? 'User'); ?>" 
                                   class="form-control" readonly>
                            <small class="form-help">Role is managed by administrators</small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security Settings Tab -->
        <div id="security-tab" class="tab-content <?php echo $activeTab === 'security' ? 'active' : ''; ?>">
            <div class="form-section">
                <h2>Change Password</h2>
                <form method="POST" class="security-form">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" 
                               class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" 
                               class="form-control" required minlength="8">
                        <small class="form-help">
                            Password must be at least 8 characters with uppercase, lowercase, and numbers
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="form-control" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>

                <div class="security-info">
                    <h3>Security Information</h3>
                    <div class="security-stats">
                        <div class="security-item">
                            <strong>Account Created:</strong> 
                            <?php echo date('Y-m-d', strtotime($user['created_at'] ?? 'now')); ?>
                        </div>
                        <div class="security-item">
                            <strong>Last Login:</strong> 
                            <?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?>
                        </div>
                        <div class="security-item">
                            <strong>Account Status:</strong> 
                            <span class="status <?php echo ($user['is_active'] ?? 0) ? 'active' : 'inactive'; ?>">
                                <?php echo ($user['is_active'] ?? 0) ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preferences Tab -->
        <div id="preferences-tab" class="tab-content <?php echo $activeTab === 'preferences' ? 'active' : ''; ?>">
            <div class="form-section">
                <h2>Application Preferences</h2>
                <form method="POST" class="preferences-form">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="update_preferences">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="theme">Theme</label>
                            <select id="theme" name="theme" class="form-control">
                                <option value="light" <?php echo ($preferences['theme'] ?? '') === 'light' ? 'selected' : ''; ?>>Light</option>
                                <option value="dark" <?php echo ($preferences['theme'] ?? '') === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                <option value="auto" <?php echo ($preferences['theme'] ?? '') === 'auto' ? 'selected' : ''; ?>>Auto</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="language">Language</label>
                            <select id="language" name="language" class="form-control">
                                <option value="en" <?php echo ($preferences['language'] ?? '') === 'en' ? 'selected' : ''; ?>>English</option>
                                <option value="sv" <?php echo ($preferences['language'] ?? '') === 'sv' ? 'selected' : ''; ?>>Svenska</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="currency_display">Default Currency Display</label>
                            <select id="currency_display" name="currency_display" class="form-control">
                                <option value="SEK" <?php echo ($preferences['currency_display'] ?? '') === 'SEK' ? 'selected' : ''; ?>>SEK</option>
                                <option value="USD" <?php echo ($preferences['currency_display'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD</option>
                                <option value="EUR" <?php echo ($preferences['currency_display'] ?? '') === 'EUR' ? 'selected' : ''; ?>>EUR</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="date_format">Date Format</label>
                            <select id="date_format" name="date_format" class="form-control">
                                <option value="Y-m-d" <?php echo ($preferences['date_format'] ?? '') === 'Y-m-d' ? 'selected' : ''; ?>>2024-12-31</option>
                                <option value="d/m/Y" <?php echo ($preferences['date_format'] ?? '') === 'd/m/Y' ? 'selected' : ''; ?>>31/12/2024</option>
                                <option value="m/d/Y" <?php echo ($preferences['date_format'] ?? '') === 'm/d/Y' ? 'selected' : ''; ?>>12/31/2024</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="decimal_places">Decimal Places</label>
                            <select id="decimal_places" name="decimal_places" class="form-control">
                                <option value="0" <?php echo ($preferences['decimal_places'] ?? '') === 0 ? 'selected' : ''; ?>>0</option>
                                <option value="1" <?php echo ($preferences['decimal_places'] ?? '') === 1 ? 'selected' : ''; ?>>1</option>
                                <option value="2" <?php echo ($preferences['decimal_places'] ?? '') === 2 ? 'selected' : ''; ?>>2</option>
                                <option value="3" <?php echo ($preferences['decimal_places'] ?? '') === 3 ? 'selected' : ''; ?>>3</option>
                                <option value="4" <?php echo ($preferences['decimal_places'] ?? '') === 4 ? 'selected' : ''; ?>>4</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="table_page_size">Table Page Size</label>
                            <select id="table_page_size" name="table_page_size" class="form-control">
                                <option value="25" <?php echo ($preferences['table_page_size'] ?? '') === 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo ($preferences['table_page_size'] ?? '') === 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo ($preferences['table_page_size'] ?? '') === 100 ? 'selected' : ''; ?>>100</option>
                                <option value="200" <?php echo ($preferences['table_page_size'] ?? '') === 200 ? 'selected' : ''; ?>>200</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="dashboard_refresh">Dashboard Refresh</label>
                            <select id="dashboard_refresh" name="dashboard_refresh" class="form-control">
                                <option value="30" <?php echo ($preferences['dashboard_refresh'] ?? '') === 30 ? 'selected' : ''; ?>>30 seconds</option>
                                <option value="60" <?php echo ($preferences['dashboard_refresh'] ?? '') === 60 ? 'selected' : ''; ?>>1 minute</option>
                                <option value="300" <?php echo ($preferences['dashboard_refresh'] ?? '') === 300 ? 'selected' : ''; ?>>5 minutes</option>
                                <option value="600" <?php echo ($preferences['dashboard_refresh'] ?? '') === 600 ? 'selected' : ''; ?>>10 minutes</option>
                                <option value="0" <?php echo ($preferences['dashboard_refresh'] ?? '') === 0 ? 'selected' : ''; ?>>Manual only</option>
                            </select>
                        </div>

                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="notifications_email" value="1" 
                                       <?php echo ($preferences['notifications_email'] ?? false) ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                Email Notifications
                            </label>
                            <small class="form-help">Receive email notifications for important updates</small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Preferences
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Activity Log Tab -->
        <div id="activity-tab" class="tab-content <?php echo $activeTab === 'activity' ? 'active' : ''; ?>">
            <div class="activity-section">
                <h2>Recent Activity</h2>
                
                <?php if (empty($activityLog)): ?>
                    <div class="no-activity">
                        <i class="fas fa-clock"></i>
                        <h3>No recent activity</h3>
                        <p>Your recent account activities will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($activityLog as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-<?php echo $this->getActivityIcon($activity['action_type']); ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-description">
                                        <?php echo htmlspecialchars($activity['description']); ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo date('Y-m-d H:i:s', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/user-management.js"></script>
<script>
// Set active tab on page load
document.addEventListener('DOMContentLoaded', function() {
    showTab('<?php echo $activeTab; ?>');
});
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>