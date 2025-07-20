<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
session_start();

// Mock user session for testing
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'testuser';
$_SESSION['user_role'] = 1;
$_SESSION['role_name'] = 'Administrator';
$_SESSION['last_activity'] = time();

$pageTitle = 'Complete Design Test - PSW 4.0';
$pageDescription = 'Testing all design improvements and modern styling';

ob_start();
?>

<div class="landing-content">
    <div class="landing-logo">
        <div style="width: 120px; height: 120px; background: linear-gradient(135deg, #00C896, #1A73E8); border-radius: 24px; margin: 0 auto 24px; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-chart-line" style="font-size: 48px; color: white;"></i>
        </div>
        <h1 class="landing-title">PSW 4.0 Design Test</h1>
        <p class="landing-subtitle">Complete Design System Implementation</p>
    </div>

    <!-- Alert Examples -->
    <div style="margin: 2rem 0;">
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Success: Design system is working correctly!
        </div>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            Error: This is what an error message looks like.
        </div>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-circle"></i>
            Warning: Important information for users.
        </div>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            Info: Helpful tips and information.
        </div>
    </div>

    <!-- Buttons -->
    <div style="text-align: center; margin: 2rem 0; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
        <button class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Primary Button
        </button>
        <button class="btn btn-primary btn-lg">
            <i class="fas fa-star"></i>
            Large Button
        </button>
        <a href="#" class="btn">
            <i class="fas fa-external-link-alt"></i>
            Link Button
        </a>
    </div>

    <!-- Cards Grid -->
    <div class="features-list">
        <div class="feature-item">
            <div class="feature-icon">
                <i class="fas fa-palette"></i>
            </div>
            <div class="feature-content">
                <h3 class="feature-title">Modern Color Palette</h3>
                <p class="feature-description">
                    Avanza green (#00C896) and Google blue (#1A73E8) with comprehensive color system.
                </p>
            </div>
        </div>

        <div class="feature-item">
            <div class="feature-icon">
                <i class="fas fa-mobile-alt"></i>
            </div>
            <div class="feature-content">
                <h3 class="feature-title">Responsive Design</h3>
                <p class="feature-description">
                    Mobile-first design that works perfectly on all devices and screen sizes.
                </p>
            </div>
        </div>

        <div class="feature-item">
            <div class="feature-icon">
                <i class="fas fa-magic"></i>
            </div>
            <div class="feature-content">
                <h3 class="feature-title">Smooth Animations</h3>
                <p class="feature-description">
                    Professional hover effects, transitions, and micro-interactions for better UX.
                </p>
            </div>
        </div>

        <div class="feature-item">
            <div class="feature-icon">
                <i class="fas fa-cog"></i>
            </div>
            <div class="feature-content">
                <h3 class="feature-title">CSS Custom Properties</h3>
                <p class="feature-description">
                    Modern CSS variables system for consistent theming and easy customization.
                </p>
            </div>
        </div>
    </div>

    <!-- Typography Examples -->
    <div style="background: white; padding: 2rem; margin: 2rem 0; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
        <h1>Heading 1 - Main Title</h1>
        <h2>Heading 2 - Section Title</h2>
        <h3>Heading 3 - Subsection</h3>
        <h4>Heading 4 - Component Title</h4>
        <p>This is regular paragraph text with proper line height and spacing. The typography system uses system fonts for optimal performance and readability.</p>
        <p class="text-muted">This is muted text for secondary information.</p>
        <p><strong>Bold text</strong> and <em>italic text</em> for emphasis.</p>
    </div>

    <!-- Form Example -->
    <div style="background: white; padding: 2rem; margin: 2rem 0; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 400px; margin-left: auto; margin-right: auto;">
        <h3>Form Example</h3>
        <form>
            <div class="form-group">
                <label for="test-input">Test Input</label>
                <input type="text" id="test-input" placeholder="Enter some text..." style="width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 16px; transition: all 0.25s ease;">
            </div>
            <div class="form-group">
                <label for="test-select">Test Select</label>
                <select id="test-select" style="width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 16px;">
                    <option>Option 1</option>
                    <option>Option 2</option>
                    <option>Option 3</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-paper-plane"></i>
                Submit Form
            </button>
        </form>
    </div>

    <!-- Status Check -->
    <div style="background: linear-gradient(135deg, #E6F9F5, #E3F2FD); padding: 2rem; margin: 2rem 0; border-radius: 16px; border: 1px solid #00C896;">
        <h3 style="color: #00A682; margin-bottom: 1rem;">✅ Design Implementation Status</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div style="background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <strong style="color: #00C896;">CSS Variables</strong><br>
                <span style="color: #6B7280;">✓ Implemented</span>
            </div>
            <div style="background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <strong style="color: #00C896;">Typography</strong><br>
                <span style="color: #6B7280;">✓ Modern System</span>
            </div>
            <div style="background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <strong style="color: #00C896;">Components</strong><br>
                <span style="color: #6B7280;">✓ Professional</span>
            </div>
            <div style="background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <strong style="color: #00C896;">Animations</strong><br>
                <span style="color: #6B7280;">✓ Smooth & Fast</span>
            </div>
            <div style="background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <strong style="color: #00C896;">Navigation</strong><br>
                <span style="color: #6B7280;">✓ Complete Menu</span>
            </div>
            <div style="background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <strong style="color: #00C896;">Responsive</strong><br>
                <span style="color: #6B7280;">✓ Mobile Ready</span>
            </div>
        </div>
    </div>

    <div class="cta-section">
        <p class="text-muted">
            <i class="fas fa-check-circle" style="color: #00C896;"></i>
            All design improvements are now active and working!
        </p>
        <a href="<?php echo BASE_URL; ?>/dashboard.php" class="btn btn-lg">
            <i class="fas fa-tachometer-alt"></i>
            View Dashboard
        </a>
    </div>
</div>

<style>
/* Additional inline styles for testing */
input:focus, select:focus {
    outline: none !important;
    border-color: #00C896 !important;
    box-shadow: 0 0 0 3px rgba(0, 200, 150, 0.1) !important;
}

.feature-item:hover {
    transform: translateY(-4px);
}

.btn:active {
    transform: translateY(0) !important;
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/templates/layouts/base.php';
?>