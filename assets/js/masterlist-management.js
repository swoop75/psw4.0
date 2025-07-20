/**
 * Masterlist Management JavaScript
 */

// Global variables
let deleteCompanyIsin = '';
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

    // Form submission
    const companyForm = document.getElementById('companyForm');
    if (companyForm) {
        companyForm.addEventListener('submit', handleFormSubmit);
    }

    // Delisted status change
    const delistedSelect = document.getElementById('delisted');
    if (delistedSelect) {
        delistedSelect.addEventListener('change', toggleDelistedDate);
    }

    // Modal close on outside click
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('companyModal');
        const deleteModal = document.getElementById('deleteModal');
        
        if (event.target === modal) {
            closeModal();
        }
        if (event.target === deleteModal) {
            closeDeleteModal();
        }
    });

    // Escape key to close modals
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
            closeDeleteModal();
        }
    });
}

/**
 * Initialize search functionality
 */
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput && searchInput.value) {
        // Auto-focus search if there's a search term
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
    const countryFilter = document.getElementById('countryFilter');
    const marketFilter = document.getElementById('marketFilter');
    const delistedFilter = document.getElementById('delistedFilter');
    
    const params = new URLSearchParams();
    
    if (searchInput && searchInput.value.trim()) {
        params.set('search', searchInput.value.trim());
    }
    
    if (countryFilter && countryFilter.value) {
        params.set('country', countryFilter.value);
    }
    
    if (marketFilter && marketFilter.value) {
        params.set('market', marketFilter.value);
    }
    
    if (delistedFilter && delistedFilter.value !== '') {
        params.set('delisted', delistedFilter.value);
    }
    
    // Reset to page 1 when filtering
    params.set('page', '1');
    
    const url = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
    window.location.href = url;
}

/**
 * Show create company modal
 */
function showCreateModal() {
    const modal = document.getElementById('companyModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalAction = document.getElementById('modalAction');
    const submitText = document.getElementById('submitText');
    const form = document.getElementById('companyForm');
    
    // Reset form
    form.reset();
    
    // Set modal for create mode
    modalTitle.textContent = 'Add Company';
    modalAction.value = 'create';
    submitText.textContent = 'Add Company';
    
    // Enable ISIN field for creation
    const isinField = document.getElementById('isin');
    if (isinField) {
        isinField.disabled = false;
    }
    
    // Hide delisted date initially
    toggleDelistedDate();
    
    modal.style.display = 'block';
    
    // Focus first input
    setTimeout(() => {
        isinField.focus();
    }, 100);
}

/**
 * Edit company
 */
function editCompany(isin) {
    const modal = document.getElementById('companyModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalAction = document.getElementById('modalAction');
    const submitText = document.getElementById('submitText');
    
    // Set modal for edit mode
    modalTitle.textContent = 'Edit Company';
    modalAction.value = 'update';
    submitText.textContent = 'Update Company';
    
    // Get company data via AJAX
    const formData = new FormData();
    formData.append('action', 'get_company');
    formData.append('isin', isin);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    showLoading('Loading company data...');
    
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
        
        if (data.success && data.company) {
            populateForm(data.company);
            
            // Disable ISIN field for editing
            const isinField = document.getElementById('isin');
            if (isinField) {
                isinField.disabled = true;
            }
            
            modal.style.display = 'block';
        } else {
            showAlert('Error loading company data: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Error loading company data: ' + error.message, 'error');
    });
}

/**
 * Populate form with company data
 */
function populateForm(company) {
    const fields = ['isin', 'ticker', 'name', 'country', 'market', 'share_type_id', 'delisted', 'delisted_date'];
    
    fields.forEach(field => {
        const element = document.getElementById(field);
        if (element && company[field] !== undefined) {
            element.value = company[field] || '';
        }
    });
    
    // Store original ISIN for update
    document.getElementById('originalIsin').value = company.isin;
    
    // Toggle delisted date visibility
    toggleDelistedDate();
}

/**
 * Close modal
 */
function closeModal() {
    const modal = document.getElementById('companyModal');
    modal.style.display = 'none';
    
    // Re-enable ISIN field
    const isinField = document.getElementById('isin');
    if (isinField) {
        isinField.disabled = false;
    }
}

/**
 * Handle form submission
 */
function handleFormSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const action = formData.get('action');
    
    // For updates, use original ISIN
    if (action === 'update') {
        formData.set('isin', formData.get('original_isin'));
    }
    
    // Validate required fields
    const requiredFields = ['isin', 'ticker', 'name', 'country'];
    let isValid = true;
    
    for (const field of requiredFields) {
        const element = document.getElementById(field);
        if (!element || !element.value.trim()) {
            showAlert(`Please fill in the ${field.toUpperCase()} field`, 'error');
            if (element) element.focus();
            isValid = false;
            break;
        }
    }
    
    if (!isValid) return;
    
    // Validate ISIN format
    const isin = formData.get('isin');
    if (isin.length !== 12) {
        showAlert('ISIN must be exactly 12 characters long', 'error');
        document.getElementById('isin').focus();
        return;
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
    .then(response => response.json())
    .then(data => {
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
        
        if (data.success) {
            showAlert(data.message || 'Company saved successfully', 'success');
            closeModal();
            
            // Reload page to show changes
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert('Error: ' + (data.message || 'Failed to save company'), 'error');
        }
    })
    .catch(error => {
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
        showAlert('Error: ' + error.message, 'error');
    });
}

/**
 * Delete company
 */
function deleteCompany(isin, companyName) {
    deleteCompanyIsin = isin;
    document.getElementById('deleteCompanyName').textContent = companyName;
    document.getElementById('deleteModal').style.display = 'block';
}

/**
 * Close delete modal
 */
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    deleteCompanyIsin = '';
}

/**
 * Confirm delete
 */
function confirmDelete() {
    if (!deleteCompanyIsin) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('isin', deleteCompanyIsin);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    const deleteButton = document.querySelector('#deleteModal .btn-danger');
    const originalText = deleteButton.innerHTML;
    
    deleteButton.disabled = true;
    deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    
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
            showAlert(data.message || 'Company deleted successfully', 'success');
            closeDeleteModal();
            
            // Reload page to show changes
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert('Error: ' + (data.message || 'Failed to delete company'), 'error');
        }
    })
    .catch(error => {
        deleteButton.disabled = false;
        deleteButton.innerHTML = originalText;
        showAlert('Error: ' + error.message, 'error');
    });
}

/**
 * Toggle delisted date field visibility
 */
function toggleDelistedDate() {
    const delistedSelect = document.getElementById('delisted');
    const delistedDateGroup = document.getElementById('delistedDateGroup');
    
    if (delistedSelect && delistedDateGroup) {
        if (delistedSelect.value === '1') {
            delistedDateGroup.style.display = 'block';
        } else {
            delistedDateGroup.style.display = 'none';
            document.getElementById('delisted_date').value = '';
        }
    }
}

/**
 * Export to CSV
 */
function exportToCSV() {
    // Get current filters
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    
    // Create download link
    const url = window.location.pathname + '?' + params.toString();
    
    // Create temporary link and click it
    const link = document.createElement('a');
    link.href = url;
    link.download = 'masterlist_export.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showAlert('CSV export started', 'success');
}

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
            alert.remove();
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
 * Validate ISIN format
 */
function validateISIN(isin) {
    // Basic ISIN validation - 12 characters, alphanumeric
    const isinRegex = /^[A-Z]{2}[A-Z0-9]{9}[0-9]$/;
    return isinRegex.test(isin);
}

/**
 * Auto-format ISIN input
 */
function formatISINInput(input) {
    // Convert to uppercase and remove invalid characters
    let value = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    
    // Limit to 12 characters
    if (value.length > 12) {
        value = value.substring(0, 12);
    }
    
    input.value = value;
    
    // Validate and show feedback
    const isValid = value.length === 12 && validateISIN(value);
    input.classList.toggle('valid', isValid);
    input.classList.toggle('invalid', value.length > 0 && !isValid);
}

// Add ISIN formatting to input field when it exists
document.addEventListener('DOMContentLoaded', function() {
    const isinInput = document.getElementById('isin');
    if (isinInput) {
        isinInput.addEventListener('input', function() {
            formatISINInput(this);
        });
    }
});