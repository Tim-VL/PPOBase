/** Activity Logs are nested below Purchase orders > Settings. */
import './page/ppobase-activity-log-list';
import deDE from '../../snippet/de-DE.json';
import enGB from '../../snippet/en-GB.json';
const { Module } = Shopware;
Module.register('ppobase-activity-log', {
    type: 'plugin', name: 'ppobase-activity-log',
    title: 'ppobase-activity-log.general.mainMenuItemGeneral',
    description: 'ppobase-activity-log.general.description',
    color: '#ff68b4', icon: 'regular-list',
    snippets: { 'de-DE': deDE, 'en-GB': enGB },
    routes: { list: { component: 'ppobase-activity-log-list', path: 'list',
        meta: { parentPath: 'ppobase.purchase.order.settings' } } },
    settingsItem: [{ to: 'ppobase.activity.log.list', icon: 'regular-list', group: 'plugins', position: 70 }],
    navigation: [{ id: 'ppobase-activity-log', label: 'ppobase-activity-log.general.mainMenuItemGeneral',
        color: '#ff68b4', icon: 'regular-list', path: 'ppobase.activity.log.list',
        parent: 'ppobase-settings-menu', position: 10 }]
});
