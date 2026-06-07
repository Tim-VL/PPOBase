import template from './ppobase-tab-positions-index.html.twig';

const { Component, Mixin } = Shopware;

Component.register('ppobase-tab-positions-index', {
    template,

    inject: ['ppobaseTabPositionService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            isSaving: false,
            tabs: [],
        };
    },

    computed: {
        columns() {
            return [
                { property: 'label', label: this.$tc('ppobase-tab-positions.list.columnName'), primary: true, allowResize: true },
                { property: 'technicalName', label: this.$tc('ppobase-tab-positions.list.columnTechnicalName'), allowResize: true },
                { property: 'defaultPosition', label: this.$tc('ppobase-tab-positions.list.columnDefaultPosition'), width: '150px', align: 'right' },
                { property: 'customPosition', label: this.$tc('ppobase-tab-positions.list.columnCustomPosition'), width: '180px' },
                { property: 'effectivePosition', label: this.$tc('ppobase-tab-positions.list.columnEffectivePosition'), width: '150px', align: 'right' },
            ];
        },
    },

    created() {
        this.loadTabs();
    },

    methods: {
        async loadTabs() {
            this.isLoading = true;

            try {
                const config = await this.ppobaseTabPositionService.getConfig(true);
                this.tabs = this.ppobaseTabPositionService.getTabsWithConfig(this.$router, config);
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-tab-positions.notification.errorTitle'),
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
            }
        },

        onCustomPositionChange(item, value) {
            const parsedValue = parseInt(value, 10);
            item.customPosition = Number.isNaN(parsedValue) ? null : parsedValue;
            item.effectivePosition = item.customPosition === null ? item.defaultPosition : item.customPosition;
        },

        onResetItem(item) {
            item.customPosition = null;
            item.effectivePosition = item.defaultPosition;
        },

        onResetAll() {
            this.tabs.forEach((item) => {
                this.onResetItem(item);
            });
        },

        buildConfigFromTabs() {
            const config = {};

            this.tabs.forEach((item) => {
                const parsedValue = parseInt(item.customPosition, 10);
                if (!Number.isNaN(parsedValue)) {
                    config[item.technicalName] = parsedValue;
                }
            });

            return config;
        },

        async onSave() {
            this.isSaving = true;

            try {
                const config = this.buildConfigFromTabs();
                await this.ppobaseTabPositionService.saveConfig(config);
                this.tabs = this.ppobaseTabPositionService.getTabsWithConfig(this.$router, config);

                this.createNotificationSuccess({
                    title: this.$tc('ppobase-tab-positions.notification.successTitle'),
                    message: this.$tc('ppobase-tab-positions.notification.saveSuccessMessage'),
                });
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-tab-positions.notification.errorTitle'),
                    message: error.message,
                });
            } finally {
                this.isSaving = false;
            }
        },
    },
});
