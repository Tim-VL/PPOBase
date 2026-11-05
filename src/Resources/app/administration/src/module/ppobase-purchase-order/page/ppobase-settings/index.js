// Simple navigation page; no data requests or extra dependencies.
Shopware.Component.register('ppobase-settings', {
    template: `
        <sw-page class="ppobase-settings">
            <template #smart-bar-header><h2>Settings</h2></template>
            <template #content>
                <div style="max-width: 960px; margin: 24px auto; padding: 0 16px;">
                    <h2 style="margin-bottom: 12px;">Settings</h2>
                    <p style="margin-bottom: 24px;">Purchase order administration settings</p>
                    <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                    <router-link style="display: inline-block; padding: 12px 16px; background: #fff; border: 1px solid #d1d9e0; border-radius: 4px; color: #526f86; text-decoration: none;" :to="{ name: 'ppobase.activity.log.list' }">Activity Logs</router-link>
                    <router-link style="display: inline-block; padding: 12px 16px; background: #fff; border: 1px solid #d1d9e0; border-radius: 4px; color: #526f86; text-decoration: none;" :to="{ name: 'ppobase.tab.positions.index' }">Product Tab Positions</router-link>
                    </div>
                </div>
            </template>
        </sw-page>
    `
});
