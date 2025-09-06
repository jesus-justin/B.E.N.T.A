// B.E.N.T.A - Business Expense and Net Transaction Analyzer
// Main JavaScript File

// Global variables
let currentUser = null;

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // Check if user is logged in
    checkAuthStatus();

    // Initialize animations
    initializeAnimations();

    // Initialize form validations
    initializeFormValidations();

    // Initialize responsive navigation
    initializeResponsiveNav();
}

// Authentication functions
function checkAuthStatus() {
    // Check if we're on an auth page
    if (window.location.pathname.includes('login.php') ||
        window.location.pathname.includes('register.php')) {
        return;
    }

    // For protected pages, check session
    fetch('api/auth_check.php')
        .then(response => response.json())
        .then(data => {
            if (!data.authenticated) {
                window.location.href = 'login.php';
            } else {
                currentUser = data.user;
                updateUserInterface();
            }
        })
        .catch(error => {
            console.error('Auth check failed:', error);
            window.location.href = 'login.php';
        });
}

function updateUserInterface() {
    // Update user info in header if exists
    const userInfoElements = document.querySelectorAll('.user-info');
    userInfoElements.forEach(element => {
        if (currentUser) {
            element.innerHTML = `<span>Welcome, ${currentUser.username}!</span>`;
        }
    });
}

// Form validation
function initializeFormValidations() {
    // Add real-time validation to forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });

            input.addEventListener('input', function() {
                clearFieldError(this);
            });
        });
    });
}

function validateField(field) {
    const value = field.value.trim();
    let isValid = true;
    let errorMessage = '';

    // Required field validation
    if (field.hasAttribute('required') && !value) {
        isValid = false;
        errorMessage = 'This field is required';
    }

    // Email validation
    if (field.type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address';
        }
    }

    // Password validation
    if (field.type === 'password' && field.name === 'password' && value) {
        if (value.length < 8) {
            isValid = false;
            errorMessage = 'Password must be at least 8 characters long';
        } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(value)) {
            isValid = false;
            errorMessage = 'Password must contain uppercase, lowercase, and number';
        }
    }

    // Number validation
    if (field.type === 'number' && value) {
        const numValue = parseFloat(value);
        if (field.name === 'amount' && (isNaN(numValue) || numValue <= 0)) {
            isValid = false;
            errorMessage = 'Please enter a valid positive amount';
        }
    }

    if (!isValid) {
        showFieldError(field, errorMessage);
    } else {
        clearFieldError(field);
    }

    return isValid;
}

function showFieldError(field, message) {
    clearFieldError(field);

    field.classList.add('error');
    field.style.borderColor = '#dc3545';

    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    errorDiv.style.color = '#dc3545';
    errorDiv.style.fontSize = '12px';
    errorDiv.style.marginTop = '5px';

    field.parentNode.appendChild(errorDiv);
}

function clearFieldError(field) {
    field.classList.remove('error');
    field.style.borderColor = '';

    const errorDiv = field.parentNode.querySelector('.field-error');
    if (errorDiv) {
        errorDiv.remove();
    }
}

// Message display functions
function showMessage(message, type = 'info', duration = 5000) {
    // Remove existing messages
    const existingMessages = document.querySelectorAll('.message');
    existingMessages.forEach(msg => msg.remove());

    // Create new message
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}`;
    messageDiv.textContent = message;
    messageDiv.style.position = 'fixed';
    messageDiv.style.top = '20px';
    messageDiv.style.right = '20px';
    messageDiv.style.zIndex = '9999';
    messageDiv.style.minWidth = '300px';

    document.body.appendChild(messageDiv);

    // Add fade-in animation
    messageDiv.style.opacity = '0';
    messageDiv.style.transform = 'translateY(-20px)';
    messageDiv.style.transition = 'all 0.3s ease';

    setTimeout(() => {
        messageDiv.style.opacity = '1';
        messageDiv.style.transform = 'translateY(0)';
    }, 100);

    // Auto remove after duration
    if (duration > 0) {
        setTimeout(() => {
            messageDiv.style.opacity = '0';
            messageDiv.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.parentNode.removeChild(messageDiv);
                }
            }, 300);
        }, duration);
    }

    return messageDiv;
}

// Modal functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';

        // Add fade-in animation
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.style.animation = 'fadeIn 0.3s ease-out';
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Responsive navigation
function initializeResponsiveNav() {
    // Create mobile menu toggle if needed
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');

    if (window.innerWidth <= 768) {
        // Create toggle button
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'sidebar-toggle';
        toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
        toggleBtn.style.cssText = `
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: #667eea;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            display: none;
        `;

        document.body.appendChild(toggleBtn);

        // Toggle sidebar
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });

        // Close sidebar when clicking outside
        mainContent.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('active');
            }
        });
    }
}

// Utility functions
function formatCurrency(amount, currency = 'PHP') {
    const symbols = {
        'PHP': '₱',
        'USD': '$',
        'EUR': '€',
        'GBP': '£',
        'JPY': '¥'
    };

    const symbol = symbols[currency] || currency;
    return symbol + parseFloat(amount).toFixed(2);
}

function formatDate(dateString, format = 'M d, Y') {
    const date = new Date(dateString);
    const options = {
        'M d, Y': { month: 'short', day: 'numeric', year: 'numeric' },
        'Y-m-d': { year: 'numeric', month: '2-digit', day: '2-digit' }
    };

    return date.toLocaleDateString('en-US', options[format] || options['M d, Y']);
}

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

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

// Loading states
function showLoading(element, text = 'Loading...') {
    if (typeof element === 'string') {
        element = document.querySelector(element);
    }

    if (element) {
        element.innerHTML = `<div class="loading">${text}</div>`;
    }
}

function hideLoading(element) {
    if (typeof element === 'string') {
        element = document.querySelector(element);
    }

    if (element) {
        const loadingDiv = element.querySelector('.loading');
        if (loadingDiv) {
            loadingDiv.remove();
        }
    }
}

// Export functions for global use
window.BENTA = {
    showMessage,
    openModal,
    closeModal,
    formatCurrency,
    formatDate,
    showLoading,
    hideLoading,
    validateField
};
