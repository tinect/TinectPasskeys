import { setButtonLoading } from '../helper/button-loading.helper';
import {
    isAbortError,
    isPasskeySupported,
    parseCreationOptions,
    postForm,
    serializeCredential,
} from '../helper/webauthn.helper';

export default class TinectPasskeyRegisterPlugin extends window.PluginBaseClass {
    static options = {
        /** Url to fetch the creation options */
        optionsUrl: '',

        /** Url to verify and store the new passkey */
        registerUrl: '',

        /** Fallback message if the server does not return one */
        errorMessage: '',

        /** Selector for the error message container */
        errorSelector: '.tinect-passkey-register-error',

        /** Selector for the hint shown when the browser does not support passkeys */
        unsupportedSelector: '.tinect-passkey-register-unsupported',

        /** Class to toggle visibility (display: none) */
        invisibleClass: 'd-none',
    };

    init() {
        this._error = this.el.querySelector(this.options.errorSelector);
        this._submitButton = this.el.querySelector('[type="submit"]');

        if (!isPasskeySupported()) {
            this.el.querySelector(this.options.unsupportedSelector)?.classList.remove(this.options.invisibleClass);
            this._submitButton.disabled = true;

            return;
        }

        this.el.addEventListener('submit', this._onSubmit.bind(this));
    }

    async _onSubmit(event) {
        event.preventDefault();

        this._error.classList.add(this.options.invisibleClass);
        setButtonLoading(this._submitButton, true);

        const passwordInput = this.el.querySelector('[name="password"]');
        const nameInput = this.el.querySelector('[name="name"]');

        try {
            const options = await postForm(this.options.optionsUrl, { password: passwordInput.value });
            passwordInput.value = '';

            const credential = await navigator.credentials.create({
                publicKey: parseCreationOptions(options),
            });

            await postForm(this.options.registerUrl, {
                credential: serializeCredential(credential),
                name: nameInput.value,
            });

            // the indicator stays until the page has reloaded
            window.location.reload();
        } catch (error) {
            setButtonLoading(this._submitButton, false);

            if (!isAbortError(error)) {
                this._error.textContent = error?.serverMessage || this.options.errorMessage;
                this._error.classList.remove(this.options.invisibleClass);
            }
        }
    }
}
