import template from './ppobase-manual-order-list.html.twig';
import './ppobase-manual-order-list.scss';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin = Shopware.Mixin;

ShopwareComponent.register('ppobase-manual-order-list', {
    template: template,

    inject: ['ppobaseApiService'],

    mixins: [ShopwareMixin.getByName('notification')],

    data: function() {
        return {
            isLoading: false,
            orders: [],
            selectedOrders: [],
            searchTerm: '',
            page: 1,
            limit: 25,
            total: 0
        };
    },

    computed: {
        columns: function() {
            return [
                { property: 'orderNumber', label: this.$tc('ppobase-manual-order.list.columnOrderNumber'), rawData: true },
                { property: 'customerName', label: this.$tc('ppobase-manual-order.list.columnCustomer'), rawData: true },
                { property: 'total', label: this.$tc('ppobase-manual-order.list.columnTotal'), rawData: true, align: 'right' },
                { property: 'orderReference', label: this.$tc('ppobase-manual-order.list.columnReference'), rawData: true },
                { property: 'createdAt', label: this.$tc('ppobase-manual-order.list.columnCreatedAt'), rawData: true }
            ];
        }
    },

    created: function() {
        this.loadOrders();
    },

    methods: {
        loadOrders: function() {
            var me = this;
            me.isLoading = true;

            me.ppobaseApiService.getRecentManualOrders(me.searchTerm || '', me.page, me.limit).then(function(response) {
                if (response.success) {
                    me.orders = response.data;
                    me.total = response.total || 0;
                    me.selectedOrders = [];
                }
                me.isLoading = false;
            }).catch(function() {
                me.isLoading = false;
                me.createNotificationError({
                    title: me.$tc('ppobase-manual-order.create.errorTitle'),
                    message: 'Failed to load manual orders.'
                });
            });
        },

        onSearch: function(searchTerm) {
            this.searchTerm = searchTerm || '';
            this.page = 1;
            this.loadOrders();
        },

        onPageChange: function(data) {
            this.page = data.page;
            this.limit = data.limit;
            this.loadOrders();
        },

        onCreateOrder: function() {
            this.$router.push({ name: 'ppobase.manual.order.create' });
        },

        onSelectionChange: function(selection) {
            this.selectedOrders = Object.values(selection || {});
        },

        onViewOrder: function(orderId) {
            this.$router.push({ name: 'ppobase.manual.order.detail', params: { id: orderId } });
        },

        onPrintInvoice: function(orderId) {
            var me = this;
            var today = new Date().toISOString().split('T')[0];
            me.ppobaseApiService.createShopwareDocument(orderId, 'invoice', { documentDate: today, documentComment: '' })
                .then(function(doc) {
                    return me.ppobaseApiService.downloadShopwareDocument(doc.documentId, doc.documentDeepLink);
                }).then(function(blob) {
                    var link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.target = '_blank';
                    link.dispatchEvent(new MouseEvent('click'));
                    link.remove();
                }).catch(function() {
                    me.createNotificationError({
                        title: me.$tc('ppobase-manual-order.create.errorTitle'),
                        message: 'Failed to generate invoice.'
                    });
                });
        },

        onPrintDeliveryNote: function(orderId) {
            var me = this;
            me.ppobaseApiService.getManualOrderDeliveryNoteHtml(orderId).then(function(blob) {
                var url = URL.createObjectURL(blob);
                window.open(url, '_blank');
            }).catch(function() {
                me.createNotificationError({
                    title: me.$tc('ppobase-manual-order.create.errorTitle'),
                    message: 'Failed to generate delivery note.'
                });
            });
        },

        onExportExcel: function(orderId, orderNumber) {
            var me = this;
            me.ppobaseApiService.exportManualOrderExcel(orderId).then(function(blob) {
                var url = URL.createObjectURL(blob);
                var link = document.createElement('a');
                link.href = url;
                link.download = 'Order_' + (orderNumber || orderId) + '.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }).catch(function() {
                me.createNotificationError({
                    title: me.$tc('ppobase-manual-order.create.errorTitle'),
                    message: 'Failed to export order.'
                });
            });
        },

        onBulkExportCsv: function() {
            var me = this;
            var ids = (me.selectedOrders || []).map(function(o) { return o.orderId || o.id; }).filter(Boolean);

            me.ppobaseApiService.bulkExportManualOrdersCsv({
                ids: ids,
                term: ids.length ? '' : (me.searchTerm || ''),
                limit: 2000
            }).then(function(blob) {
                var url = URL.createObjectURL(blob);
                var link = document.createElement('a');
                var ts = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
                link.href = url;
                link.download = 'ManualOrders_' + ts + '.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }).catch(function() {
                me.createNotificationError({
                    title: me.$tc('ppobase-manual-order.create.errorTitle'),
                    message: 'Failed to export manual orders.'
                });
            });
        },

        formatDate: function(dateStr) {
            if (!dateStr) return '';
            var d = new Date(dateStr);
            return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },

        formatCurrency: function(value, currency) {
            if (value === null || value === undefined) return '';
            return currency + ' ' + Number(value).toFixed(2);
        }
    }
});
