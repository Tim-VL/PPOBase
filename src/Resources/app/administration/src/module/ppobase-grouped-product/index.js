/**
 * Grouped Products Module
 *
 * Registers the navigation entry under the PPOBase catalog menu root
 * before Purchase Orders, plus the settings item
 * so the module appears in Settings > Extensions.
 */

import './page/ppobase-grouped-product-list';

import deDE from '../../snippet/de-DE.json';
import enGB from '../../snippet/en-GB.json';

const { Module } = Shopware;

Module.register('ppobase-grouped-product', {
    type: 'plugin',
    name: 'ppobase-grouped-product',
    title: 'ppobase-grouped-product.general.mainMenuItemGeneral',
    description: 'ppobase-grouped-product.general.description',
    color: '#4CAF50',
    icon: 'regular-products',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        list: {
            component: 'ppobase-grouped-product-list',
            path: 'list',
            meta: {
                parentPath: 'sw.product.index',
            },
        },
    },

    // Appears in Settings > Extensions
    settingsItem: [{
        to: 'ppobase.grouped.product.list',
        icon: 'regular-products',
        group: 'plugins',
        position: 60,
    }],

    // Navigation entry under the PPOBase catalog menu root
    navigation: [
        // {
        //     id: 'ppobase-grouped-product',
        //     label: 'ppobase-grouped-product.general.mainMenuItemGeneral',
        //     color: '#4CAF50',
        //     path: 'ppobase.grouped.product.list',
        //     icon: 'regular-products',
        //     parent: 'ppobase-purchase-order-menu-root',
        //     position: 50,
        // },
        {
            id: 'ppobase-grouped-product-catalogs',
            label: 'ppobase-grouped-product.general.mainMenuItemGeneral',
            color: '#4CAF50',
            path: 'ppobase.grouped.product.list',
            icon: 'regular-products',
            parent: 'sw-catalogue',
            position: 50,
        },

    ],
});
