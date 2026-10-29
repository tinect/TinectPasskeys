import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';
import TinectPasskeyApiService from './service/tinect-passkey-api.service';

Shopware.Application.addServiceProvider(
    'tinectPasskeyApiService',
    (container) => {
        const initContainer = Shopware.Application.getContainer('init');

        return new TinectPasskeyApiService(
            initContainer.httpClient,
            container.loginService
        );
    }
);

Shopware.Component.register(
    'tinect-passkey-customer-card',
    () => import('./component/tinect-passkey-customer-card')
);

Shopware.Component.override(
    'sw-customer-detail-base',
    () => import('./override/sw-customer-detail-base')
);

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);
