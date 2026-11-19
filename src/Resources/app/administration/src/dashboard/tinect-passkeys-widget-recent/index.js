import template from './tinect-passkeys-widget-recent.html.twig';
import './tinect-passkeys-widget-recent.scss';

/**
 * The most recently added passkeys with their customers.
 */
export default {
    template,

    inject: ['acl', 'tinectPasskeyApiService'],

    props: {
        settings: {
            type: Object,
            required: false,
            default: () => ({}),
        },
    },

    data() {
        return {
            passkeys: [],
            isLoading: true,
            hasError: false,
        };
    },

    computed: {
        salesChannelId() {
            return this.settings.salesChannelId ?? null;
        },

        limit() {
            return this.settings.limit ?? 10;
        },

        params() {
            return {
                salesChannelId: this.salesChannelId,
                limit: this.limit,
            };
        },

        columns() {
            return [
                {
                    property: 'customer',
                    label: this.$t(
                        'tinect-passkeys.dashboard.recent.columnCustomer'
                    ),
                    primary: true,
                },
                {
                    property: 'name',
                    label: this.$t('tinect-passkeys.customerCard.columnName'),
                },
                {
                    property: 'createdAt',
                    label: this.$t(
                        'tinect-passkeys.customerCard.columnCreatedAt'
                    ),
                },
                {
                    property: 'lastUsedAt',
                    label: this.$t(
                        'tinect-passkeys.customerCard.columnLastUsedAt'
                    ),
                },
            ];
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },
    },

    watch: {
        params: {
            immediate: true,
            handler() {
                this.load();
            },
        },
    },

    methods: {
        async load() {
            if (!this.acl.can('customer.viewer')) {
                this.isLoading = false;
                return;
            }

            this.isLoading = true;
            this.hasError = false;

            try {
                const result =
                    await this.tinectPasskeyApiService.getDashboardRecent(
                        this.params
                    );

                this.passkeys = result.passkeys;
            } catch {
                this.passkeys = [];
                this.hasError = true;
            } finally {
                this.isLoading = false;
            }
        },

        // Database values are stored in UTC without timezone suffix.
        formatDate(value) {
            return value ? this.dateFilter(`${value.replace(' ', 'T')}Z`) : '-';
        },
    },
};
