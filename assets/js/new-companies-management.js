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
    initializeCheckboxDropdowns();
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
    
    // Reset form
    form.reset();
    
    // Set modal for create mode
    modalTitle.textContent = 'Add New Company';
    modalAction.value = 'add';
    submitText.textContent = 'Add New Company';
    
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
function editEntry(companyId) {
    const modal = document.getElementById('entryModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalAction = document.getElementById('modalAction');
    const submitText = document.getElementById('submitText');
    
    // Set modal for edit mode
    modalTitle.textContent = 'Edit Company Entry';
    modalAction.value = 'update';
    submitText.textContent = 'Update Entry';
    
    // Get entry data via AJAX
    const formData = new FormData();
    formData.append('action', 'get_entry');
    formData.append('new_companies_id', companyId);
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
            
            // Store company ID
            document.getElementById('companyId').value = companyId;
            
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
    const requiredFields = ['company', 'ticker'];
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
    formData.append('new_companies_id', deleteEntryId);
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