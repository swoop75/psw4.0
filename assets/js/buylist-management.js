/**
 * Buylist Management JavaScript
 */

// Global variables
let deleteEntryId = '';
let deleteMasterlistId = '';
let searchTimeout;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    initializeSearch();
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
    const statusFilter = document.getElementById('statusFilter');
    const countryFilter = document.getElementById('countryFilter');
    const strategyFilter = document.getElementById('strategyFilter');
    const brokerFilter = document.getElementById('brokerFilter');
    
    const params = new URLSearchParams();
    
    if (searchInput && searchInput.value.trim()) {
        params.set('search', searchInput.value.trim());
    }
    
    if (statusFilter && statusFilter.value) {
        params.set('status_id', statusFilter.value);
    }
    
    if (countryFilter && countryFilter.value) {
        params.set('country', countryFilter.value);
    }
    
    if (strategyFilter && strategyFilter.value) {
        params.set('strategy_group_id', strategyFilter.value);
    }
    
    if (brokerFilter && brokerFilter.value) {
        params.set('broker_id', brokerFilter.value);
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
    
    // Reset form
    form.reset();
    
    // Set modal for create mode
    modalTitle.textContent = 'Add to Buylist';
    modalAction.value = 'add';
    submitText.textContent = 'Add to Buylist';
    
    // No tabs to reset in simplified form
    
    modal.style.display = 'block';
    
    // Focus first input
    setTimeout(() => {
        const companyField = document.getElementById('company');
        if (companyField) {
            companyField.focus();
        }
    }, 100);
}

/**
 * Edit entry
 */
function editEntry(buylistId) {
    const modal = document.getElementById('entryModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalAction = document.getElementById('modalAction');
    const submitText = document.getElementById('submitText');
    
    // Set modal for edit mode
    modalTitle.textContent = 'Edit Buylist Entry';
    modalAction.value = 'update';
    submitText.textContent = 'Update Entry';
    
    // Get entry data via AJAX
    const formData = new FormData();
    formData.append('action', 'get_entry');
    formData.append('buy_list_id', buylistId);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    showLoading('Loading entry data...');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success && data.entry) {
            populateEntryForm(data.entry);
            
            // Store buylist ID
            document.getElementById('buylistId').value = buylistId;
            
            modal.style.display = 'block';
        } else {
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
        'company_name', 'ticker', 'country', 'currency', 'exchange', 'isin', 'business_description',
        'status_id', 'priority_level', 'target_price', 'target_quantity', 'notes', 'research_notes',
        'expected_dividend_yield', 'pe_ratio', 'price_to_book', 'debt_to_equity', 'roe',
        'analyst_rating', 'risk_level', 'sector', 'market_cap_category', 'target_allocation_percent',
        'stop_loss_price', 'take_profit_price', 'entry_strategy', 'exit_strategy',
        'last_analysis_date', 'next_review_date', 'price_alert_enabled', 'price_alert_target'
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
}

/**
 * Handle entry form submission
 */
function handleEntryFormSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const action = formData.get('action');
    
    // Validate required fields
    const requiredFields = ['company_name', 'ticker', 'status_id'];
    let isValid = true;
    
    for (const field of requiredFields) {
        const element = document.getElementById(field);
        if (!element || !element.value.trim()) {
            showAlert(`Please fill in the ${field.replace('_', ' ')} field`, 'error');
            if (element) element.focus();
            isValid = false;
            break;
        }
    }
    
    if (!isValid) return;
    
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
    .then(response => response.json())
    .then(data => {
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
function deleteEntry(buylistId, companyName) {
    deleteEntryId = buylistId;
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
    formData.append('buy_list_id', deleteEntryId);
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
function addToMasterlist(buylistId, companyName) {
    deleteMasterlistId = buylistId;
    document.getElementById('masterlistCompanyName').textContent = companyName;
    document.getElementById('masterlistBuylistId').value = buylistId;
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