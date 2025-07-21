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
        
        // Add event listeners
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

/**
 * Create tooltip HTML content
 */
function createTooltipContent(element) {
    const tooltip = document.createElement('div');
    tooltip.className = 'company-tooltip';
    
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
            <div class="tooltip-company-name">${escapeHtml(company)}</div>
            <span class="tooltip-ticker">${escapeHtml(ticker)}</span>
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
 * Show tooltip with smart positioning
 */
function showTooltip(event) {
    const tooltip = this.querySelector('.company-tooltip');
    if (!tooltip) return;
    
    // Show tooltip first to get dimensions
    tooltip.style.opacity = '1';
    tooltip.style.visibility = 'visible';
    
    // Smart positioning based on screen location
    setTimeout(() => {
        const tooltipRect = tooltip.getBoundingClientRect();
        const parentRect = this.getBoundingClientRect();
        const windowWidth = window.innerWidth;
        const windowHeight = window.innerHeight;
        
        // Reset all position classes
        tooltip.classList.remove('adjust-position', 'position-above', 'position-below', 'position-left', 'position-right');
        
        // Determine horizontal positioning
        const spaceRight = windowWidth - parentRect.right;
        const spaceLeft = parentRect.left;
        
        if (spaceRight < tooltipRect.width + 20 && spaceLeft > tooltipRect.width + 20) {
            // Not enough space on right, move to left
            tooltip.classList.add('position-left');
        } else {
            // Default right positioning
            tooltip.classList.add('position-right');
        }
        
        // Determine vertical positioning
        const spaceBelow = windowHeight - parentRect.bottom;
        const spaceAbove = parentRect.top;
        
        if (spaceBelow < tooltipRect.height + 20 && spaceAbove > tooltipRect.height + 20) {
            // Not enough space below, move above
            tooltip.classList.add('position-above');
        } else {
            // Default below positioning
            tooltip.classList.add('position-below');
        }
        
        // Fine-tune vertical position to keep tooltip on screen
        const currentTop = tooltipRect.top;
        const currentBottom = tooltipRect.bottom;
        
        if (currentBottom > windowHeight - 20) {
            // Tooltip goes below screen, adjust upward
            const adjustment = currentBottom - (windowHeight - 20);
            tooltip.style.top = `-${adjustment}px`;
        } else if (currentTop < 20) {
            // Tooltip goes above screen, adjust downward
            const adjustment = 20 - currentTop;
            tooltip.style.top = `${adjustment}px`;
        } else {
            tooltip.style.top = '0';
        }
    }, 10);
}

/**
 * Hide tooltip
 */
function hideTooltip(event) {
    const tooltip = this.querySelector('.company-tooltip');
    if (!tooltip) return;
    
    tooltip.style.opacity = '0';
    tooltip.style.visibility = 'hidden';
    tooltip.style.top = '';
    tooltip.classList.remove('adjust-position', 'position-above', 'position-below', 'position-left', 'position-right');
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