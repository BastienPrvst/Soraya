<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    'bootstrap' => [
        'version' => '5.3.8',
    ],
    '@popperjs/core' => [
        'version' => '2.11.8',
    ],
    'bootstrap/dist/css/bootstrap.min.css' => [
        'version' => '5.3.8',
        'type' => 'css',
    ],
    '@hotwired/turbo' => [
        'version' => '8.0.23',
    ],
    'chart.js' => [
        'version' => '4.0.1',
    ],
    '@kurkle/color' => [
        'version' => '0.3.4',
    ],
    'admin' => [
        'path' => './assets/admin.js',
        'entrypoint' => true,
    ],
    '@symfony/ux-chartjs/controller' => [
        'path' => './vendor/symfony/ux-chartjs/assets/dist/controller.js',
    ],
    'swiper' => [
        'version' => '14.1.0',
    ],
    'swiper/modules' => [
        'version' => '14.1.0',
    ],
    'swiper/css' => [
        'version' => '14.1.0',
    ],
    'swiper/css/navigation' => [
        'version' => '14.1.0',
    ],
    'swiper/css/thumbs' => [
        'version' => '14.1.0',
    ],
    'product-swiper.js' => [
        'path' => './assets/js/product-swiper.js',
        'entrypoint' => true,
    ],
    'jquery' => [
        'version' => '3.7.1',
    ],
    'mondial_relay' => [
        'path' => './assets/js/mondialrelay.js',
        'entrypoint' => true,
    ],
    'relay' => [
        'path' => './assets/js/relay.js',
        'entrypoint' => true,
    ]
];
