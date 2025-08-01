/**
 * File: assets/js/improved-main.js
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\assets\js\improved-main.js
 * Description: Enhanced JavaScript for PSW 4.0 - Modern interactions and animations
 */

// Global app object
window.PSW = window.PSW || {};

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    PSW.init();
});

// Main PSW object
PSW = {
    // Configuration
    config: {
        animationDuration: 250,
        scrollThreshold: 50,
        debounceDelay: 300
    },

    // Initialize application
    init: function() {
        this.initHeader();
        this.initDropdowns();
        this.initNavigation();
        this.initAnimations();
        this.initUtilities();
        this.initScrollEffects();
        console.log('PSW 4.0 initialized successfully');
    },

    // Header functionality
    initHeader: function() {
        const header = document.querySelector('.header');
        if (!header) return;

        // Add scroll effect to header
        let scrollTimer = null;
        window.addEventListener('scroll', () => {
            if (scrollTimer) clearTimeout(scrollTimer);
            
            const scrolled = window.scrollY > this.config.scrollThreshold;
            header.classList.toggle('scrolled', scrolled);
            
            scrollTimer = setTimeout(() => {
                // Optional: Add additional scroll-based effects
            }, 100);
        });

        // Add smooth logo hover effect
        const logo = document.querySelector('.logo');
        if (logo) {
            logo.addEventListener('mouseenter', () => {
                logo.style.transform = 'translateY(-2px)';
            });
            
            logo.addEventListener('mouseleave', () => {
                logo.style.transform = 'translateY(0)';
            });
        }
    },

    // Dropdown menu functionality
    initDropdowns: function() {
        const loginToggle = document.querySelector('.login-toggle');
        const loginDropdown = document.querySelector('.login-dropdown, #loginDropdown');
        
        if (!loginToggle || !loginDropdown) return;

        let isOpen = false;
        let closeTimer = null;
        let autofillInProgress = false;

        // Toggle dropdown - only explicit open/close
        const toggleDropdown = (show) => {
            if (closeTimer) {
                clearTimeout(closeTimer);
                closeTimer = null;
            }

            // If show is explicitly set, use it. Otherwise toggle.
            if (show !== undefined) {
                isOpen = show;
            } else {
                isOpen = !isOpen;
            }
            
            loginDropdown.classList.toggle('show', isOpen);
            
            // Update ARIA attributes for accessibility
            loginToggle.setAttribute('aria-expanded', isOpen);
            loginDropdown.setAttribute('aria-hidden', !isOpen);
            
            console.log('Login dropdown toggled:', isOpen ? 'OPEN' : 'CLOSED');
        };

        // ONLY click to toggle - no hover behavior
        loginToggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            toggleDropdown();
            console.log('Login button clicked');
        });

        // Disable ALL hover behavior to prevent conflicts

        // Enhanced password manager detection
        const isPasswordManagerElement = (element) => {
            if (!element) return false;
            
            // Check element and all parents
            let current = element;
            while (current && current !== document.body) {
                // LastPass selectors
                if (current.hasAttribute?.('data-lastpass-root') ||
                    current.id?.includes('lastpass') ||
                    current.className?.includes('lastpass') ||
                    current.closest?.('#lastpass-vault') ||
                    current.closest?.('[data-lastpass-root]') ||
                    current.closest?.('.lp-element')) {
                    return true;
                }
                
                // 1Password selectors
                if (current.hasAttribute?.('data-1password') ||
                    current.id?.includes('1password') ||
                    current.className?.includes('1password')) {
                    return true;
                }
                
                // Bitwarden selectors
                if (current.hasAttribute?.('data-bitwarden') ||
                    current.id?.includes('bitwarden') ||
                    current.className?.includes('bitwarden')) {
                    return true;
                }
                
                // Generic password manager indicators
                if (current.className?.includes('password-manager') ||
                    current.className?.includes('extension-') ||
                    current.hasAttribute?.('data-password-manager')) {
                    return true;
                }
                
                current = current.parentElement;
            }
            
            return false;
        };

        // COMPLETELY disable all click-based closing
        document.addEventListener('click', (e) => {
            // NEVER close the dropdown automatically
            // Only allow manual closing via specific buttons or escape key
            return;
        });

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isOpen) {
                toggleDropdown(false);
                loginToggle.focus();
            }
        });

        // Add explicit close button handler
        const closeBtn = loginDropdown.querySelector('.close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleDropdown(false);
                console.log('Close button clicked');
            });
        }

        // Make sure form submission doesn't close dropdown prematurely
        const loginForm = loginDropdown.querySelector('.login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => {
                console.log('Form submitted - dropdown will remain open until page redirects');
            });
        }

        console.log('Login dropdown initialized - ALL automatic closing disabled for LastPass compatibility');

        // Handle dropdown links
        const dropdownLinks = loginDropdown.querySelectorAll('.dropdown-link');
        dropdownLinks.forEach(link => {
            link.addEventListener('click', () => {
                toggleDropdown(false);
            });
        });
    },

    // Navigation menu functionality
    initNavigation: function() {
        const navItems = document.querySelectorAll('.nav-item, .unified-header .nav-item');
        
        navItems.forEach(navItem => {
            const navLink = navItem.querySelector('.nav-link');
            const submenu = navItem.querySelector('.submenu');
            
            if (!submenu) return;
            
            let isHovering = false;
            let hoverTimer = null;
            
            // Show submenu on hover
            const showSubmenu = () => {
                if (hoverTimer) {
                    clearTimeout(hoverTimer);
                    hoverTimer = null;
                }
                isHovering = true;
                submenu.style.opacity = '1';
                submenu.style.visibility = 'visible';
                submenu.style.transform = 'translateY(0)';
            };
            
            // Hide submenu with delay
            const hideSubmenu = () => {
                isHovering = false;
                hoverTimer = setTimeout(() => {
                    if (!isHovering) {
                        submenu.style.opacity = '0';
                        submenu.style.visibility = 'hidden';
                        submenu.style.transform = 'translateY(-10px)';
                    }
                }, 200);
            };
            
            // Mouse enter on nav item
            navItem.addEventListener('mouseenter', showSubmenu);
            
            // Mouse leave on nav item
            navItem.addEventListener('mouseleave', hideSubmenu);
            
            // Mouse enter on submenu (prevent hiding)
            submenu.addEventListener('mouseenter', () => {
                if (hoverTimer) {
                    clearTimeout(hoverTimer);
                    hoverTimer = null;
                }
                isHovering = true;
            });
            
            // Mouse leave on submenu
            submenu.addEventListener('mouseleave', hideSubmenu);
            
            // Click handling for dropdown-only links
            if (navLink.classList.contains('nav-dropdown-only')) {
                navLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (submenu.style.opacity === '1') {
                        hideSubmenu();
                    } else {
                        showSubmenu();
                    }
                });
            }
            
            // Handle submenu clicks
            const submenuLinks = submenu.querySelectorAll('.submenu-link');
            submenuLinks.forEach(link => {
                link.addEventListener('click', () => {
                    // Add loading state
                    link.style.opacity = '0.7';
                    setTimeout(() => {
                        link.style.opacity = '1';
                    }, 200);
                });
            });
        });
        
        // Keyboard navigation support
        this.initKeyboardNavigation();
    },
    
    // Keyboard navigation for accessibility
    initKeyboardNavigation: function() {
        const navLinks = document.querySelectorAll('.nav-link, .submenu-link');
        
        navLinks.forEach((link, index) => {
            link.addEventListener('keydown', (e) => {
                switch(e.key) {
                    case 'ArrowRight':
                        e.preventDefault();
                        const nextLink = navLinks[index + 1];
                        if (nextLink) nextLink.focus();
                        break;
                        
                    case 'ArrowLeft':
                        e.preventDefault();
                        const prevLink = navLinks[index - 1];
                        if (prevLink) prevLink.focus();
                        break;
                        
                    case 'ArrowDown':
                        e.preventDefault();
                        const parentNavItem = link.closest('.nav-item');
                        if (parentNavItem) {
                            const firstSubmenuLink = parentNavItem.querySelector('.submenu-link');
                            if (firstSubmenuLink) firstSubmenuLink.focus();
                        }
                        break;
                        
                    case 'Escape':
                        e.preventDefault();
                        link.blur();
                        break;
                }
            });
        });
    },

    // Animation enhancements
    initAnimations: function() {
        // Add subtle animations to cards and widgets
        const animatedElements = document.querySelectorAll('.metric-card, .dashboard-widget, .feature-item, .quick-action-card');
        
        // Intersection Observer for scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.transform = 'translateY(0)';
                    entry.target.style.opacity = '1';
                } else {
                    entry.target.style.transform = 'translateY(20px)';
                    entry.target.style.opacity = '0.8';
                }
            });
        }, observerOptions);

        animatedElements.forEach(element => {
            element.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
            observer.observe(element);
        });

        // Enhanced hover effects for interactive elements
        this.initHoverEffects();
    },

    // Hover effects for better user feedback
    initHoverEffects: function() {
        // Quick action cards
        const quickActionCards = document.querySelectorAll('.quick-action-card');
        quickActionCards.forEach(card => {
            const icon = card.querySelector('.action-icon');
            
            card.addEventListener('mouseenter', () => {
                if (icon) {
                    icon.style.transform = 'scale(1.1) rotate(5deg)';
                }
            });
            
            card.addEventListener('mouseleave', () => {
                if (icon) {
                    icon.style.transform = 'scale(1) rotate(0deg)';
                }
            });
        });

        // Metric cards
        const metricCards = document.querySelectorAll('.metric-card');
        metricCards.forEach(card => {
            const icon = card.querySelector('.metric-icon');
            
            card.addEventListener('mouseenter', () => {
                if (icon) {
                    icon.style.transform = 'scale(1.1)';
                }
            });
            
            card.addEventListener('mouseleave', () => {
                if (icon) {
                    icon.style.transform = 'scale(1)';
                }
            });
        });

        // Button enhancements
        const buttons = document.querySelectorAll('.btn, .login-toggle');
        buttons.forEach(button => {
            button.addEventListener('mousedown', () => {
                button.style.transform = 'scale(0.98)';
            });
            
            button.addEventListener('mouseup', () => {
                button.style.transform = '';
            });
            
            button.addEventListener('mouseleave', () => {
                button.style.transform = '';
            });
        });
    },

    // Utility functions
    initUtilities: function() {
        // Number formatting with animation
        this.animateNumbers();
        
        // Smooth scrolling for anchor links
        this.initSmoothScrolling();
        
        // Form enhancements
        this.initFormEnhancements();
    },

    // Animate numbers on page load
    animateNumbers: function() {
        const numberElements = document.querySelectorAll('.metric-value');
        
        numberElements.forEach(element => {
            const text = element.textContent;
            const number = parseFloat(text.replace(/[^\d.-]/g, ''));
            
            if (!isNaN(number) && number > 0) {
                let start = 0;
                const duration = 1500;
                const increment = number / (duration / 16);
                
                element.textContent = '0';
                
                const timer = setInterval(() => {
                    start += increment;
                    if (start >= number) {
                        start = number;
                        clearInterval(timer);
                    }
                    
                    // Format the number back to original format
                    if (text.includes('SEK')) {
                        element.textContent = Math.floor(start).toLocaleString() + ' SEK';
                    } else if (text.includes('%')) {
                        element.textContent = start.toFixed(2) + '%';
                    } else {
                        element.textContent = Math.floor(start).toLocaleString();
                    }
                }, 16);
            }
        });
    },

    // Smooth scrolling for anchor links
    initSmoothScrolling: function() {
        const links = document.querySelectorAll('a[href^="#"]');
        
        links.forEach(link => {
            link.addEventListener('click', (e) => {
                const href = link.getAttribute('href');
                if (href === '#') return;
                
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    },

    // Form enhancements
    initFormEnhancements: function() {
        // Enhanced focus states
        const inputs = document.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            input.addEventListener('focus', () => {
                input.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', () => {
                input.parentElement.classList.remove('focused');
            });
            
            // Floating label effect
            const checkFloatingLabel = () => {
                if (input.value) {
                    input.parentElement.classList.add('has-value');
                } else {
                    input.parentElement.classList.remove('has-value');
                }
            };
            
            input.addEventListener('input', checkFloatingLabel);
            checkFloatingLabel(); // Check initial state
        });

        // Form validation feedback
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                const invalidInputs = form.querySelectorAll(':invalid');
                
                invalidInputs.forEach(input => {
                    input.parentElement.classList.add('error');
                    
                    // Remove error class on input
                    input.addEventListener('input', () => {
                        if (input.validity.valid) {
                            input.parentElement.classList.remove('error');
                        }
                    }, { once: true });
                });
            });
        });
    },

    // Scroll effects
    initScrollEffects: function() {
        let ticking = false;
        
        const updateScrollEffects = () => {
            const scrolled = window.scrollY;
            const rate = scrolled * -0.5;
            
            // Parallax effect for header backgrounds
            const headers = document.querySelectorAll('.dashboard-header, .landing-content');
            headers.forEach(header => {
                header.style.transform = `translateY(${rate * 0.1}px)`;
            });
            
            ticking = false;
        };

        window.addEventListener('scroll', () => {
            if (!ticking) {
                requestAnimationFrame(updateScrollEffects);
                ticking = true;
            }
        });
    },

    // Utility functions
    utils: {
        // Debounce function
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        // Format currency
        formatCurrency: function(amount, currency = 'SEK') {
            return new Intl.NumberFormat('sv-SE', {
                style: 'currency',
                currency: currency,
                minimumFractionDigits: 2
            }).format(amount);
        },

        // Format number
        formatNumber: function(number) {
            return new Intl.NumberFormat('sv-SE').format(number);
        },

        // Show notification
        showNotification: function(message, type = 'info', duration = 5000) {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
                <button class="notification-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 300);
                }
            }, duration);
        }
    }
};

// Global utility functions for easy access
window.showNotification = PSW.utils.showNotification;
window.formatCurrency = PSW.utils.formatCurrency;
window.formatNumber = PSW.utils.formatNumber;

// Global functions for dropdown (backward compatibility)
function toggleUserMenu() {
    const dropdown = document.querySelector('.login-dropdown, #loginDropdown');
    if (dropdown) {
        const isCurrentlyOpen = dropdown.classList.contains('show');
        dropdown.classList.toggle('show');
        console.log('Global toggleUserMenu called:', !isCurrentlyOpen ? 'OPENING' : 'CLOSING');
    }
}

function toggleLogin() {
    console.log('Global toggleLogin called');
    toggleUserMenu();
}