<?php
/**
 * File: format_preferences.php
 * Description: Format preferences page for PSW 4.0 - user can select number, date, time formats
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/utils/Localization.php';

// Require authentication
Auth::requireAuth();

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $formatKey = $_POST['format_preference'] ?? '';
        
        if (empty($formatKey)) {
            throw new Exception('Please select a format preference.');
        }
        
        // Update user's format preference
        Localization::updateUserFormat($formatKey);
        
        $message = 'Format preferences updated successfully!';
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = 'Error updating preferences: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get current format and available formats
$currentFormat = Localization::getCurrentFormatKey();
$availableFormats = Localization::getAvailableFormats();

// Initialize variables for template
$pageTitle = 'Format Preferences - PSW 4.0';
$pageDescription = 'Configure number, date, and time display formats';

// Prepare content
ob_start();
?>

<div class="psw-content">
    <!-- Page Header -->
    <div class="psw-card psw-mb-6">
        <div class="psw-card-header">
            <h1 class="psw-card-title">
                <i class="fas fa-cog psw-card-title-icon"></i>
                Format Preferences
            </h1>
            <p class="psw-card-subtitle">Configure how numbers, dates, and times are displayed throughout the application</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="psw-alert psw-alert-<?php echo $messageType; ?>" style="margin-bottom: 1.5rem;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Format Selection Form -->
    <div class="psw-card">
        <div class="psw-card-header">
            <div class="psw-card-title">
                <i class="fas fa-globe psw-card-title-icon"></i>
                Select Format Style
            </div>
        </div>
        <div class="psw-card-content">
            <form method="POST" action="">
                <div style="display: grid; gap: 1.5rem;">
                    <?php foreach ($availableFormats as $formatKey => $format): ?>
                        <?php $examples = Localization::getFormatExamples($formatKey); ?>
                        <div class="format-option <?php echo $currentFormat === $formatKey ? 'selected' : ''; ?>" 
                             onclick="selectFormat('<?php echo $formatKey; ?>')">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <input type="radio" 
                                       name="format_preference" 
                                       value="<?php echo $formatKey; ?>" 
                                       id="format_<?php echo $formatKey; ?>"
                                       <?php echo $currentFormat === $formatKey ? 'checked' : ''; ?>>
                                
                                <div style="flex-grow: 1;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <h3 style="font-size: var(--font-size-lg); font-weight: 600; color: var(--text-primary); margin: 0;">
                                            <?php echo htmlspecialchars($format['name']); ?>
                                            <?php if ($currentFormat === $formatKey): ?>
                                                <span style="color: var(--primary-accent); font-size: var(--font-size-sm); font-weight: 500;">(Current)</span>
                                            <?php endif; ?>
                                        </h3>
                                        <div style="color: var(--text-muted); font-size: var(--font-size-sm); font-family: var(--font-family-mono);">
                                            <?php echo $formatKey; ?>
                                        </div>
                                    </div>
                                    
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                                        <div class="format-example">
                                            <div class="format-example-label">Numbers</div>
                                            <div class="format-example-value"><?php echo $examples['number']; ?></div>
                                            <div class="format-example-value"><?php echo $examples['large_number']; ?></div>
                                        </div>
                                        
                                        <div class="format-example">
                                            <div class="format-example-label">Dates</div>
                                            <div class="format-example-value"><?php echo $examples['date']; ?></div>
                                            <div class="format-example-value"><?php echo $examples['date_short']; ?></div>
                                        </div>
                                        
                                        <div class="format-example">
                                            <div class="format-example-label">Time</div>
                                            <div class="format-example-value"><?php echo $examples['time']; ?></div>
                                            <div class="format-example-value"><?php echo $examples['datetime']; ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="psw-btn psw-btn-primary">
                        <i class="fas fa-save psw-btn-icon"></i>
                        Save Preferences
                    </button>
                    <a href="<?php echo BASE_URL; ?>/user_settings.php" class="psw-btn psw-btn-secondary">
                        <i class="fas fa-arrow-left psw-btn-icon"></i>
                        Back to Settings
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview Section -->
    <div class="psw-card" style="margin-top: 1.5rem;">
        <div class="psw-card-header">
            <div class="psw-card-title">
                <i class="fas fa-eye psw-card-title-icon"></i>
                Live Preview
            </div>
        </div>
        <div class="psw-card-content">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                <div class="preview-card">
                    <h4 style="color: var(--text-primary); margin-bottom: 0.5rem;">Sample Numbers</h4>
                    <div id="preview-numbers">
                        <div>Small: <span class="preview-value" data-type="number" data-value="123.45" data-decimals="2">123.45</span></div>
                        <div>Large: <span class="preview-value" data-type="number" data-value="1234567" data-decimals="0">1,234,567</span></div>
                        <div>Decimal: <span class="preview-value" data-type="number" data-value="1234.5678" data-decimals="4">1,234.5678</span></div>
                    </div>
                </div>
                
                <div class="preview-card">
                    <h4 style="color: var(--text-primary); margin-bottom: 0.5rem;">Sample Dates</h4>
                    <div id="preview-dates">
                        <div>Today: <span class="preview-value" data-type="date" data-value="<?php echo date('Y-m-d'); ?>"><?php echo date('M d, Y'); ?></span></div>
                        <div>Short: <span class="preview-value" data-type="date-short" data-value="<?php echo date('Y-m-d'); ?>"><?php echo date('m/d/Y'); ?></span></div>
                        <div>Time: <span class="preview-value" data-type="time" data-value="<?php echo date('Y-m-d H:i:s'); ?>"><?php echo date('g:i A'); ?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.format-option {
    border: 2px solid var(--border-primary);
    border-radius: var(--radius-lg);
    padding: var(--spacing-4);
    cursor: pointer;
    transition: all var(--transition-base);
    background: var(--bg-card);
}

.format-option:hover {
    border-color: var(--primary-accent);
    background: var(--primary-accent-light);
}

.format-option.selected {
    border-color: var(--primary-accent);
    background: var(--primary-accent-light);
    box-shadow: 0 0 0 1px var(--primary-accent);
}

.format-example {
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    padding: var(--spacing-3);
}

.format-example-label {
    font-size: var(--font-size-sm);
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: var(--spacing-2);
}

.format-example-value {
    font-family: var(--font-family-mono);
    font-size: var(--font-size-sm);
    color: var(--text-primary);
    background: var(--bg-card);
    padding: var(--spacing-1) var(--spacing-2);
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-1);
}

.preview-card {
    background: var(--bg-secondary);
    border-radius: var(--radius-lg);
    padding: var(--spacing-4);
}

.preview-value {
    font-family: var(--font-family-mono);
    font-weight: 600;
    color: var(--primary-accent);
}
</style>

<script>
function selectFormat(formatKey) {
    // Update radio button
    document.getElementById('format_' + formatKey).checked = true;
    
    // Update visual selection
    document.querySelectorAll('.format-option').forEach(option => {
        option.classList.remove('selected');
    });
    document.querySelector(`input[value="${formatKey}"]`).closest('.format-option').classList.add('selected');
    
    // Update live preview
    updatePreview(formatKey);
}

function updatePreview(formatKey) {
    // Define format configurations
    const formats = <?php echo json_encode($availableFormats); ?>;
    const format = formats[formatKey];
    
    if (!format) return;
    
    // Update number previews
    document.querySelectorAll('[data-type="number"]').forEach(el => {
        const value = parseFloat(el.dataset.value);
        const decimals = parseInt(el.dataset.decimals);
        const formatted = formatNumber(value, decimals, format.number_decimal_separator, format.number_thousands_separator);
        el.textContent = formatted;
    });
    
    // Update date previews (simplified - would need more complex logic for full implementation)
    // For now, just show the format examples from PHP
    const examples = <?php echo json_encode(array_map(function($key) { return Localization::getFormatExamples($key); }, array_keys($availableFormats))); ?>;
    const formatExamples = examples[Object.keys(formats).indexOf(formatKey)];
    
    const dateEl = document.querySelector('[data-type="date"]');
    if (dateEl && formatExamples) {
        dateEl.textContent = formatExamples.date;
    }
    
    const dateShortEl = document.querySelector('[data-type="date-short"]');
    if (dateShortEl && formatExamples) {
        dateShortEl.textContent = formatExamples.date_short;
    }
    
    const timeEl = document.querySelector('[data-type="time"]');
    if (timeEl && formatExamples) {
        timeEl.textContent = formatExamples.time;
    }
}

function formatNumber(number, decimals, decimalSep, thousandsSep) {
    const parts = number.toFixed(decimals).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSep);
    return parts.join(decimalSep);
}

// Initialize with current format
document.addEventListener('DOMContentLoaded', function() {
    const currentFormat = '<?php echo $currentFormat; ?>';
    updatePreview(currentFormat);
});
</script>

<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base-redesign.php';
?>