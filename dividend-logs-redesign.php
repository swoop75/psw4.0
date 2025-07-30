<?php
/**
 * File: dividend-logs-redesign.php
 * Description: Redesigned dividend logs page using the new beautiful layout and date range picker
 * This is the main entry point for the redesigned dividend logs functionality
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/controllers/DividendLogsController.php';
require_once __DIR__ . '/src/components/DateRangePicker.php';

// Authentication check
if (!Auth::isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

try {
    $controller = new DividendLogsController();
    
    // Get filter parameters
    $filters = [
        'year' => $_GET['year'] ?? '',
        'currency' => $_GET['currency'] ?? '',
        'company' => $_GET['company'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'amount_min' => $_GET['amount_min'] ?? '',
        'amount_max' => $_GET['amount_max'] ?? '',
        'sort' => $_GET['sort'] ?? 'payment_date',
        'order' => $_GET['order'] ?? 'DESC'
    ];
    
    // Pagination parameters
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(25, min(200, (int)($_GET['per_page'] ?? 50)));
    
    // Get dividend logs data
    $logsData = $controller->getDividendLogs($filters, $page, $perPage);
    
    // Page configuration
    $pageTitle = 'Dividend Transaction History - PSW 4.0';
    $pageDescription = 'Complete record of all dividend payments with advanced filtering and export capabilities';
    
    // Additional CSS files for this page
    $additionalCSS = [
        ASSETS_URL . '/css/date-range-picker.css?v=' . time()
    ];
    
    // Include the redesigned base layout
    include __DIR__ . '/templates/layouts/base-redesign.php';
    
} catch (Exception $e) {
    Logger::error('Dividend logs error: ' . $e->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'user_id' => Auth::getUserId(),
        'filters' => $filters ?? []
    ]);
    
    // Show error page
    $errorMessage = 'Unable to load dividend logs. Please try again later.';
    include __DIR__ . '/templates/pages/error.php';
}
?>

<?php if (isset($logsData)): ?>
<!-- Page Content -->
<?php include __DIR__ . '/templates/pages/dividend-logs-redesign.php'; ?>

<!-- Required JavaScript -->
<script src="<?php echo ASSETS_URL; ?>/js/date-range-picker.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo ASSETS_URL; ?>/js/dividend-logs.js?v=<?php echo time(); ?>"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the date range picker
    const dateRangePicker = new DateRangePicker('dividend-date-range', {
        defaultMonthsBack: 3,
        onApply: function(dateRange) {
            console.log('Date range applied:', dateRange);
            
            // Update form and auto-submit if desired
            const form = document.querySelector('.psw-filters-form');
            if (form) {
                // The hidden inputs are automatically updated by the DateRangePicker component
                // You can add auto-submit logic here if needed
                
                // Optional: Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';
                    submitBtn.disabled = true;
                }
                
                // Optional: Auto-submit after a short delay
                // setTimeout(() => form.submit(), 500);
            }
        }
    });
    
    // Set initial values if they exist
    <?php if (!empty($filters['date_from']) || !empty($filters['date_to'])): ?>
    dateRangePicker.setDateRange(
        '<?php echo $filters['date_from'] ?? ''; ?>', 
        '<?php echo $filters['date_to'] ?? ''; ?>'
    );
    <?php endif; ?>
    
    // Initialize other dividend logs functionality
    if (typeof initializeDividendLogs === 'function') {
        initializeDividendLogs();
    }
});

// Enhanced export function
async function exportDividendLogs() {
    if (window.isExporting) return;
    
    try {
        window.isExporting = true;
        const exportBtn = document.querySelector('[onclick="exportDividendLogs()"]');
        
        if (exportBtn) {
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
            exportBtn.disabled = true;
        }
        
        // Build export URL with current filters including date range
        const url = new URL('/api/dividend-logs-export.php', window.location.origin);
        const filters = window.dividendLogsData?.filters || {};
        
        Object.keys(filters).forEach(key => {
            if (filters[key]) {
                url.searchParams.set(key, filters[key]);
            }
        });
        
        // Add date range from picker
        const dateRangePicker = window.dividendDateRangePicker;
        if (dateRangePicker) {
            const dateRange = dateRangePicker.getDateRange();
            if (dateRange.from) url.searchParams.set('date_from', dateRange.from);
            if (dateRange.to) url.searchParams.set('date_to', dateRange.to);
        }
        
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error('Export failed');
        }
        
        const blob = await response.blob();
        const downloadUrl = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = `dividend-logs-${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(downloadUrl);
        
        // Show success message using PSW design system
        showNotification('Dividend logs exported successfully', 'success');
        
    } catch (error) {
        console.error('Export error:', error);
        showNotification('Failed to export dividend logs', 'error');
    } finally {
        window.isExporting = false;
        const exportBtn = document.querySelector('[onclick="exportDividendLogs()"]');
        if (exportBtn) {
            exportBtn.innerHTML = '<i class="fas fa-download"></i> <span>Export CSV</span>';
            exportBtn.disabled = false;
        }
    }
}

// Enhanced print function
function printDividendLogs() {
    // Add print-specific styles
    const printStyles = `
        <style media="print">
            .psw-sidebar, .psw-page-actions, .psw-pagination, .psw-card-actions { display: none !important; }
            .psw-page-container { margin: 0 !important; padding: 20px !important; }
            .psw-table { font-size: 12px !important; }
            .psw-card { box-shadow: none !important; border: 1px solid #ccc !important; }
        </style>
    `;
    
    const originalTitle = document.title;
    document.title = 'Dividend Transaction History - ' + new Date().toLocaleDateString();
    
    // Add print styles temporarily
    const styleElement = document.createElement('div');
    styleElement.innerHTML = printStyles;
    document.head.appendChild(styleElement);
    
    window.print();
    
    // Cleanup
    setTimeout(() => {
        document.title = originalTitle;
        document.head.removeChild(styleElement);
    }, 1000);
}

// Notification helper function for PSW design system
function showNotification(message, type = 'info') {
    // This would integrate with your notification system
    // For now, we'll use a simple approach
    const notification = document.createElement('div');
    notification.className = `psw-notification psw-notification-${type}`;
    notification.innerHTML = `
        <div class="psw-notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 5000);
}
</script>

<!-- Notification Styles -->
<style>
.psw-notification {
    position: fixed;
    top: var(--spacing-4);
    right: var(--spacing-4);
    z-index: 10000;
    padding: var(--spacing-3) var(--spacing-4);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-lg);
    background: var(--bg-card);
    border-left: 4px solid var(--primary-accent);
    animation: slideInRight 0.3s ease-out;
}

.psw-notification-success {
    border-left-color: var(--success-color);
    background: var(--success-bg);
}

.psw-notification-error {
    border-left-color: var(--error-color);
    background: var(--error-bg);
}

.psw-notification-content {
    display: flex;
    align-items: center;
    gap: var(--spacing-2);
    color: var(--text-primary);
    font-size: var(--font-size-sm);
    font-weight: 500;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
</style>
<?php endif; ?>