import './page/ppobase-purchase-order-list';
import './page/ppobase-purchase-order-detail';
import './page/ppobase-purchase-order-create';

import deDE from '../../snippet/de-DE.json';
import enGB from '../../snippet/en-GB.json';

const { Module } = Shopware;

Module.register('ppobase-purchase-order', {
    type: 'plugin',
    name: 'ppobase-purchase-order',
    title: 'ppobase-purchase-order.general.mainMenuItemGeneral',
    description: 'ppobase-purchase-order.general.description',
    color: '#1E88E5',
    icon: 'regular-shopping-bag',
    entity: 'ppobase_purchase_order',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
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
            label: 'ppobase.general.purchaseOrderMenu',
            color: '#1E88E5',
            path: 'ppobase.purchase.order.list',
            icon: 'regular-shopping-bag',
            parent: 'sw-catalogue',
            position: 100
        },
        {
            id: 'ppobase-purchase-order',
            label: 'ppobase-purchase-order.general.mainMenuItemGeneral',
            color: '#1E88E5',
            path: 'ppobase.purchase.order.list',
            icon: 'regular-shopping-bag',
            parent: 'ppobase-purchase-order-menu-root',
            position: 60
        }
    ]
});
