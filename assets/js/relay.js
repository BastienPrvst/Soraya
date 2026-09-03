import $ from 'jquery';

let widgetInitialized = false;

function initRelayWidget() {
    if (widgetInitialized) {
        return;
    }

    $("#Zone_Widget").MR_ParcelShopPicker({
        Brand: "TTNTWSDB",
        Country: "FR",
        AllowedCountries: "FR,BE,LU,ES",
        PostCode: "75001",
        NbResults: "10",
        Responsive: true,
        EnableGmap: false,
        Target: '#Target_Widget',
        MapScrollWheel: true
    });

    widgetInitialized = true;
}

function initChangeRelayButton() {
    const btn = document.querySelector('.change_relay');

    if (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();

            const relayInfo = document.querySelector('#relay_info');
            if (relayInfo) {
                relayInfo.remove();
            }

            const zone = document.querySelector('#Zone_Widget');
            if (zone) {
                zone.classList.remove('hidden');
                initRelayWidget();
            }
        });
    }
}

function init() {
    const zone = document.querySelector('#Zone_Widget');
    if (zone && !zone.classList.contains('hidden')) {
        initRelayWidget();
    }

    initChangeRelayButton();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
