<?php
/**
 * File: templates/layouts/base-redesign.php 
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\templates\layouts\base-redesign.php
 * Description: New redesigned base HTML template layout for PSW 4.0
 * Features: Full-screen layout, collapsible sidebar, dark/light theme support
 */

// Get user's theme preference (default to light)
$userTheme = $_SESSION['user_theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $userTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? APP_NAME; ?></title>
    <meta name="description" content="<?php echo $pageDescription ?? 'Pengamaskinen Sverige + Worldwide - Dividend Portfolio Management'; ?>">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/psw-redesign.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Additional CSS -->
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/public/assets/img/psw-logo.png">
    <link rel="shortcut icon" type="image/png" href="<?php echo BASE_URL; ?>/public/assets/img/psw-logo.png">
    
    <!-- Prevent theme flickering -->
    <script>
        (function() {
            // Priority: localStorage > session > default
            const sessionTheme = '<?php echo $userTheme; ?>';
            const localTheme = localStorage.getItem('psw-theme');
            const finalTheme = localTheme || sessionTheme;
            
            document.documentElement.setAttribute('data-theme', finalTheme);
            
            // Sync localStorage with session if they differ
            if (localTheme !== sessionTheme && sessionTheme !== 'light') {
                localStorage.setItem('psw-theme', sessionTheme);
            } else if (localTheme && localTheme !== sessionTheme) {
                // Update session to match localStorage
                fetch('/update_theme.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ theme: localTheme })
                }).catch(e => console.warn('Theme sync failed:', e));
            }
        })();
    </script>
</head>
<body class="psw-theme-transition">
    <div class="psw-app">
        <?php if (Auth::isLoggedIn()): ?>
            <!-- Sidebar Navigation -->
            <nav class="psw-sidebar">
                <div class="psw-sidebar-content">
                    <!-- Logo Section -->
                    <div class="psw-logo">
                        <div class="psw-logo-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <span class="psw-logo-text">PSW 4.0</span>
                    </div>
                    
                    <!-- Navigation Menu -->
                    <div class="psw-nav">
                        <!-- Dashboard -->
                        <div class="psw-nav-section">
                            <div class="psw-nav-item">
                                <a href="<?php echo BASE_URL; ?>/dashboard.php" class="psw-nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                                    <div class="psw-nav-icon">
                                        <i class="fas fa-tachometer-alt"></i>
                                    </div>
                                    <span class="psw-nav-text">Dashboard</span>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Administration -->
                        <div class="psw-nav-section">
                            <div class="psw-nav-item" id="nav-administration">
                                <a href="javascript:void(0)" class="psw-nav-link">
                                    <div class="psw-nav-icon">
                                        <i class="fas fa-cogs"></i>
                                    </div>
                                    <span class="psw-nav-text">Administration</span>
                                    <i class="fas fa-chevron-down psw-nav-expand"></i>
                                </a>
                                <div class="psw-nav-submenu">
                                    <a href="<?php echo BASE_URL; ?>/masterlist_management.php" class="psw-nav-submenu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'masterlist_management.php') ? 'active' : ''; ?>">
                                        <i class="fas fa-building"></i> Masterlist
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/buylist_management.php" class="psw-nav-submenu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'buylist_management.php' || basename($_SERVER['PHP_SELF']) == 'new_companies_management.php') ? 'active' : ''; ?>">
                                        <i class="fas fa-plus-circle"></i> New Companies
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/user_management.php" class="psw-nav-submenu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'user_management.php') ? 'active' : ''; ?>">
                                        <i class="fas fa-users"></i> User Management
                                    </a>
                                    <?php 
                                    $adminRoles = ['Admin', 'admin', 'Administrator', 'administrator'];
                                    if (isset($_SESSION['role_name']) && in_array($_SESSION['role_name'], $adminRoles)): 
                                    ?>
                                        <a href="<?php echo BASE_URL; ?>/admin.php" class="psw-nav-submenu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin.php') ? 'active' : ''; ?>">
                                            <i class="fas fa-shield-alt"></i> Site Management
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Logs -->
                        <div class="psw-nav-section">
                            <div class="psw-nav-item" id="nav-logs">
                                <a href="javascript:void(0)" class="psw-nav-link">
                                    <div class="psw-nav-icon">
                                        <i class="fas fa-chart-bar"></i>
                                    </div>
                                    <span class="psw-nav-text">Logs</span>
                                    <i class="fas fa-chevron-down psw-nav-expand"></i>
                                </a>
                                <div class="psw-nav-submenu">
                                    <a href="<?php echo BASE_URL; ?>/dividend_logs.php" class="psw-nav-submenu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dividend_logs.php') ? 'active' : ''; ?>">
                                        <i class="fas fa-coins"></i> Dividends
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Rules -->
                        <div class="psw-nav-section">
                            <div class="psw-nav-item" id="nav-rules">
                                <a href="javascript:void(0)" class="psw-nav-link">
                                    <div class="psw-nav-icon">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <span class="psw-nav-text">Rules</span>
                                    <i class="fas fa-chevron-down psw-nav-expand"></i>
                                </a>
                                <div class="psw-nav-submenu">
                                    <a href="<?php echo BASE_URL; ?>/philosophy.php" class="psw-nav-submenu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'philosophy.php') ? 'active' : ''; ?>">
                                        <i class="fas fa-lightbulb"></i> Philosophy
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- User Menu Area -->
            <div class="psw-user-area">
                <div class="psw-user-menu" id="userMenu">
                    <button class="psw-user-button" onclick="toggleUserMenu()">
                        <div class="psw-user-avatar">
                            <?php echo strtoupper(substr(Auth::getUsername(), 0, 1)); ?>
                        </div>
                        <span class="psw-user-name"><?php echo Auth::getUsername(); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    
                    <div class="psw-user-dropdown">
                        <a href="<?php echo BASE_URL; ?>/user_settings.php" class="psw-user-dropdown-item">
                            <i class="fas fa-user-cog psw-user-dropdown-icon"></i>
                            User Settings
                        </a>
                        <a href="<?php echo BASE_URL; ?>/theme_settings.php" class="psw-user-dropdown-item">
                            <i class="fas fa-palette psw-user-dropdown-icon"></i>
                            Theme Settings
                        </a>
                        <div style="border-top: 1px solid var(--border-primary); margin: var(--spacing-2) 0;"></div>
                        <a href="<?php echo BASE_URL; ?>/logout.php" class="psw-user-dropdown-item danger">
                            <i class="fas fa-sign-out-alt psw-user-dropdown-icon"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <main class="psw-main">
                <div class="psw-content">
                    <?php 
                    // Display flash messages
                    $flashTypes = ['success', 'error', 'warning', 'info'];
                    foreach ($flashTypes as $type):
                        if (isset($_SESSION["flash_{$type}"])):
                    ?>
                            <div class="psw-alert psw-alert-<?php echo $type; ?>">
                                <?php echo $_SESSION["flash_{$type}"]; unset($_SESSION["flash_{$type}"]); ?>
                            </div>
                    <?php 
                        endif;
                    endforeach;
                    ?>
                    
                    <?php echo $content ?? ''; ?>
                </div>
            </main>
        <?php else: ?>
            <!-- Non-logged-in Layout (Landing Page) -->
            <div class="psw-landing">
                <!-- User Menu for Login -->
                <div class="psw-user-area">
                    <div class="psw-user-menu" id="loginMenu">
                        <button class="psw-user-button" onclick="toggleLoginMenu()">
                            <div class="psw-user-avatar">
                                <i class="fas fa-sign-in-alt"></i>
                            </div>
                            <span class="psw-user-name">Login</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        
                        <div class="psw-user-dropdown" style="min-width: 280px;">
                            <div style="padding: var(--spacing-4); border-bottom: 1px solid var(--border-primary);">
                                <h3 style="margin: 0; font-size: var(--font-size-lg); color: var(--text-primary);">Sign In</h3>
                            </div>
                            
                            <?php if (isset($_SESSION['login_error'])): ?>
                                <div class="psw-alert psw-alert-error" style="margin: var(--spacing-3);">
                                    <?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="<?php echo BASE_URL; ?>/login.php" style="padding: var(--spacing-4);">
                                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                                
                                <div class="psw-form-group">
                                    <label for="username" class="psw-form-label">Username or Email</label>
                                    <input type="text" id="username" name="username" class="psw-form-input" required autocomplete="username">
                                </div>
                                
                                <div class="psw-form-group">
                                    <label for="password" class="psw-form-label">Password</label>
                                    <input type="password" id="password" name="password" class="psw-form-input" required autocomplete="current-password">
                                </div>
                                
                                <button type="submit" class="psw-btn psw-btn-primary" style="width: 100%;">
                                    <i class="fas fa-sign-in-alt psw-btn-icon"></i>
                                    Sign In
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Landing Page Content -->
                <div class="psw-content">
                    <?php echo $content ?? ''; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript -->
    <script src="<?php echo ASSETS_URL; ?>/js/psw-redesign.js?v=<?php echo time(); ?>"></script>
    
    <!-- Additional JavaScript -->
    <?php if (isset($additionalJS)): ?>
        <?php foreach ($additionalJS as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>