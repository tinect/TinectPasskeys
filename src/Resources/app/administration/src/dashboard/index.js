/**
 * Widgets for the optional FroshAdminDashboard plugin.
 *
 * Plugin bundles are loaded in parallel, so `Shopware.FroshDashboard` may not exist yet when this file runs.
 * The widgets are therefore registered right before the dashboard grid is created. Without FroshAdminDashboard
 * the override is never resolved and nothing happens.
 */

const GROUP_ID = 'tinect-passkeys';

const salesChannelSetting = {
    name: 'salesChannelId',
    type: 'entity',
    entity: 'sales_channel',
    label: 'tinect-passkeys.dashboard.salesChannel',
    helpText: 'tinect-passkeys.dashboard.salesChannelHelp',
    default: null,
};

let registered = false;

function registerWidgets() {
    const dashboard = Shopware.FroshDashboard;

    if (registered || !dashboard) {
        return;
    }

    registered = true;

    dashboard.registerGroup({
        id: GROUP_ID,
        label: 'tinect-passkeys.dashboard.group',
        position: 40,
    });

    dashboard.registerWidget({
        id: 'tinect-passkeys-widget-summary',
        label: 'tinect-passkeys.dashboard.summary.label',
        description: 'tinect-passkeys.dashboard.summary.description',
        icon: 'regular-key',
        component: 'tinect-passkeys-widget-summary',
        defaultSize: 'medium',
        supportedSizes: ['small', 'medium', 'large', 'full'],
        group: GROUP_ID,
        acl: ['customer.viewer'],
        settings: [salesChannelSetting],
    });

    dashboard.registerWidget({
        id: 'tinect-passkeys-widget-added',
        label: 'tinect-passkeys.dashboard.added.label',
        description: 'tinect-passkeys.dashboard.added.description',
        icon: 'regular-chart-line',
        component: 'tinect-passkeys-widget-added',
        defaultSize: 'medium',
        supportedSizes: ['medium', 'large', 'full'],
        group: GROUP_ID,
        acl: ['customer.viewer'],
        settings: [salesChannelSetting],
    });

    dashboard.registerWidget({
        id: 'tinect-passkeys-widget-recent',
        label: 'tinect-passkeys.dashboard.recent.label',
        description: 'tinect-passkeys.dashboard.recent.description',
        icon: 'regular-list',
        component: 'tinect-passkeys-widget-recent',
        defaultSize: 'large',
        supportedSizes: ['medium', 'large', 'full'],
        group: GROUP_ID,
        acl: ['customer.viewer'],
        settings: [
            salesChannelSetting,
            {
                name: 'limit',
                type: 'select',
                label: 'tinect-passkeys.dashboard.recent.limit',
                default: 10,
                options: [
                    { value: 5, label: '5' },
                    { value: 10, label: '10' },
                    { value: 20, label: '20' },
                    { value: 50, label: '50' },
                ],
            },
        ],
    });
}

Shopware.Component.register(
    'tinect-passkeys-widget-summary',
    () => import('./tinect-passkeys-widget-summary')
);
Shopware.Component.register(
    'tinect-passkeys-widget-added',
    () => import('./tinect-passkeys-widget-added')
);
Shopware.Component.register(
    'tinect-passkeys-widget-recent',
    () => import('./tinect-passkeys-widget-recent')
);

Shopware.Component.override('frosh-dashboard-grid', {
    beforeCreate() {
        registerWidgets();
    },
});
