// B.E.N.T.A - Business Expense and Net Transaction Analyzer
// Animation Effects

// Initialize animations when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeAnimations();
});

function initializeAnimations() {
    // Add fade-in animation to main content
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.classList.add('fade-in');
    }

    // Add slide-in animation to sidebar
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.add('slide-in');
    }

    // Animate stat cards on scroll
    animateOnScroll();

    // Add hover effects to interactive elements
    addHoverEffects();

    // Initialize loading animations
    initializeLoadingAnimations();

    // Add click animations
    addClickAnimations();
}

function animateOnScroll() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
            }
        });
    }, observerOptions);

    // Observe stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        observer.observe(card);
    });

    // Observe other elements
    const animateElements = document.querySelectorAll('.transaction-item, .category-item, .report-section');
    animateElements.forEach(element => {
        observer.observe(element);
    });
}

function addHoverEffects() {
    // Add hover effect to buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px) scale(1.02)';
        });

        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Add hover effect to table rows
    const tableRows = document.querySelectorAll('.data-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8f9fa';
            this.style.transform = 'scale(1.01)';
            this.style.transition = 'all 0.2s ease';
        });

        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
            this.style.transform = 'scale(1)';
        });
    });

    // Add hover effect to cards
    const cards = document.querySelectorAll('.stat-card, .recent-transactions, .quick-actions');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.15)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
        });
    });
}

function addClickAnimations() {
    // Add ripple effect to buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            createRippleEffect(e, this);
        });
    });

    // Add click animation to sidebar menu items
    const menuItems = document.querySelectorAll('.sidebar-menu a');
    menuItems.forEach(item => {
        item.addEventListener('click', function() {
            // Remove active class from all items
            menuItems.forEach(i => i.parentElement.classList.remove('active'));
            // Add active class to clicked item
            this.parentElement.classList.add('active');

            // Add click animation
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
        });
    });
}

function createRippleEffect(event, element) {
    const ripple = document.createElement('span');
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;

    ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        left: ${x}px;
        top: ${y}px;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        transform: scale(0);
        animation: ripple 0.6s ease-out;
        pointer-events: none;
    `;

    element.style.position = 'relative';
    element.style.overflow = 'hidden';
    element.appendChild(ripple);

    setTimeout(() => {
        ripple.remove();
    }, 600);
}

// Add ripple animation to CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

function initializeLoadingAnimations() {
    // Add loading animation to elements with loading class
    const loadingElements = document.querySelectorAll('.loading');
    loadingElements.forEach(element => {
        element.innerHTML = `
            <div class="loading-spinner">
                <div class="spinner"></div>
                <span>${element.textContent || 'Loading...'}</span>
            </div>
        `;
    });
}

// Loading spinner styles
const spinnerStyles = document.createElement('style');
spinnerStyles.textContent = `
    .loading-spinner {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
    }

    .spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #667eea;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(spinnerStyles);

// Page transition animations
function animatePageTransition(fromPage, toPage) {
    const mainContent = document.querySelector('.main-content');

    // Fade out current content
    mainContent.style.opacity = '0';
    mainContent.style.transform = 'translateY(20px)';

    setTimeout(() => {
        // Load new content (this would be handled by your routing)
        // For now, just fade back in
        mainContent.style.opacity = '1';
        mainContent.style.transform = 'translateY(0)';
        mainContent.style.transition = 'all 0.3s ease';
    }, 300);
}

// Form field animations
function animateFormFields() {
    const formGroups = document.querySelectorAll('.form-group');
    formGroups.forEach((group, index) => {
        group.style.opacity = '0';
        group.style.transform = 'translateY(20px)';

        setTimeout(() => {
            group.style.transition = 'all 0.5s ease';
            group.style.opacity = '1';
            group.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

// Success/error message animations
function animateMessage(messageElement) {
    messageElement.style.animation = 'slideInFromTop 0.5s ease-out';
}

// Add message animation styles
const messageStyles = document.createElement('style');
messageStyles.textContent = `
    @keyframes slideInFromTop {
        from {
            transform: translateY(-100%);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(messageStyles);

// Modal animations
function animateModal(modal) {
    const modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.style.animation = 'modalFadeIn 0.3s ease-out';
    }
}

// Add modal animation styles
const modalStyles = document.createElement('style');
modalStyles.textContent = `
    @keyframes modalFadeIn {
        from {
            transform: scale(0.8) translateY(-20px);
            opacity: 0;
        }
        to {
            transform: scale(1) translateY(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(modalStyles);

// Progress bar animation for forms
function animateProgress(progressElement, percentage) {
    progressElement.style.width = '0%';
    progressElement.style.transition = 'width 0.5s ease';

    setTimeout(() => {
        progressElement.style.width = percentage + '%';
    }, 100);
}

// Typing animation for text
function typeWriter(element, text, speed = 50) {
    let i = 0;
    element.textContent = '';

    function type() {
        if (i < text.length) {
            element.textContent += text.charAt(i);
            i++;
            setTimeout(type, speed);
        }
    }

    type();
}

// Pulse animation for notifications
function addPulseAnimation(element) {
    element.style.animation = 'pulse 2s infinite';
}

const pulseStyles = document.createElement('style');
pulseStyles.textContent = `
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
`;
document.head.appendChild(pulseStyles);

// Shake animation for error states
function shakeElement(element) {
    element.style.animation = 'shake 0.5s ease-in-out';
}

const shakeStyles = document.createElement('style');
shakeStyles.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
`;
document.head.appendChild(shakeStyles);

// Export animation functions
window.AnimationUtils = {
    animatePageTransition,
    animateFormFields,
    animateMessage,
    animateModal,
    animateProgress,
    typeWriter,
    addPulseAnimation,
    shakeElement,
    createRippleEffect
};
