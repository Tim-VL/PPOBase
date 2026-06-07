/**
 * PO History Module - Purchase Order activity timeline
 */

import './page/ppobase-po-history-list';
import './page/ppobase-po-history-detail';

import localSnippetEnGB from './snippet/en-GB.json';
import localSnippetDeDE from './snippet/de-DE.json';
import globalSnippetEnGB from '../../snippet/en-GB.json';
import globalSnippetDeDE from '../../snippet/de-DE.json';

const { Module } = Shopware;

// Merge local PO history snippets into global
const mergedEnGB = { ...globalSnippetEnGB, ...localSnippetEnGB };
const mergedDeDE = { ...globalSnippetDeDE, ...localSnippetDeDE };

Module.register('ppobase-po-history', {
    type: 'plugin',
    name: 'ppobase-po-history',
    title: 'ppobase-po-history.general.mainMenuLabel',
    description: 'ppobase-po-history.general.description',
    color: '#57D9A3',
    icon: 'regular-clock',

    snippets: {
        'de-DE': mergedDeDE,
        'en-GB': mergedEnGB
    },

    routes: {
        list: {
            component: 'ppobase-po-history-list',
            path: 'list',
            meta: {
                parentPath: 'sw.catalogue.index'
            }
        },
        detail: {
            component: 'ppobase-po-history-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'ppobase.po.history.list'
            },
            props: {
                default(route) {
                    return { logId: route.params.id };
                }
            }
        }
    },

    settingsItem: [{
        to: 'ppobase.po.history.list',
        icon: 'regular-clock',
        group: 'plugins',
        position: 60
    }],

    navigation: [{
        id: 'ppobase-po-history',
        label: 'ppobase-po-history.general.mainMenuLabel',
        color: '#57D9A3',
        icon: 'regular-clock',
        path: 'ppobase.po.history.list',
        parent: 'ppobase-purchase-order-menu-root',
        position: 60
    }]
});
