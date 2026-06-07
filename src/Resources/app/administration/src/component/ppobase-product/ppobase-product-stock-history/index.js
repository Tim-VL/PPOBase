import template from './ppobase-product-stock-history.html.twig';
import './ppobase-product-stock-history.scss';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin = Shopware.Mixin;

ShopwareComponent.register('ppobase-product-stock-history', {
    template: template,

    inject: ['ppobaseApiService'],

    mixins: [ShopwareMixin.getByName('notification')],

    data: function() {
        return {
            isLoading: false,
            includeAll: false,
            entries: [],
        };
    },

    computed: {
        productId: function() {
            return this.$route.params.id;
        },

        columns: function() {
            return [
                { property: 'date', label: this.$tc('ppobase-product.history.columnDate'), width: '180px' },
                { property: 'type', label: this.$tc('ppobase-product.history.columnType'), width: '140px' },
                { property: 'reference', label: this.$tc('ppobase-product.history.columnReference'), primary: true },
                { property: 'quantityChange', label: this.$tc('ppobase-product.history.columnQtyChange'), width: '120px', align: 'center' },
                { property: 'stockAfter', label: this.$tc('ppobase-product.history.columnStockAfter'), width: '120px', align: 'center' },
                { property: 'unitPrice', label: this.$tc('ppobase-product.history.columnPrice'), width: '120px', align: 'right' },
                { property: 'user', label: this.$tc('ppobase-product.history.columnUser'), width: '140px' },
            ];
        },
    },

    created: function() {
        this.loadHistory();
    },

    methods: {
        loadHistory: async function() {
            this.isLoading = true;
            try {
                var result = await this.ppobaseApiService.getProductStockHistory(this.productId, this.includeAll);
                this.entries = (result && result.data) ? result.data : [];
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-product.packaging.errorTitle'),
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
            }
        },

        formatDate: function(value) {
            if (!value) {
                return '-';
            }
            var d = new Date(value);
            if (isNaN(d.getTime())) {
                return '-';
            }
            return d.toLocaleString();
        },

        formatCurrency: function(value) {
            if (value === null || value === undefined) {
                return '-';
            }
            var num = parseFloat(value);
            if (isNaN(num)) {
                return '-';
            }
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'EUR' }).format(num);
        },

        formatQuantity: function(value) {
            var num = parseInt(value, 10) || 0;
            return num >= 0 ? ('+' + num) : String(num);
        },

        typeLabel: function(type) {
            if (type === 'manual_booking') return this.$tc('ppobase-product.history.typeManual');
            if (type === 'goods_receipt') return this.$tc('ppobase-product.history.typeGoodsReceipt');
            return this.$tc('ppobase-product.history.typeSalesOrder');
        },

        typeVariant: function(type) {
            if (type === 'manual_booking') return 'info';
            if (type === 'goods_receipt') return 'success';
            return 'warning';
        },

        onReferenceClick: function(item) {
            if (!item || !item.referenceId) {
                return;
            }

            if (item.type === 'goods_receipt') {
                this.$router.push({ name: 'ppobase.goods.receipt.detail', params: { id: item.referenceId } });
                return;
            }

            if (item.type === 'sales_order') {
                this.$router.push({ name: 'sw.order.detail', params: { id: item.referenceId } });
            }
        },
    },
});
