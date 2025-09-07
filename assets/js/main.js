// B.E.N.T.A - Business Expense and Net Transaction Analyzer
// Main JavaScript File

// Global variables
let currentUser = null;
let csrfToken = null;



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

    // Initialize enhanced validations and CSRF
    initializeEnhancedValidations();

    // Initialize amount formatting
    initializeAmountFormatting();

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

    // Skip further validation if field is empty and not required
    if (!value && !field.hasAttribute('required')) {
        clearFieldError(field);
        return true;
    }

    // Enhanced validation based on field type and name
    switch (field.type) {
        case 'email':
            isValid = validateEmail(value);
            errorMessage = isValid ? '' : 'Please enter a valid email address';
            break;

        case 'password':
            if (field.name === 'password') {
                isValid = validatePassword(value);
                errorMessage = isValid ? '' : getPasswordErrorMessage(value);
            } else if (field.name === 'confirm_password') {
                isValid = validateConfirmPassword(value, field);
                errorMessage = isValid ? '' : 'Passwords do not match';
            }
            break;

        case 'text':
        case 'textarea':
            if (field.name === 'username') {
                isValid = validateUsername(value);
                errorMessage = isValid ? '' : 'Username must be 3-50 characters, alphanumeric only';
            } else if (field.name === 'description') {
                isValid = validateDescription(value);
                errorMessage = isValid ? '' : 'Description must be less than 1000 characters';
            }
            break;

        case 'number':
        case 'date':
            if (field.name === 'amount') {
                isValid = validateAmount(value);
                errorMessage = isValid ? '' : 'Please enter a valid positive amount (max 999999.99)';
            } else if (field.name === 'date' || field.type === 'date') {
                isValid = validateDate(value);
                errorMessage = isValid ? '' : 'Please enter a valid date';
            }
            break;

        case 'tel':
            isValid = validatePhone(value);
            errorMessage = isValid ? '' : 'Please enter a valid phone number';
            break;

        case 'url':
            isValid = validateURL(value);
            errorMessage = isValid ? '' : 'Please enter a valid URL';
            break;
    }

    // Custom validation for select fields
    if (field.tagName === 'SELECT' && field.name === 'category_id') {
        isValid = validateCategorySelection(value);
        errorMessage = isValid ? '' : 'Please select a valid category';
    }

    if (!isValid) {
        showFieldError(field, errorMessage);
    } else {
        clearFieldError(field);
    }

    return isValid;
}

// Enhanced validation functions
function validateEmail(email) {
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    return emailRegex.test(email) && email.length <= 100;
}

function validatePassword(password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    const hasMinLength = password.length >= 8;
    const hasUpperCase = /[A-Z]/.test(password);
    const hasLowerCase = /[a-z]/.test(password);
    const hasNumbers = /\d/.test(password);
    const hasNoSpaces = !/\s/.test(password);
    const notCommon = !isCommonPassword(password);

    return hasMinLength && hasUpperCase && hasLowerCase && hasNumbers && hasNoSpaces && notCommon;
}

function getPasswordErrorMessage(password) {
    if (password.length < 8) return 'Password must be at least 8 characters long';
    if (!/[A-Z]/.test(password)) return 'Password must contain at least one uppercase letter';
    if (!/[a-z]/.test(password)) return 'Password must contain at least one lowercase letter';
    if (!/\d/.test(password)) return 'Password must contain at least one number';
    if (/\s/.test(password)) return 'Password cannot contain spaces';
    if (isCommonPassword(password)) return 'Please choose a stronger password';
    return 'Password does not meet requirements';
}

function validateConfirmPassword(confirmPassword, field) {
    const passwordField = document.querySelector('input[name="password"]');
    return passwordField && confirmPassword === passwordField.value;
}

function validateUsername(username) {
    const usernameRegex = /^[a-zA-Z0-9_]{3,50}$/;
    return usernameRegex.test(username);
}

function validateDescription(description) {
    return description.length <= 1000;
}

function validateAmount(amount) {
    const numValue = parseFloat(amount);
    return !isNaN(numValue) && numValue > 0 && numValue <= 999999.99 && /^\d+(\.\d{1,2})?$/.test(amount);
}

function validateDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const minDate = new Date('2000-01-01');

    return date instanceof Date && !isNaN(date) &&
           date >= minDate && date <= new Date(now.getFullYear() + 1, now.getMonth(), now.getDate());
}

function validatePhone(phone) {
    const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
    return phoneRegex.test(phone.replace(/[\s\-\(\)]/g, ''));
}

function validateURL(url) {
    try {
        new URL(url);
        return true;
    } catch {
        return false;
    }
}

function validateCategorySelection(categoryId) {
    return categoryId && categoryId !== '' && !isNaN(categoryId);
}

function isCommonPassword(password) {
    const commonPasswords = [
        'password', '12345678', 'qwerty', 'abc123', 'password123',
        'admin', 'letmein', 'welcome', 'monkey', 'dragon'
    ];
    return commonPasswords.includes(password.toLowerCase());
}

// CSRF Token Management
function initializeCSRFToken() {
    // Try to get CSRF token from meta tag or generate one
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        csrfToken = metaToken.getAttribute('content');
    }

    // Add CSRF token to all forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        addCSRFTokenToForm(form);
    });
}

function addCSRFTokenToForm(form) {
    // Remove existing CSRF token if present
    const existingToken = form.querySelector('input[name="csrf_token"]');
    if (existingToken) {
        existingToken.remove();
    }

    // Add new CSRF token
    const tokenInput = document.createElement('input');
    tokenInput.type = 'hidden';
    tokenInput.name = 'csrf_token';
    tokenInput.value = csrfToken || generateCSRFToken();
    form.appendChild(tokenInput);
}

function generateCSRFToken() {
    return Math.random().toString(36).substring(2) + Date.now().toString(36);
}

// Enhanced form submission validation
function validateFormBeforeSubmit(form) {
    const inputs = form.querySelectorAll('input, select, textarea');
    let isFormValid = true;
    let firstInvalidField = null;

    inputs.forEach(input => {
        if (!validateField(input)) {
            isFormValid = false;
            if (!firstInvalidField) {
                firstInvalidField = input;
            }
        }
    });

    if (!isFormValid && firstInvalidField) {
        firstInvalidField.focus();
        firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    return isFormValid;
}

// Initialize CSRF and form enhancements
function initializeEnhancedValidations() {
    // Initialize CSRF tokens
    initializeCSRFToken();

    // Add form submission validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateFormBeforeSubmit(this)) {
                e.preventDefault();
                return false;
            }
        });

        // Add CSRF token to form
        addCSRFTokenToForm(form);
    });

    // Add accessibility improvements
    addAccessibilityImprovements();
}

function addAccessibilityImprovements() {
    // Add ARIA labels to error messages
    const errorMessages = document.querySelectorAll('.field-error');
    errorMessages.forEach(error => {
        error.setAttribute('role', 'alert');
        error.setAttribute('aria-live', 'polite');
    });

    // Add ARIA labels to required fields
    const requiredFields = document.querySelectorAll('input[required], select[required], textarea[required]');
    requiredFields.forEach(field => {
        field.setAttribute('aria-required', 'true');
        const label = document.querySelector(`label[for="${field.id}"]`);
        if (label) {
            label.setAttribute('aria-label', label.textContent + ' (required)');
        }
    });
}

// Enhanced input sanitization
function sanitizeInput(input) {
    return input
        .replace(/[<>'"&]/g, '') // Remove potentially dangerous characters
        .trim()
        .substring(0, 1000); // Limit length
}

// Enhanced number formatting for amounts
function formatAmountInput(input) {
    let value = input.value.replace(/[^\d.]/g, '');
    const parts = value.split('.');
    if (parts.length > 2) {
        value = parts[0] + '.' + parts.slice(1).join('');
    }
    if (parts[1] && parts[1].length > 2) {
        value = parts[0] + '.' + parts[1].substring(0, 2);
    }
    input.value = value;
}

// Add amount formatting to number inputs
function initializeAmountFormatting() {
    const amountInputs = document.querySelectorAll('input[name="amount"]');
    amountInputs.forEach(input => {
        input.addEventListener('input', function() {
            formatAmountInput(this);
        });
    });
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
