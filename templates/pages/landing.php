<?php
/**
 * File: templates/pages/landing.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\templates\pages\landing.php
 * Description: Landing page template for PSW 4.0
 */
?>

<div class="landing-content">
    <div class="landing-logo">
        <img src="<?php echo ASSETS_URL; ?>/img/psw-logo.svg" alt="PSW Logo" onerror="this.style.display='none'" style="height: 120px; width: 120px;">
        <h1 class="landing-title"><?php echo APP_FULL_NAME; ?></h1>
        <p class="landing-subtitle">Professional Dividend Portfolio Management & Analysis</p>
    </div>

    <div class="features-list">
        <div class="feature-item">
            <div class="feature-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="feature-content">
                <h3 class="feature-title">Comprehensive Portfolio Tracking</h3>
                <p class="feature-description">
                    Monitor your dividend portfolio across multiple brokers and accounts with real-time performance tracking and detailed analytics.
                </p>
            </div>
        </div>

        <div class="feature-item">
            <div class="feature-icon">
                <i class="fas fa-coins"></i>
            </div>
            <div class="feature-content">
                <h3 class="feature-title">Dividend Income Analysis</h3>
                <p class="feature-description">
                    Track dividend payments, forecast future income, and analyze yield patterns with comprehensive historical data and projections.
                </p>
            </div>
        </div>

        <div class="feature-item">
            <div class="feature-icon">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="feature-content">
                <h3 class="feature-title">Portfolio Allocation Insights</h3>
                <p class="feature-description">
                    Visualize your portfolio allocation by sector, geography, and asset class with interactive charts and detailed breakdowns.
                </p>
            </div>
        </div>

        <div class="feature-item">
            <div class="feature-icon">
                <i class="fas fa-building"></i>
            </div>
            <div class="feature-content">
                <h3 class="feature-title">Company Research & Analysis</h3>
                <p class="feature-description">
                    Access detailed company information, financial statements, dividend history, and custom scoring metrics for informed decisions.
                </p>
            </div>
        </div>

        <div class="feature-item">
            <div class="feature-icon">
                <i class="fas fa-file-export"></i>
            </div>
            <div class="feature-content">
                <h3 class="feature-title">Professional Reporting</h3>
                <p class="feature-description">
                    Generate detailed reports and export data to Excel for further analysis, tax reporting, and portfolio documentation.
                </p>
            </div>
        </div>

        <div class="feature-item">
            <div class="feature-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="feature-content">
                <h3 class="feature-title">Secure & Professional</h3>
                <p class="feature-description">
                    Built with enterprise-grade security, role-based access control, and comprehensive audit logging for professional use.
                </p>
            </div>
        </div>
    </div>

    <?php if (!Auth::isLoggedIn()): ?>
        <div class="cta-section">
            <p class="text-muted">
                <i class="fas fa-info-circle"></i>
                Login to access your dividend portfolio dashboard and detailed analytics.
            </p>
        </div>
    <?php else: ?>
        <div class="cta-section">
            <a href="<?php echo BASE_URL; ?>/dashboard.php" class="btn btn-primary btn-lg">
                <i class="fas fa-tachometer-alt"></i>
                Go to Dashboard
            </a>
        </div>
    <?php endif; ?>
</div>