/**
 * Tooltip functionality for company details
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeTooltips();
});

/**
 * Initialize tooltip functionality
 */
function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip="true"]');
    
    tooltipElements.forEach(element => {
        // Create tooltip content
        const tooltip = createTooltipContent(element);
        element.appendChild(tooltip);
        
        // Simple and reliable event listeners
        element.addEventListener('mouseenter', function(e) {
            showTooltip.call(this, e);
        });
        
        element.addEventListener('mouseleave', function(e) {
            // Small delay to allow moving to modal
            setTimeout(() => {
                const modal = document.querySelector('.tooltip-modal-container');
                if (modal && !modal.matches(':hover')) {
                    hideTooltip.call(this, e);
                }
            }, 200);
        });
    });
}

/**
 * Create tooltip HTML content
 */
function createTooltipContent(element) {
    const tooltip = document.createElement('div');
    tooltip.className = 'company-tooltip';
    
    // Store reference to create backdrop later
    tooltip.dataset.hasBackdrop = 'true';
    
    const company = element.dataset.company || 'N/A';
    const ticker = element.dataset.ticker || 'N/A';
    const isin = element.dataset.isin || 'N/A';
    const strategyGroup = element.dataset.strategyGroup || 'No Strategy';
    const strategyId = element.dataset.strategyId || 'N/A';
    const newGroup = element.dataset.newGroup || 'No Group';
    const broker = element.dataset.broker || 'No Broker';
    
    // Format strategy display with group number
    const strategyDisplay = (strategyGroup !== 'No Strategy' && strategyId !== 'N/A' && strategyId !== '') 
        ? `Group ${strategyId}: ${strategyGroup}`
        : strategyGroup === 'No Strategy' ? 'No Strategy Assigned' : strategyGroup;
    const yield_ = element.dataset.yield || 'N/A';
    const country = element.dataset.country || 'N/A';
    const status = element.dataset.status || 'No Status';
    const comments = element.dataset.comments || 'No comments';
    const inspiration = element.dataset.inspiration || 'No inspiration noted';
    
    tooltip.innerHTML = `
        <div class="tooltip-header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div class="tooltip-company-name">${escapeHtml(company)}</div>
                    <span class="tooltip-ticker">${escapeHtml(ticker)}</span>
                </div>
                <button class="tooltip-close" onclick="this.parentElement.parentElement.parentElement.parentElement.style.display='none'" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666; margin-left: 20px;">&times;</button>
            </div>
        </div>
        
        <div class="tooltip-section">
            <div class="tooltip-section-title">Organization</div>
            <div class="tooltip-row">
                <span class="tooltip-label">ISIN:</span>
                <span class="tooltip-value mono">${escapeHtml(isin)}</span>
            </div>
            <div class="tooltip-row">
                <span class="tooltip-label">Country:</span>
                <span class="tooltip-value">${escapeHtml(country)}</span>
            </div>
            <div class="tooltip-row">
                <span class="tooltip-label">Strategy:</span>
                <span class="tooltip-value tooltip-strategy">${escapeHtml(strategyDisplay)}</span>
            </div>
            <div class="tooltip-row">
                <span class="tooltip-label">New Group:</span>
                <span class="tooltip-value tooltip-group mono">${escapeHtml(newGroup)}</span>
            </div>
            <div class="tooltip-row">
                <span class="tooltip-label">Broker:</span>
                <span class="tooltip-value tooltip-broker">${escapeHtml(broker)}</span>
            </div>
        </div>
        
        <div class="tooltip-section">
            <div class="tooltip-section-title">Investment Details</div>
            <div class="tooltip-row">
                <span class="tooltip-label">Yield:</span>
                <span class="tooltip-value tooltip-yield">${escapeHtml(yield_)}</span>
            </div>
            <div class="tooltip-row">
                <span class="tooltip-label">Status:</span>
                <span class="tooltip-value">
                    <span class="tooltip-status">${escapeHtml(status)}</span>
                </span>
            </div>
        </div>
        
        ${comments !== 'No comments' ? `
        <div class="tooltip-section">
            <div class="tooltip-section-title">Comments</div>
            <div class="tooltip-text-content">${escapeHtml(comments)}</div>
        </div>
        ` : ''}
        
        ${inspiration !== 'No inspiration noted' ? `
        <div class="tooltip-section">
            <div class="tooltip-section-title">Inspiration</div>
            <div class="tooltip-text-content">${escapeHtml(inspiration)}</div>
        </div>
        ` : ''}
    `;
    
    return tooltip;
}

/**
 * Show modal tooltip
 */
function showTooltip(event) {
    const tooltip = this.querySelector('.company-tooltip');
    if (!tooltip) return;
    
    // Hide the entire page body content
    document.body.style.overflow = 'hidden';
    
    // Create and show modal container
    let modalContainer = document.querySelector('.tooltip-modal-container');
    if (!modalContainer) {
        modalContainer = document.createElement('div');
        modalContainer.className = 'tooltip-modal-container';
        modalContainer.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.75);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 1;
            visibility: visible;
        `;
        document.body.appendChild(modalContainer);
    }
    
    // Clear any existing content and add tooltip
    modalContainer.innerHTML = '';
    modalContainer.appendChild(tooltip.cloneNode(true));
    const modalTooltip = modalContainer.querySelector('.company-tooltip');
    
    // Simple mouse leave detection
    modalContainer.addEventListener('mouseleave', function(e) {
        setTimeout(() => {
            hideTooltip();
        }, 100);
    });
    
    modalTooltip.style.cssText = `
        position: relative !important;
        top: auto !important;
        left: auto !important;
        transform: none !important;
        z-index: auto !important;
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto !important;
        background: #ffffff !important;
        border: 3px solid #2d3748 !important;
        border-radius: 16px !important;
        padding: 32px !important;
        width: 520px !important;
        max-height: 85vh !important;
        color: #1f2937 !important;
        font-size: 14px !important;
        line-height: 1.6 !important;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
        box-shadow: 0 32px 64px rgba(0, 0, 0, 0.3), 0 8px 16px rgba(0, 0, 0, 0.2) !important;
        overflow-y: auto !important;
        display: block !important;
        margin: 0 !important;
    `;
    
    // Debug logging
    console.log('Tooltip shown:', tooltip);
    console.log('Tooltip styles:', {
        opacity: tooltip.style.opacity,
        visibility: tooltip.style.visibility,
        zIndex: tooltip.style.zIndex,
        transform: tooltip.style.transform
    });
    
    // Add click-to-close functionality
    tooltip.addEventListener('click', function(e) {
        if (e.target === tooltip) {
            hideTooltip.call(tooltip.parentElement);
        }
    });
}

/**
 * Hide modal tooltip
 */
function hideTooltip(event) {
    // Remove modal container completely
    const modalContainer = document.querySelector('.tooltip-modal-container');
    if (modalContainer) {
        modalContainer.remove();
    }
    
    // Restore body overflow
    document.body.style.overflow = '';
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Update tooltip content dynamically (useful for AJAX updates)
 */
function updateTooltip(element, newData) {
    const tooltip = element.querySelector('.company-tooltip');
    if (!tooltip) return;
    
    // Update data attributes
    Object.keys(newData).forEach(key => {
        element.dataset[key] = newData[key];
    });
    
    // Remove old tooltip and create new one
    tooltip.remove();
    const newTooltip = createTooltipContent(element);
    element.appendChild(newTooltip);
}

/**
 * Refresh all tooltips (useful after table updates)
 */
function refreshTooltips() {
    // Remove existing tooltips
    document.querySelectorAll('.company-tooltip').forEach(tooltip => {
        tooltip.remove();
    });
    
    // Reinitialize
    initializeTooltips();
}

// Export functions for external use
window.tooltipUtils = {
    updateTooltip,
    refreshTooltips,
    initializeTooltips
};