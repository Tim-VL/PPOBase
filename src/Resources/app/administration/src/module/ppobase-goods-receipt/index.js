import './page/ppobase-goods-receipt-list';
import './page/ppobase-goods-receipt-detail';

import deDE from '../../snippet/de-DE.json';
import enGB from '../../snippet/en-GB.json';

const { Module } = Shopware;

Module.register('ppobase-goods-receipt', {
    type: 'plugin',
    name: 'ppobase-goods-receipt',
    title: 'ppobase-goods-receipt.general.mainMenuItemGeneral',
    description: 'ppobase-goods-receipt.general.description',
    color: '#43A047',
    icon: 'regular-inbox',
    entity: 'ppobase_goods_receipt',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        list: {
            component: 'ppobase-goods-receipt-list',
            path: 'list'
        },
        detail: {
            component: 'ppobase-goods-receipt-detail',
            path: 'detail/:id',
            props: {
                default: (route) => ({
                    goodsReceiptId: route.params.id
                })
            },
            meta: {
                parentPath: 'ppobase.goods.receipt.list'
            }
        }
    },

    settingsItem: [{
        to: 'ppobase.goods.receipt.list',
        icon: 'regular-inbox',
        group: 'plugins',
        position: 40
    }],

    navigation: [{
        id: 'ppobase-goods-receipt',
        label: 'ppobase-goods-receipt.general.mainMenuItemGeneral',
        color: '#43A047',
        path: 'ppobase.goods.receipt.list',
        icon: 'regular-inbox',
        parent: 'ppobase-purchase-order-menu-root',
        position: 40
    }]
});
