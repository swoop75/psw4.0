<?php
// c:/Users/laoan/Documents/GitHub/psw/psw4.0/public/templates/pages/index.php

// Correct path to header, since templates folder is inside public
require_once APPROOT . '/public/templates/includes/header.php';
?>

<div class="landing-page">
    <div class="hero-section">
        <!-- Use the variables passed from the controller -->
        <h1><?php echo $title; ?></h1>
        <p class="subtitle"><?php echo $description; ?></p>
        
        <ul class="feature-list">
            <li><i class="fas fa-chart-line"></i> Track your portfolio's performance with interactive charts and detailed metrics.</li>
            <li><i class="fas fa-coins"></i> Monitor dividend income, history, and future estimates with precision.</li>
            <li><i class="fas fa-search-dollar"></i> Conduct in-depth research on companies with comprehensive financial data.</li>
            <li><i class="fas fa-globe-europe"></i> Analyze portfolio allocation by sector, industry, and geography.</li>
            <li><i class="fas fa-cogs"></i> Utilize powerful tools designed for the detail-oriented dividend investor.</li>
            <li><i class="fas fa-shield-alt"></i> Securely manage and visualize your investment journey.</li>
        </ul>
    </div>
</div>

<?php
// Correct path to footer
require_once APPROOT . '/public/templates/includes/footer.php';
?>
