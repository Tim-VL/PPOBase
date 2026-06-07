/**
 * PPOBase Supplier Module - Renamed to "Suppliers"
 */

import './page/ppobase-supplier-list';
import './page/ppobase-supplier-detail';
import './page/ppobase-supplier-create';
import './page/ppobase-supplier-products';

import deDE from '../../snippet/de-DE.json';
import enGB from '../../snippet/en-GB.json';

const { Module } = Shopware;

Module.register('ppobase-supplier', {
    type: 'plugin',
    name: 'ppobase-supplier',
    title: 'ppobase-supplier.general.mainMenuItemGeneral',
    description: 'ppobase-supplier.general.description',
    color: '#1E88E5',
    icon: 'regular-users',
    entity: 'ppobase_supplier',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        list: {
            component: 'ppobase-supplier-list',
            path: 'list'
        },
        detail: {
            component: 'ppobase-supplier-detail',
            path: 'detail/:id',
            props: {
                default: (route) => ({
                    supplierId: route.params.id
                })
            },
            meta: {
                parentPath: 'ppobase.supplier.list'
            }
        },
        create: {
            component: 'ppobase-supplier-create',
            path: 'create',
            meta: {
                parentPath: 'ppobase.supplier.list'
            }
        },
        products: {
            component: 'ppobase-supplier-products',
            path: 'products/:id',
            props: {
                default: (route) => ({
                    supplierId: route.params.id
                })
            },
            meta: {
                parentPath: 'ppobase.supplier.list'
            }
        }
    },

    settingsItem: [{
        to: 'ppobase.supplier.list',
        icon: 'regular-users',
        group: 'plugins',
        position: 20
    }],

    navigation: [{
        id: 'ppobase-supplier',
        label: 'ppobase-supplier.general.mainMenuItemGeneral',
        color: '#1E88E5',
        path: 'ppobase.supplier.list',
        icon: 'regular-users',
        parent: 'ppobase-purchase-order-menu-root',
        position: 20
    }]
});
