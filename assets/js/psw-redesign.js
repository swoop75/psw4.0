/**
 * PSW 4.0 Redesign JavaScript
 * Handles sidebar navigation, user menu, theme switching, and interactive components
 * Version: 1.0
 */

class PSWApp {
    constructor() {
        this.init();
    }

    init() {
        this.initSidebar();
        this.initUserMenu();
        this.initTheme();
        this.initNavigation();
        this.bindEvents();
        
        console.log('PSW 4.0 Redesign initialized');
    }

    /**
     * Initialize sidebar functionality
     */
    initSidebar() {
        const sidebar = document.querySelector('.psw-sidebar');
        if (!sidebar) return;

        // Sidebar hover functionality is handled by CSS
        // This is for any additional sidebar logic if needed
        
        // Add active states based on current page
        this.setActiveNavigation();
    }

    /**
     * Set active navigation item based on current page
     */
    setActiveNavigation() {
        const currentPath = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.psw-nav-link, .psw-nav-submenu-link');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href && href.includes(currentPath)) {
                link.classList.add('active');
                
                // If it's a submenu link, expand the parent
                if (link.classList.contains('psw-nav-submenu-link')) {
                    const parentItem = link.closest('.psw-nav-item');
                    if (parentItem) {
                        parentItem.classList.add('expanded');
                    }
                }
            }
        });
    }

    /**
     * Initialize user menu functionality
     */
    initUserMenu() {
        // Close user menu when clicking outside
        document.addEventListener('click', (e) => {
            const userMenu = document.getElementById('userMenu') || document.getElementById('loginMenu');
            if (userMenu && !userMenu.contains(e.target)) {
                userMenu.classList.remove('open');
            }
        });
    }

    /**
     * Initialize theme system
     */
    initTheme() {
        // Get saved theme from localStorage or session
        const savedTheme = localStorage.getItem('psw-theme') || 
                          document.documentElement.getAttribute('data-theme') || 
                          'light';
        
        // Set theme immediately to avoid flickering
        document.documentElement.setAttribute('data-theme', savedTheme);
        
        this.setTheme(savedTheme);
        
        // Listen for theme toggle events
        document.addEventListener('themeChange', (e) => {
            this.setTheme(e.detail.theme);
        });
    }

    /**
     * Set application theme
     * @param {string} theme - 'light' or 'dark'
     */
    setTheme(theme) {
        // Set theme immediately
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('psw-theme', theme);
        
        // Update server-side session via AJAX
        this.updateServerTheme(theme);
        
        // Dispatch event for other components
        window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme } }));
    }

    /**
     * Update theme preference on server
     * @param {string} theme - Theme name
     */
    updateServerTheme(theme) {
        fetch('/update_theme.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ theme: theme })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Theme updated on server:', data.theme);
            } else {
                console.warn('Server theme update failed:', data);
            }
        })
        .catch(error => {
            console.warn('Could not update server theme:', error);
        });
    }

    /**
     * Initialize navigation functionality
     */
    initNavigation() {
        // Handle submenu toggles
        const navItems = document.querySelectorAll('.psw-nav-item');
        navItems.forEach(item => {
            const link = item.querySelector('.psw-nav-link');
            const submenu = item.querySelector('.psw-nav-submenu');
            
            if (submenu && link) {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.toggleSubmenu(item);
                });
            }
        });
    }

    /**
     * Toggle submenu expansion
     * @param {Element} navItem - Navigation item element
     */
    toggleSubmenu(navItem) {
        const isExpanded = navItem.classList.contains('expanded');
        
        // Close all other submenus
        document.querySelectorAll('.psw-nav-item.expanded').forEach(item => {
            if (item !== navItem) {
                item.classList.remove('expanded');
            }
        });
        
        // Toggle current submenu
        navItem.classList.toggle('expanded', !isExpanded);
    }

    /**
     * Bind global event listeners
     */
    bindEvents() {
        // Escape key to close menus
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllMenus();
            }
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            this.handleResize();
        });
    }

    /**
     * Close all open menus
     */
    closeAllMenus() {
        const userMenu = document.getElementById('userMenu') || document.getElementById('loginMenu');
        if (userMenu) {
            userMenu.classList.remove('open');
        }
    }

    /**
     * Handle window resize events
     */
    handleResize() {
        // Handle any resize-specific logic here
        // Currently desktop-only, so minimal resize handling needed
    }

    /**
     * Show notification
     * @param {string} message - Notification message
     * @param {string} type - Notification type (success, error, warning, info)
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `psw-alert psw-alert-${type}`;
        notification.innerHTML = `
            ${message}
            <button onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; margin-left: auto; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        `;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
            animation: slideInRight 0.3s ease-out;
        `;

        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
    }

    /**
     * Handle AJAX form submissions
     * @param {HTMLFormElement} form - Form element
     * @param {Function} onSuccess - Success callback
     * @param {Function} onError - Error callback
     */
    submitForm(form, onSuccess = null, onError = null) {
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton?.innerHTML;

        // Show loading state
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        }

        fetch(form.action, {
            method: form.method,
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification(data.message || 'Operation completed successfully', 'success');
                if (onSuccess) onSuccess(data);
            } else {
                this.showNotification(data.error || 'An error occurred', 'error');
                if (onError) onError(data);
            }
        })
        .catch(error => {
            console.error('Form submission error:', error);
            this.showNotification('Network error occurred', 'error');
            if (onError) onError(error);
        })
        .finally(() => {
            // Reset button state
            if (submitButton && originalText) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            }
        });
    }

    /**
     * Initialize data tables with sorting and filtering
     * @param {string} selector - Table selector
     * @param {Object} options - Table options
     */
    initDataTable(selector, options = {}) {
        const table = document.querySelector(selector);
        if (!table) return;

        // Add sorting functionality
        const headers = table.querySelectorAll('th[data-sortable]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                this.sortTable(table, header);
            });
        });

        // Add row hover effects (handled by CSS)
        // Add any additional table functionality here
    }

    /**
     * Sort table by column
     * @param {HTMLTableElement} table - Table element
     * @param {HTMLElement} header - Header element
     */
    sortTable(table, header) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const columnIndex = Array.from(header.parentElement.children).indexOf(header);
        const isAscending = !header.classList.contains('sort-asc');

        // Sort rows
        rows.sort((a, b) => {
            const aValue = a.children[columnIndex]?.textContent.trim() || '';
            const bValue = b.children[columnIndex]?.textContent.trim() || '';
            
            // Try to parse as numbers
            const aNum = parseFloat(aValue.replace(/[^0-9.-]/g, ''));
            const bNum = parseFloat(bValue.replace(/[^0-9.-]/g, ''));
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return isAscending ? aNum - bNum : bNum - aNum;
            }
            
            // String comparison
            return isAscending ? 
                aValue.localeCompare(bValue) : 
                bValue.localeCompare(aValue);
        });

        // Update header classes
        table.querySelectorAll('th').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
        });
        header.classList.add(isAscending ? 'sort-asc' : 'sort-desc');

        // Re-append sorted rows
        rows.forEach(row => tbody.appendChild(row));
    }
}

// Global functions for backwards compatibility and inline event handlers

/**
 * Toggle user menu dropdown
 */
function toggleUserMenu() {
    const userMenu = document.getElementById('userMenu');
    if (userMenu) {
        userMenu.classList.toggle('open');
    }
}

/**
 * Toggle login menu dropdown
 */
function toggleLoginMenu() {
    const loginMenu = document.getElementById('loginMenu');
    if (loginMenu) {
        loginMenu.classList.toggle('open');
    }
}

/**
 * Toggle theme between light and dark
 */
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    // Dispatch custom event
    document.dispatchEvent(new CustomEvent('themeChange', {
        detail: { theme: newTheme }
    }));
}

/**
 * Set specific theme
 * @param {string} theme - Theme name ('light' or 'dark')
 */
function setTheme(theme) {
    document.dispatchEvent(new CustomEvent('themeChange', {
        detail: { theme: theme }
    }));
}

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.pswApp = new PSWApp();
});

// Add CSS animations for notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .sort-asc::after {
        content: " ↑";
        color: var(--primary-accent);
    }
    
    .sort-desc::after {
        content: " ↓";
        color: var(--primary-accent);
    }
`;
document.head.appendChild(style);