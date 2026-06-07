const CONFIG_KEY = 'PPOBase.config.productTabPositions';

const CORE_TAB_DEFAULTS = [
    { technicalName: 'sw.product.detail.base', label: 'Basic Information', routePathSegment: 'base', defaultPosition: 10 },
    { technicalName: 'sw.product.detail.specifications', label: 'Specifications', routePathSegment: 'specifications', defaultPosition: 20 },
    { technicalName: 'sw.product.detail.prices', label: 'Advanced Prices', routePathSegment: 'prices', defaultPosition: 30 },
    { technicalName: 'sw.product.detail.variants', label: 'Variants', routePathSegment: 'variants', defaultPosition: 40 },
    { technicalName: 'sw.product.detail.layout', label: 'Layout', routePathSegment: 'layout', defaultPosition: 50 },
    { technicalName: 'sw.product.detail.seo', label: 'SEO', routePathSegment: 'seo', defaultPosition: 60 },
    { technicalName: 'sw.product.detail.crossSelling', label: 'Cross Selling', routePathSegment: 'cross-selling', defaultPosition: 70 },
    { technicalName: 'sw.product.detail.reviews', label: 'Reviews', routePathSegment: 'reviews', defaultPosition: 80 },
];

const PPOBASE_TAB_DEFAULTS = [
    { technicalName: 'sw.product.detail.ppobaseStock', label: 'Stock Bookings', routePathSegment: 'ppobase-stock', defaultPosition: 500 },
    { technicalName: 'sw.product.detail.ppobasePurchase', label: 'Purchase', routePathSegment: 'ppobase-purchase', defaultPosition: 510 },
    { technicalName: 'sw.product.detail.ppobasePackaging', label: 'Packaging', routePathSegment: 'ppobase-packaging', defaultPosition: 520 },
    { technicalName: 'sw.product.detail.ppobaseHistory', label: 'Stock History', routePathSegment: 'ppobase-history', defaultPosition: 530 },
    { technicalName: 'sw.product.detail.ppobaseGrouped', label: 'Grouped Products', routePathSegment: 'ppobase-grouped', defaultPosition: 540 },
];

export default class PpobaseTabPositionService {
    constructor(systemConfigApiService) {
        this.systemConfigApiService = systemConfigApiService;
        this.config = null;
    }

    getConfigKey() {
        return CONFIG_KEY;
    }

    async getConfig(forceReload = false) {
        if (this.config && !forceReload) {
            return this.config;
        }

        const values = await this.systemConfigApiService.getValues('PPOBase.config');
        this.config = this.normalizeConfig(values[CONFIG_KEY]);

        return this.config;
    }

    async saveConfig(config) {
        this.config = this.normalizeConfig(config);
        await this.systemConfigApiService.saveValues({
            [CONFIG_KEY]: this.config,
        });

        window.dispatchEvent(new CustomEvent('ppobase-product-tab-positions-save', {
            detail: this.config,
        }));

        return this.config;
    }

    normalizeConfig(config) {
        const normalized = {};
        const source = config && typeof config === 'object' ? config : {};

        Object.keys(source).forEach((technicalName) => {
            const value = parseInt(source[technicalName], 10);
            if (!Number.isNaN(value)) {
                normalized[technicalName] = value;
            }
        });

        return normalized;
    }

    getDefaultTabs(router) {
        const routeTabs = this.getProductDetailRouteTabs(router);
        const knownTabs = CORE_TAB_DEFAULTS.concat(PPOBASE_TAB_DEFAULTS);
        const knownByName = {};

        knownTabs.forEach((tab) => {
            knownByName[tab.technicalName] = Object.assign({}, tab);
        });

        routeTabs.forEach((tab) => {
            if (knownByName[tab.technicalName]) {
                knownByName[tab.technicalName] = Object.assign({}, knownByName[tab.technicalName], tab, {
                    label: knownByName[tab.technicalName].label,
                    defaultPosition: knownByName[tab.technicalName].defaultPosition,
                    routePathSegment: knownByName[tab.technicalName].routePathSegment || tab.routePathSegment,
                });
                return;
            }

            knownByName[tab.technicalName] = tab;
        });

        return Object.values(knownByName).sort((a, b) => {
            const posA = this.getDefaultPosition(a);
            const posB = this.getDefaultPosition(b);
            if (posA !== posB) {
                return posA - posB;
            }

            return a.technicalName.localeCompare(b.technicalName);
        });
    }

    getProductDetailRouteTabs(router) {
        if (!router || typeof router.getRoutes !== 'function') {
            return [];
        }

        const routes = router.getRoutes();
        const productRoutes = routes.filter((route) => {
            return route.name
                && typeof route.name === 'string'
                && route.name.indexOf('sw.product.detail.') === 0;
        });

        return productRoutes.map((route, index) => {
            return {
                technicalName: route.name,
                label: this.getRouteLabel(route),
                defaultPosition: 1000 + index,
                routePathSegment: this.getRoutePathSegment(route),
            };
        });
    }

    getRouteLabel(route) {
        if (route.meta && route.meta.ppobaseTabLabel) {
            return route.meta.ppobaseTabLabel;
        }

        if (route.meta && route.meta.title) {
            return route.meta.title;
        }

        return this.prettifyTechnicalName(route.name);
    }

    getRoutePathSegment(route) {
        if (!route || !route.path) {
            return '';
        }

        const segments = route.path.split('/').filter((segment) => {
            return segment && segment.indexOf(':') !== 0;
        });

        return segments[segments.length - 1] || '';
    }

    prettifyTechnicalName(technicalName) {
        return String(technicalName || '')
            .replace(/^sw\.product\.detail\./, '')
            .replace(/([a-z])([A-Z])/g, '$1 $2')
            .replace(/[._-]+/g, ' ')
            .replace(/\b\w/g, (char) => char.toUpperCase());
    }

    getDefaultPosition(tab) {
        const position = parseInt(tab.defaultPosition, 10);
        return Number.isNaN(position) ? 9999 : position;
    }

    getEffectivePosition(tab, config) {
        const customPosition = config && Object.prototype.hasOwnProperty.call(config, tab.technicalName)
            ? parseInt(config[tab.technicalName], 10)
            : null;

        return Number.isNaN(customPosition) || customPosition === null
            ? this.getDefaultPosition(tab)
            : customPosition;
    }

    getTabsWithConfig(router, config) {
        const normalizedConfig = this.normalizeConfig(config);

        return this.getDefaultTabs(router).map((tab) => {
            const hasCustomPosition = Object.prototype.hasOwnProperty.call(normalizedConfig, tab.technicalName);
            const customPosition = hasCustomPosition ? normalizedConfig[tab.technicalName] : null;

            return Object.assign({}, tab, {
                customPosition,
                effectivePosition: hasCustomPosition ? customPosition : this.getDefaultPosition(tab),
            });
        });
    }

    getTechnicalNameFromTabElement(tabElement, router) {
        if (!tabElement) {
            return '';
        }

        const href = tabElement.getAttribute('href') || '';
        const pathSegment = this.getPathSegmentFromHref(href);
        const bySegment = this.getDefaultTabs(router).find((tab) => tab.routePathSegment === pathSegment);

        if (bySegment) {
            return bySegment.technicalName;
        }

        const className = Array.from(tabElement.classList).find((name) => {
            return name.indexOf('sw-product-detail__tab-') === 0;
        });

        if (className) {
            return `class:${className.replace('sw-product-detail__tab-', '')}`;
        }

        return String(tabElement.textContent || '').trim();
    }

    getPathSegmentFromHref(href) {
        const cleanHref = String(href || '').split('?')[0].split('#').pop() || '';
        const segments = cleanHref.split('/').filter(Boolean);

        return segments[segments.length - 1] || '';
    }

    sortTabElements(tabElements, router, config) {
        const normalizedConfig = this.normalizeConfig(config);
        const defaultTabs = this.getDefaultTabs(router);
        const defaultsByName = {};

        defaultTabs.forEach((tab) => {
            defaultsByName[tab.technicalName] = tab;
        });

        Array.from(tabElements || []).forEach((tabElement, index) => {
            const technicalName = this.getTechnicalNameFromTabElement(tabElement, router);
            const defaultTab = defaultsByName[technicalName] || { technicalName, defaultPosition: 10000 + index };
            const position = this.getEffectivePosition(defaultTab, normalizedConfig);

            tabElement.style.order = String(position);
            tabElement.dataset.ppobaseTabTechnicalName = technicalName;
            tabElement.dataset.ppobaseTabPosition = String(position);
        });
    }
}
