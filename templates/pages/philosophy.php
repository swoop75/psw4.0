<?php
/**
 * File: templates/pages/philosophy.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\templates\pages\philosophy.php
 * Description: Philosophy page template for PSW 4.0
 */
?>

<!-- Custom Philosophy Page Styles -->
<style>
        /* Main Content */
        .main-content {
            max-width: 80vw; /* Match management pages width */
            margin: 0 auto;
            padding: 2rem;
            padding-top: 120px; /* Account for sticky header + spacing */
            box-sizing: border-box;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 4rem;
        }
        
        .page-title {
            font-size: 3.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #00C896 0%, #1A73E8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
            letter-spacing: -2px;
        }
        
        .page-subtitle {
            font-size: 1.5rem;
            color: #6B7280;
            font-weight: 400;
        }
        
        .content-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 2rem;
            /* Enhanced styling to match management pages */
            position: relative;
            overflow: hidden;
        }
        
        .content-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                rgba(0, 200, 150, 0.02) 0%, 
                rgba(26, 115, 232, 0.01) 50%, 
                rgba(0, 200, 150, 0.02) 100%);
            pointer-events: none;
            z-index: 0;
        }
        
        .content-card > * {
            position: relative;
            z-index: 1;
        }
        
        .intro-text {
            font-size: 1.2rem;
            line-height: 1.8;
            color: #374151;
            margin-bottom: 2.5rem;
            font-weight: 400;
        }
        
        .philosophy-text {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #4B5563;
            margin-bottom: 2rem;
        }
        
        .philosophy-text p {
            margin-bottom: 1.5rem;
        }
        
        .highlights-section {
            background: linear-gradient(135deg, #E6F9F5 0%, #E3F2FD 100%);
            border: 2px solid #00C896;
            border-radius: 20px;
            padding: 2.5rem;
            margin: 2.5rem 0;
        }
        
        .highlights-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #00A682;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .highlights-list {
            list-style: none;
            padding: 0;
        }
        
        .highlights-list li {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 1.05rem;
            line-height: 1.6;
        }
        
        .highlight-icon {
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, #00C896 0%, #1A73E8 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 0.1rem;
        }
        
        .highlight-icon i {
            font-size: 12px;
            color: white;
        }
        
        .highlight-text {
            color: #1F2937;
        }
        
        .highlight-text strong {
            color: #00A682;
            font-weight: 600;
        }
        
        .freedom-badge {
            background: linear-gradient(135deg, #00C896 0%, #34A853 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 50px;
            text-align: center;
            font-weight: 600;
            font-size: 1.1rem;
            margin: 2rem 0;
            box-shadow: 0 10px 30px rgba(0, 200, 150, 0.3);
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.8);
            color: #00C896;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            border: 2px solid #00C896;
            transition: all 0.3s ease;
            margin-top: 2rem;
        }
        
        .back-button:hover {
            background: rgba(0, 200, 150, 0.1);
            transform: translateY(-2px);
            color: #00A682;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                max-width: 95vw; /* More space on mobile */
                padding: 1rem;
                padding-top: 100px; /* Reduced top padding on mobile */
            }
            
            .page-title {
                font-size: 2.5rem;
                letter-spacing: -1px;
            }
            
            .page-subtitle {
                font-size: 1.25rem;
            }
            
            .content-card {
                padding: 2rem;
                border-radius: 16px;
            }
            
            .intro-text {
                font-size: 1.1rem;
            }
            
            .philosophy-text {
                font-size: 1rem;
            }
            
            .highlights-section {
                padding: 2rem;
            }
        }
</style>

<!-- Beautiful Typography -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">My Investment Philosophy</h1>
        <p class="page-subtitle">A Disciplined Approach to Dividend Investing</p>
    </div>

    <div class="content-card">
        <div class="intro-text">
            Welcome to my dividend investing journey—this is PSW: Pengamaskinen Sverige + Worldwide.
        </div>
        
        <div class="philosophy-text">
            <p>At the heart of my strategy is a simple goal: <strong>building consistent income through dividends</strong>. My portfolio includes more than 500 companies, all selected through a highly disciplined, data-driven approach.</p>
            
            <p>I don't chase market predictions or get caught up in short-term noise. Emotions and gut feelings take a backseat. Instead, I've designed a structured system that ranks and scores companies, guiding every buy (and the occasional sell) based on clear, predefined rules. I only trade on weekends to stay focused and keep daily market swings from clouding my judgment.</p>
            
            <p>Rather than trimming positions, I prefer to channel new cash and reinvested dividends into smaller holdings. It's all about steady, reliable growth—and for me, dividends are what it's all about.</p>
        </div>
        
        <div class="highlights-section">
            <h3 class="highlights-title">
                <i class="fas fa-star"></i>
                Highlights of the PSW Approach
            </h3>
            
            <ul class="highlights-list">
                <li>
                    <div class="highlight-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="highlight-text">
                        <strong>Focus on Dividends & Income:</strong> My core goal is to build a steady stream of income.
                    </div>
                </li>
                
                <li>
                    <div class="highlight-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="highlight-text">
                        <strong>Data-Driven System:</strong> Investment decisions are based on objective rules and scoring, not predictions.
                    </div>
                </li>
                
                <li>
                    <div class="highlight-icon">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div class="highlight-text">
                        <strong>Disciplined & Emotion-Free:</strong> Orders are placed only on weekends to avoid market distractions and emotional trading.
                    </div>
                </li>
                
                <li>
                    <div class="highlight-icon">
                        <i class="fas fa-seedling"></i>
                    </div>
                    <div class="highlight-text">
                        <strong>Long-Term Growth:</strong> I rarely sell and reinvest all new cash and dividends into smaller positions to maximize portfolio growth.
                    </div>
                </li>
                
                <li>
                    <div class="highlight-icon">
                        <i class="fas fa-globe-americas"></i>
                    </div>
                    <div class="highlight-text">
                        <strong>Global Reach:</strong> Investments span across the globe, not limited to any single market.
                    </div>
                </li>
                
                <li>
                    <div class="highlight-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="highlight-text">
                        <strong>Over 500 Companies:</strong> A highly diversified portfolio designed for resilience.
                    </div>
                </li>
            </ul>
        </div>
        
        <div class="freedom-badge">
            <i class="fas fa-trophy"></i>
            Freedom level: > 100% FI(RE). Committed to achieving complete financial independence through dividends.
        </div>
        
        <a href="<?php echo BASE_URL; ?>/" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to Home
        </a>
    </div>
</main>

<script>
    console.log('📚 PSW Philosophy page loaded successfully!');
    
    // Add smooth scroll behavior
    document.addEventListener('DOMContentLoaded', function() {
        // Smooth scrolling for navigation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Add reading progress indicator
        const progressBar = document.createElement('div');
        progressBar.style.cssText = `
            position: fixed;
            top: 80px;
            left: 0;
            width: 0%;
            height: 3px;
            background: linear-gradient(135deg, #00C896 0%, #1A73E8 100%);
            z-index: 1001;
            transition: width 0.1s ease;
        `;
        document.body.appendChild(progressBar);
        
        window.addEventListener('scroll', () => {
            const scrolled = window.scrollY;
            const maxScroll = document.body.scrollHeight - window.innerHeight;
            const progress = (scrolled / maxScroll) * 100;
            progressBar.style.width = Math.min(progress, 100) + '%';
        });
        
        console.log('✨ Philosophy page interactions ready!');
    });
</script>