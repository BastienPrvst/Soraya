if (!window.__ordersToggleInit) {
    window.__ordersToggleInit = true;

    document.addEventListener('click', function (e) {

        // Déplier / replier les produits d'une commande
        const seeBtn = e.target.closest('.see-order');
        if (seeBtn) {
            const order = seeBtn.closest('.order');
            const isOpen = order.classList.toggle('is-open');

            seeBtn.setAttribute('aria-expanded', isOpen);
            seeBtn.textContent = isOpen ? 'Masquer la commande' : 'Voir la commande';
            return;
        }

        // Afficher / masquer les commandes supplémentaires (8 max)
        const moreBtn = e.target.closest('#toggle-more-orders');
        if (moreBtn) {
            const expanded = moreBtn.getAttribute('aria-expanded') === 'true';
            const extras = document.querySelectorAll('.extra-order');

            extras.forEach(function (el) {
                el.classList.toggle('hidden', expanded);
                el.classList.toggle('is-shown', !expanded);
            });

            // Fait défiler doucement jusqu'à la première commande révélée
            if (!expanded && extras.length) {
                extras[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            moreBtn.setAttribute('aria-expanded', !expanded);
            moreBtn.textContent = expanded ? 'Voir le reste des commandes' : 'Masquer les commandes';
        }
    });

    document.addEventListener('click', (e) => {
        const box = document.getElementById('profile-info');
        if (!box) return;

        if (e.target.closest('#profile-edit')) {
            box.classList.add('is-editing');
        }

        const cancel = e.target.closest('#profile-cancel');
        if (cancel && !box.hasAttribute('data-submitted')) {
            e.preventDefault();
            box.classList.remove('is-editing');
            box.querySelector('form').reset();
        }
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#address-erase')) return;

        document.querySelectorAll('#address-fields input, #address-fields select')
            .forEach((field) => (field.value = ''));
    });
}
