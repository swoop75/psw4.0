<?php
/**
 * File: templates/pages/landing.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\templates\pages\landing.php
 * Description: Beautiful landing page for PSW 4.0 - Updated with perfect design
 */
?>

<!-- Custom Landing Page Styles -->
<style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        
        /* Hide legacy header for all users */
        .header {
            display: none !important;
        }
        
        
        .hero-section {
            max-width: 1000px;
            margin: 0 auto;
            padding: 8rem 2rem 4rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        /* Logo + Title Layout */
        .hero-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 3rem;
            margin-bottom: 4rem;
        }
        
        .hero-logo {
            flex-shrink: 0;
        }
        
        .logo-circle {
            width: 180px;
            height: 180px;
            background: linear-gradient(135deg, #00C896 0%, #1A73E8 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 20px 40px rgba(0, 200, 150, 0.4);
            position: relative;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .logo-circle::before {
            content: '';
            position: absolute;
            top: -4px;
            left: -4px;
            right: -4px;
            bottom: -4px;
            background: linear-gradient(135deg, #00C896, #1A73E8, #FF6D01);
            border-radius: 50%;
            z-index: -1;
            animation: rotate 4s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .logo-circle i {
            font-size: 80px;
            color: white;
            z-index: 2;
        }
        
        /* Beautiful Typography */
        .hero-title {
            flex-grow: 1;
            text-align: left;
            line-height: 1.1;
        }
        
        .title-main {
            display: block;
            font-size: 5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #00C896 0%, #1A73E8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
            letter-spacing: -3px;
            animation: slideInUp 1s ease 0.2s both;
        }
        
        .title-location {
            display: block;
            font-size: 2.5rem;
            font-weight: 400;
            color: #6B7280;
            font-style: italic;
            animation: slideInUp 1s ease 0.4s both;
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Beautiful Features */
        .features-container {
            max-width: 800px;
            margin: 0 auto -2rem;
            text-align: left;
        }
        
        .feature-row {
            display: flex;
            align-items: flex-start;
            gap: 2rem;
            margin-bottom: 2.5rem;
            padding: 2rem;
            border-radius: 16px;
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.8s ease forwards;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }
        
        .feature-row:nth-child(1) { animation-delay: 0.6s; }
        .feature-row:nth-child(2) { animation-delay: 0.8s; }
        .feature-row:nth-child(3) { animation-delay: 1.0s; }
        .feature-row:nth-child(4) { animation-delay: 1.2s; }
        .feature-row:nth-child(5) { animation-delay: 1.4s; }
        .feature-row:nth-child(6) { animation-delay: 1.6s; }
        .feature-row:nth-child(7) { animation-delay: 1.8s; }
        
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .feature-row:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateX(10px);
            box-shadow: 0 20px 40px rgba(0, 200, 150, 0.15);
            color: inherit;
            text-decoration: none;
        }
        
        .feature-icon-circle {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #00C896 0%, #1A73E8 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 10px 30px rgba(0, 200, 150, 0.3);
            transition: transform 0.3s ease;
        }
        
        .feature-row:hover .feature-icon-circle {
            transform: scale(1.1) rotate(5deg);
        }
        
        .feature-icon-circle i {
            font-size: 2rem;
            color: white;
        }
        
        .feature-text h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1F2937;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }
        
        .feature-text p {
            color: #6B7280;
            font-size: 1.1rem;
            line-height: 1.6;
            margin: 0;
        }
        
        /* Login Prompt for Non-Authenticated Users */
        .login-prompt {
            text-align: center;
            margin-top: 3rem;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 200, 150, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 400px;
            margin: 0 auto;
        }
        
        .login-card h2 {
            color: #1F2937;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .login-card h2 i {
            color: #00C896;
            margin-right: 0.5rem;
        }
        
        .login-card p {
            color: #6B7280;
            font-size: 1.1rem;
        }
        
        /* Dashboard CTA for Authenticated Users */
        .dashboard-cta {
            text-align: center;
            margin-top: 3rem;
        }
        
        .hero-button {
            background: linear-gradient(135deg, #00C896 0%, #1A73E8 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(0, 200, 150, 0.3);
            text-decoration: none;
        }
        
        .hero-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0, 200, 150, 0.4);
            color: white;
            text-decoration: none;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-section {
                padding: 3rem 1rem 2rem;
            }
            
            .hero-header {
                flex-direction: column;
                gap: 2rem;
                text-align: center;
            }
            
            .hero-title {
                text-align: center;
            }
            
            .logo-circle {
                width: 120px;
                height: 120px;
            }
            
            .logo-circle i {
                font-size: 60px;
            }
            
            .title-main {
                font-size: 3.5rem;
                letter-spacing: -2px;
            }
            
            .title-location {
                font-size: 1.8rem;
            }
            
            .feature-row {
                flex-direction: column;
                text-align: center;
                gap: 1.5rem;
                padding: 1.5rem;
            }
            
            .feature-row:hover {
                transform: translateY(-5px);
            }
            
            .feature-icon-circle {
                margin: 0 auto;
            }
            
            .feature-text h3,
            .feature-text p {
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .hero-header {
                gap: 1.5rem;
            }
            
            .logo-circle {
                width: 100px;
                height: 100px;
            }
            
            .logo-circle i {
                font-size: 48px;
            }
            
            .title-main {
                font-size: 2.8rem;
            }
            
            .title-location {
                font-size: 1.5rem;
            }
        }
</style>

<!-- Beautiful Typography -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">


<div class="hero-section">
    <!-- Logo + Title Side by Side -->
    <div class="hero-header">
        <div class="hero-logo">
            <div class="logo-circle">
                <i class="fas fa-chart-line"></i>
            </div>
        </div>
        <div class="hero-title">
            <span class="title-main">Pengamaskinen</span>
            <span class="title-location">Sverige + Worldwide</span>
        </div>
    </div>
    
    <!-- Beautiful Feature List -->
    <div class="features-container">
        <div class="feature-row">
            <div class="feature-icon-circle">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="feature-text">
                <h3>Portfolio Tracking & Analytics</h3>
                <p>Complete overview of dividend investments with detailed performance metrics and real-time tracking</p>
            </div>
        </div>
        
        <div class="feature-row">
            <div class="feature-icon-circle">
                <i class="fas fa-coins"></i>
            </div>
            <div class="feature-text">
                <h3>Dividend Income Management</h3>
                <p>Track payments, forecast future income, and analyze yield patterns with comprehensive historical data</p>
            </div>
        </div>
        
        <div class="feature-row">
            <div class="feature-icon-circle">
                <i class="fas fa-globe-americas"></i>
            </div>
            <div class="feature-text">
                <h3>Global Market Coverage</h3>
                <p>Monitor Swedish and international dividend stocks from multiple exchanges worldwide with real-time data</p>
            </div>
        </div>
        
        <div class="feature-row">
            <div class="feature-icon-circle">
                <i class="fas fa-building"></i>
            </div>
            <div class="feature-text">
                <h3>Company Research Hub</h3>
                <p>In-depth analysis, financial statements, dividend history, and custom scoring metrics for informed decisions</p>
            </div>
        </div>
        
        <div class="feature-row">
            <div class="feature-icon-circle">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="feature-text">
                <h3>Professional & Secure</h3>
                <p>Enterprise-grade security with role-based access control, comprehensive audit logging, and data protection</p>
            </div>
        </div>
        
        <div class="feature-row">
            <div class="feature-icon-circle">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="feature-text">
                <h3>Advanced Reporting</h3>
                <p>Generate detailed reports and export data for tax reporting, portfolio analysis, and comprehensive documentation</p>
            </div>
        </div>
        
        <div class="feature-row">
            <div class="feature-icon-circle">
                <i class="fas fa-file-export"></i>
            </div>
            <div class="feature-text">
                <h3>Flexible Exporting</h3>
                <p>Export portfolio data, dividend reports, and analytics in multiple formats including Excel spreadsheets, CSV files, and PDF documents for seamless integration with financial workflows</p>
            </div>
        </div>
    </div>
    
    <?php if (Auth::isLoggedIn()): ?>
        <div class="dashboard-cta">
            <a href="<?php echo BASE_URL; ?>/dashboard.php" class="hero-button">
                <i class="fas fa-tachometer-alt"></i>
                Go to Dashboard
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
    console.log('🎨 Beautiful Pengamaskinen landing page with ORIGINAL logo loaded!');
    
    // Enhanced interactions
    document.addEventListener('DOMContentLoaded', function() {
        // Parallax effect for logo
        const logo = document.querySelector('.logo-circle');
        if (logo) {
            document.addEventListener('mousemove', function(e) {
                const x = (e.clientX / window.innerWidth) * 100;
                const y = (e.clientY / window.innerHeight) * 100;
                
                logo.style.transform = `translate(${(x-50)*0.05}px, ${(y-50)*0.05}px)`;
            });
        }
        
        // Enhanced feature interactions
        const featureRows = document.querySelectorAll('.feature-row');
        featureRows.forEach((row, index) => {
            row.addEventListener('mouseenter', function() {
                const icon = this.querySelector('.feature-icon-circle i');
                if (icon) {
                    icon.style.transform = 'scale(1.2)';
                }
            });
            
            row.addEventListener('mouseleave', function() {
                const icon = this.querySelector('.feature-icon-circle i');
                if (icon) {
                    icon.style.transform = 'scale(1)';
                }
            });
        });
        
        console.log('✨ All animations and interactions ready!');
    });
</script>