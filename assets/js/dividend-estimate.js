/**
 * File: assets/js/dividend-estimate.js
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\assets\js\dividend-estimate.js
 * Description: Dividend estimate page JavaScript for PSW 4.0
 */

// Global variables
let monthlyChart = null;
let currentChartView = 'bar'; // 'bar' or 'line'

/**
 * Initialize dividend estimate page
 */
document.addEventListener('DOMContentLoaded', function() {
    initializeDividendEstimate();
});

/**
 * Initialize page components
 */
function initializeDividendEstimate() {
    console.log('Initializing dividend estimate page...');
    
    // Check if required data is available
    if (typeof window.dividendEstimateData === 'undefined') {
        console.error('Dividend estimate data not available');
        return;
    }
    
    initializeCharts();
    initializeEventListeners();
    initializeTooltips();
}

/**
 * Initialize charts
 */
function initializeCharts() {
    try {
        initializeMonthlyChart();
    } catch (error) {
        console.error('Error initializing charts:', error);
        showChartPlaceholder();
    }
}

/**
 * Initialize monthly breakdown chart
 */
function initializeMonthlyChart() {
    const canvas = document.getElementById('monthlyChart');
    if (!canvas) {
        console.warn('Monthly chart canvas not found');
        return;
    }
    
    const ctx = canvas.getContext('2d');
    const monthlyData = window.dividendEstimateData.monthlyBreakdown;
    
    // For now, create a simple bar chart placeholder
    // This would be replaced with Chart.js implementation
    drawSimpleBarChart(ctx, monthlyData);
}

/**
 * Draw a simple bar chart (placeholder for Chart.js)
 */
function drawSimpleBarChart(ctx, data) {
    const canvas = ctx.canvas;
    const width = canvas.width;
    const height = canvas.height;
    
    // Clear canvas
    ctx.fillStyle = '#f8f9fa';
    ctx.fillRect(0, 0, width, height);
    
    if (!data || data.length === 0) {
        // Show no data message
        ctx.fillStyle = '#6c757d';
        ctx.font = '16px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('Monthly Chart', width / 2, height / 2 - 10);
        ctx.font = '12px Arial';
        ctx.fillText('Chart.js integration pending', width / 2, height / 2 + 10);
        return;
    }
    
    // Basic chart parameters
    const padding = 40;
    const chartWidth = width - (padding * 2);
    const chartHeight = height - (padding * 2);
    const barWidth = chartWidth / data.length * 0.8;
    const barSpacing = chartWidth / data.length * 0.2;
    
    // Find max value for scaling
    const maxValue = Math.max(...data.map(item => 
        Math.max(item.actual_amount || 0, item.estimated_amount || 0)
    ));
    
    // Draw bars
    data.forEach((item, index) => {
        const x = padding + (index * (barWidth + barSpacing));
        const actualHeight = (item.actual_amount || 0) / maxValue * chartHeight;
        const estimatedHeight = (item.estimated_amount || 0) / maxValue * chartHeight;
        
        // Draw actual bar (if exists)
        if (item.is_actual && item.actual_amount > 0) {
            ctx.fillStyle = '#28a745';
            ctx.fillRect(x, height - padding - actualHeight, barWidth / 2, actualHeight);
        }
        
        // Draw estimated bar
        if (item.estimated_amount > 0) {
            ctx.fillStyle = item.is_actual ? '#28a745' : '#ffc107';
            const barX = item.is_actual ? x + (barWidth / 2) : x;
            const barW = item.is_actual ? barWidth / 2 : barWidth;
            ctx.fillRect(barX, height - padding - estimatedHeight, barW, estimatedHeight);
        }
        
        // Draw month label
        ctx.fillStyle = '#495057';
        ctx.font = '10px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(
            item.month_name.substring(0, 3), 
            x + (barWidth / 2), 
            height - padding + 15
        );
    });
}

/**
 * Show chart placeholder when Chart.js is not available
 */
function showChartPlaceholder() {
    const canvas = document.getElementById('monthlyChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#f8f9fa';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#6c757d';
    ctx.font = '16px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('Chart Placeholder', canvas.width / 2, canvas.height / 2 - 10);
    ctx.font = '12px Arial';
    ctx.fillText('Integration with Chart.js pending', canvas.width / 2, canvas.height / 2 + 10);
}

/**
 * Initialize event listeners
 */
function initializeEventListeners() {
    // Quarter year selector
    const quarterYear = document.getElementById('quarterYear');
    if (quarterYear) {
        quarterYear.addEventListener('change', function() {
            updateQuarterlyView(this.value);
        });
    }
    
    // Chart view toggle
    const chartToggle = document.querySelector('[onclick="toggleChartView()"]');
    if (chartToggle) {
        chartToggle.addEventListener('click', function(e) {
            e.preventDefault();
            toggleChartView();
        });
    }
    
    // Refresh data button (if exists)
    const refreshBtn = document.getElementById('refreshEstimates');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            refreshEstimateData();
        });
    }
}

/**
 * Initialize tooltips for accuracy bars and other elements
 */
function initializeTooltips() {
    // Add hover effects to accuracy bars
    const accuracyBars = document.querySelectorAll('.accuracy-bar');
    accuracyBars.forEach(bar => {
        bar.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.02)';
            this.style.transition = 'transform 0.2s ease';
        });
        
        bar.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Add hover effects to quarter cards
    const quarterCards = document.querySelectorAll('.quarter-card');
    quarterCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.transition = 'transform 0.2s ease';
            this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });
}

/**
 * Toggle chart view between bar and line
 */
function toggleChartView() {
    currentChartView = currentChartView === 'bar' ? 'line' : 'bar';
    console.log('Toggling chart view to:', currentChartView);
    
    // Re-initialize chart with new view
    // This would integrate with Chart.js to change chart type
    initializeMonthlyChart();
    
    // Update button text
    const toggleBtn = document.querySelector('[onclick="toggleChartView()"]');
    if (toggleBtn) {
        const icon = toggleBtn.querySelector('i');
        const text = currentChartView === 'bar' ? 'Line View' : 'Bar View';
        toggleBtn.innerHTML = `<i class="fas fa-exchange-alt"></i> ${text}`;
    }
}

/**
 * Update quarterly view for selected year
 */
function updateQuarterlyView(year) {
    console.log('Updating quarterly view for year:', year);
    
    // This would make an AJAX request to get quarterly data for the selected year
    // For now, just show a loading indicator
    const quarterlyGrid = document.querySelector('.quarterly-grid');
    if (quarterlyGrid) {
        quarterlyGrid.style.opacity = '0.5';
        
        // Simulate loading
        setTimeout(() => {
            quarterlyGrid.style.opacity = '1';
            showAlert(`Quarterly data updated for ${year}`, 'info');
        }, 500);
    }
}

/**
 * Refresh estimate data via AJAX
 */
async function refreshEstimateData() {
    try {
        showLoadingIndicator();
        
        const response = await makeRequest('/public/api/dividend-estimate.php', {
            method: 'GET'
        });
        
        if (response.success) {
            // Update global data
            window.dividendEstimateData = response.data;
            
            // Re-initialize components with new data
            initializeCharts();
            updateEstimateMetrics(response.data);
            
            showAlert('Estimates updated successfully', 'success');
        } else {
            showAlert('Failed to refresh estimates', 'error');
        }
        
    } catch (error) {
        console.error('Estimate refresh error:', error);
        showAlert('Error refreshing estimates', 'error');
    } finally {
        hideLoadingIndicator();
    }
}

/**
 * Update estimate metrics on the page
 */
function updateEstimateMetrics(data) {
    // Update annual estimates
    if (data.annualEstimates) {
        const currentYearElement = document.querySelector('.summary-value');
        if (currentYearElement) {
            currentYearElement.textContent = formatCurrency(data.annualEstimates.current_year_estimate) + ' SEK';
        }
    }
    
    // Update accuracy metrics
    if (data.estimateAccuracy) {
        const accuracyElements = document.querySelectorAll('.accuracy-fill');
        const accuracyValues = document.querySelectorAll('.accuracy-value');
        
        if (accuracyElements.length >= 3 && accuracyValues.length >= 3) {
            accuracyElements[0].style.width = data.estimateAccuracy.overall_accuracy + '%';
            accuracyValues[0].textContent = data.estimateAccuracy.overall_accuracy.toFixed(1) + '%';
            
            accuracyElements[1].style.width = data.estimateAccuracy.monthly_accuracy + '%';
            accuracyValues[1].textContent = data.estimateAccuracy.monthly_accuracy.toFixed(1) + '%';
            
            accuracyElements[2].style.width = data.estimateAccuracy.annual_accuracy + '%';
            accuracyValues[2].textContent = data.estimateAccuracy.annual_accuracy.toFixed(1) + '%';
        }
    }
}

/**
 * Show loading indicator
 */
function showLoadingIndicator() {
    const container = document.querySelector('.dividend-estimate-container');
    if (container) {
        container.classList.add('loading');
    }
}

/**
 * Hide loading indicator
 */
function hideLoadingIndicator() {
    const container = document.querySelector('.dividend-estimate-container');
    if (container) {
        container.classList.remove('loading');
    }
}

/**
 * Export dividend estimates to Excel
 */
function exportEstimates() {
    // TODO: Implement Excel export functionality
    showAlert('Export functionality coming soon', 'info');
}

/**
 * Print dividend estimates
 */
function printEstimates() {
    window.print();
}

/**
 * Navigate to monthly overview
 */
function viewMonthlyOverview() {
    window.location.href = BASE_URL + '/dividend_estimate_monthly.php';
}

/**
 * Format number as currency
 */
function formatCurrency(amount, currency = 'SEK') {
    return new Intl.NumberFormat('sv-SE', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}