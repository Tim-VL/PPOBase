import './page/ppobase-manual-order-create';
import './page/ppobase-manual-order-list';
import './page/ppobase-manual-order-detail';

import deDE from '../../snippet/de-DE.json';
import enGB from '../../snippet/en-GB.json';

var Module = Shopware.Module;

Module.register('ppobase-manual-order', {
    type: 'plugin',
    name: 'ppobase-manual-order',
    title: 'ppobase-manual-order.general.mainMenuItemGeneral',
    description: 'ppobase-manual-order.general.description',
    color: '#7B1FA2',
    icon: 'regular-shopping-cart',

    snippets: { 'de-DE': deDE, 'en-GB': enGB },

    routes: {
        create: {
            component: 'ppobase-manual-order-create',
            path: 'create',
            meta: { parentPath: 'ppobase.manual.order.list' }
        },
        list: {
            component: 'ppobase-manual-order-list',
            path: 'list'
        },
        detail: {
            component: 'ppobase-manual-order-detail',
            path: 'detail/:id',
            meta: { parentPath: 'ppobase.manual.order.list' }
        }
    },

    settingsItem: [{
        to: 'ppobase.manual.order.list',
        icon: 'regular-shopping-cart',
        group: 'plugins',
        position: 10
    }],

    navigation: [
        {
            id: 'ppobase-manual-order',
            label: 'ppobase-manual-order.general.mainMenuItemGeneral',
            color: '#7B1FA2',
            path: 'ppobase.manual.order.list',
            icon: 'regular-shopping-cart',
            parent: 'ppobase-purchase-order-menu-root',
            position: 10
        },
        {
            id: 'ppobase-manual-order-sw-link',
            label: 'ppobase-manual-order.general.mainMenuItemGeneral',
            color: '#7B1FA2',
            path: 'ppobase.manual.order.list',
            icon: 'regular-shopping-cart',
            parent: 'sw-order',
            position: 100
        }
    ]
});
