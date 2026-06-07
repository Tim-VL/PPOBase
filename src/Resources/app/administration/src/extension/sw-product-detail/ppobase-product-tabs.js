const ShopwareModule = Shopware.Module;

ShopwareModule.register('ppobase-product-tabs', {
    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.product.detail') {
            if (!Array.isArray(currentRoute.children)) {
                currentRoute.children = [];
            }

            if (!currentRoute.children.some((child) => child.name === 'sw.product.detail.ppobaseStock')) {
                currentRoute.children.push({
                    name: 'sw.product.detail.ppobaseStock',
                    component: 'ppobase-product-stock-bookings',
                    path: '/sw/product/detail/:id?/ppobase-stock',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                        ppobaseTabLabel: 'Stock Bookings',
                    },
                });
            }

            if (!currentRoute.children.some((child) => child.name === 'sw.product.detail.ppobasePurchase')) {
                currentRoute.children.push({
                    name: 'sw.product.detail.ppobasePurchase',
                    component: 'ppobase-product-purchase',
                    path: '/sw/product/detail/:id?/ppobase-purchase',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                        ppobaseTabLabel: 'Purchase',
                    },
                });
            }

            if (!currentRoute.children.some((child) => child.name === 'sw.product.detail.ppobasePackaging')) {
                currentRoute.children.push({
                    name: 'sw.product.detail.ppobasePackaging',
                    component: 'ppobase-product-packaging',
                    path: '/sw/product/detail/:id?/ppobase-packaging',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                        ppobaseTabLabel: 'Packaging',
                    },
                });
            }

            if (!currentRoute.children.some((child) => child.name === 'sw.product.detail.ppobaseHistory')) {
                currentRoute.children.push({
                    name: 'sw.product.detail.ppobaseHistory',
                    component: 'ppobase-product-stock-history',
                    path: '/sw/product/detail/:id?/ppobase-history',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                        ppobaseTabLabel: 'Stock History',
                    },
                });
            }

            if (!currentRoute.children.some((child) => child.name === 'sw.product.detail.ppobaseGrouped')) {
                currentRoute.children.push({
                    name: 'sw.product.detail.ppobaseGrouped',
                    component: 'ppobase-product-grouped',
                    path: '/sw/product/detail/:id?/ppobase-grouped',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.editor',
                        ppobaseTabLabel: 'Grouped Products',
                    },
                });
            }
        }

        next(currentRoute);
    },
});
