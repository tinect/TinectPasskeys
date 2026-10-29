// Mirrors the markup of Shopware's ButtonLoadingIndicatorUtil, which can not be imported in the ES build of plugins.
const LOADER_CLASS = 'loader';
const LOADING_CLASS = 'is-loading-indicator-before';

export function isButtonLoading(button) {
    return button.querySelector(`.${LOADER_CLASS}`) !== null;
}

export function setButtonLoading(button, loading) {
    if (loading === isButtonLoading(button)) {
        return;
    }

    if (loading) {
        button.insertAdjacentHTML(
            'afterbegin',
            `<div class="${LOADER_CLASS}" role="status"><span class="visually-hidden">Loading...</span></div>`
        );
        button.classList.add(LOADING_CLASS);
        button.disabled = true;

        return;
    }

    button.querySelectorAll(`.${LOADER_CLASS}`).forEach((loader) => loader.remove());
    button.classList.remove(LOADING_CLASS);
    button.disabled = false;
}
