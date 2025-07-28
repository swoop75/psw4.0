<?php
/**
 * File: templates/header.php
 * Description: Header template for PSW 4.0 user management pages
 */

if (!isset($pageTitle)) {
    $pageTitle = 'PSW 4.0';
}

if (!isset($currentPage)) {
    $currentPage = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - PSW 4.0</title>
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Security Headers -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?php echo Security::generateCsrfToken(); ?>">
</head>
<body class="user-management-page">
    <nav class="main-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <a href="dashboard.php">
                    <img src="assets/img/psw-logo.svg" alt="PSW 4.0" class="nav-logo">
                    <span class="nav-title">PSW 4.0</span>
                </a>
            </div>
            
            <div class="nav-user">
                <div class="user-info">
                    <span class="username"><?php echo htmlspecialchars(Auth::getUsername() ?? 'Guest'); ?></span>
                    <span class="user-role"><?php echo htmlspecialchars($_SESSION['role_name'] ?? 'User'); ?></span>
                </div>
                <div class="user-actions">
                    <a href="user_management.php" class="nav-link <?php echo $currentPage === 'user_management' ? 'active' : ''; ?>">
                        <i class="fas fa-user-cog"></i> Settings
                    </a>
                    <a href="logout.php" class="nav-link logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <main class="main-content">