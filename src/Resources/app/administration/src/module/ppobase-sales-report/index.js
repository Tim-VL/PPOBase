import './page/ppobase-sales-report-dashboard';
import './page/ppobase-sales-report-customers';
import './page/ppobase-sales-report-items';
import './page/ppobase-sales-report-by-customer';
import './page/ppobase-sales-report-by-item';
import './page/ppobase-sales-report-orders';

import deDE from '../../snippet/de-DE.json';
import enGB from '../../snippet/en-GB.json';

const { Module } = Shopware;

Module.register('ppobase-sales-report', {
    type: 'plugin',
    name: 'ppobase-sales-report',
    title: 'ppobase-sales-report.general.mainMenuItemGeneral',
    description: 'ppobase-sales-report.general.description',
    color: '#FF9800',
    icon: 'regular-chart-line',

    snippets: { 'de-DE': deDE, 'en-GB': enGB },

    routes: {
        dashboard: {
            component: 'ppobase-sales-report-dashboard',
            path: 'dashboard'
        },
        customers: {
            component: 'ppobase-sales-report-customers',
            path: 'customers',
            meta: { parentPath: 'ppobase.sales.report.dashboard' }
        },
        items: {
            component: 'ppobase-sales-report-items',
            path: 'items',
            meta: { parentPath: 'ppobase.sales.report.dashboard' }
        },
        'by-customer': {
            component: 'ppobase-sales-report-by-customer',
            path: 'by-customer/:customerId?',
            props: { default: (route) => ({ customerId: route.params.customerId || null }) },
            meta: { parentPath: 'ppobase.sales.report.dashboard' }
        },
        'by-item': {
            component: 'ppobase-sales-report-by-item',
            path: 'by-item/:productId?',
            props: { default: (route) => ({ productId: route.params.productId || null }) },
            meta: { parentPath: 'ppobase.sales.report.dashboard' }
        },
        orders: {
            component: 'ppobase-sales-report-orders',
            path: 'orders',
            meta: { parentPath: 'ppobase.sales.report.dashboard' }
        }
    },

    settingsItem: [{
        to: 'ppobase.sales.report.dashboard',
        icon: 'regular-chart-line',
        group: 'plugins',
        position: 50
    }],

    navigation: [{
        id: 'ppobase-sales-report',
        label: 'ppobase-sales-report.general.mainMenuItemGeneral',
        color: '#FF9800',
        path: 'ppobase.sales.report.dashboard',
        icon: 'regular-chart-line',
        parent: 'ppobase-purchase-order-menu-root',
        position: 50
    }]
});

