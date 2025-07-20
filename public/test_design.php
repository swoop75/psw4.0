<?php
/**
 * Design Test Page - Verify improved CSS is loading
 */
session_start();
require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Design Test - PSW 4.0</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/improved-main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <h1 style="text-align: center; margin: 2rem 0;">PSW 4.0 Design Test</h1>
        
        <!-- Test CSS Variables -->
        <div style="background: var(--primary-color, red); color: white; padding: 1rem; margin: 1rem 0; border-radius: var(--radius-lg, 4px);">
            <strong>CSS Variables Test:</strong> If this is green (#00C896), CSS variables are working!
        </div>
        
        <!-- Test Button Styling -->
        <div style="margin: 2rem 0;">
            <button class="btn btn-primary">
                <i class="fas fa-star"></i> Primary Button Test
            </button>
            <button class="btn btn-secondary" style="margin-left: 1rem;">
                <i class="fas fa-cog"></i> Secondary Button Test
            </button>
        </div>
        
        <!-- Test Card Styling -->
        <div class="metric-card" style="margin: 2rem 0;">
            <div class="metric-header">
                <h3>Test Card</h3>
                <div class="metric-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
            <div class="metric-value">123,456</div>
            <div class="metric-info">Test metric information</div>
        </div>
        
        <!-- Test Form Elements -->
        <div class="form-group" style="margin: 2rem 0;">
            <label for="test-input">Test Input:</label>
            <input type="text" id="test-input" placeholder="Type here to test focus styles...">
        </div>
        
        <!-- Test Alert -->
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            If you can see proper styling with green colors and modern design, the improved CSS is working!
        </div>
        
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            If this looks like plain HTML or old styling, there's a CSS loading issue.
        </div>
        
        <!-- CSS Loading Check -->
        <script>
            window.addEventListener('DOMContentLoaded', function() {
                const testElement = document.createElement('div');
                testElement.style.cssText = 'color: var(--primary-color, red);';
                document.body.appendChild(testElement);
                
                const computedColor = getComputedStyle(testElement).color;
                const resultDiv = document.createElement('div');
                resultDiv.style.cssText = 'margin: 2rem 0; padding: 1rem; border: 2px solid #ccc; background: #f9f9f9;';
                
                if (computedColor.includes('0, 200, 150') || computedColor.includes('#00C896')) {
                    resultDiv.innerHTML = '<strong style="color: green;">✓ SUCCESS:</strong> Improved CSS is loading correctly! Primary color detected.';
                } else {
                    resultDiv.innerHTML = '<strong style="color: red;">✗ FAILED:</strong> Improved CSS not loading. Detected color: ' + computedColor;
                }
                
                document.body.appendChild(resultDiv);
                document.body.removeChild(testElement);
            });
        </script>
        
        <!-- File Check -->
        <div style="margin: 2rem 0; padding: 1rem; background: #f0f0f0; border-radius: 8px;">
            <h3>File Path Check:</h3>
            <p><strong>Expected CSS file:</strong> <?= BASE_URL ?>/assets/css/improved-main.css</p>
            <p><strong>BASE_URL:</strong> <?= BASE_URL ?></p>
            <p><strong>Check browser network tab to see if CSS file loads with 200 status.</strong></p>
        </div>
    </div>
    
    <script src="<?= BASE_URL ?>/assets/js/improved-main.js"></script>
</body>
</html>