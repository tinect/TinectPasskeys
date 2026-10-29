import template from './tinect-passkey-customer-card.html.twig';

export default {
    template,

    inject: ['acl', 'tinectPasskeyApiService'],

    mixins: [Shopware.Mixin.getByName('notification')],

    props: {
        customerId: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            passkeys: [],
            passwordLoginDisabled: false,
            isLoading: false,
            passkeyToDelete: null,
            showEnablePasswordLoginModal: false,
        };
    },

    computed: {
        columns() {
            return [
                {
                    property: 'name',
                    label: this.$t('tinect-passkeys.customerCard.columnName'),
                    primary: true,
                },
                {
                    property: 'createdAt',
                    label: this.$t('tinect-passkeys.customerCard.columnCreatedAt'),
                },
                {
                    property: 'lastUsedAt',
                    label: this.$t('tinect-passkeys.customerCard.columnLastUsedAt'),
                },
            ];
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },
    },

    watch: {
        customerId: {
            immediate: true,
            handler() {
                this.loadPasskeys();
            },
        },
    },

    methods: {
        async loadPasskeys() {
            if (!this.acl.can('customer.viewer')) {
                return;
            }

            this.isLoading = true;

            try {
                const result = await this.tinectPasskeyApiService.getCustomerPasskeys(this.customerId);

                this.passkeys = result.passkeys;
                this.passwordLoginDisabled = result.passwordLoginDisabled;
            } finally {
                this.isLoading = false;
            }
        },

        async enablePasswordLogin() {
            this.showEnablePasswordLoginModal = false;
            this.isLoading = true;

            try {
                await this.tinectPasskeyApiService.enableCustomerPasswordLogin(this.customerId);
                this.createNotificationSuccess({
                    message: this.$t('tinect-passkeys.customerCard.notificationPasswordLoginEnabled'),
                });
            } catch {
                this.createNotificationError({
                    message: this.$t('tinect-passkeys.customerCard.notificationPasswordLoginError'),
                });
            } finally {
                await this.loadPasskeys();
            }
        },

        async deletePasskey(passkey) {
            this.isLoading = true;

            try {
                await this.tinectPasskeyApiService.deleteCustomerPasskey(this.customerId, passkey.id);
                this.createNotificationSuccess({
                    message: this.$t('tinect-passkeys.customerCard.notificationDeleteSuccess'),
                });
            } catch {
                this.createNotificationError({
                    message: this.$t('tinect-passkeys.customerCard.notificationDeleteError'),
                });
            } finally {
                this.passkeyToDelete = null;
                await this.loadPasskeys();
            }
        },

        // Database values are stored in UTC without timezone suffix.
        formatDate(value) {
            return value ? this.dateFilter(`${value.replace(' ', 'T')}Z`) : '-';
        },
    },
};
