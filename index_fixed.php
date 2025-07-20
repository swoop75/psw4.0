<?php
/**
 * Fixed index.php that works on both localhost and server
 */

// Start session
session_start();

// Determine the correct base path dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . '://' . $host;

// Get the directory path
$request_uri = $_SERVER['REQUEST_URI'];
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
if ($script_dir !== '/') {
    $base_url .= $script_dir;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengemaskinen Sverige + Worldwide</title>
    
    <!-- Dynamic CSS loading -->
    <link rel="stylesheet" href="assets/css/improved-main.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Fallback styles in case CSS doesn't load */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        
        .header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #00C896, #1A73E8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .login-toggle {
            background: linear-gradient(135deg, #00C896, #1A73E8);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.5rem;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <a href="<?php echo $base_url; ?>" class="logo" style="text-decoration: none;">
                <span class="logo-text">PSW 4.0</span>
            </a>
            
            <div class="login-container">
                <button class="login-toggle" onclick="toggleLogin()">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
        </div>
    </header>

    <main class="main-container">
        <!-- Beautiful Landing Page -->
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

    <!-- Debug Info -->
    <div style="position: fixed; bottom: 20px; left: 20px; background: rgba(0,0,0,0.8); color: white; padding: 1rem; border-radius: 8px; font-size: 0.875rem; max-width: 300px;">
        <strong>🔧 Debug Info:</strong><br>
        Current URL: <?php echo $base_url; ?><br>
        Host: <?php echo $host; ?><br>
        CSS Path: assets/css/improved-main.css<br>
        <span id="css-test" style="color: #fbbf24;">Testing CSS...</span>
    </div>

    <script src="assets/js/improved-main.js?v=<?php echo time(); ?>"></script>
    
    <script>
        function toggleLogin() {
            alert('Login functionality would be here!');
        }
        
        // Test CSS loading
        document.addEventListener('DOMContentLoaded', function() {
            const logoCircle = document.querySelector('.logo-circle');
            if (logoCircle) {
                const computedStyle = window.getComputedStyle(logoCircle);
                const bg = computedStyle.background || computedStyle.backgroundColor;
                
                const cssTest = document.getElementById('css-test');
                if (bg.includes('gradient') || bg.includes('200, 150')) {
                    cssTest.innerHTML = '✅ CSS: Working!';
                    cssTest.style.color = '#4ade80';
                } else {
                    cssTest.innerHTML = '❌ CSS: Check path';
                    cssTest.style.color = '#ef4444';
                }
            }
            
            console.log('🎨 Fixed landing page loaded!');
        });
    </script>
</body>
</html>