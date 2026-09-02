import './stimulus_bootstrap.js';
import './js/delivery.js';

import $ from 'jquery';

window.$ = $;
window.jQuery = $;

import '@hotwired/turbo';

import './styles/app.css';
import './css/styles.css';

await import('./js/mondialrelay.js');
await import('./js/relay.js');
