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
    <header class="header">
        <div class="header-container">
            <a href="<?php echo BASE_URL; ?>" class="logo">
                <img src="<?php echo ASSETS_URL; ?>/img/psw-logo.svg" alt="PSW Logo" onerror="this.style.display='none'">
                <span class="logo-text"><?php echo APP_FULL_NAME; ?></span>
            </a>
            
            <div class="login-container">
                <?php if (Auth::isLoggedIn()): ?>
                    <!-- Logged in user menu -->
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
                            <a href="<?php echo BASE_URL; ?>/dashboard.php" class="dropdown-link">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                            <a href="<?php echo BASE_URL; ?>/masterlist_management.php" class="dropdown-link">
                                <i class="fas fa-building"></i> Masterlist Management
                            </a>
                            <a href="<?php echo BASE_URL; ?>/buylist_management.php" class="dropdown-link">
                                <i class="fas fa-star"></i> Buylist Management
                            </a>
                            <a href="<?php echo BASE_URL; ?>/user_management.php" class="dropdown-link">
                                <i class="fas fa-user-cog"></i> User Management
                            </a>
                            <hr style="margin: 12px 0; border: none; border-top: 1px solid #e9ecef;">
                            <a href="<?php echo BASE_URL; ?>/logout.php" class="dropdown-link text-danger">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Login dropdown -->
                    <button class="login-toggle" onclick="toggleLogin()">
                        <i class="fas fa-sign-in-alt"></i>
                        Login
                        <i class="fas fa-chevron-down"></i>
                    </button>
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
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if (Auth::isLoggedIn()): ?>
        <!-- Navigation Menu for logged in users -->
        <nav class="main-nav">
            <div class="nav-container">
                <?php 
                $menu = Auth::getAccessibleMenu();
                
                // Define page mappings for menu items
                $pageMapping = [
                    'dashboard' => 'dashboard.php',
                    'portfolio' => '#', // No main portfolio page
                    'allocation' => '#', // No allocation page yet
                    'dividend_estimate' => 'dividend_estimate.php',
                    'logs' => '#', // No main logs page
                    'buying' => '#', // No main buying page
                    'rules' => '#', // No rules page yet
                    'administration' => '#' // No main admin page
                ];
                
                // Define submenu page mappings
                $submenuMapping = [
                    'portfolio' => [
                        'company_list' => 'masterlist_management.php',
                        'company_page' => 'company_detail.php'
                    ],
                    'dividend_estimate' => [
                        'overview' => 'dividend_estimate.php',
                        'monthly_overview' => 'dividend_estimate.php?view=monthly'
                    ],
                    'logs' => [
                        'dividends' => 'logs_dividends.php',
                        'trades' => '#',
                        'corporate_actions' => '#',
                        'cash_transactions' => '#',
                        'expenses' => '#'
                    ],
                    'buying' => [
                        'buylist_management' => 'buylist_management.php',
                        'new_companies' => '#'
                    ],
                    'rules' => [
                        'rulebook' => '#'
                    ],
                    'administration' => [
                        'page_management' => '#',
                        'admin_management' => '#',
                        'user_management' => 'user_management.php',
                        'masterlist_management' => 'masterlist_management.php'
                    ]
                ];
                
                foreach ($menu as $key => $item):
                    $mainUrl = $pageMapping[$key] ?? '#';
                ?>
                    <div class="nav-item">
                        <a href="<?php echo $mainUrl === '#' ? 'javascript:void(0)' : BASE_URL . '/' . $mainUrl; ?>" class="nav-link <?php echo $mainUrl === '#' ? 'nav-dropdown-only' : ''; ?>">
                            <i class="<?php echo ICONS[$key] ?? 'fas fa-circle'; ?>"></i>
                            <?php echo $item['title']; ?>
                            <?php if (isset($item['submenu']) && !empty($item['submenu'])): ?>
                                <i class="fas fa-chevron-down nav-arrow"></i>
                            <?php endif; ?>
                        </a>
                        
                        <?php if (isset($item['submenu']) && !empty($item['submenu'])): ?>
                            <div class="submenu">
                                <?php foreach ($item['submenu'] as $subKey => $subItem): 
                                    $subUrl = $submenuMapping[$key][$subKey] ?? '#';
                                ?>
                                    <a href="<?php echo $subUrl === '#' ? 'javascript:void(0)' : BASE_URL . '/' . $subUrl; ?>" class="submenu-link">
                                        <?php echo $subItem['title']; ?>
                                        <?php if ($subItem['admin_only']): ?>
                                            <span class="admin-badge">ADMIN</span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </nav>
    <?php endif; ?>

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