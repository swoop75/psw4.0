<?php
/**
 * File: templates/pages/landing.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\templates\pages\landing.php
 * Description: Beautiful landing page for PSW 4.0
 */
?>

<div class="hero-section">
    <!-- Logo -->
    <div class="hero-logo">
        <div class="logo-circle">
            <i class="fas fa-chart-line"></i>
        </div>
    </div>
    
    <!-- Main Headline -->
    <h1 class="hero-title">
        <span class="title-main">Pengemaskinen</span>
        <span class="title-location">Sverige + Worldwide</span>
    </h1>
    
    <!-- Feature List with Beautiful Icons -->
    <div class="features-container">
        <div class="feature-row">
            <div class="feature-icon-circle">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="feature-text">
                <h3>Portfolio Tracking & Analytics</h3>
                <p>Complete overview of your dividend investments with detailed performance metrics</p>
            </div>
        </div>
        
        <div class="feature-row">
            <div class="feature-icon-circle">
                <i class="fas fa-coins"></i>
            </div>
            <div class="feature-text">
                <h3>Dividend Income Management</h3>
                <p>Track payments, forecast income, and analyze yield patterns across all holdings</p>
            </div>
        </div>
        
        <div class="feature-row">
            <div class="feature-icon-circle">
                <i class="fas fa-globe-americas"></i>
            </div>
            <div class="feature-text">
                <h3>Global Market Coverage</h3>
                <p>Monitor Swedish and international dividend stocks from multiple exchanges</p>
            </div>
        </div>
        
        <div class="feature-row">
            <div class="feature-icon-circle">
                <i class="fas fa-building"></i>
            </div>
            <div class="feature-text">
                <h3>Company Research Hub</h3>
                <p>In-depth analysis, financial data, and custom scoring for informed decisions</p>
            </div>
        </div>
        
        <div class="feature-row">
            <div class="feature-icon-circle">
                <i class="fas fa-shield-check"></i>
            </div>
            <div class="feature-text">
                <h3>Professional & Secure</h3>
                <p>Enterprise-grade security with role-based access and comprehensive audit trails</p>
            </div>
        </div>
        
        <div class="feature-row">
            <div class="feature-icon-circle">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="feature-text">
                <h3>Advanced Reporting</h3>
                <p>Generate detailed reports and export data for tax reporting and analysis</p>
            </div>
        </div>
    </div>
    
    <?php if (!Auth::isLoggedIn()): ?>
        <div class="login-prompt">
            <div class="login-card">
                <h2>
                    <i class="fas fa-sign-in-alt"></i>
                    Access Your Portfolio
                </h2>
                <p>Login to view your personalized dividend portfolio dashboard and detailed analytics.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="dashboard-cta">
            <a href="<?php echo BASE_URL; ?>/dashboard.php" class="hero-button">
                <i class="fas fa-tachometer-alt"></i>
                Go to Dashboard
            </a>
        </div>
    <?php endif; ?>
</div>