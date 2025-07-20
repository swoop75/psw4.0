/**
 * File: assets/js/user-management.js
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\assets\js\user-management.js
 * Description: User management JavaScript functionality for PSW 4.0
 */

// Tab management
function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        content.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab content
    const selectedTab = document.getElementById(tabName + '-tab');
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Add active class to selected tab button
    const selectedButton = document.querySelector(`[onclick="showTab('${tabName}')"]`);
    if (selectedButton) {
        selectedButton.classList.add('active');
    }
    
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.replaceState({}, '', url);
}

// Form validation
function validateProfileForm() {
    const email = document.getElementById('email');
    const fullName = document.getElementById('full_name');
    
    let isValid = true;
    
    // Email validation
    if (email && email.value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email.value)) {
            showFieldError(email, 'Please enter a valid email address');
            isValid = false;
        } else {
            clearFieldError(email);
        }
    }
    
    // Full name validation
    if (fullName && fullName.value && fullName.value.length > 100) {
        showFieldError(fullName, 'Full name must be 100 characters or less');
        isValid = false;
    } else if (fullName) {
        clearFieldError(fullName);
    }
    
    return isValid;
}

function validatePasswordForm() {
    const currentPassword = document.getElementById('current_password');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    let isValid = true;
    
    // Current password validation
    if (!currentPassword.value) {
        showFieldError(currentPassword, 'Current password is required');
        isValid = false;
    } else {
        clearFieldError(currentPassword);
    }
    
    // New password validation
    if (!newPassword.value) {
        showFieldError(newPassword, 'New password is required');
        isValid = false;
    } else if (newPassword.value.length < 8) {
        showFieldError(newPassword, 'Password must be at least 8 characters long');
        isValid = false;
    } else if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(newPassword.value)) {
        showFieldError(newPassword, 'Password must contain uppercase, lowercase, and numbers');
        isValid = false;
    } else {
        clearFieldError(newPassword);
    }
    
    // Confirm password validation
    if (!confirmPassword.value) {
        showFieldError(confirmPassword, 'Please confirm your new password');
        isValid = false;
    } else if (newPassword.value !== confirmPassword.value) {
        showFieldError(confirmPassword, 'Passwords do not match');
        isValid = false;
    } else {
        clearFieldError(confirmPassword);
    }
    
    return isValid;
}

function showFieldError(field, message) {
    clearFieldError(field);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    errorDiv.style.color = '#dc3545';
    errorDiv.style.fontSize = '0.75rem';
    errorDiv.style.marginTop = '4px';
    
    field.parentNode.appendChild(errorDiv);
    field.style.borderColor = '#dc3545';
}

function clearFieldError(field) {
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    field.style.borderColor = '#ced4da';
}

// Form submission handling
document.addEventListener('DOMContentLoaded', function() {
    // Profile form validation
    const profileForm = document.querySelector('.profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            if (!validateProfileForm()) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    // Password form validation
    const passwordForm = document.querySelector('.security-form');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            if (!validatePasswordForm()) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    // Real-time validation
    const emailField = document.getElementById('email');
    if (emailField) {
        emailField.addEventListener('blur', function() {
            validateProfileForm();
        });
    }
    
    const newPasswordField = document.getElementById('new_password');
    if (newPasswordField) {
        newPasswordField.addEventListener('input', function() {
            updatePasswordStrength(this.value);
        });
    }
    
    const confirmPasswordField = document.getElementById('confirm_password');
    if (confirmPasswordField) {
        confirmPasswordField.addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            if (this.value && newPassword !== this.value) {
                showFieldError(this, 'Passwords do not match');
            } else {
                clearFieldError(this);
            }
        });
    }
    
    // Auto-save preferences (debounced)
    const preferenceInputs = document.querySelectorAll('.preferences-form input, .preferences-form select');
    preferenceInputs.forEach(input => {
        input.addEventListener('change', debounce(function() {
            autoSavePreferences();
        }, 1000));
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + S to save current form
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            const activeTab = document.querySelector('.tab-content.active');
            const form = activeTab ? activeTab.querySelector('form') : null;
            if (form) {
                const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.click();
                }
            }
        }
        
        // Tab navigation with Ctrl + number
        if ((e.ctrlKey || e.metaKey) && e.key >= '1' && e.key <= '4') {
            e.preventDefault();
            const tabs = ['profile', 'security', 'preferences', 'activity'];
            const tabIndex = parseInt(e.key) - 1;
            if (tabs[tabIndex]) {
                showTab(tabs[tabIndex]);
            }
        }
    });
});

// Password strength indicator
function updatePasswordStrength(password) {
    const strengthIndicator = document.getElementById('password-strength');
    if (!strengthIndicator) {
        // Create strength indicator if it doesn't exist
        const newPasswordField = document.getElementById('new_password');
        if (!newPasswordField) return;
        
        const indicator = document.createElement('div');
        indicator.id = 'password-strength';
        indicator.className = 'password-strength';
        indicator.style.marginTop = '8px';
        newPasswordField.parentNode.appendChild(indicator);
    }
    
    const strength = calculatePasswordStrength(password);
    const indicator = document.getElementById('password-strength');
    
    indicator.innerHTML = `
        <div class="strength-bar">
            <div class="strength-fill strength-${strength.level}" style="width: ${strength.percentage}%"></div>
        </div>
        <div class="strength-text">${strength.text}</div>
    `;
}

function calculatePasswordStrength(password) {
    let score = 0;
    
    if (password.length >= 8) score += 25;
    if (password.length >= 12) score += 25;
    if (/[a-z]/.test(password)) score += 10;
    if (/[A-Z]/.test(password)) score += 10;
    if (/[0-9]/.test(password)) score += 10;
    if (/[^A-Za-z0-9]/.test(password)) score += 20;
    
    let level, text;
    if (score < 30) {
        level = 'weak';
        text = 'Weak password';
    } else if (score < 60) {
        level = 'fair';
        text = 'Fair password';
    } else if (score < 90) {
        level = 'good';
        text = 'Good password';
    } else {
        level = 'strong';
        text = 'Strong password';
    }
    
    return {
        percentage: Math.min(score, 100),
        level: level,
        text: text
    };
}

// Auto-save preferences
function autoSavePreferences() {
    const form = document.querySelector('.preferences-form');
    if (!form) return;
    
    const formData = new FormData(form);
    
    // Show saving indicator
    showSavingIndicator();
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Show success indicator briefly
        showSavedIndicator();
    })
    .catch(error => {
        console.error('Auto-save failed:', error);
        showSaveErrorIndicator();
    });
}

function showSavingIndicator() {
    const indicator = getOrCreateSaveIndicator();
    indicator.textContent = 'Saving...';
    indicator.className = 'save-indicator saving';
    indicator.style.display = 'block';
}

function showSavedIndicator() {
    const indicator = getOrCreateSaveIndicator();
    indicator.textContent = 'Saved';
    indicator.className = 'save-indicator saved';
    
    setTimeout(() => {
        indicator.style.display = 'none';
    }, 2000);
}

function showSaveErrorIndicator() {
    const indicator = getOrCreateSaveIndicator();
    indicator.textContent = 'Save failed';
    indicator.className = 'save-indicator error';
    
    setTimeout(() => {
        indicator.style.display = 'none';
    }, 3000);
}

function getOrCreateSaveIndicator() {
    let indicator = document.getElementById('save-indicator');
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'save-indicator';
        indicator.style.position = 'fixed';
        indicator.style.top = '20px';
        indicator.style.right = '20px';
        indicator.style.padding = '8px 16px';
        indicator.style.borderRadius = '4px';
        indicator.style.fontSize = '0.875rem';
        indicator.style.fontWeight = '500';
        indicator.style.zIndex = '1000';
        indicator.style.display = 'none';
        document.body.appendChild(indicator);
    }
    return indicator;
}

// Utility function for debouncing
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

// Activity icon mapping
function getActivityIcon(actionType) {
    const iconMap = {
        'login': 'sign-in-alt',
        'logout': 'sign-out-alt',
        'profile_updated': 'user-edit',
        'password_changed': 'key',
        'preferences_updated': 'cog',
        'email_updated': 'envelope',
        'security': 'shield-alt',
        'default': 'clock'
    };
    
    return iconMap[actionType] || iconMap['default'];
}

// Export functions for global access
window.showTab = showTab;
window.getActivityIcon = getActivityIcon;