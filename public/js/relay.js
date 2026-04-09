$(document).ready(function () {
    $("#Zone_Widget").MR_ParcelShopPicker({
        Brand: "TTNTWSDB",
        Country: "FR",
        AllowedCountries: "FR,BE,LU,ES",
        PostCode: "75001",
        NbResults: "10",
        Responsive: true,
        ShowResultsOnMap: true,
        EnableGmap: false,
        Target: '#Target_Widget',
        MapScrollWheel: true
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const btn = document.querySelector('.change_relay');

    if (btn) {
        btn.addEventListener('click', function () {
            const relayInfo = document.querySelector('#relay_info');

            if (relayInfo) {
                relayInfo.remove();
            }

            const zone = document.querySelector('#Zone_Widget');
            if (zone) {
                zone.classList.remove('d-none');
            }
        });
    }
});
