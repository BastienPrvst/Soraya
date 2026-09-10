import Swiper from 'swiper';
import { Navigation, Thumbs } from 'swiper/modules';

import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/thumbs';

document.addEventListener('DOMContentLoaded', () => {
    const mainSwiperEl = document.querySelector('.main-swiper');
    if (!mainSwiperEl) {
        return;
    }

    const thumbSwiperEl = document.querySelector('.thumb-swiper');
    const thumbSwiper = thumbSwiperEl
        ? new Swiper(thumbSwiperEl, {
            modules: [Navigation, Thumbs],
            slidesPerView: 3,
            spaceBetween: 12,
            watchSlidesProgress: true,
        })
        : null;

    const mainSwiperConfig = {
        modules: [Navigation, Thumbs],
        spaceBetween: 0,
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
    };

    if (thumbSwiper) {
        mainSwiperConfig.thumbs = { swiper: thumbSwiper };
    }

    const mainSwiper = new Swiper(mainSwiperEl, mainSwiperConfig);

    mainSwiperEl.classList.remove('opacity-0');
    thumbSwiperEl?.classList.remove('opacity-0');

    thumbSwiperEl?.classList.remove('opacity-0');

    const quantityInput = document.getElementById('quantity-input');
    const quantityDisplay = document.getElementById('quantity-display');

    document.querySelectorAll('[data-qty-btn]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const delta = parseInt(btn.dataset.qtyBtn, 10);
            let value = parseInt(quantityInput.value, 10) + delta;
            if (value < 1) value = 1;
            quantityInput.value = value;
            quantityDisplay.textContent = value;
        });
    });
});
