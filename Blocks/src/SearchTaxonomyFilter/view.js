import './view.scss';

// Toggle function for taxonomy filter
window.toggleTaxonomyFilter = function (header) {
    const content = header.nextElementSibling;
    const toggle = header.querySelector('.taxonomy-filter__toggle');

    if (content.classList.contains('collapsed')) {
        content.classList.remove('collapsed');
        toggle.classList.remove('collapsed');
    } else {
        content.classList.add('collapsed');
        toggle.classList.add('collapsed');
    }
};

// Preserve scroll position on filter changes
(function () {
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }

    const scrollPos = sessionStorage.getItem('filterScrollPosition');
    if (scrollPos) {
        requestAnimationFrame(() => {
            window.scrollTo(0, parseInt(scrollPos));
            sessionStorage.removeItem('filterScrollPosition');
        });
    }
})();

document.addEventListener('DOMContentLoaded', function () {
    const filterCheckboxes = document.querySelectorAll('.taxonomy-filter__checkbox');
    filterCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', function () {
            sessionStorage.setItem('filterScrollPosition', window.scrollY);
        });
    });
}); 