document.addEventListener('DOMContentLoaded', () => {

    const collapses = document.querySelectorAll('.collapse-div');

    collapses.forEach((collapse) => {
        const trigger = collapse.querySelector('.collapse-trigger');

        trigger.addEventListener('click', () => {
            const isOpen = collapse.classList.contains('is-open');
            collapse.classList.toggle('is-open', !isOpen);
            trigger.setAttribute('aria-expanded', String(!isOpen));
        });
    });

});
