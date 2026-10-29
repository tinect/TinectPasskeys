import { setButtonLoading } from '../helper/button-loading.helper';
import {
    isAbortError,
    isPasskeySupported,
    parseRequestOptions,
    postForm,
    serializeCredential,
} from '../helper/webauthn.helper';

/**
 * Confirms a form with one of the customer's passkeys before it is sent.
 */
export default class TinectPasskeyConfirmPlugin extends window.PluginBaseClass {
    static options = {
        /** Url to fetch the assertion options for the confirmation */
        optionsUrl: '',

        /** Fallback message if the server does not return one */
        errorMessage: '',

        /** Message if the browser does not support passkeys */
        unsupportedMessage: '',

        /** Selector for the error message container */
        errorSelector: '.tinect-passkey-confirm-error',

        /** Class to toggle visibility (display: none) */
        invisibleClass: 'd-none',
    };

    init() {
        this._submitButton = this.el.querySelector('[type="submit"]');
        // the form can sit in a modal footer, the message in its body
        this._error =
            this.el.querySelector(this.options.errorSelector) ??
            this.el
                .closest('.modal-content')
                ?.querySelector(this.options.errorSelector);

        this.el.addEventListener('submit', this._onSubmit.bind(this));
    }

    async _onSubmit(event) {
        event.preventDefault();

        this._error?.classList.add(this.options.invisibleClass);

        if (!isPasskeySupported()) {
            this._showError(this.options.unsupportedMessage);

            return;
        }

        setButtonLoading(this._submitButton, true);

        try {
            const options = await postForm(this.options.optionsUrl, {});
            const credential = await navigator.credentials.get({
                publicKey: parseRequestOptions(options),
            });

            await postForm(this.el.action, {
                ...Object.fromEntries(new FormData(this.el)),
                credential: serializeCredential(credential),
            });

            // the indicator stays until the page has reloaded
            window.location.reload();
        } catch (error) {
            setButtonLoading(this._submitButton, false);

            if (!isAbortError(error)) {
                this._showError(
                    error?.serverMessage || this.options.errorMessage
                );
            }
        }
    }

    _showError(message) {
        if (!this._error) {
            return;
        }

        this._error.textContent = message;
        this._error.classList.remove(this.options.invisibleClass);
    }
}
