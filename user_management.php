<?php
/**
 * File: public/user_management.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\public\user_management.php
 * Description: User management page for PSW 4.0
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/controllers/UserManagementController.php';
require_once __DIR__ . '/src/utils/Logger.php';
require_once __DIR__ . '/src/utils/Security.php';

// Check authentication
Auth::requireAuth();

// Check if user has admin privileges
$adminRoles = ['Admin', 'admin', 'Administrator', 'administrator'];
$isAdmin = isset($_SESSION['role_name']) && in_array($_SESSION['role_name'], $adminRoles);

/**
 * Get FontAwesome icon for activity type
 * @param string $actionType The action type
 * @return string FontAwesome icon name
 */
function getActivityIcon($actionType) {
    $icons = [
        'login' => 'sign-in-alt',
        'logout' => 'sign-out-alt',
        'profile_updated' => 'user-edit',
        'password_changed' => 'key',
        'password_generated' => 'random',
        'preferences_updated' => 'cog',
        'account_created' => 'user-plus',
        'email_changed' => 'envelope',
        'role_changed' => 'user-shield',
        'data_export' => 'download',
        'settings_changed' => 'tools',
        'security_alert' => 'shield-alt'
    ];
    
    return $icons[$actionType] ?? 'info-circle';
}

// Initialize controller
$controller = new UserManagementController();
$error = '';
$success = '';
$activeTab = $_GET['tab'] ?? ($isAdmin ? 'users' : 'profile');

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
                    
                case 'generate_password':
                    $result = $controller->generateRandomPassword();
                    if ($result['success']) {
                        $success = 'New password generated: <strong>' . $result['password'] . '</strong> - Please save this password!';
                        $activeTab = 'security';
                    } else {
                        $error = $result['message'];
                    }
                    break;
                    
                case 'edit_user':
                    if (!$isAdmin) {
                        $error = 'Unauthorized access.';
                        break;
                    }
                    $result = $controller->editUser($_POST);
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                        header('Content-Type: application/json');
                        echo json_encode($result);
                        exit;
                    }
                    if ($result['success']) {
                        $success = 'User updated successfully!';
                        $activeTab = 'users';
                    } else {
                        $error = $result['message'];
                    }
                    break;
                    
                case 'toggle_user_status':
                    if (!$isAdmin) {
                        $error = 'Unauthorized access.';
                        break;
                    }
                    $result = $controller->toggleUserStatus($_POST['user_id'], $_POST['active']);
                    header('Content-Type: application/json');
                    echo json_encode($result);
                    exit;
                    
                default:
                    $error = 'Invalid action.';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get user profile data and admin data if applicable
try {
    $profileData = $controller->getUserProfile();
    $user = $profileData['user'];
    $stats = $profileData['profile_stats'];
    $preferences = $profileData['preferences'];
    $activityLog = $profileData['activity_log'];
    
    // Get admin-specific data if user is admin
    if ($isAdmin) {
        $adminData = $controller->getAdminData();
        $allUsers = $adminData['users'] ?? [];
        $adminStats = $adminData['stats'] ?? [];
    } else {
        $allUsers = [];
        $adminStats = [];
    }
} catch (Exception $e) {
    Logger::error('User management page error: ' . $e->getMessage());
    $error = 'Failed to load profile data.';
    $user = [];
    $stats = [];
    $preferences = [];
    $activityLog = [];
    $allUsers = [];
    $adminStats = [];
}

// Initialize variables for template
$pageTitle = 'User Management - PSW 4.0';
$pageDescription = 'User account management and preferences';
$additionalCSS = [
    BASE_URL . '/assets/css/improved-user-management.css?v=' . time()
];
$additionalJS = [
    BASE_URL . '/assets/js/user-management.js?v=' . time()
];

$csrfToken = Security::generateCSRFToken();

// Prepare content for user management page
ob_start();
?>

<div class="user-management-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>
                <i class="fas fa-user-cog"></i>
                User Management
            </h1>
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

    <!-- Statistics -->
    <div class="account-stats">
        <?php if ($isAdmin): ?>
            <!-- Admin Statistics -->
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($adminStats['total_users'] ?? count($allUsers)); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chart-bar"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo htmlspecialchars($adminStats['most_popular_page'] ?? 'N/A'); ?></div>
                    <div class="stat-label">Most Popular Page</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($adminStats['active_users'] ?? 0); ?></div>
                    <div class="stat-label">Active Users</div>
                </div>
            </div>
        <?php else: ?>
            <!-- Personal Statistics -->
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
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $user['last_login'] ? date('M j, Y', strtotime($user['last_login'])) : 'Never'; ?></div>
                    <div class="stat-label">Last Login</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tab Navigation -->
    <div class="tabs-container">
        <div class="tab-nav">
            <?php if ($isAdmin): ?>
                <button class="tab-button <?php echo $activeTab === 'users' ? 'active' : ''; ?>" 
                        onclick="showTab('users')">
                    <i class="fas fa-users"></i> All Users
                </button>
            <?php endif; ?>
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

        <?php if ($isAdmin): ?>
        <!-- All Users Tab (Admin Only) -->
        <div id="users-tab" class="tab-content <?php echo $activeTab === 'users' ? 'active' : ''; ?>">
            <div class="users-section">
                <div class="section-header">
                    <h2>User Management</h2>
                    <button class="btn btn-primary" onclick="showAddUserModal()">
                        <i class="fas fa-user-plus"></i> Add New User
                    </button>
                </div>
                
                <div class="users-table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsers as $userItem): ?>
                                <tr class="user-row" data-user-id="<?php echo $userItem['user_id']; ?>">
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <i class="fas fa-user-circle"></i>
                                            </div>
                                            <div class="user-details">
                                                <div class="username"><?php echo htmlspecialchars($userItem['username']); ?></div>
                                                <div class="full-name"><?php echo htmlspecialchars($userItem['full_name'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($userItem['email'] ?? ''); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo strtolower($userItem['role_name'] ?? 'user'); ?>">
                                            <?php echo htmlspecialchars($userItem['role_name'] ?? 'User'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-indicator status-<?php echo ($userItem['active'] ?? 1) ? 'active' : 'inactive'; ?>">
                                            <span class="status-dot"></span>
                                            <?php echo ($userItem['active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="last-activity">
                                        <?php echo $userItem['last_login'] ? date('M j, Y', strtotime($userItem['last_login'])) : 'Never'; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($userItem['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" onclick="editUser(<?php echo $userItem['user_id']; ?>)" title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon" onclick="toggleUserStatus(<?php echo $userItem['user_id']; ?>, <?php echo ($userItem['active'] ?? 1) ? 'false' : 'true'; ?>)" 
                                                    title="<?php echo ($userItem['active'] ?? 1) ? 'Deactivate' : 'Activate'; ?> User">
                                                <i class="fas fa-<?php echo ($userItem['active'] ?? 1) ? 'user-slash' : 'user-check'; ?>"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

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

                <!-- Generate Password Form -->
                <form method="POST" class="generate-password-form" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="generate_password">
                    
                    <h3>Generate Random Password</h3>
                    <p>Click the button below to generate a secure random password. The new password will be displayed once and should be saved immediately.</p>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-secondary" onclick="return confirm('This will generate a new random password and immediately update your account. Are you sure?')">
                            <i class="fas fa-random"></i> Generate Random Password
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
                                    <i class="fas fa-<?php echo getActivityIcon($activity['action_type']); ?>"></i>
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

<script>
// Set active tab on page load
document.addEventListener('DOMContentLoaded', function() {
    showTab('<?php echo $activeTab; ?>');
});
</script>

<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base.php';
?>