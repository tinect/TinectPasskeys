import { setButtonLoading } from '../helper/button-loading.helper';
import {
    isAbortError,
    isConditionalMediationAvailable,
    isPasskeySupported,
    parseRequestOptions,
    postForm,
    serializeCredential,
} from '../helper/webauthn.helper';

export default class TinectPasskeyLoginPlugin extends window.PluginBaseClass {
    static options = {
        /** Url to fetch the assertion options */
        optionsUrl: '',

        /** Url to verify the assertion and log in */
        loginUrl: '',

        /** Fallback message if the server does not return one */
        errorMessage: '',

        /** Selector for the button starting the passkey login */
        buttonSelector: '.tinect-passkey-login-button',

        /** Selector for the error message container */
        errorSelector: '.tinect-passkey-login-error',

        /** Class to toggle visibility (display: none) */
        invisibleClass: 'd-none',
    };

    init() {
        if (!isPasskeySupported()) {
            return;
        }

        this._form = this.el.closest('form');
        this._button = this.el.querySelector(this.options.buttonSelector);
        this._error = this.el.querySelector(this.options.errorSelector);
        this._conditionalAbortController = null;

        this.el.classList.remove(this.options.invisibleClass);
        this._button.addEventListener('click', this._onButtonClick.bind(this));

        this._startConditionalLogin();
    }

    async _startConditionalLogin() {
        if (
            !this._form?.querySelector('input[autocomplete~="webauthn"]') ||
            !(await isConditionalMediationAvailable())
        ) {
            return;
        }

        this._conditionalAbortController = new AbortController();

        try {
            await this._login({
                mediation: 'conditional',
                signal: this._conditionalAbortController.signal,
            });
        } catch (error) {
            // aborted when the button is clicked, the button flow owns the indicator then
            if (isAbortError(error)) {
                return;
            }

            this._setLoading(false);
            this._showError(error);
        }
    }

    async _onButtonClick(event) {
        event.preventDefault();

        this._conditionalAbortController?.abort();
        this._conditionalAbortController = null;
        this._hideError();
        this._setLoading(true);

        try {
            await this._login({});
        } catch (error) {
            this._setLoading(false);

            if (!isAbortError(error)) {
                this._showError(error);
            }
        }
    }

    async _login(requestOptions) {
        const options = await postForm(this.options.optionsUrl, {});
        const credential = await navigator.credentials.get({
            ...requestOptions,
            publicKey: parseRequestOptions(options),
        });

        if (!credential) {
            this._setLoading(false);

            return;
        }

        // the conditional login starts from the autofill, so the button shows the progress only from here on
        this._setLoading(true);

        const result = await postForm(this.options.loginUrl, {
            credential: serializeCredential(credential),
            redirectTo: this._form?.querySelector('[name="redirectTo"]')?.value,
            redirectParameters: this._form?.querySelector(
                '[name="redirectParameters"]'
            )?.value,
        });

        // the indicator stays until the browser has navigated away
        window.location.assign(result.redirectUrl);
    }

    _setLoading(loading) {
        setButtonLoading(this._button, loading);
    }

    _showError(error) {
        if (!this._error) {
            return;
        }

        this._error.textContent =
            error?.serverMessage || this.options.errorMessage;
        this._error.classList.remove(this.options.invisibleClass);
    }

    _hideError() {
        this._error?.classList.add(this.options.invisibleClass);
    }
}
