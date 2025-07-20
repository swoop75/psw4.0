<?php
// c:/Users/laoan/Documents/GitHub/psw/psw4.0/templates/includes/header.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITENAME; ?></title>
    <!-- Font Awesome for Icons (as per design_document.txt) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Google Fonts (as per design_document.txt) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="<?php echo URLROOT; ?>/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <div class="logo-container">
                <!-- Placeholder for logo -->
                <img src="https://via.placeholder.com/150x50.png?text=PSW+4.0+Logo" alt="PSW 4.0 Logo" class="logo">
            </div>
            <nav class="main-nav">
                <!-- This area will be populated with the menu when the user is logged in -->
            </nav>
            <div class="user-actions">
                <a href="#" id="login-toggle">Login <i class="fas fa-caret-down"></i></a>
                <div id="login-dropdown" class="login-dropdown">
                    <form action="<?php echo URLROOT; ?>/users/login" method="post">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" name="username" id="username" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" name="password" id="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </header>
    <main class="main-content">
        <div class="container">