import template from './sw-product-detail.html.twig';
import './sw-product-detail.scss';

Shopware.Component.override('sw-product-detail', {
    template: template,

    mounted() {
        this.initPpobaseProductTabPositions();
    },

    updated() {
        this.applyPpobaseProductTabPositions();
    },

    beforeUnmount() {
        window.removeEventListener('ppobase-product-tab-positions-save', this.onPpobaseProductTabPositionsSave);
    },

    methods: {
        initPpobaseProductTabPositions() {
            window.addEventListener('ppobase-product-tab-positions-save', this.onPpobaseProductTabPositionsSave);
            this.loadPpobaseProductTabPositions();
        },

        async loadPpobaseProductTabPositions(forceReload) {
            try {
                this.ppobaseTabPositionConfig = await Shopware.Service('ppobaseTabPositionService').getConfig(forceReload === true);
                this.applyPpobaseProductTabPositions();
            } catch (error) {
                this.ppobaseTabPositionConfig = {};
                this.applyPpobaseProductTabPositions();
            }
        },

        onPpobaseProductTabPositionsSave(event) {
            this.ppobaseTabPositionConfig = event && event.detail ? event.detail : {};
            this.applyPpobaseProductTabPositions();
        },

        applyPpobaseProductTabPositions() {
            this.$nextTick(() => {
                const tabContainer = this.$el && this.$el.querySelector('.sw-product-detail-page__tabs .sw-tabs__content');
                if (!tabContainer) {
                    return;
                }

                const tabItems = tabContainer.querySelectorAll('.sw-tabs-item');
                Shopware.Service('ppobaseTabPositionService').sortTabElements(
                    tabItems,
                    this.$router,
                    this.ppobaseTabPositionConfig || {},
                );
            });
        },
    },
});
