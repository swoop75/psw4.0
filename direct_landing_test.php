<?php
// Direct landing page test - bypassing all authentication and redirects
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengemaskinen Sverige + Worldwide</title>
    
    <!-- Force load CSS -->
    <link rel="stylesheet" href="./assets/css/improved-main.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Force override any conflicting styles */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            background-color: #f8fafc !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .main-container {
            max-width: 1200px !important;
            margin: 0 auto !important;
            padding: 1.5rem !important;
        }
    </style>
</head>
<body>

    <!-- Simple Header -->
    <header style="background: white; border-bottom: 1px solid #e5e7eb; padding: 1rem 0; margin-bottom: 2rem;">
        <div style="max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; display: flex; justify-content: space-between; align-items: center;">
            <h1 style="margin: 0; font-size: 1.5rem; color: #1f2937;">PSW 4.0</h1>
            <div style="background: linear-gradient(135deg, #00C896, #1A73E8); color: white; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.875rem;">
                Direct Test Mode
            </div>
        </div>
    </header>

    <main class="main-container">
        <!-- Beautiful Landing Page Content -->
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
            
            <!-- Login Prompt -->
            <div class="login-prompt">
                <div class="login-card">
                    <h2>
                        <i class="fas fa-sign-in-alt"></i>
                        Access Your Portfolio
                    </h2>
                    <p>Login to view your personalized dividend portfolio dashboard and detailed analytics.</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Test Status -->
    <div style="position: fixed; bottom: 20px; right: 20px; background: #00C896; color: white; padding: 1rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); max-width: 300px;">
        <strong>🧪 Direct Test Results:</strong><br>
        <span id="css-status">Checking CSS...</span><br>
        <span id="js-status">Checking JS...</span>
    </div>

    <script src="./assets/js/improved-main.js?v=<?php echo time(); ?>"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Test if CSS is loading
            const logoCircle = document.querySelector('.logo-circle');
            const computedStyle = window.getComputedStyle(logoCircle);
            const background = computedStyle.background || computedStyle.backgroundColor;
            
            const cssStatus = document.getElementById('css-status');
            const jsStatus = document.getElementById('js-status');
            
            if (background.includes('gradient') || background.includes('200, 150') || background.includes('rgb(0, 200, 150)')) {
                cssStatus.innerHTML = '✅ CSS: Working!';
                cssStatus.style.color = '#4ade80';
            } else {
                cssStatus.innerHTML = '❌ CSS: Not loading';
                cssStatus.style.color = '#ef4444';
            }
            
            // Test if JS is working
            if (typeof PSW !== 'undefined') {
                jsStatus.innerHTML = '✅ JS: Working!';
                jsStatus.style.color = '#4ade80';
            } else {
                jsStatus.innerHTML = '❌ JS: Not loading';
                jsStatus.style.color = '#ef4444';
            }
            
            console.log('🧪 Direct landing test loaded');
            console.log('CSS background:', background);
            console.log('PSW object:', typeof PSW);
        });
    </script>
</body>
</html>