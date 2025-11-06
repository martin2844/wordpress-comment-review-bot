/**
 * WordPress Review Bot Frontend JavaScript - Development Version
 */
console.log('WordPress Review Bot Frontend Development loaded');

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initFrontend();
});

function initFrontend() {
    // Add frontend functionality here
    initReviewCards();
    initFormValidation();
}

function initReviewCards() {
    const reviewCards = document.querySelectorAll('.wrb-review-card');

    reviewCards.forEach(card => {
        const helpfulBtns = card.querySelectorAll('.wrb-helpful-btn');

        helpfulBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                handleHelpfulClick(this);
            });
        });
    });
}

function handleHelpfulClick(button) {
    const reviewId = button.dataset.reviewId;
    const isHelpful = button.dataset.helpful;

    // Show loading state
    button.disabled = true;
    button.textContent = 'Submitting...';

    // Simulate API call
    fetch(wrb_ajax.ajax_url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'wrb_mark_helpful',
            review_id: reviewId,
            helpful: isHelpful,
            nonce: wrb_ajax.nonce
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.textContent = 'Thanks!';
            button.disabled = true;
        } else {
            button.disabled = false;
            button.textContent = 'Error';
        }
    })
    .catch(() => {
        button.disabled = false;
        button.textContent = 'Error';
    });
}

function initFormValidation() {
    const forms = document.querySelectorAll('.wrb-review-form');

    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const title = form.querySelector('#wrb-review-title').value.trim();
            const content = form.querySelector('#wrb-review-content').value.trim();
            const submitBtn = form.querySelector('button[type="submit"]');

            // Basic validation
            if (!title || !content) {
                alert('Please fill in all required fields.');
                return;
            }

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';

            // Simulate form submission
            setTimeout(() => {
                form.reset();
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Review';
                alert('Review submitted successfully!');
            }, 1500);
        });
    });
}

// Hot Module Replacement support
if (import.meta.hot) {
    import.meta.hot.accept();
}