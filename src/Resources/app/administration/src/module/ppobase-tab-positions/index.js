import './page/ppobase-tab-positions-index';

import deDE from '../../snippet/de-DE.json';
import enGB from '../../snippet/en-GB.json';

const { Module } = Shopware;

Module.register('ppobase-tab-positions', {
    type: 'plugin',
    name: 'ppobase-tab-positions',
    title: 'ppobase-tab-positions.general.mainMenuItemGeneral',
    description: 'ppobase-tab-positions.general.description',
    color: '#1E88E5',
    icon: 'regular-sliders-h',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        index: {
            component: 'ppobase-tab-positions-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
            },
        },
    },

    settingsItem: [{
        to: 'ppobase.tab.positions.index',
        icon: 'regular-products',
        group: 'plugins',
        position: 60,
    }],

    navigation: [
         {
            id: 'ppobase-product-tabs-catalogs',
            label: 'ppobase-tab-positions.general.mainMenuItemGeneral',
            color: '#4CAF50',
            path: 'ppobase.tab.positions.index',
            icon: 'regular-products',
            parent: 'sw-catalogue',
            position: 60,
        },
    ],
});
