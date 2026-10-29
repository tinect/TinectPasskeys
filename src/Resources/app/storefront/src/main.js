window.PluginManager.register(
    'TinectPasskeyLogin',
    () => import('./plugin/tinect-passkey-login.plugin'),
    '[data-tinect-passkey-login]'
);

window.PluginManager.register(
    'TinectPasskeyRegister',
    () => import('./plugin/tinect-passkey-register.plugin'),
    '[data-tinect-passkey-register]'
);

window.PluginManager.register(
    'TinectPasskeyConfirm',
    () => import('./plugin/tinect-passkey-confirm.plugin'),
    '[data-tinect-passkey-confirm]'
);
