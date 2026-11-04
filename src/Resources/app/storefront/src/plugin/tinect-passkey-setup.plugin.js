import {
    isAbortError,
    isPasskeySupported,
    parseCreationOptions,
    postForm,
    serializeCredential,
} from '../helper/webauthn.helper';

const BROWSERS = [
    ['Edg/', 'Edge'],
    ['OPR/', 'Opera'],
    ['SamsungBrowser/', 'Samsung Internet'],
    ['Firefox/', 'Firefox'],
    ['FxiOS/', 'Firefox'],
    ['CriOS/', 'Chrome'],
    ['Chrome/', 'Chrome'],
    ['Safari/', 'Safari'],
];

const OPERATING_SYSTEMS = [
    ['iPhone', 'iOS'],
    ['iPad', 'iPadOS'],
    ['Android', 'Android'],
    ['Windows', 'Windows'],
    ['Mac OS X', 'macOS'],
    ['CrOS', 'ChromeOS'],
    ['Linux', 'Linux'],
];

function describeDevice() {
    const ua = navigator.userAgent;
    const browser = BROWSERS.find(([token]) => ua.includes(token))?.[1];
    const os = OPERATING_SYSTEMS.find(([token]) => ua.includes(token))?.[1];

    return [browser, os].filter(Boolean).join(' / ');
}

export default class TinectPasskeySetupPlugin extends window.PluginBaseClass {
    static options = {
        optionsUrl: '',
        registerUrl: '',
        confirmOptionsUrl: '',
        errorMessage: '',
    };

    init() {
        this._nameInput = this.el.querySelector('[name="name"]');
        this._submitButton = this.el.querySelector('.tinect-passkey-setup-submit');
        this._error = this.el.querySelector('.tinect-passkey-setup-error');
        this._unsupported = this.el.querySelector('.tinect-passkey-setup-unsupported');

        if (!isPasskeySupported()) {
            this._unsupported.classList.remove('d-none');
            this._submitButton.disabled = true;
            return;
        }

        // prefill name with detected device
        if (this._nameInput && !this._nameInput.value) {
            this._nameInput.value = describeDevice();
        }

        this._submitButton.addEventListener('click', this._onSubmit.bind(this));

        // auto-open the modal
        const modal = window.bootstrap?.Modal.getOrCreateInstance(this.el);
        modal?.show();
    }

    async _onSubmit() {
        this._error.classList.add('d-none');
        this._submitButton.disabled = true;

        try {
            const options = await postForm(this.options.optionsUrl, {
                password: '',
            });

            const credential = await navigator.credentials.create({
                publicKey: parseCreationOptions(options),
            });

            await postForm(this.options.registerUrl, {
                credential: serializeCredential(credential),
                name: this._nameInput?.value || '',
            });

            window.bootstrap?.Modal.getInstance(this.el)?.hide();
            window.location.reload();
        } catch (error) {
            this._submitButton.disabled = false;

            if (!isAbortError(error)) {
                this._error.textContent = error?.serverMessage || this.options.errorMessage;
                this._error.classList.remove('d-none');
            }
        }
    }
}
