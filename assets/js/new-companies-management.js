/**
 * Buylist Management JavaScript
 */

// JavaScript file for New Companies Management

// Global variables
let deleteEntryId = '';
let deleteMasterlistId = '';
let searchTimeout;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    initializeSearch();
    initializeCheckboxDropdowns();
    
    // Initialize Börsdata fields state (since Börsdata is now default)
    if (typeof toggleBorsdataFields === 'function') {
        toggleBorsdataFields();
    }
});

/**
 * Initialize event listeners
 */
function initializeEventListeners() {
    // Search input
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', debounceSearch);
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilters();
            }
        });
    }

    // Form submissions
    const entryForm = document.getElementById('entryForm');
    if (entryForm) {
        entryForm.addEventListener('submit', handleEntryFormSubmit);
    }

    const masterlistForm = document.getElementById('masterlistForm');
    if (masterlistForm) {
        masterlistForm.addEventListener('submit', handleMasterlistFormSubmit);
    }

    // Modal close on outside click
    window.addEventListener('click', function(event) {
        const entryModal = document.getElementById('entryModal');
        const deleteModal = document.getElementById('deleteModal');
        const masterlistModal = document.getElementById('masterlistModal');
        
        if (event.target === entryModal) {
            closeModal();
        }
        if (event.target === deleteModal) {
            closeDeleteModal();
        }
        if (event.target === masterlistModal) {
            closeMasterlistModal();
        }
    });

    // Escape key to close modals
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
            closeDeleteModal();
            closeMasterlistModal();
        }
    });
}

/**
 * Initialize search functionality
 */
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput && searchInput.value) {
        searchInput.focus();
        searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
    }
}

/**
 * Debounced search function
 */
function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 500);
}

/**
 * Apply filters and search
 */
function applyFilters() {
    const searchInput = document.getElementById('searchInput');
    
    const params = new URLSearchParams();
    
    if (searchInput && searchInput.value.trim()) {
        params.set('search', searchInput.value.trim());
    }
    
    // Handle all checkbox dropdown filters
    const statusValues = getDropdownValues('status');
    if (statusValues.length > 0) {
        params.set('status_id', statusValues.join(','));
    }
    
    const countryValues = getDropdownValues('country');
    if (countryValues.length > 0) {
        params.set('country', countryValues.join(','));
    }
    
    const strategyValues = getDropdownValues('strategy');
    if (strategyValues.length > 0) {
        params.set('strategy_group_id', strategyValues.join(','));
    }
    
    const brokerValues = getDropdownValues('broker');
    if (brokerValues.length > 0) {
        params.set('broker_id', brokerValues.join(','));
    }
    
    // Reset to page 1 when filtering
    params.set('page', '1');
    
    const url = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
    window.location.href = url;
}

/**
 * Refresh data
 */
function refreshData() {
    window.location.reload();
}

/**
 * Show add entry modal
 */
function showAddModal() {
    const modal = document.getElementById('entryModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalAction = document.getElementById('modalAction');
    const submitText = document.getElementById('submitText');
    const form = document.getElementById('entryForm');
    
    if (!modal) return;
    
    // Reset form
    if (form) form.reset();
    
    // Set modal for create mode
    if (modalTitle) modalTitle.textContent = 'Add New Company';
    if (modalAction) modalAction.value = 'add';
    if (submitText) submitText.textContent = 'Add New Company';
    
    // Show modal with both display and show class for opacity
    modal.style.display = 'block';
    modal.classList.add('show');
    
    // Focus ISIN field (since Börsdata mode is default)
    setTimeout(() => {
        const isinField = document.getElementById('isin');
        if (isinField) isinField.focus();
    }, 100);
}

/**
 * Edit entry
 */
function editEntry(companyId) {
    console.log('Edit entry called with ID:', companyId); // Debug
    
    const modal = document.getElementById('entryModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalAction = document.getElementById('modalAction');
    const submitText = document.getElementById('submitText');
    
    if (!modal || !modalTitle || !modalAction || !submitText) {
        console.error('Modal elements missing:', { modal: !!modal, modalTitle: !!modalTitle, modalAction: !!modalAction, submitText: !!submitText });
        alert('Error: Modal elements not found');
        return;
    }
    
    // Set modal for edit mode
    modalTitle.textContent = 'Edit Company Entry';
    modalAction.value = 'update';
    submitText.textContent = 'Update Entry';
    
    // Get entry data via AJAX
    const formData = new FormData();
    formData.append('action', 'get_entry');
    formData.append('new_company_id', companyId);
    
    const csrfToken = document.querySelector('input[name="csrf_token"]');
    if (!csrfToken) {
        console.error('CSRF token not found');
        alert('Error: Security token not found');
        return;
    }
    formData.append('csrf_token', csrfToken.value);
    
    console.log('Sending AJAX request for company ID:', companyId); // Debug
    showLoading('Loading entry data...');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status); // Debug
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data); // Debug
        hideLoading();
        
        if (data.success && data.entry) {
            console.log('Entry data received:', data.entry); // Debug
            populateEntryForm(data.entry);
            
            // Store company ID
            const companyIdField = document.getElementById('companyId');
            if (companyIdField) {
                companyIdField.value = companyId;
            } else {
                console.error('Company ID field not found');
            }
            
            modal.style.display = 'block';
            modal.classList.add('show');
        } else {
            console.error('Failed to load entry:', data); // Debug
            showAlert('Error loading entry data: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Error loading entry data: ' + error.message, 'error');
    });
}

/**
 * Populate entry form with data
 */
function populateEntryForm(entry) {
    const fields = [
        'company', 'ticker', 'country_name', 'currency', 'exchange', 'isin', 'business_description',
        'status_id', 'priority_level', 'target_price', 'target_quantity', 'notes', 'research_notes',
        'expected_dividend_yield', 'pe_ratio', 'price_to_book', 'debt_to_equity', 'roe',
        'analyst_rating', 'risk_level', 'sector', 'market_cap_category', 'target_allocation_percent',
        'stop_loss_price', 'take_profit_price', 'entry_strategy', 'exit_strategy',
        'last_analysis_date', 'next_review_date', 'price_alert_enabled', 'price_alert_target',
        'yield', 'new_companies_status_id', 'strategy_group_id', 'new_group_id', 'broker_id',
        'inspiration', 'comments'
    ];
    
    fields.forEach(field => {
        const element = document.getElementById(field);
        if (element && entry[field] !== undefined && entry[field] !== null) {
            if (element.type === 'checkbox') {
                element.checked = !!entry[field];
            } else {
                element.value = entry[field];
            }
        }
    });
}

/**
 * Close entry modal
 */
function closeModal() {
    const modal = document.getElementById('entryModal');
    modal.style.display = 'none';
    modal.classList.remove('show');
}

/**
 * Handle entry form submission
 */
function handleEntryFormSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const action = formData.get('action');
    
    console.log('Form submission - Action:', action); // Debug
    console.log('Form data entries:'); // Debug
    for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
    }
    
    // Validate required fields based on Börsdata mode
    const borsdataToggle = document.getElementById('borsdata_available');
    const isBorsdataMode = borsdataToggle && borsdataToggle.value === '1';
    
    let requiredFields = [];
    if (isBorsdataMode) {
        // In Börsdata mode, only ISIN is required
        requiredFields = ['isin'];
    } else {
        // In manual mode, company and ticker are required
        requiredFields = ['company', 'ticker'];
    }
    
    let isValid = true;
    
    for (const field of requiredFields) {
        const element = document.getElementById(field);
        if (!element || !element.value.trim()) {
            const fieldName = field === 'isin' ? 'ISIN' : 
                             field === 'company' ? 'Company Name' :
                             field === 'ticker' ? 'Ticker' : 
                             field.replace('_', ' ');
            showAlert(`Please fill in the ${fieldName} field`, 'error');
            if (element) element.focus();
            isValid = false;
            break;
        }
    }
    
    if (!isValid) return;
    
    // Additional validation for update action
    if (action === 'update') {
        const companyId = formData.get('new_company_id');
        console.log('Update - Company ID:', companyId); // Debug
        if (!companyId) {
            console.error('No company ID found for update');
            showAlert('Error: Company ID is missing', 'error');
            return;
        }
    }
    
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    // Show loading state
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => {
        console.log('Update response status:', response.status); // Debug
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        console.log('Raw response text:', text); // Debug
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned invalid JSON. Check console for details.');
        }
    })
    .then(data => {
        console.log('Parsed response data:', data); // Debug
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
        
        if (data.success) {
            showAlert(data.message || 'Entry saved successfully', 'success');
            closeModal();
            
            // Reload page to show changes
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            console.error('Update failed:', data); // Debug
            showAlert('Error: ' + (data.message || 'Failed to save entry'), 'error');
        }
    })
    .catch(error => {
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
        showAlert('Error: ' + error.message, 'error');
    });
}

/**
 * Delete entry
 */
function deleteEntry(companyId, companyName) {
    deleteEntryId = companyId;
    document.getElementById('deleteCompanyName').textContent = companyName;
    document.getElementById('deleteModal').style.display = 'block';
}

/**
 * Close delete modal
 */
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    deleteEntryId = '';
}

/**
 * Confirm delete
 */
function confirmDelete() {
    if (!deleteEntryId) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('new_company_id', deleteEntryId);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    const deleteButton = document.querySelector('#deleteModal .btn-danger');
    const originalText = deleteButton.innerHTML;
    
    deleteButton.disabled = true;
    deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Removing...';
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        deleteButton.disabled = false;
        deleteButton.innerHTML = originalText;
        
        if (data.success) {
            showAlert(data.message || 'Entry removed successfully', 'success');
            closeDeleteModal();
            
            // Reload page to show changes
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert('Error: ' + (data.message || 'Failed to remove entry'), 'error');
        }
    })
    .catch(error => {
        deleteButton.disabled = false;
        deleteButton.innerHTML = originalText;
        showAlert('Error: ' + error.message, 'error');
    });
}

/**
 * Add to masterlist
 */
function addToMasterlist(companyId, companyName) {
    deleteMasterlistId = companyId;
    document.getElementById('masterlistCompanyName').textContent = companyName;
    document.getElementById('masterlistCompanyId').value = companyId;
    document.getElementById('masterlistModal').style.display = 'block';
}

/**
 * Close masterlist modal
 */
function closeMasterlistModal() {
    document.getElementById('masterlistModal').style.display = 'none';
    deleteMasterlistId = '';
}

/**
 * Handle masterlist form submission
 */
function handleMasterlistFormSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    // Show loading state
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
        
        if (data.success) {
            showAlert(data.message || 'Company added to masterlist successfully', 'success');
            closeMasterlistModal();
            
            // Reload page to show changes
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert('Error: ' + (data.message || 'Failed to add to masterlist'), 'error');
        }
    })
    .catch(error => {
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
        showAlert('Error: ' + error.message, 'error');
    });
}

// Tab functionality removed - using simplified single form

/**
 * Show alert message
 */
function showAlert(message, type = 'info') {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create new alert
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    
    const icon = type === 'success' ? 'check-circle' : 
                 type === 'error' ? 'exclamation-triangle' : 
                 'info-circle';
    
    alert.innerHTML = `
        <i class="fas fa-${icon}"></i>
        ${message}
    `;
    
    // Insert after header
    const header = document.querySelector('.page-header');
    header.parentNode.insertBefore(alert, header.nextSibling);
    
    // Auto-hide success messages
    if (type === 'success') {
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
    
    // Scroll to alert
    alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/**
 * Show loading message
 */
function showLoading(message = 'Loading...') {
    showAlert(`<i class="fas fa-spinner fa-spin"></i> ${message}`, 'info');
}

/**
 * Hide loading message
 */
function hideLoading() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert.innerHTML.includes('fa-spinner')) {
            alert.remove();
        }
    });
}

/**
 * Format currency
 */
function formatCurrency(amount, currency = 'SEK') {
    return new Intl.NumberFormat('sv-SE', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2
    }).format(amount);
}

/**
 * Format date
 */
function formatDate(dateString) {
    if (!dateString) return '-';
    
    const date = new Date(dateString);
    return date.toLocaleDateString('sv-SE');
}

/**
 * Initialize checkbox dropdown functionality
 */
function initializeCheckboxDropdowns() {
    const dropdowns = document.querySelectorAll('.checkbox-dropdown');
    
    dropdowns.forEach(dropdown => {
        const button = dropdown.querySelector('.dropdown-button');
        const content = dropdown.querySelector('.dropdown-content');
        const checkboxes = dropdown.querySelectorAll('input[type="checkbox"]');
        const textElement = button.querySelector('.dropdown-text');
        const arrow = button.querySelector('.arrow');
        
        // Toggle dropdown on button click
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            
            // Close other dropdowns
            dropdowns.forEach(otherDropdown => {
                if (otherDropdown !== dropdown) {
                    otherDropdown.querySelector('.dropdown-button').classList.remove('open');
                    otherDropdown.querySelector('.dropdown-content').classList.remove('show');
                }
            });
            
            // Toggle current dropdown
            button.classList.toggle('open');
            content.classList.toggle('show');
        });
        
        // Handle checkbox changes
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateDropdownText(dropdown);
                applyFilters();
            });
        });
        
        // Prevent dropdown from closing when clicking inside content
        content.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        // Initialize text
        updateDropdownText(dropdown);
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        dropdowns.forEach(dropdown => {
            dropdown.querySelector('.dropdown-button').classList.remove('open');
            dropdown.querySelector('.dropdown-content').classList.remove('show');
        });
    });
}

/**
 * Update dropdown button text based on selected checkboxes
 */
function updateDropdownText(dropdown) {
    const button = dropdown.querySelector('.dropdown-button');
    const textElement = button.querySelector('.dropdown-text');
    const checkboxes = dropdown.querySelectorAll('input[type="checkbox"]:checked');
    const filterType = dropdown.dataset.filter;
    
    if (checkboxes.length === 0) {
        switch(filterType) {
            case 'status':
                textElement.innerHTML = 'All Statuses';
                textElement.className = 'dropdown-text dropdown-placeholder';
                break;
            case 'country':
                textElement.innerHTML = 'All Countries';
                textElement.className = 'dropdown-text dropdown-placeholder';
                break;
            case 'strategy':
                textElement.innerHTML = 'All Strategy Groups';
                textElement.className = 'dropdown-text dropdown-placeholder';
                break;
            case 'broker':
                textElement.innerHTML = 'All Brokers';
                textElement.className = 'dropdown-text dropdown-placeholder';
                break;
            default:
                textElement.innerHTML = 'All Options';
                textElement.className = 'dropdown-text dropdown-placeholder';
        }
    } else if (checkboxes.length === 1) {
        textElement.textContent = checkboxes[0].nextElementSibling.textContent;
        textElement.className = 'dropdown-text dropdown-selected';
    } else {
        textElement.innerHTML = `<span class="selected-count">${checkboxes.length}</span> selected`;
        textElement.className = 'dropdown-text dropdown-selected';
    }
}

/**
 * Get selected values from checkbox dropdown
 */
function getDropdownValues(filterType) {
    const dropdown = document.querySelector(`[data-filter="${filterType}"]`);
    if (!dropdown) return [];
    
    const checkboxes = dropdown.querySelectorAll('input[type="checkbox"]:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

/**
 * Reset to default filters (removes search and custom filters, keeps admin defaults)
 */
function resetToDefaults() {
    // Clear search input
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.value = '';
    }
    
    // Redirect to base URL without any parameters (will load admin defaults)
    window.location.href = window.location.pathname;
}

/**
 * Search all items (remove filters but keep search)
 */
function searchAllItems() {
    const searchInput = document.getElementById('searchInput');
    const searchTerm = searchInput ? searchInput.value.trim() : '';
    
    if (!searchTerm) {
        // If no search term, just reset to defaults
        resetToDefaults();
        return;
    }
    
    // Redirect with only search parameter
    const params = new URLSearchParams();
    params.set('search', searchTerm);
    params.set('page', '1');
    
    window.location.href = window.location.pathname + '?' + params.toString();
}

/**
 * Company Panel Functions - Avanza Style
 */

// Toggle company details panel
function toggleCompanyPanel(button) {
    // Try to find .company-info in the current element first (for the original "more" button)
    let companyInfo = button.closest('.company-info');
    
    // If not found, look for .company-info in the same table row (for action buttons)
    if (!companyInfo) {
        const row = button.closest('tr');
        if (row) {
            companyInfo = row.querySelector('.company-info');
        }
    }
    
    const panel = document.getElementById('companyPanel');
    const backdrop = document.getElementById('companyPanelBackdrop');
    
    if (!panel || !backdrop || !companyInfo) return;
    
    if (panel.classList.contains('open')) {
        closeCompanyPanel();
    } else {
        openCompanyPanel(companyInfo);
    }
}

// Open company panel with data
function openCompanyPanel(companyInfo) {
    try {
        const panel = document.getElementById('companyPanel');
        const backdrop = document.getElementById('companyPanelBackdrop');
        const title = document.getElementById('companyPanelTitle');
        
        if (!panel || !backdrop || !title) {
            console.error('Panel elements missing:', { panel: !!panel, backdrop: !!backdrop, title: !!title });
            return;
        }
        
        const loading = panel.querySelector('.company-panel-loading');
        const dataContent = panel.querySelector('.company-panel-data');
        
        if (!loading || !dataContent) {
            console.error('Panel content elements missing:', { loading: !!loading, dataContent: !!dataContent });
            return;
        }
        
        // Show loading state
        loading.style.display = 'flex';
        dataContent.style.display = 'none';
        
        // Update panel title
        title.textContent = companyInfo.dataset.company || 'Company Details';
        
        // Open panel with animation
        panel.classList.add('open');
        backdrop.classList.add('active');
        
        // Simulate loading delay and populate data
        setTimeout(() => {
            try {
                populateCompanyPanel(companyInfo);
                loading.style.display = 'none';
                dataContent.style.display = 'flex';
            } catch (error) {
                console.error('Error populating panel:', error);
                loading.style.display = 'none';
                dataContent.innerHTML = '<p>Error loading company details</p>';
                dataContent.style.display = 'block';
            }
        }, 500);
    } catch (error) {
        console.error('Error in openCompanyPanel:', error);
    }
}

// Close company panel
function closeCompanyPanel() {
    const panel = document.getElementById('companyPanel');
    const backdrop = document.getElementById('companyPanelBackdrop');
    
    panel.classList.remove('open');
    backdrop.classList.remove('active');
}

// Populate panel with company data
function populateCompanyPanel(companyInfo) {
    const dataContent = document.querySelector('.company-panel-data');
    const data = companyInfo.dataset;
    
    // Determine if data comes from Börsdata
    const isBorsdata = data.isin && data.ticker && data.company !== 'Pending Börsdata lookup';
    const dataSourceBadge = isBorsdata 
        ? '<span class="borsdata-badge" title="Data from Börsdata"><i class="fas fa-database"></i> BD</span>'
        : '<span class="manual-badge" title="Manual entry"><i class="fas fa-user-edit"></i> Manual</span>';
    
    const html = `
        <div class="company-hero">
            <h2>
                ${escapeHtml(data.company)}
                ${dataSourceBadge}
            </h2>
            <div class="ticker">${escapeHtml(data.ticker)}</div>
        </div>
        
        <div class="panel-section">
            <div class="panel-section-title">Investment Details</div>
            <div class="panel-info-grid">
                <div class="panel-info-row">
                    <span class="panel-info-label">ISIN:</span>
                    <span class="panel-info-value">${escapeHtml(data.isin)}</span>
                </div>
                <div class="panel-info-row">
                    <span class="panel-info-label">Country:</span>
                    <span class="panel-info-value">${escapeHtml(data.country)}</span>
                </div>
                <div class="panel-info-row">
                    <span class="panel-info-label">Yield:</span>
                    <span class="panel-info-value panel-badge">${escapeHtml(data.yield)}</span>
                </div>
                <div class="panel-info-row">
                    <span class="panel-info-label">Status:</span>
                    <span class="panel-info-value">${escapeHtml(data.status)}</span>
                </div>
            </div>
        </div>
        
        <div class="panel-section">
            <div class="panel-section-title">Organization</div>
            <div class="panel-info-grid">
                <div class="panel-info-row">
                    <span class="panel-info-label">Strategy Group:</span>
                    <span class="panel-info-value">${escapeHtml(data.strategyGroup && data.strategyGroup !== 'No Strategy' ? data.strategyGroup : 'No Strategy')}</span>
                </div>
                <div class="panel-info-row">
                    <span class="panel-info-label">New Group:</span>
                    <span class="panel-info-value">${escapeHtml(data.newGroup)}</span>
                </div>
                <div class="panel-info-row">
                    <span class="panel-info-label">Broker:</span>
                    <span class="panel-info-value">${escapeHtml(data.broker)}</span>
                </div>
            </div>
        </div>
        
        <div class="panel-section">
            <div class="panel-section-title">Comments</div>
            <div class="panel-text-content">${escapeHtml(data.comments && data.comments !== 'No comments' ? data.comments : 'No comments added')}</div>
        </div>
        
        <div class="panel-section">
            <div class="panel-section-title">Inspiration</div>
            <div class="panel-text-content">${escapeHtml(data.inspiration && data.inspiration !== 'No inspiration noted' ? data.inspiration : 'No inspiration noted')}</div>
        </div>
        
        <div class="panel-actions">
            <button class="panel-action-btn" onclick="editEntry(${data.companyId}); closeCompanyPanel();">
                <i class="fas fa-edit"></i> Edit
            </button>
            <button class="panel-action-btn success" onclick="addToMasterlistFromPanel(${data.companyId}, '${escapeHtml(data.company)}'); closeCompanyPanel();">
                <i class="fas fa-plus-circle"></i> Add to Masterlist
            </button>
            <button class="panel-action-btn danger" onclick="deleteEntryFromPanel(${data.companyId}, '${escapeHtml(data.company)}'); closeCompanyPanel();">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    `;
    
    dataContent.innerHTML = html;
}

// Open company page (placeholder)
// Helper functions for panel actions
function addToMasterlistFromPanel(companyId, companyName) {
    addToMasterlist(companyId, companyName);
}

function deleteEntryFromPanel(companyId, companyName) {
    deleteEntry(companyId, companyName);
}

function openCompanyPage(companyId) {
    // For now, show a placeholder message
    alert('Company page feature coming soon!\nCompany ID: ' + companyId);
    // Future: window.location.href = '/company/' + companyId;
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close panel when clicking outside
document.addEventListener('click', function(event) {
    const panel = document.getElementById('companyPanel');
    const backdrop = document.getElementById('companyPanelBackdrop');
    
    if (panel && panel.classList.contains('open') && 
        !panel.contains(event.target) && 
        !event.target.closest('.company-details-btn')) {
        closeCompanyPanel();
    }
});

// Close panel with escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeCompanyPanel();
        closeAllActionsDropdowns();
    }
});

/* Removed dropdown JavaScript - now using direct panel opening */

/**
 * Toggle Börsdata fields based on selection
 */
function toggleBorsdataFields() {
    const borsdataToggle = document.getElementById('borsdata_available');
    const isEnabled = borsdataToggle.value === '1';
    
    // Get form elements
    const isinField = document.getElementById('isin');
    const companyField = document.getElementById('company');
    const tickerField = document.getElementById('ticker');
    const countryField = document.getElementById('country_name');
    const yieldField = document.getElementById('yield');
    
    // Get help text elements
    const isinHelp = document.getElementById('isinHelp');
    const companyHelp = document.getElementById('companyHelp');
    const tickerHelp = document.getElementById('tickerHelp');
    const countryHelp = document.getElementById('countryHelp');
    const yieldHelp = document.getElementById('yieldHelp');
    
    // Get required indicators
    const isinRequired = document.getElementById('isinRequired');
    const companyRequired = document.getElementById('companyRequired');
    const tickerRequired = document.getElementById('tickerRequired');
    
    if (isEnabled) {
        // Börsdata mode - ISIN required, other fields auto-populated
        isinField.required = true;
        isinRequired.style.display = 'inline';
        isinHelp.textContent = 'Required for auto-population from Börsdata';
        
        // Make other fields not required and show they'll be auto-filled
        companyField.required = false;
        tickerField.required = false;
        companyRequired.style.display = 'none';
        tickerRequired.style.display = 'none';
        
        // Show help text
        companyHelp.style.display = 'block';
        tickerHelp.style.display = 'block';
        countryHelp.style.display = 'block';
        yieldHelp.style.display = 'block';
        
        // Make fields readonly to indicate they'll be auto-filled
        companyField.setAttribute('placeholder', 'Will be auto-filled from Börsdata');
        tickerField.setAttribute('placeholder', 'Will be auto-filled from Börsdata');
        
        // Add CSS class for styling
        companyField.classList.add('borsdata-auto-field');
        tickerField.classList.add('borsdata-auto-field');
        if (countryField) countryField.classList.add('borsdata-auto-field');
        if (yieldField) yieldField.classList.add('borsdata-auto-field');
        
    } else {
        // Manual mode - ISIN optional, other fields required
        isinField.required = false;
        isinRequired.style.display = 'none';
        isinHelp.textContent = 'International Securities Identification Number';
        
        // Make other fields required
        companyField.required = true;
        tickerField.required = true;
        companyRequired.style.display = 'inline';
        tickerRequired.style.display = 'inline';
        
        // Hide help text
        companyHelp.style.display = 'none';
        tickerHelp.style.display = 'none';
        countryHelp.style.display = 'none';
        yieldHelp.style.display = 'none';
        
        // Reset placeholders
        companyField.setAttribute('placeholder', 'e.g., Tesla Inc');
        tickerField.setAttribute('placeholder', 'e.g., TSLA');
        
        // Remove CSS class
        companyField.classList.remove('borsdata-auto-field');
        tickerField.classList.remove('borsdata-auto-field');
        if (countryField) countryField.classList.remove('borsdata-auto-field');
        if (yieldField) yieldField.classList.remove('borsdata-auto-field');
    }
}

/**
 * Preview Börsdata data when ISIN is entered
 */
function previewBorsdataData() {
    const borsdataToggle = document.getElementById('borsdata_available');
    const isinField = document.getElementById('isin');
    const companyField = document.getElementById('company');
    const tickerField = document.getElementById('ticker');
    
    // Only preview if Börsdata mode is enabled and ISIN is provided
    if (borsdataToggle.value !== '1' || !isinField.value || isinField.value.length < 8) {
        return;
    }
    
    // Show loading state
    companyField.placeholder = 'Loading from Börsdata...';
    tickerField.placeholder = 'Loading...';
    
    // Make AJAX request to preview the data
    const formData = new FormData();
    formData.append('action', 'preview_borsdata');
    formData.append('isin', isinField.value);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.company_data) {
            // Auto-fill the preview data
            companyField.value = data.company_data.company || '';
            tickerField.value = data.company_data.ticker || '';
            
            // Update hidden fields
            const countryField = document.getElementById('country_name');
            const countryIdField = document.getElementById('country_id');
            const yieldField = document.getElementById('yield');
            
            if (countryField) countryField.value = data.company_data.country || '';
            if (countryIdField) countryIdField.value = data.company_data.country_id || '';
            if (yieldField) yieldField.value = data.company_data.yield || '';
            
            // Update placeholders to show success
            companyField.placeholder = 'Auto-filled from Börsdata';
            tickerField.placeholder = 'Auto-filled from Börsdata';
        } else {
            // Reset placeholders if no data found
            companyField.placeholder = 'Company not found in Börsdata';
            tickerField.placeholder = 'Enter manually';
        }
    })
    .catch(error => {
        console.error('Preview error:', error);
        companyField.placeholder = 'Error loading data';
        tickerField.placeholder = 'Enter manually';
    });
}