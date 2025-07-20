/**
 * File: assets/js/main.js
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\assets\js\main.js
 * Description: Main JavaScript file for PSW 4.0 - handles UI interactions and dropdown functionality
 */

// Global variables
let loginDropdownOpen = false;
let userMenuOpen = false;

/**
 * Initialize application when DOM is loaded
 */
document.addEventListener('DOMContentLoaded', function() {
    initializeDropdowns();
    initializeKeyboardShortcuts();
    initializeForms();
});

/**
 * Initialize dropdown functionality
 */
function initializeDropdowns() {
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        const loginContainer = document.querySelector('.login-container');
        const loginDropdown = document.getElementById('loginDropdown');
        const userMenu = document.getElementById('userMenu');
        
        // Close login dropdown if clicking outside
        if (loginDropdown && loginDropdownOpen && !loginContainer.contains(event.target)) {
            closeLogin();
        }
        
        // Close user menu if clicking outside
        if (userMenu && userMenuOpen && !loginContainer.contains(event.target)) {
            closeUserMenu();
        }
    });
    
    // Handle escape key to close dropdowns
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            if (loginDropdownOpen) {
                closeLogin();
            }
            if (userMenuOpen) {
                closeUserMenu();
            }
        }
    });
}

/**
 * Toggle login dropdown
 */
function toggleLogin() {
    const dropdown = document.getElementById('loginDropdown');
    if (!dropdown) return;
    
    if (loginDropdownOpen) {
        closeLogin();
    } else {
        openLogin();
    }
}

/**
 * Open login dropdown
 */
function openLogin() {
    const dropdown = document.getElementById('loginDropdown');
    if (!dropdown) return;
    
    dropdown.classList.add('active');
    loginDropdownOpen = true;
    
    // Focus on username field
    setTimeout(() => {
        const usernameField = document.getElementById('username');
        if (usernameField) {
            usernameField.focus();
        }
    }, 100);
}

/**
 * Close login dropdown
 */
function closeLogin() {
    const dropdown = document.getElementById('loginDropdown');
    if (!dropdown) return;
    
    dropdown.classList.remove('active');
    loginDropdownOpen = false;
}

/**
 * Toggle user menu dropdown
 */
function toggleUserMenu() {
    const menu = document.getElementById('userMenu');
    if (!menu) return;
    
    if (userMenuOpen) {
        closeUserMenu();
    } else {
        openUserMenu();
    }
}

/**
 * Open user menu dropdown
 */
function openUserMenu() {
    const menu = document.getElementById('userMenu');
    if (!menu) return;
    
    menu.classList.add('active');
    userMenuOpen = true;
}

/**
 * Close user menu dropdown
 */
function closeUserMenu() {
    const menu = document.getElementById('userMenu');
    if (!menu) return;
    
    menu.classList.remove('active');
    userMenuOpen = false;
}

/**
 * Initialize keyboard shortcuts
 */
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(event) {
        // Only trigger if not in an input field
        if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA') {
            return;
        }
        
        // Space key - open search (future implementation)
        if (event.code === 'Space') {
            event.preventDefault();
            // TODO: Implement search functionality
            console.log('Search shortcut triggered');
        }
        
        // Alt + L - Toggle login
        if (event.altKey && event.code === 'KeyL') {
            event.preventDefault();
            if (!document.querySelector('.user-menu')) { // Only if not logged in
                toggleLogin();
            }
        }
    });
}

/**
 * Initialize form handling
 */
function initializeForms() {
    // Handle login form submission
    const loginForm = document.querySelector('.login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(event) {
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            
            if (!username.value.trim() || !password.value.trim()) {
                event.preventDefault();
                showAlert('Please enter both username and password.', 'error');
                return;
            }
            
            // Show loading state
            const submitButton = loginForm.querySelector('.btn-login');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
            }
        });
    }
}

/**
 * Show alert message
 * @param {string} message - Alert message
 * @param {string} type - Alert type (success, error, warning, info)
 */
function showAlert(message, type = 'info') {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create new alert
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = message;
    
    // Insert at top of main container
    const mainContainer = document.querySelector('.main-container');
    if (mainContainer) {
        mainContainer.insertBefore(alert, mainContainer.firstChild);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
}

/**
 * Utility function to make AJAX requests
 * @param {string} url - Request URL
 * @param {object} options - Request options
 * @returns {Promise} - Fetch promise
 */
function makeRequest(url, options = {}) {
    const defaults = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    const config = { ...defaults, ...options };
    
    return fetch(url, config)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error('Request failed:', error);
            showAlert('An error occurred. Please try again.', 'error');
            throw error;
        });
}

/**
 * Format number with proper thousands separators
 * @param {number} num - Number to format
 * @param {number} decimals - Number of decimal places
 * @returns {string} - Formatted number
 */
function formatNumber(num, decimals = 2) {
    return new Intl.NumberFormat('sv-SE', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(num);
}

/**
 * Format currency
 * @param {number} amount - Amount to format
 * @param {string} currency - Currency code (default SEK)
 * @returns {string} - Formatted currency
 */
function formatCurrency(amount, currency = 'SEK') {
    return new Intl.NumberFormat('sv-SE', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

/**
 * Format date for display
 * @param {Date|string} date - Date to format
 * @returns {string} - Formatted date
 */
function formatDate(date) {
    const d = new Date(date);
    return d.toLocaleDateString('sv-SE');
}

/**
 * Debounce function for search inputs
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in milliseconds
 * @returns {Function} - Debounced function
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