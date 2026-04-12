document.addEventListener('DOMContentLoaded', function() {
    const input = document.querySelector('input[name="search"]');
    const overlay = document.getElementById('search-overlay');
    let overlayActive = false;
    let overlayLinkClicked = false;

    function showOverlay() {
        overlay.style.display = 'block';
        overlay.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div></div>';
        const ajaxUrl = input.getAttribute('data-ajax-url');
        fetch(ajaxUrl + '?search=' + encodeURIComponent(input.value))
            .then(r => r.text())
            .then(html => {
                overlay.innerHTML = html;
            });
    }

    function hideOverlay() {
        overlay.style.display = 'none';
    }

    input.addEventListener('focus', function() {
        showOverlay();
    });

    input.addEventListener('input', function() {
        showOverlay();
    });

    input.addEventListener('blur', function() {
        setTimeout(function() {
            if (!overlayActive && !overlayLinkClicked) {
                hideOverlay();
            }
        }, 150);
    });

    overlay.addEventListener('mousedown', function(e) {
        overlayActive = true;
    });
    overlay.addEventListener('mouseup', function(e) {
        overlayActive = false;
    });
    overlay.addEventListener('click', function(e) {
        if (e.target.tagName === 'A') {
            overlayLinkClicked = true;
            overlay.style.display = 'none';
            setTimeout(function() { overlayLinkClicked = false; }, 500);
        }
    });
});