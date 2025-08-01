/**
 * File: assets/js/dashboard.js
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\assets\js\dashboard.js
 * Description: Dashboard-specific JavaScript for PSW 4.0 - handles charts and interactive elements
 */

// Global variables
let allocationChart = null;
let performanceChart = null;
let dividendTrendsChart = null;
let currentAllocationView = 'sector';

/**
 * Initialize dashboard when DOM is loaded
 */
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
});

/**
 * Initialize dashboard components
 */
function initializeDashboard() {
    console.log('Initializing dashboard...');
    
    // Check if required data is available
    if (typeof window.dashboardData === 'undefined') {
        console.error('Dashboard data not available');
        return;
    }
    
    initializeCharts();
    initializeEventListeners();
    updateLastRefreshTime();
    
    // Auto-refresh every 5 minutes
    setInterval(refreshDashboardData, 300000);
}

/**
 * Initialize charts
 */
function initializeCharts() {
    try {
        initializeAllocationChart();
        initializePerformanceChart();
        initializeDividendTrendsChart();
    } catch (error) {
        console.error('Error initializing charts:', error);
    }
}

/**
 * Initialize allocation pie chart
 */
function initializeAllocationChart() {
    const canvas = document.getElementById('allocationChart');
    if (!canvas) {
        console.warn('Allocation chart canvas not found');
        return;
    }
    
    const ctx = canvas.getContext('2d');
    const allocationData = window.dashboardData.allocation.by_sector;
    
    // Chart.js configuration
    const config = {
        type: 'doughnut',
        data: {
            labels: allocationData.map(item => item.name),
            datasets: [{
                data: allocationData.map(item => item.percentage),
                backgroundColor: [
                    '#007bff', '#28a745', '#ffc107', '#dc3545', 
                    '#6f42c1', '#fd7e14', '#20c997', '#6c757d'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = formatCurrency(allocationData[context.dataIndex].value);
                            const percentage = context.parsed + '%';
                            return `${label}: ${value} (${percentage})`;
                        }
                    }
                }
            }
        }
    };
    
    // Create chart (Chart.js library would be needed)
    // For now, create a simple legend
    updateAllocationLegend(allocationData);
}

/**
 * Initialize performance line chart
 */
function initializePerformanceChart() {
    const canvas = document.getElementById('performanceChart');
    if (!canvas) {
        console.warn('Performance chart canvas not found');
        return;
    }
    
    // For now, just show a placeholder message
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#f8f9fa';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#6c757d';
    ctx.font = '16px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('Performance Chart', canvas.width / 2, canvas.height / 2 - 10);
    ctx.font = '12px Arial';
    ctx.fillText('Chart.js integration pending', canvas.width / 2, canvas.height / 2 + 10);
}

/**
 * Update allocation legend
 */
function updateAllocationLegend(data) {
    const legend = document.getElementById('allocationLegend');
    if (!legend) return;
    
    const colors = [
        '#007bff', '#28a745', '#ffc107', '#dc3545', 
        '#6f42c1', '#fd7e14', '#20c997', '#6c757d'
    ];
    
    let legendHTML = '';
    data.forEach((item, index) => {
        const color = colors[index % colors.length];
        legendHTML += `
            <div class="legend-item">
                <div class="legend-color" style="background-color: ${color};"></div>
                <div class="legend-label">${item.name}</div>
                <div class="legend-value">${item.percentage}%</div>
            </div>
        `;
    });
    
    legend.innerHTML = legendHTML;
}

/**
 * Initialize event listeners
 */
function initializeEventListeners() {
    // Allocation view selector
    const allocationView = document.getElementById('allocationView');
    if (allocationView) {
        allocationView.addEventListener('change', function() {
            currentAllocationView = this.value;
            updateAllocationChart();
        });
    }
    
    // Performance timeframe selector
    const performanceTimeframe = document.getElementById('performanceTimeframe');
    if (performanceTimeframe) {
        performanceTimeframe.addEventListener('change', function() {
            updatePerformanceChart(this.value);
        });
    }
    
    // Refresh button (if added)
    const refreshBtn = document.getElementById('refreshDashboard');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            refreshDashboardData();
        });
    }
}

/**
 * Update allocation chart based on selected view
 */
function updateAllocationChart() {
    let data;
    switch (currentAllocationView) {
        case 'country':
            data = window.dashboardData.allocation.by_country;
            break;
        case 'asset_class':
            data = window.dashboardData.allocation.by_asset_class;
            break;
        default:
            data = window.dashboardData.allocation.by_sector;
    }
    
    updateAllocationLegend(data);
    // TODO: Update actual chart when Chart.js is integrated
}

/**
 * Update performance chart based on timeframe
 */
function updatePerformanceChart(timeframe) {
    // TODO: Filter performance data based on timeframe
    console.log('Updating performance chart for timeframe:', timeframe);
}

/**
 * Refresh dashboard data via AJAX
 */
async function refreshDashboardData() {
    try {
        showLoadingIndicator();
        
        const response = await makeRequest('/public/api/dashboard.php', {
            method: 'GET'
        });
        
        if (response.success) {
            // Update global data
            window.dashboardData = response.data;
            
            // Update UI elements
            updateMetricCards(response.data.portfolio_metrics);
            updateRecentDividends(response.data.recent_dividends);
            updateUpcomingDividends(response.data.upcoming_dividends);
            updateAllocationChart();
            
            showAlert('Dashboard updated successfully', 'success');
        } else {
            showAlert('Failed to refresh dashboard data', 'error');
        }
        
        updateLastRefreshTime();
        
    } catch (error) {
        console.error('Dashboard refresh error:', error);
        showAlert('Error refreshing dashboard', 'error');
    } finally {
        hideLoadingIndicator();
    }
}

/**
 * Update metric cards with new data
 */
function updateMetricCards(metrics) {
    // Update portfolio value
    const valueElement = document.querySelector('.metric-card .metric-value');
    if (valueElement && metrics.total_value) {
        valueElement.textContent = formatCurrency(metrics.total_value) + ' SEK';
    }
    
    // Update daily change
    const changeElement = document.querySelector('.metric-change');
    if (changeElement && metrics.daily_change !== undefined) {
        const isPositive = metrics.daily_change >= 0;
        changeElement.className = `metric-change ${isPositive ? 'positive' : 'negative'}`;
        
        const arrow = isPositive ? 'up' : 'down';
        changeElement.innerHTML = `
            <i class="fas fa-arrow-${arrow}"></i>
            ${formatNumber(metrics.daily_change, 2)} SEK 
            (${formatNumber(metrics.daily_change_percent, 2)}%) today
        `;
    }
    
    // TODO: Update other metric cards
}

/**
 * Update recent dividends table
 */
function updateRecentDividends(dividends) {
    const tbody = document.querySelector('.dashboard-widget:nth-of-type(2) tbody');
    if (!tbody || !dividends) return;
    
    let html = '';
    dividends.forEach(dividend => {
        html += `
            <tr>
                <td>${formatDate(dividend.date, 'M j')}</td>
                <td>
                    <strong>${dividend.symbol}</strong>
                    <div class="text-muted small">${dividend.company}</div>
                </td>
                <td>${formatNumber(dividend.shares)}</td>
                <td>${dividend.currency} ${formatNumber(dividend.dividend_per_share, 2)}</td>
                <td><strong>${formatNumber(dividend.sek_amount, 2)}</strong></td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

/**
 * Update upcoming dividends table
 */
function updateUpcomingDividends(upcomingDividends) {
    const tbody = document.querySelector('.dashboard-right .table tbody');
    if (!tbody || !upcomingDividends) return;
    
    let html = '';
    upcomingDividends.forEach(upcoming => {
        html += `
            <tr>
                <td>${formatDate(upcoming.ex_date, 'M j')}</td>
                <td>
                    <strong>${upcoming.symbol}</strong>
                    <div class="text-muted small">${upcoming.company.substring(0, 20)}${upcoming.company.length > 20 ? '...' : ''}</div>
                </td>
                <td>${upcoming.currency} ${formatNumber(upcoming.estimated_total, 2)}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

/**
 * Show loading indicator
 */
function showLoadingIndicator() {
    // Add loading state to dashboard
    const dashboard = document.querySelector('.dashboard-container');
    if (dashboard) {
        dashboard.classList.add('loading');
    }
}

/**
 * Hide loading indicator
 */
function hideLoadingIndicator() {
    const dashboard = document.querySelector('.dashboard-container');
    if (dashboard) {
        dashboard.classList.remove('loading');
    }
}

/**
 * Update last refresh time - DISABLED
 */
function updateLastRefreshTime() {
    // This function is disabled to remove the grey timestamp
    // Also remove any existing timestamp element
    const indicator = document.getElementById('lastRefresh');
    if (indicator) {
        indicator.remove();
    }
    return;
}

/**
 * Format date for dashboard display
 */
function formatDate(dateString, format = 'short') {
    const date = new Date(dateString);
    
    switch (format) {
        case 'M j':
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        case 'short':
            return date.toLocaleDateString('sv-SE');
        default:
            return date.toLocaleDateString('sv-SE');
    }
}

/**
 * Export dashboard data to Excel (placeholder)
 */
function exportDashboardData() {
    // TODO: Implement Excel export functionality
    showAlert('Export functionality coming soon', 'info');
}

/**
 * Initialize dividend trends chart
 */
function initializeDividendTrendsChart() {
    const canvas = document.getElementById('dividendTrendsChart');
    if (!canvas) {
        console.warn('Dividend trends chart canvas not found');
        return;
    }
    
    const ctx = canvas.getContext('2d');
    const dividendStats = window.dashboardData.dividendStats;
    
    if (!dividendStats || !dividendStats.monthly_trends || dividendStats.monthly_trends.length === 0) {
        // Show empty state message
        ctx.fillStyle = '#f8f9fa';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#6c757d';
        ctx.font = '16px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('No Dividend Data', canvas.width / 2, canvas.height / 2 - 10);
        ctx.font = '12px Arial';
        ctx.fillText('Import dividends to see trends', canvas.width / 2, canvas.height / 2 + 10);
        return;
    }
    
    // Prepare data for chart
    const monthlyData = dividendStats.monthly_trends;
    const labels = monthlyData.map(item => item.month_name || item.month);
    const amounts = monthlyData.map(item => parseFloat(item.total_amount || 0));
    const payments = monthlyData.map(item => parseInt(item.payment_count || 0));
    
    // Simple canvas chart implementation (without Chart.js for now)
    drawDividendTrendsChart(ctx, canvas, labels, amounts, payments);
}

/**
 * Draw dividend trends chart on canvas
 */
function drawDividendTrendsChart(ctx, canvas, labels, amounts, payments) {
    const padding = 40;
    const chartWidth = canvas.width - (padding * 2);
    const chartHeight = canvas.height - (padding * 2);
    
    // Clear canvas
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Set background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    if (amounts.length === 0) return;
    
    // Find max values for scaling
    const maxAmount = Math.max(...amounts);
    const maxPayments = Math.max(...payments);
    
    if (maxAmount === 0) return;
    
    // Draw grid lines
    ctx.strokeStyle = '#e9ecef';
    ctx.lineWidth = 1;
    
    // Vertical grid lines
    const stepX = chartWidth / (labels.length - 1 || 1);
    for (let i = 0; i < labels.length; i++) {
        const x = padding + (i * stepX);
        ctx.beginPath();
        ctx.moveTo(x, padding);
        ctx.lineTo(x, padding + chartHeight);
        ctx.stroke();
    }
    
    // Horizontal grid lines
    for (let i = 0; i <= 5; i++) {
        const y = padding + (i * chartHeight / 5);
        ctx.beginPath();
        ctx.moveTo(padding, y);
        ctx.lineTo(padding + chartWidth, y);
        ctx.stroke();
    }
    
    // Draw amount line (primary color)
    ctx.strokeStyle = '#00C896';
    ctx.lineWidth = 3;
    ctx.beginPath();
    
    amounts.forEach((amount, index) => {
        const x = padding + (index * stepX);
        const y = padding + chartHeight - ((amount / maxAmount) * chartHeight);
        
        if (index === 0) {
            ctx.moveTo(x, y);
        } else {
            ctx.lineTo(x, y);
        }
    });
    ctx.stroke();
    
    // Draw amount points
    ctx.fillStyle = '#00C896';
    amounts.forEach((amount, index) => {
        const x = padding + (index * stepX);
        const y = padding + chartHeight - ((amount / maxAmount) * chartHeight);
        
        ctx.beginPath();
        ctx.arc(x, y, 4, 0, 2 * Math.PI);
        ctx.fill();
    });
    
    // Draw labels
    ctx.fillStyle = '#6c757d';
    ctx.font = '11px Arial';
    ctx.textAlign = 'center';
    
    labels.forEach((label, index) => {
        const x = padding + (index * stepX);
        const y = canvas.height - 10;
        
        // Rotate and abbreviate long month names
        const shortLabel = label.length > 8 ? label.substring(0, 3) : label;
        ctx.fillText(shortLabel, x, y);
    });
    
    // Draw title
    ctx.fillStyle = '#495057';
    ctx.font = 'bold 14px Arial';
    ctx.textAlign = 'left';
    ctx.fillText('Monthly Dividend Income (SEK)', padding, 20);
    
    // Draw legend
    ctx.fillStyle = '#00C896';
    ctx.fillRect(padding, 25, 15, 3);
    ctx.fillStyle = '#6c757d';
    ctx.font = '12px Arial';
    ctx.fillText('Total Amount', padding + 20, 32);
}

/**
 * Print dashboard (placeholder)
 */
function printDashboard() {
    window.print();
}