// collapse.js
document.addEventListener('click', (e) => {
    const trigger = e.target.closest('.collapse-trigger');
    if (!trigger) return;

    const collapse = trigger.closest('.collapse-div');
    if (!collapse) return;

    const isOpen = collapse.classList.toggle('is-open');
    trigger.setAttribute('aria-expanded', String(isOpen));
});
