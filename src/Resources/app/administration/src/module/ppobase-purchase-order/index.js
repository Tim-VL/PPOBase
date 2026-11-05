import './page/ppobase-purchase-order-list';
import './page/ppobase-purchase-order-detail';
import './page/ppobase-purchase-order-create';
import './page/ppobase-overview';
import './page/ppobase-settings';

import deDE from '../../snippet/de-DE.json';
import enGB from '../../snippet/en-GB.json';
import navigationEnGB from './snippet/en-GB.json';
import navigationDeDE from './snippet/de-DE.json';

const { Module } = Shopware;
Module.register('ppobase-purchase-order', {
    // Register as core to allow a top-level Administration menu entry.
    type: 'core',
    name: 'ppobase-purchase-order',
    title: 'ppobase-purchase-order.general.mainMenuItemGeneral',
    description: 'ppobase-purchase-order.general.description',
    color: '#1E88E5',
    icon: 'regular-shopping-bag',
    entity: 'ppobase_purchase_order',

    snippets: {
        'de-DE': { ...deDE, ...navigationDeDE },
        'en-GB': { ...enGB, ...navigationEnGB }
    },
    routes: {
        overview: {
            component: 'ppobase-overview',
            path: 'overview'
        },
        settings: {
            component: 'ppobase-settings',
            path: 'settings'
        },
        list: {
            component: 'ppobase-purchase-order-list',
            path: 'list'
        },
        detail: {
            component: 'ppobase-purchase-order-detail',
            path: 'detail/:id',
            props: {
                default: (route) => ({
                    purchaseOrderId: route.params.id
                })
            },
            meta: {
                parentPath: 'ppobase.purchase.order.list'
            }
        },
        create: {
            component: 'ppobase-purchase-order-create',
            path: 'create',
            meta: {
                parentPath: 'ppobase.purchase.order.list'
            }
        }
    },
    settingsItem: [{
        to: 'ppobase.purchase.order.list',
        icon: 'regular-shopping-bag',
        group: 'plugins',
        position: 30
    }],
    navigation: [
        {
            id: 'ppobase-purchase-order-menu-root',
            label: 'ppobase-navigation.purchaseOrders',
            color: '#1E88E5',
            path: 'ppobase.purchase.order.overview',
            icon: 'regular-shopping-bag',
            // No parent: show PPOBase in the main Administration menu.
            position: 100
        },
        {
            id: 'ppobase-overview-menu',
            label: 'ppobase-navigation.overview',
            path: 'ppobase.purchase.order.overview',
            parent: 'ppobase-purchase-order-menu-root',
            position: 10
        },
        {
            id: 'ppobase-purchase-order',
            label: 'ppobase-purchase-order.general.mainMenuItemGeneral',
            color: '#1E88E5',
            path: 'ppobase.purchase.order.list',
            icon: 'regular-shopping-bag',
            parent: 'ppobase-purchase-order-menu-root',
            position: 20
        },
        {
            id: 'ppobase-settings-menu',
            label: 'ppobase-navigation.settings',
            path: 'ppobase.purchase.order.settings',
            parent: 'ppobase-purchase-order-menu-root',
            position: 50
        }
    ]
});
