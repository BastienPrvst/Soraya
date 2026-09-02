document.addEventListener('DOMContentLoaded', function() {

    const street = document.querySelector('.street_input');
    if (!street) return;

    const wrapper = street.parentElement;

    // création du container suggestions
    const list = document.createElement('div');
    list.classList.add('autocomplete-list');

    wrapper.style.position = 'relative';
    wrapper.appendChild(list);

    let timeout = null;

    street.addEventListener("input", function () {

        clearTimeout(timeout);

        timeout = setTimeout(() => {

            const value = this.value.trim();

            list.style.backgroundColor = '#E2D6C3'

            if (value.length < 3) {
                list.innerHTML = '';
                list.style.display = 'none';
                return;
            }

            let params = new URLSearchParams({ q: value });

            fetch(`https://data.geopf.fr/geocodage/search?${params}`)
                .then(res => res.json())
                .then(data => {

                    const results = (data.features || []).filter(item => {
                        const p = item.properties;
                        return p.housenumber && (p.street || p.name);
                    });

                    list.innerHTML = '';

                    if (results.length === 0) {
                        list.style.display = 'none';
                        return;
                    }

                    results.slice(0, 5).forEach(item => {

                        const label = item.properties.label;

                        const div = document.createElement('div');
                        div.classList.add('autocomplete-item');
                        div.textContent = label;

                        div.addEventListener('click', () => {

                            const props = item.properties;

                            // Numéro + voie uniquement
                            const number = props.housenumber ?? '';
                            const streetName = props.street ?? props.name ?? '';

                            street.value = [number, streetName]
                                .filter(Boolean)
                                .join(' ')
                                .trim();

                            // Ville
                            const cityInput = document.querySelector('.city_input');
                            if (cityInput) {
                                cityInput.value = props.city || '';
                            }

                            // Code postal
                            const zipInput = document.querySelector('.zipcode_input');
                            if (zipInput) {
                                zipInput.value = props.postcode || '';
                            }

                            list.innerHTML = '';
                            list.style.display = 'none';
                        });

                        list.appendChild(div);
                    });

                    list.style.display = 'block';

                })
                .catch(err => console.error(err));

        }, 300);

    });

    // fermeture si clic extérieur
    document.addEventListener('click', function (e) {
        if (!wrapper.contains(e.target)) {
            list.innerHTML = '';
            list.style.display = 'none';
        }
    });

});
