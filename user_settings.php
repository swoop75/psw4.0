<?php
/**
 * File: user_settings.php
 * Description: User settings page for PSW 4.0 - theme preferences, profile settings
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/utils/Security.php';
require_once __DIR__ . '/src/utils/Logger.php';

// Require authentication
Auth::requireAuth();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }
        
        $success_messages = [];
        
        // Handle theme change
        if (isset($_POST['theme'])) {
            $theme = $_POST['theme'];
            if (in_array($theme, ['light', 'dark'])) {
                $_SESSION['user_theme'] = $theme;
                $success_messages[] = 'Theme preference updated successfully';
                
                // TODO: Update database when user preferences table is available
                Logger::logUserAction('theme_changed', "User changed theme to: $theme");
            }
        }
        
        // Handle profile updates (when user profile functionality is added)
        // if (isset($_POST['update_profile'])) {
        //     // Update user profile logic here
        // }
        
        if (!empty($success_messages)) {
            $_SESSION['flash_success'] = implode('. ', $success_messages);
        }
        
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
        
    } catch (Exception $e) {
        Logger::error('User settings error: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'An error occurred while updating your settings';
        
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Get current user theme
$currentTheme = $_SESSION['user_theme'] ?? 'light';

// Set page variables
$pageTitle = 'User Settings - ' . APP_NAME;
$pageDescription = 'Manage your account settings and preferences';

try {
    // Prepare content
    ob_start();
    ?>
    
    <div class="psw-settings-page">
        <!-- Page Header -->
        <div class="psw-card psw-mb-6">
            <div class="psw-card-header">
                <h1 class="psw-card-title">
                    <i class="fas fa-user-cog psw-card-title-icon"></i>
                    User Settings
                </h1>
                <p class="psw-card-subtitle">Manage your account preferences and settings</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: var(--spacing-6);">
            <!-- Theme Preferences -->
            <div class="psw-card">
                <div class="psw-card-header">
                    <h2 class="psw-card-title">
                        <i class="fas fa-palette psw-card-title-icon"></i>
                        Theme Preferences
                    </h2>
                    <p class="psw-card-subtitle">Choose your preferred interface theme</p>
                </div>
                <div class="psw-card-content">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div style="display: flex; flex-direction: column; gap: var(--spacing-4);">
                            <!-- Light Theme Option -->
                            <label style="display: flex; align-items: center; gap: var(--spacing-3); padding: var(--spacing-4); background-color: var(--bg-secondary); border-radius: var(--radius-lg); cursor: pointer; border: 2px solid <?php echo $currentTheme === 'light' ? 'var(--primary-accent)' : 'var(--border-primary)'; ?>;">
                                <input type="radio" name="theme" value="light" <?php echo $currentTheme === 'light' ? 'checked' : ''; ?> style="margin: 0;">
                                <div style="display: flex; align-items: center; gap: var(--spacing-3); flex: 1;">
                                    <div style="width: 48px; height: 32px; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border: 1px solid #e2e8f0; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-sun" style="color: #f59e0b; font-size: var(--font-size-lg);"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: var(--text-primary);">Light Theme</div>
                                        <div style="font-size: var(--font-size-sm); color: var(--text-muted);">Clean and bright interface</div>
                                    </div>
                                </div>
                                <?php if ($currentTheme === 'light'): ?>
                                    <i class="fas fa-check-circle" style="color: var(--primary-accent); font-size: var(--font-size-lg);"></i>
                                <?php endif; ?>
                            </label>

                            <!-- Dark Theme Option -->
                            <label style="display: flex; align-items: center; gap: var(--spacing-3); padding: var(--spacing-4); background-color: var(--bg-secondary); border-radius: var(--radius-lg); cursor: pointer; border: 2px solid <?php echo $currentTheme === 'dark' ? 'var(--primary-accent)' : 'var(--border-primary)'; ?>;">
                                <input type="radio" name="theme" value="dark" <?php echo $currentTheme === 'dark' ? 'checked' : ''; ?> style="margin: 0;">
                                <div style="display: flex; align-items: center; gap: var(--spacing-3); flex: 1;">
                                    <div style="width: 48px; height: 32px; background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border: 1px solid #334155; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-moon" style="color: #7c3aed; font-size: var(--font-size-lg);"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: var(--text-primary);">Dark Theme</div>
                                        <div style="font-size: var(--font-size-sm); color: var(--text-muted);">Easy on the eyes for extended use</div>
                                    </div>
                                </div>
                                <?php if ($currentTheme === 'dark'): ?>
                                    <i class="fas fa-check-circle" style="color: var(--primary-accent); font-size: var(--font-size-lg);"></i>
                                <?php endif; ?>
                            </label>
                        </div>

                        <div style="margin-top: var(--spacing-6); text-align: right;">
                            <button type="submit" class="psw-btn psw-btn-primary">
                                <i class="fas fa-save psw-btn-icon"></i>
                                Save Theme Preference
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Account Information -->
            <div class="psw-card">
                <div class="psw-card-header">
                    <h2 class="psw-card-title">
                        <i class="fas fa-user psw-card-title-icon"></i>
                        Account Information
                    </h2>
                    <p class="psw-card-subtitle">Your account details and status</p>
                </div>
                <div class="psw-card-content">
                    <div style="display: flex; flex-direction: column; gap: var(--spacing-4);">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--spacing-3); background-color: var(--bg-secondary); border-radius: var(--radius-md);">
                            <span style="color: var(--text-muted); font-size: var(--font-size-sm);">Username:</span>
                            <span style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars(Auth::getUsername()); ?></span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--spacing-3); background-color: var(--bg-secondary); border-radius: var(--radius-md);">
                            <span style="color: var(--text-muted); font-size: var(--font-size-sm);">User ID:</span>
                            <span style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars(Auth::getUserId()); ?></span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--spacing-3); background-color: var(--bg-secondary); border-radius: var(--radius-md);">
                            <span style="color: var(--text-muted); font-size: var(--font-size-sm);">Role:</span>
                            <span style="font-weight: 600; color: var(--primary-accent);"><?php echo htmlspecialchars($_SESSION['role_name'] ?? 'User'); ?></span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--spacing-3); background-color: var(--bg-secondary); border-radius: var(--radius-md);">
                            <span style="color: var(--text-muted); font-size: var(--font-size-sm);">Current Theme:</span>
                            <span style="font-weight: 600; color: var(--text-primary); text-transform: capitalize;"><?php echo $currentTheme; ?></span>
                        </div>
                    </div>
                    
                    <div style="margin-top: var(--spacing-6); padding-top: var(--spacing-4); border-top: 1px solid var(--border-primary);">
                        <p style="color: var(--text-muted); font-size: var(--font-size-sm); text-align: center;">
                            <i class="fas fa-info-circle"></i>
                            Additional profile settings will be available in future updates
                        </p>
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
                <div style="display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="psw-btn psw-btn-secondary">
                        <i class="fas fa-tachometer-alt psw-btn-icon"></i>
                        Back to Dashboard
                    </a>
                    
                    <a href="<?php echo BASE_URL; ?>/logout.php" class="psw-btn psw-btn-secondary" style="color: var(--error-color); border-color: var(--error-color);">
                        <i class="fas fa-sign-out-alt psw-btn-icon"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Auto-submit theme form when radio button is clicked
    document.querySelectorAll('input[name="theme"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                const selectedTheme = this.value;
                
                // Update theme immediately
                document.documentElement.setAttribute('data-theme', selectedTheme);
                localStorage.setItem('psw-theme', selectedTheme);
                
                // Update server via AJAX instead of form submission
                fetch('/update_theme.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ theme: selectedTheme })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        if (window.pswApp) {
                            window.pswApp.showNotification('Theme preference updated successfully', 'success');
                        }
                        console.log('Theme updated successfully:', selectedTheme);
                    } else {
                        console.error('Theme update failed:', data);
                    }
                })
                .catch(error => {
                    console.error('Theme update error:', error);
                    // Revert if failed
                    const currentTheme = '<?php echo $currentTheme; ?>';
                    document.documentElement.setAttribute('data-theme', currentTheme);
                    localStorage.setItem('psw-theme', currentTheme);
                });
            }
        });
    });
    </script>

    <?php
    $content = ob_get_clean();
    
    // Include redesigned base layout
    include __DIR__ . '/templates/layouts/base-redesign.php';
    
} catch (Exception $e) {
    Logger::error('User settings page error: ' . $e->getMessage());
    
    $pageTitle = 'Settings Error - ' . APP_NAME;
    $content = '
        <div class="psw-card">
            <div class="psw-card-content" style="text-align: center; padding: var(--spacing-8);">
                <i class="fas fa-exclamation-triangle" style="font-size: var(--font-size-4xl); color: var(--error-color); margin-bottom: var(--spacing-4);"></i>
                <h1 style="color: var(--text-primary); margin-bottom: var(--spacing-4);">Settings Error</h1>
                <p style="color: var(--text-secondary); margin-bottom: var(--spacing-2);">We apologize, but there was an error loading your settings.</p>
                <p style="color: var(--text-muted);">Please try refreshing the page or contact support if the problem persists.</p>
            </div>
        </div>
    ';
    
    if (APP_DEBUG) {
        $content .= '
            <div class="psw-alert psw-alert-error psw-mb-4">
                <strong>Debug:</strong> ' . htmlspecialchars($e->getMessage()) . '
            </div>
        ';
    }
    
    include __DIR__ . '/templates/layouts/base-redesign.php';
}
?>