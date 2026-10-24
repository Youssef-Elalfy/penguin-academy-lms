import 'bootstrap/js/dist/offcanvas';

document.querySelectorAll('form[data-submit]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented || !form.checkValidity()) return;
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
        const button = form.querySelector('button[type="submit"]');
        if (!button) return;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.textContent = 'Please wait…';
    });
});

window.addEventListener('pageshow', (event) => {
    if (event.persisted) window.location.reload();
});


// Each row remains a labeled native select; ordering also works without JavaScript.
document.querySelectorAll('[data-order-form]').forEach((form) => {
    const rows = [...form.querySelectorAll('.order-row')];
    rows.forEach((row, index) => {
        row.querySelector('.order-controls').hidden = false;
        const up = row.querySelector('[data-order-up]');
        const down = row.querySelector('[data-order-down]');
        up.disabled = index === 0;
        down.disabled = index === rows.length - 1;
        const move = (target) => {
            const current = row.querySelector('select');
            const neighbor = rows[target].querySelector('select');
            [current.value, neighbor.value] = [neighbor.value, current.value];
            form.querySelector('[data-order-status]').textContent = 'Lesson moved to position ' + (target + 1) + '. Save to apply the new order.';
            neighbor.focus();
        };
        up.addEventListener('click', () => move(index - 1));
        down.addEventListener('click', () => move(index + 1));
    });
});

// Announce server validation and expose the expanded state of mobile navigation.
const errorSummary = document.querySelector('[data-error-summary]');
if (errorSummary) errorSummary.focus();
const navigation = document.getElementById('academy-navigation');
const navigationToggle = document.querySelector('[data-bs-target="#academy-navigation"][data-bs-toggle]');
if (navigation && navigationToggle) {
    navigation.addEventListener('shown.bs.offcanvas', () => navigationToggle.setAttribute('aria-expanded', 'true'));
    navigation.addEventListener('hidden.bs.offcanvas', () => navigationToggle.setAttribute('aria-expanded', 'false'));
}
