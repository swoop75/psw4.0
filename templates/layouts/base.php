<?php
/**
 * File: templates/layouts/base.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\templates\layouts\base.php
 * Description: Base HTML template layout for PSW 4.0
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? APP_NAME; ?></title>
    <meta name="description" content="<?php echo $pageDescription ?? 'Pengamaskinen Sverige + Worldwide - Dividend Portfolio Management'; ?>">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/improved-main.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Additional CSS -->
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.ico">
</head>
<body>
    <!-- Unified Top Navigation Bar -->
    <header class="unified-header">
        <div class="header-container">
            <a href="<?php echo BASE_URL; ?>" class="logo-header">
                <div class="logo-mini">
                    <i class="fas fa-chart-line"></i>
                </div>
                <span class="logo-text">PSW 4.0</span>
            </a>
            
            <div class="nav-links">
                <?php if (!Auth::isLoggedIn()): ?>
                    <!-- Non-logged-in navigation -->
                    <a href="<?php echo BASE_URL; ?>/philosophy.php" class="nav-link">Philosophy</a>
                    <div class="login-container">
                        <button class="login-toggle" onclick="toggleLogin()">
                            <i class="fas fa-sign-in-alt"></i>
                            Login
                        </button>
                        <!-- Login dropdown for non-logged-in users -->
                        <div class="login-dropdown" id="loginDropdown">
                            <?php if (isset($_SESSION['login_error'])): ?>
                                <div class="alert alert-error">
                                    <?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="<?php echo BASE_URL; ?>/login.php" class="login-form">
                                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                                
                                <div class="form-group">
                                    <label for="username">Username or Email</label>
                                    <input type="text" id="username" name="username" required autocomplete="username">
                                </div>
                                
                                <div class="form-group">
                                    <label for="password">Password</label>
                                    <input type="password" id="password" name="password" required autocomplete="current-password">
                                </div>
                                
                                <button type="submit" class="btn-login">
                                    <i class="fas fa-sign-in-alt"></i>
                                    Sign In
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Logged-in navigation -->
                    <div class="nav-item">
                        <a href="javascript:void(0)" class="nav-link nav-dropdown-only">
                            <i class="fas fa-cogs"></i>
                            Functions
                            <i class="fas fa-chevron-down nav-arrow"></i>
                        </a>
                        <div class="submenu">
                            <a href="<?php echo BASE_URL; ?>/dashboard.php" class="submenu-link">Dashboard</a>
                            <a href="<?php echo BASE_URL; ?>/masterlist_management.php" class="submenu-link">Masterlist Management</a>
                            <a href="<?php echo BASE_URL; ?>/buylist_management.php" class="submenu-link">Buylist Management</a>
                            <a href="<?php echo BASE_URL; ?>/user_management.php" class="submenu-link">User Management</a>
                        </div>
                    </div>
                    
                    <div class="nav-item">
                        <a href="javascript:void(0)" class="nav-link nav-dropdown-only">
                            <i class="fas fa-book"></i>
                            Rules
                            <i class="fas fa-chevron-down nav-arrow"></i>
                        </a>
                        <div class="submenu">
                            <a href="<?php echo BASE_URL; ?>/philosophy.php" class="submenu-link">Philosophy</a>
                            <a href="#" class="submenu-link">Rulebook</a>
                        </div>
                    </div>
                    
                    <div class="user-menu">
                        <button class="login-toggle" onclick="toggleUserMenu()">
                            <i class="fas fa-user"></i>
                            <?php echo Auth::getUsername(); ?>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="login-dropdown" id="userMenu">
                            <div class="user-info">
                                <p><strong><?php echo Auth::getUsername(); ?></strong></p>
                                <p class="text-muted"><?php echo $_SESSION['role_name']; ?></p>
                            </div>
                            <hr style="margin: 12px 0; border: none; border-top: 1px solid #e9ecef;">
                            <a href="<?php echo BASE_URL; ?>/logout.php" class="dropdown-link text-danger">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>


    <main class="main-container">
        <?php 
        // Display flash messages
        $flashTypes = ['success', 'error', 'warning', 'info'];
        foreach ($flashTypes as $type):
            if (isset($_SESSION["flash_{$type}"])):
        ?>
                <div class="alert alert-<?php echo $type; ?>">
                    <?php echo $_SESSION["flash_{$type}"]; unset($_SESSION["flash_{$type}"]); ?>
                </div>
        <?php 
            endif;
        endforeach;
        ?>
        
        <?php echo $content ?? ''; ?>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_FULL_NAME; ?> v<?php echo APP_VERSION; ?> | Built with PHP</p>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="<?php echo ASSETS_URL; ?>/js/improved-main.js?v=<?php echo time(); ?>"></script>
    
    <!-- Additional JavaScript -->
    <?php if (isset($additionalJS)): ?>
        <?php foreach ($additionalJS as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>