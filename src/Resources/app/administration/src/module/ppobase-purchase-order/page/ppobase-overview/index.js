// Simple navigation page; no data requests or extra dependencies.
Shopware.Component.register('ppobase-overview', {
    template: `
        <sw-page class="ppobase-overview">
            <template #smart-bar-header><h2>Overview</h2></template>
            <template #content>
                <div style="max-width: 960px; margin: 24px auto; padding: 0 16px;">
                    <h2 style="margin-bottom: 12px;">Overview</h2>
                    <p style="margin-bottom: 24px;">Purchasing tools and shortcuts</p>
                    <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                    <router-link style="display: inline-block; padding: 12px 16px; background: #fff; border: 1px solid #d1d9e0; border-radius: 4px; color: #526f86; text-decoration: none;" :to="{ name: 'ppobase.purchase.order.list' }">Purchase Orders</router-link>
                    <router-link style="display: inline-block; padding: 12px 16px; background: #fff; border: 1px solid #d1d9e0; border-radius: 4px; color: #526f86; text-decoration: none;" :to="{ name: 'ppobase.goods.receipt.list' }">Goods Receipts</router-link>
                    <router-link style="display: inline-block; padding: 12px 16px; background: #fff; border: 1px solid #d1d9e0; border-radius: 4px; color: #526f86; text-decoration: none;" :to="{ name: 'ppobase.supplier.list' }">Suppliers</router-link>
                    <router-link style="display: inline-block; padding: 12px 16px; background: #fff; border: 1px solid #d1d9e0; border-radius: 4px; color: #526f86; text-decoration: none;" :to="{ name: 'ppobase.purchase.order.settings' }">Settings</router-link>
                    </div>
                </div>
            </template>
        </sw-page>
    `
});
