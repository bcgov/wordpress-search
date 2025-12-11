import './view.scss';

window.applyTaxonomyFilters = function () {
    const currentUrl = new URL(window.location.href);
    const params = currentUrl.searchParams;

    // Preserve sort parameters before clearing filters
    const sortParam = params.get('sort');
    const metaSortParam = params.get('meta_sort');
    const metaFieldParam = params.get('meta_field');

    // Clear old taxonomy filters and pagination
    Array.from(params.keys())
        .filter(key => key.startsWith('taxonomy_') || key === 'paged')
        .forEach(key => params.delete(key));

    // Restore sort parameters if they existed
    if (sortParam) {
        params.set('sort', sortParam);
    }
    if (metaSortParam) {
        params.set('meta_sort', metaSortParam);
    }
    if (metaFieldParam) {
        params.set('meta_field', metaFieldParam);
    }

    // Remove /page/2/ from path (robust version)
    currentUrl.pathname = currentUrl.pathname
        .replace(/\/page\/\d+\/?/g, '/')
        .replace(/\/+/g, '/')
        .replace(/\/$/, '') || '/';  // Remove trailing slash except for root

    // If original URL had trailing slash on non-page paths, restore it?
    // Usually not needed — WordPress treats both as same

    // Rebuild taxonomy params
    document.querySelectorAll('.taxonomy-filter__checkbox:checked').forEach(checkbox => {
        let name = checkbox.name.replace('[]', '');
        let value = checkbox.value;

        if (params.has(name)) {
            params.set(name, params.get(name) + ',' + value);
        } else {
            params.set(name, value);
        }
    });

    // Use replaceState for instant feel (optional, if you want no reload)
    // Or just: window.location.href = currentUrl.toString();
    window.location.href = currentUrl.toString();
};