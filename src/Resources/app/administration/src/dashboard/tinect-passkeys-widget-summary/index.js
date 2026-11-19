import template from './tinect-passkeys-widget-summary.html.twig';
import './tinect-passkeys-widget-summary.scss';

/**
 * Key figures: customers with passkeys (and their share), passkeys, customers that used a passkey recently and
 * customers that log in with passkeys only.
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
            summary: null,
            isLoading: true,
            hasError: false,
        };
    },

    computed: {
        salesChannelId() {
            return this.settings.salesChannelId ?? null;
        },

        adoptionRate() {
            if (!this.summary?.customers) {
                return 0;
            }

            return this.summary.customersWithPasskey / this.summary.customers;
        },

        tiles() {
            if (!this.summary) {
                return [];
            }

            return [
                {
                    key: 'customersWithPasskey',
                    value: this.formatNumber(this.summary.customersWithPasskey),
                    label: this.$t(
                        'tinect-passkeys.dashboard.summary.customersWithPasskey'
                    ),
                    hint: this.$t(
                        'tinect-passkeys.dashboard.summary.adoptionRate',
                        {
                            rate: this.formatPercent(this.adoptionRate),
                            customers: this.formatNumber(
                                this.summary.customers
                            ),
                        }
                    ),
                },
                {
                    key: 'passkeys',
                    value: this.formatNumber(this.summary.passkeys),
                    label: this.$t(
                        'tinect-passkeys.dashboard.summary.passkeys'
                    ),
                },
                {
                    key: 'activeCustomers',
                    value: this.formatNumber(this.summary.activeCustomers),
                    label: this.$t(
                        'tinect-passkeys.dashboard.summary.activeCustomers',
                        { days: this.summary.activeDays }
                    ),
                },
                {
                    key: 'passwordLoginDisabled',
                    value: this.formatNumber(
                        this.summary.passwordLoginDisabled
                    ),
                    label: this.$t(
                        'tinect-passkeys.dashboard.summary.passwordLoginDisabled'
                    ),
                },
            ];
        },
    },

    watch: {
        salesChannelId: {
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
                this.summary =
                    await this.tinectPasskeyApiService.getDashboardSummary({
                        salesChannelId: this.salesChannelId,
                    });
            } catch {
                this.summary = null;
                this.hasError = true;
            } finally {
                this.isLoading = false;
            }
        },

        formatNumber(value) {
            return new Intl.NumberFormat().format(value);
        },

        formatPercent(value) {
            return new Intl.NumberFormat(undefined, {
                style: 'percent',
                maximumFractionDigits: 1,
            }).format(value);
        },
    },
};
