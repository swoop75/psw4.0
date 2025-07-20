/**
 * File: assets/js/dividend-logs.js
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\assets\js\dividend-logs.js
 * Description: Dividend logs page JavaScript for PSW 4.0
 */

// Global variables
let currentFilters = {};
let isExporting = false;

/**
 * Initialize dividend logs page
 */
document.addEventListener('DOMContentLoaded', function() {
    initializeDividendLogs();
});

/**
 * Initialize page components
 */
function initializeDividendLogs() {
    console.log('Initializing dividend logs page...');
    
    // Check if required data is available
    if (typeof window.dividendLogsData !== 'undefined') {
        currentFilters = window.dividendLogsData.filters;
    }
    
    initializeEventListeners();
    initializeTableFeatures();
    initializeFilterPersistence();
}

/**
 * Initialize event listeners
 */
function initializeEventListeners() {
    // Form submission with loading state
    const filtersForm = document.querySelector('.filters-form');
    if (filtersForm) {
        filtersForm.addEventListener('submit', function(e) {
            showLoadingState();
        });
    }
    
    // Auto-submit on certain filter changes
    const autoSubmitElements = ['year', 'currency', 'per_page'];
    autoSubmitElements.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', function() {
                debounce(() => {
                    showLoadingState();
                    filtersForm.submit();
                }, 300)();
            });
        }
    });
    
    // Company search with debounce
    const companyInput = document.getElementById('company');
    if (companyInput) {
        companyInput.addEventListener('input', debounce(function() {
            // Could implement live search suggestions here
        }, 500));
    }
    
    // Date range validation
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    if (dateFrom && dateTo) {
        dateFrom.addEventListener('change', validateDateRange);
        dateTo.addEventListener('change', validateDateRange);
    }
    
    // Amount range validation
    const amountMin = document.getElementById('amount_min');
    const amountMax = document.getElementById('amount_max');
    if (amountMin && amountMax) {
        amountMin.addEventListener('change', validateAmountRange);
        amountMax.addEventListener('change', validateAmountRange);
    }
}

/**
 * Initialize table features
 */
function initializeTableFeatures() {
    // Add hover effects to table rows
    const tableRows = document.querySelectorAll('.dividends-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('click', function() {
            // Could implement row selection or detail view
            this.classList.toggle('selected');
        });
    });
    
    // Initialize tooltips for truncated company names
    initializeTooltips();
}

/**
 * Initialize filter persistence
 */
function initializeFilterPersistence() {
    // Save current filters to localStorage
    if (Object.keys(currentFilters).length > 0) {
        localStorage.setItem('dividendLogsFilters', JSON.stringify(currentFilters));
    }
    
    // Add clear all filters functionality
    const clearAllBtn = document.querySelector('a[href$="logs_dividends.php"]');
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function() {
            localStorage.removeItem('dividendLogsFilters');
        });
    }
}

/**
 * Initialize tooltips
 */
function initializeTooltips() {
    const companyNames = document.querySelectorAll('.company-info strong');
    companyNames.forEach(element => {
        if (element.scrollWidth > element.clientWidth) {
            element.title = element.textContent;
        }
    });
}

/**
 * Change sorting
 */
function changeSorting() {
    const sortSelect = document.getElementById('sortBy');
    if (!sortSelect) return;
    
    const [sortField, sortOrder] = sortSelect.value.split('_');
    
    // Update current URL with new sort parameters
    const url = new URL(window.location);
    url.searchParams.set('sort', sortField);
    url.searchParams.set('order', sortOrder);
    url.searchParams.set('page', '1'); // Reset to first page
    
    showLoadingState();
    window.location.href = url.toString();
}

/**
 * Export dividend logs to CSV
 */
async function exportDividendLogs() {
    if (isExporting) return;
    
    try {
        isExporting = true;
        const exportBtn = document.querySelector('[onclick="exportDividendLogs()"]');
        
        if (exportBtn) {
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
            exportBtn.disabled = true;
        }
        
        // Build export URL with current filters
        const url = new URL('/public/api/dividend-logs-export.php', window.location.origin);
        Object.keys(currentFilters).forEach(key => {
            if (currentFilters[key]) {
                url.searchParams.set(key, currentFilters[key]);
            }
        });
        
        // Create a temporary download link
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
        
        showAlert('Dividend logs exported successfully', 'success');
        
    } catch (error) {
        console.error('Export error:', error);
        showAlert('Failed to export dividend logs', 'error');
    } finally {
        isExporting = false;
        const exportBtn = document.querySelector('[onclick="exportDividendLogs()"]');
        if (exportBtn) {
            exportBtn.innerHTML = '<i class="fas fa-download"></i> Export CSV';
            exportBtn.disabled = false;
        }
    }
}

/**
 * Print dividend logs
 */
function printDividendLogs() {
    // Add print-specific styles and trigger print
    window.print();
}

/**
 * Validate date range
 */
function validateDateRange() {
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    if (!dateFrom || !dateTo) return;
    
    const fromDate = new Date(dateFrom.value);
    const toDate = new Date(dateTo.value);
    
    if (dateFrom.value && dateTo.value && fromDate > toDate) {
        dateTo.setCustomValidity('End date must be after start date');
        showAlert('End date must be after start date', 'warning');
    } else {
        dateTo.setCustomValidity('');
    }
}

/**
 * Validate amount range
 */
function validateAmountRange() {
    const amountMin = document.getElementById('amount_min');
    const amountMax = document.getElementById('amount_max');
    
    if (!amountMin || !amountMax) return;
    
    const minAmount = parseFloat(amountMin.value);
    const maxAmount = parseFloat(amountMax.value);
    
    if (amountMin.value && amountMax.value && minAmount > maxAmount) {
        amountMax.setCustomValidity('Maximum amount must be greater than minimum amount');
        showAlert('Maximum amount must be greater than minimum amount', 'warning');
    } else {
        amountMax.setCustomValidity('');
    }
}

/**
 * Show loading state
 */
function showLoadingState() {
    const container = document.querySelector('.dividend-logs-container');
    if (container) {
        container.classList.add('loading');
    }
    
    // Show loading overlay
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.innerHTML = `
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Loading dividend logs...</p>
        </div>
    `;
    document.body.appendChild(overlay);
}

/**
 * Quick filter functions
 */
function filterByYear(year) {
    const yearSelect = document.getElementById('year');
    if (yearSelect) {
        yearSelect.value = year;
        document.querySelector('.filters-form').submit();
    }
}

function filterByCurrency(currency) {
    const currencySelect = document.getElementById('currency');
    if (currencySelect) {
        currencySelect.value = currency;
        document.querySelector('.filters-form').submit();
    }
}

function filterByCompany(companyName) {
    const companyInput = document.getElementById('company');
    if (companyInput) {
        companyInput.value = companyName;
        document.querySelector('.filters-form').submit();
    }
}

/**
 * Advanced filtering functions
 */
function filterByAmountRange(min, max) {
    const minInput = document.getElementById('amount_min');
    const maxInput = document.getElementById('amount_max');
    
    if (minInput) minInput.value = min || '';
    if (maxInput) maxInput.value = max || '';
    
    document.querySelector('.filters-form').submit();
}

function filterByDateRange(from, to) {
    const fromInput = document.getElementById('date_from');
    const toInput = document.getElementById('date_to');
    
    if (fromInput) fromInput.value = from || '';
    if (toInput) toInput.value = to || '';
    
    document.querySelector('.filters-form').submit();
}

/**
 * Get selected table rows
 */
function getSelectedRows() {
    return document.querySelectorAll('.dividends-table tbody tr.selected');
}

/**
 * Calculate selection totals
 */
function calculateSelectionTotals() {
    const selectedRows = getSelectedRows();
    let total = 0;
    let tax = 0;
    let net = 0;
    
    selectedRows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 10) {
            const totalCell = cells[7].textContent.replace(/[^\d.-]/g, '');
            const taxCell = cells[8].textContent.replace(/[^\d.-]/g, '');
            const netCell = cells[9].textContent.replace(/[^\d.-]/g, '');
            
            total += parseFloat(totalCell) || 0;
            tax += parseFloat(taxCell) || 0;
            net += parseFloat(netCell) || 0;
        }
    });
    
    return { total, tax, net, count: selectedRows.length };
}

/**
 * Show selection summary
 */
function showSelectionSummary() {
    const totals = calculateSelectionTotals();
    
    if (totals.count > 0) {
        const message = `Selected ${totals.count} payments:\n` +
                       `Total: ${formatCurrency(totals.total)} SEK\n` +
                       `Tax: ${formatCurrency(totals.tax)} SEK\n` +
                       `Net: ${formatCurrency(totals.net)} SEK`;
        
        showAlert(message, 'info');
    } else {
        showAlert('No rows selected', 'warning');
    }
}

/**
 * Keyboard shortcuts
 */
document.addEventListener('keydown', function(e) {
    // Only trigger if not in an input field
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') {
        return;
    }
    
    switch (e.key) {
        case 'e':
        case 'E':
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                exportDividendLogs();
            }
            break;
            
        case 'p':
        case 'P':
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                printDividendLogs();
            }
            break;
            
        case 'f':
        case 'F':
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                const companyInput = document.getElementById('company');
                if (companyInput) {
                    companyInput.focus();
                }
            }
            break;
    }
});

/**
 * Format currency for display
 */
function formatCurrency(amount, currency = 'SEK') {
    return new Intl.NumberFormat('sv-SE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

/**
 * Utility: Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}