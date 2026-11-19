import template from './tinect-passkeys-widget-added.html.twig';
import './tinect-passkeys-widget-added.scss';

const RANGES = {
    '7Days': 7,
    '30Days': 30,
    '90Days': 90,
    '180Days': 180,
    '360Days': 360,
};

const DEFAULT_RANGE = '30Days';

/**
 * Added passkeys per day for a selectable range.
 */
export default {
    template,

    inject: ['acl', 'tinectPasskeyApiService'],

    emits: ['update-settings'],

    props: {
        settings: {
            type: Object,
            required: false,
            default: () => ({}),
        },
    },

    data() {
        return {
            series: [],
            selectedRange: Object.hasOwn(RANGES, this.settings.range)
                ? this.settings.range
                : DEFAULT_RANGE,
            fromDate: null,
            isLoading: true,
        };
    },

    computed: {
        salesChannelId() {
            return this.settings.salesChannelId ?? null;
        },

        availableRanges() {
            return Object.keys(RANGES);
        },

        defaultRangeIndex() {
            return Math.max(
                0,
                this.availableRanges.indexOf(this.selectedRange)
            );
        },

        total() {
            return this.series.reduce((sum, point) => sum + point.y, 0);
        },

        formattedTotal() {
            return new Intl.NumberFormat().format(this.total);
        },

        chartSeries() {
            return [
                {
                    name: this.$t('tinect-passkeys.dashboard.added.seriesName'),
                    data: this.series,
                },
            ];
        },

        chartOptions() {
            return {
                dataLabels: {
                    enabled: false,
                },
                xaxis: {
                    type: 'datetime',
                    min: this.fromDate?.getTime(),
                    labels: {
                        datetimeUTC: false,
                    },
                },
                yaxis: {
                    min: 0,
                    forceNiceScale: true,
                    labels: {
                        formatter: (value) => Math.round(value).toString(),
                    },
                },
            };
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

            const days = RANGES[this.selectedRange];
            const fromDate = new Date();
            fromDate.setHours(0, 0, 0, 0);
            fromDate.setDate(fromDate.getDate() - days);

            this.isLoading = true;
            this.fromDate = fromDate;

            try {
                const result =
                    await this.tinectPasskeyApiService.getDashboardAddedPerDay({
                        days,
                        salesChannelId: this.salesChannelId,
                        timeZone: this.timeZone(),
                    });

                this.series = result.days.map((day) => ({
                    x: Date.parse(`${day.date}T00:00:00`),
                    y: day.count,
                }));
            } catch {
                this.series = [];
            } finally {
                this.isLoading = false;
            }
        },

        timeZone() {
            return (
                Shopware.Store.get('session').currentUser?.timeZone ??
                Intl.DateTimeFormat().resolvedOptions().timeZone
            );
        },

        onRangeUpdate(range) {
            if (!Object.hasOwn(RANGES, range)) {
                return;
            }

            this.selectedRange = range;
            this.$emit('update-settings', { range });
            this.load();
        },
    },
};
