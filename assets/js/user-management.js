/**
 * File: assets/js/user-management.js
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\assets\js\user-management.js
 * Description: User management JavaScript functionality for PSW 4.0
 */

// Admin view management (All Users, Activity Log, Statistics)
function showView(viewName) {
    // Hide all admin view contents
    const viewContents = document.querySelectorAll('.tab-content');
    viewContents.forEach(content => {
        content.classList.remove('active');
    });
    
    // Remove active class from all view buttons
    const viewButtons = document.querySelectorAll('.tab-button');
    viewButtons.forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected view content
    const selectedView = document.querySelector(`[data-view="${viewName}"]`);
    if (selectedView) {
        selectedView.classList.add('active');
    }
    
    // Add active class to selected view button
    const selectedButton = document.querySelector(`[onclick="showView('${viewName}')"]`);
    if (selectedButton) {
        selectedButton.classList.add('active');
    }
    
    // Update URL without reload
    const url = new URL(window.location);
    if (viewName === 'users') {
        url.searchParams.delete('view');
    } else {
        url.searchParams.set('view', viewName);
    }
    window.history.replaceState({}, '', url);
}

// Individual user tab management (Profile, Security, Preferences, Activity)
function showUserTab(tabName) {
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
    const selectedButton = document.querySelector(`[onclick="showUserTab('${tabName}')"]`);
    if (selectedButton) {
        selectedButton.classList.add('active');
    }
    
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.replaceState({}, '', url);
}

// Legacy tab management for backward compatibility
function showTab(tabName) {
    // Check if we're in individual user mode
    const url = new URL(window.location);
    if (url.searchParams.has('user_id')) {
        showUserTab(tabName);
    } else {
        showView(tabName);
    }
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

// User Management Functions (Admin Only)

/**
 * Edit user - opens edit modal
 * @param {number} userId User ID to edit
 */
function editUser(userId) {
    try {
        console.log('editUser called with userId:', userId);
        
        // Get user data from the table row
        const userRow = document.querySelector(`[data-user-id="${userId}"]`);
        
        if (!userRow) {
            console.error('User row not found for ID:', userId);
            alert('User not found');
            return;
        }
        
        console.log('Found user row:', userRow);
        
        const username = userRow.querySelector('.username')?.textContent?.trim() || '';
        const fullName = userRow.querySelector('.full-name')?.textContent?.trim() || '';
        const email = userRow.cells[1]?.textContent?.trim() || '';
        const currentRole = userRow.querySelector('.role-badge')?.textContent?.trim() || '';
        const statusIndicator = userRow.querySelector('.status-indicator');
        const isActive = statusIndicator?.classList.contains('status-active') || false;
        
        const userData = {
            username: username,
            fullName: fullName,
            email: email,
            role: currentRole,
            active: !!isActive
        };
        
        console.log('User data extracted:', userData);
        
        showEditUserModal(userId, userData);
    } catch (error) {
        console.error('Error in editUser function:', error);
        alert('An error occurred while opening the edit dialog');
    }
}

// Global variables for status change modal
let pendingStatusChange = null;

/**
 * Toggle user active/inactive status
 * @param {number} userId User ID
 * @param {boolean|string} newStatus New active status
 */
function toggleUserStatus(userId, newStatus) {
    // Convert string to boolean if needed
    if (typeof newStatus === 'string') {
        newStatus = newStatus === 'true';
    }
    
    // Get user data from the table row
    const userRow = document.querySelector(`[data-user-id="${userId}"]`);
    if (!userRow) {
        alert('User not found');
        return;
    }
    
    const username = userRow.querySelector('.username')?.textContent || 'Unknown User';
    const action = newStatus ? 'activate' : 'deactivate';
    
    // Store the pending change
    pendingStatusChange = {
        userId: userId,
        newStatus: newStatus,
        username: username,
        action: action
    };
    
    // Show beautiful modal
    showStatusChangeModal(action, username, newStatus);
}

/**
 * Show edit user modal
 * @param {number} userId User ID
 * @param {object} userData User data
 */
function showEditUserModal(userId, userData) {
    try {
        // Create modal HTML
        const modalHTML = `
            <div id="editUserModal" class="modal" style="display: block;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Edit User: ${userData.username}</h3>
                        <button type="button" class="modal-close" onclick="closeEditUserModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="editUserForm">
                            <input type="hidden" name="csrf_token" value="${getCSRFToken()}">
                            <input type="hidden" name="action" value="edit_user">
                            <input type="hidden" name="user_id" value="${userId}">
                            
                            <div class="form-group">
                                <label for="edit_username">Username</label>
                                <input type="text" id="edit_username" value="${userData.username}" class="form-control" readonly>
                                <small class="form-help">Username cannot be changed</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_full_name">Full Name</label>
                                <input type="text" id="edit_full_name" name="full_name" value="${userData.fullName}" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_email">Email</label>
                                <input type="email" id="edit_email" name="email" value="${userData.email}" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_role">Role</label>
                                <select id="edit_role" name="role_id" class="form-control" required>
                                    <option value="1" ${userData.role.toLowerCase().includes('admin') ? 'selected' : ''}>Administrator</option>
                                    <option value="2" ${!userData.role.toLowerCase().includes('admin') ? 'selected' : ''}>User</option>
                                </select>
                            </div>
                            
                            <div class="form-group checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="active" value="1" ${userData.active ? 'checked' : ''}>
                                    Active User
                                </label>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        console.log('Creating modal with HTML:', modalHTML);
        
        // Remove existing modal if any
        const existingModal = document.getElementById('editUserModal');
        if (existingModal) {
            existingModal.remove();
            console.log('Removed existing modal');
        }
        
        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        console.log('Modal added to DOM');
        
        // Add form submit handler
        const form = document.getElementById('editUserForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log('Form submit event triggered');
                submitEditUserForm(this);
            });
            console.log('Form event listener added');
        } else {
            console.error('Form not found after modal creation');
        }
        
    } catch (error) {
        console.error('Error in showEditUserModal:', error);
        alert('Failed to open edit dialog');
    }
}

/**
 * Close edit user modal
 */
function closeEditUserModal() {
    const modal = document.getElementById('editUserModal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    }
}

/**
 * Submit edit user form
 * @param {HTMLFormElement} form Form element
 */
function submitEditUserForm(form) {
    console.log('submitEditUserForm called');
    const formData = new FormData(form);
    const submitButton = form.querySelector('button[type="submit"]');
    
    // Log form data for debugging
    console.log('Form data:');
    for (let [key, value] of formData.entries()) {
        console.log(key, value);
    }
    
    // Show loading state
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    fetch('user_management.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            showNotification('User updated successfully', 'success');
            closeEditUserModal();
            // Reload the page to show updated data
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(data.message || 'Failed to update user', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while updating user', 'error');
    })
    .finally(() => {
        // Reset button state
        submitButton.disabled = false;
        submitButton.innerHTML = '<i class="fas fa-save"></i> Save Changes';
    });
}

/**
 * Update user status in table
 * @param {number} userId User ID
 * @param {boolean} isActive Active status
 */
function updateUserStatusInTable(userId, isActive) {
    const userRow = document.querySelector(`[data-user-id="${userId}"]`);
    if (!userRow) return;
    
    const statusCell = userRow.querySelector('.status-indicator');
    const actionButton = userRow.querySelector(`[onclick*="toggleUserStatus(${userId}"]`);
    
    if (statusCell) {
        statusCell.className = `status-indicator status-${isActive ? 'active' : 'inactive'}`;
        statusCell.innerHTML = `
            <span class="status-dot"></span>
            ${isActive ? 'Active' : 'Inactive'}
        `;
    }
    
    if (actionButton) {
        actionButton.setAttribute('onclick', `toggleUserStatus(${userId}, ${!isActive})`);
        actionButton.setAttribute('title', `${isActive ? 'Deactivate' : 'Activate'} User`);
        actionButton.innerHTML = `<i class="fas fa-${isActive ? 'user-slash' : 'user-check'}"></i>`;
    }
}

/**
 * Show notification
 * @param {string} message Notification message
 * @param {string} type Notification type (success, error, warning)
 */
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification alert alert-${type === 'error' ? 'error' : type === 'success' ? 'success' : 'info'}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        ${message}
    `;
    
    // Insert at top of user management container
    const container = document.querySelector('.user-management-container');
    if (container) {
        container.insertBefore(notification, container.firstChild);
    }
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

/**
 * Get CSRF token from page
 * @returns {string} CSRF token
 */
function getCSRFToken() {
    const tokenInput = document.querySelector('input[name="csrf_token"]');
    return tokenInput ? tokenInput.value : '';
}

/**
 * Show add user modal
 */
function showAddUserModal() {
    alert('Add user functionality will be implemented in the next phase.');
}

/**
 * Show status change modal
 * @param {string} action Action to perform (activate/deactivate)
 * @param {string} username Username
 * @param {boolean} newStatus New status
 */
function showStatusChangeModal(action, username, newStatus) {
    const modal = document.getElementById('statusChangeModal');
    const actionElement = document.getElementById('statusChangeAction');
    const userNameElement = document.getElementById('statusChangeUserName');
    const warningElement = document.getElementById('statusChangeWarning');
    const confirmBtn = document.getElementById('confirmStatusChangeBtn');
    const confirmText = document.getElementById('confirmStatusChangeText');
    
    if (!modal || !actionElement || !userNameElement) {
        console.error('Status change modal elements not found');
        return;
    }
    
    // Set modal content
    actionElement.textContent = action;
    userNameElement.textContent = username;
    confirmText.textContent = action.charAt(0).toUpperCase() + action.slice(1);
    
    // Set button style
    confirmBtn.className = `btn ${newStatus ? 'btn-primary' : 'btn-danger'}`;
    confirmBtn.querySelector('i').className = `fas fa-${newStatus ? 'user-check' : 'user-slash'}`;
    
    // Add warning for deactivation
    if (!newStatus) {
        warningElement.textContent = 'The user will not be able to log in while deactivated.';
        warningElement.style.display = 'block';
    } else {
        warningElement.style.display = 'none';
    }
    
    // Show modal
    modal.style.display = 'block';
    modal.classList.add('show');
}

/**
 * Close status change modal
 */
function closeStatusChangeModal() {
    const modal = document.getElementById('statusChangeModal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }
    pendingStatusChange = null;
}

/**
 * Confirm status change
 */
function confirmStatusChange() {
    if (!pendingStatusChange) {
        return;
    }
    
    const { userId, newStatus } = pendingStatusChange;
    
    // Show loading state
    const confirmBtn = document.getElementById('confirmStatusChangeBtn');
    const originalContent = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    // Make AJAX request
    fetch('user_management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'toggle_user_status',
            user_id: userId,
            active: newStatus ? 1 : 0,
            csrf_token: getCSRFToken()
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the UI
            updateUserStatusInTable(userId, newStatus);
            showNotification('User status updated successfully', 'success');
            closeStatusChangeModal();
        } else {
            showNotification(data.message || 'Failed to update user status', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while updating user status', 'error');
    })
    .finally(() => {
        // Reset button state
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = originalContent;
    });
}

/**
 * Show generate password confirmation modal
 */
function showGeneratePasswordModal() {
    const modal = document.getElementById('generatePasswordModal');
    if (modal) {
        modal.style.display = 'block';
        modal.classList.add('show');
    }
}

/**
 * Close generate password modal
 */
function closeGeneratePasswordModal() {
    const modal = document.getElementById('generatePasswordModal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }
}

/**
 * Confirm password generation
 */
function confirmGeneratePassword() {
    const confirmBtn = document.querySelector('#generatePasswordModal .btn-primary');
    const originalContent = confirmBtn.innerHTML;
    
    // Show loading state
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    
    // Create and submit form directly (not AJAX)
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'user_management.php';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'generate_password';
    form.appendChild(actionInput);
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = getCSRFToken();
    form.appendChild(csrfInput);
    
    // Add form to page and submit
    document.body.appendChild(form);
    form.submit();
    
    // Note: The page will reload automatically with the form submission,
    // so we don't need to reset the button state
}

// Export functions for global access
window.showTab = showTab;
window.showView = showView;
window.showUserTab = showUserTab;
window.getActivityIcon = getActivityIcon;
window.editUser = editUser;
window.toggleUserStatus = toggleUserStatus;
window.showAddUserModal = showAddUserModal;
window.closeEditUserModal = closeEditUserModal;
window.showStatusChangeModal = showStatusChangeModal;
window.closeStatusChangeModal = closeStatusChangeModal;
window.confirmStatusChange = confirmStatusChange;
window.showGeneratePasswordModal = showGeneratePasswordModal;
window.closeGeneratePasswordModal = closeGeneratePasswordModal;
window.confirmGeneratePassword = confirmGeneratePassword;