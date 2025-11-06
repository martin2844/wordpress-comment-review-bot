/**
 * WordPress Review Bot Admin JavaScript - Development Version
 */
console.log('WordPress Review Bot Admin Development loaded');

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initAdmin();
});

function initAdmin() {
    // Add admin functionality here
    const buttons = document.querySelectorAll('.wrb-button');

    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            handleButtonClick(this);
        });
    });
}

function handleButtonClick(button) {
    const action = button.dataset.action;

    if (action) {
        // Show loading state
        button.disabled = true;
        button.textContent = 'Loading...';

        // Simulate API call
        setTimeout(() => {
            button.disabled = false;
            button.textContent = 'Success!';

            setTimeout(() => {
                button.textContent = button.dataset.originalText || 'Submit';
            }, 2000);
        }, 1000);
    }
}

// Hot Module Replacement support
if (import.meta.hot) {
    import.meta.hot.accept();
}