import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    open() {
        this.element.classList.remove('hidden');
        this.element.classList.add('flex');
    }

    close() {
        this.element.classList.add('hidden');
        this.element.classList.remove('flex');
    }
}
