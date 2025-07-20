<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSS Loading Test - PSW 4.0</title>
    
    <!-- Test direct CSS loading -->
    <link rel="stylesheet" href="./assets/css/improved-main.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
    /* Inline test styles to ensure something shows */
    .test-container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 2rem;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .test-card {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        margin: 1rem 0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border: 1px solid #e5e7eb;
    }
    
    .status-good {
        background: linear-gradient(135deg, #E6F9F5, #E3F2FD);
        border-color: #00C896;
        color: #00A682;
    }
    
    .status-bad {
        background: linear-gradient(135deg, #FEE2E2, #FEF3F2);
        border-color: #EA4335;
        color: #DC2626;
    }
    
    .css-test {
        background: var(--primary-color, #ff0000);
        color: white;
        padding: 1rem;
        border-radius: 8px;
        margin: 1rem 0;
    }
    </style>
</head>
<body style="background: #f8fafc; margin: 0; padding: 0;">

<div class="test-container">
    <h1 style="text-align: center; color: #1f2937;">PSW 4.0 CSS Loading Test</h1>
    
    <div class="test-card">
        <h2><i class="fas fa-code"></i> CSS Variables Test</h2>
        <div class="css-test">
            If this shows GREEN background, CSS variables are working.<br>
            If this shows RED background, CSS is not loading properly.
        </div>
        
        <p><strong>CSS File Path:</strong> ./assets/css/improved-main.css</p>
        <p><strong>Current URL:</strong> <?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?></p>
        <p><strong>Server Path:</strong> <?php echo __DIR__; ?></p>
    </div>
    
    <div class="test-card">
        <h2><i class="fas fa-palette"></i> Design System Test</h2>
        
        <!-- Test alert -->
        <div class="alert alert-success" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem 1.25rem; border-radius: 8px; margin: 1rem 0; background: rgba(52, 168, 83, 0.1); color: #34A853; border: 1px solid rgba(52, 168, 83, 0.3);">
            <i class="fas fa-check-circle"></i>
            This is a success alert with improved styling
        </div>
        
        <!-- Test button -->
        <button class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #00C896, #00A682); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.25s ease;">
            <i class="fas fa-star"></i>
            Test Button
        </button>
        
        <!-- Test card -->
        <div class="feature-item" style="background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin: 1rem 0; border: 1px solid #e5e7eb; transition: transform 0.25s ease;">
            <div class="feature-icon" style="width: 60px; height: 60px; background: linear-gradient(135deg, #00C896, #1A73E8); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem;">
                <i class="fas fa-chart-line" style="font-size: 1.5rem; color: white;"></i>
            </div>
            <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.75rem; color: #1f2937;">Test Feature Card</h3>
            <p style="color: #6b7280; line-height: 1.7; margin: 0;">This card tests the improved styling and should have hover effects.</p>
        </div>
    </div>
    
    <div class="test-card status-good">
        <h2><i class="fas fa-check-circle"></i> What Should Be Working</h2>
        <ul>
            <li>Modern color scheme (Avanza green #00C896, Google blue #1A73E8)</li>
            <li>Improved typography with system fonts</li>
            <li>Professional cards with shadows and rounded corners</li>
            <li>Smooth hover animations and transitions</li>
            <li>Responsive navigation menu with dropdowns</li>
            <li>Modern alert styles and button gradients</li>
        </ul>
    </div>
    
    <div class="test-card">
        <h2><i class="fas fa-info-circle"></i> Troubleshooting</h2>
        
        <?php
        $cssPath = __DIR__ . '/assets/css/improved-main.css';
        if (file_exists($cssPath)) {
            echo '<div class="status-good"><i class="fas fa-check"></i> CSS file exists at: ' . $cssPath . '</div>';
            echo '<p>File size: ' . number_format(filesize($cssPath)) . ' bytes</p>';
            echo '<p>Last modified: ' . date('Y-m-d H:i:s', filemtime($cssPath)) . '</p>';
        } else {
            echo '<div class="status-bad"><i class="fas fa-times"></i> CSS file NOT found at: ' . $cssPath . '</div>';
        }
        
        // Test if CSS variables are being applied
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const testEl = document.querySelector(".css-test");
                const computedStyle = window.getComputedStyle(testEl);
                const bgColor = computedStyle.backgroundColor;
                
                console.log("CSS Test Element Background:", bgColor);
                
                if (bgColor.includes("200, 150") || bgColor.includes("#00C896")) {
                    testEl.innerHTML = "✅ SUCCESS: CSS variables are working! Background is GREEN.";
                } else {
                    testEl.innerHTML = "❌ FAILED: CSS not loading properly. Background should be green.";
                    testEl.style.background = "#ff4444";
                }
            });
        </script>';
        ?>
    </div>
    
    <div class="test-card">
        <h2><i class="fas fa-external-link-alt"></i> Quick Links</h2>
        <p>Try these direct links to test the improved design:</p>
        <ul>
            <li><a href="./test_design_complete.php" style="color: #00C896;">Complete Design Test</a></li>
            <li><a href="./test_navigation.php" style="color: #00C896;">Navigation Test</a></li>
            <li><a href="./dashboard.php" style="color: #00C896;">Dashboard (requires login)</a></li>
            <li><a href="./buylist_management.php" style="color: #00C896;">Buylist Management (requires login)</a></li>
        </ul>
    </div>
</div>

<script src="./assets/js/improved-main.js?v=<?php echo time(); ?>"></script>

</body>
</html>