

console.log('ici')

document.addEventListener('DOMContentLoaded', function() {
    let street = document.querySelector('#order_deliveryAddress_street1');
    if (!street) return;

    street.addEventListener("input", function () {
        console.log('ici');
        let params = new URLSearchParams({ q: this.value });

        fetch(`https://data.geopf.fr/geocodage/search?${params}`)
            .then(res => res.json())
            .then(data => console.log(data))
            .catch(err => console.error(err));
    });
});

