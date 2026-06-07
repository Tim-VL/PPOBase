import template from './ppobase-manual-order-detail.html.twig';
import './ppobase-manual-order-detail.scss';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin = Shopware.Mixin;

ShopwareComponent.register('ppobase-manual-order-detail', {
    template: template,

    inject: ['ppobaseApiService'],

    mixins: [ShopwareMixin.getByName('notification')],

    data: function() {
        return {
            isLoading: false,
            isSaving: false,
            isPrintingInvoice: false,
            isPrintingDelivery: false,
            order: null,
            editOrderReference: '',
            editShippingDate: null,
            editNotes: ''
        };
    },

    computed: {
        orderId: function() {
            return this.$route.params.id;
        }
    },

    created: function() {
        this.loadOrder();
    },

    methods: {
        loadOrder: function() {
            var me = this;
            me.isLoading = true;

            me.ppobaseApiService.getManualOrderPrintData(me.orderId).then(function(response) {
                me.isLoading = false;
                if (response.success && response.data) {
                    me.order = response.data;
                    me.editOrderReference = response.data.orderReference || '';
                    me.editShippingDate = response.data.shippingDate || null;
                    me.editNotes = response.data.notes || '';
                }
            }).catch(function() {
                me.isLoading = false;
                me.createNotificationError({
                    title: me.$tc('ppobase-manual-order.detail.errorTitle'),
                    message: 'Failed to load order.'
                });
            });
        },

        onBack: function() {
            this.$router.push({ name: 'ppobase.manual.order.list' });
        },

        onSave: function() {
            var me = this;
            me.isSaving = true;

            me.ppobaseApiService.updateManualOrder(me.orderId, {
                orderReference: me.editOrderReference,
                shippingDate: me.editShippingDate || null,
                notes: me.editNotes
            }).then(function(response) {
                me.isSaving = false;
                if (response.success) {
                    me.createNotificationSuccess({
                        title: me.$tc('ppobase-manual-order.detail.saveSuccessTitle'),
                        message: me.$tc('ppobase-manual-order.detail.saveSuccessMessage')
                    });
                    // Update local order data to reflect saved values
                    if (me.order) {
                        me.order.orderReference = me.editOrderReference;
                        me.order.shippingDate = me.editShippingDate;
                        me.order.notes = me.editNotes;
                    }
                } else {
                    me.createNotificationError({
                        title: me.$tc('ppobase-manual-order.detail.errorTitle'),
                        message: response.error || 'Failed to save order.'
                    });
                }
            }).catch(function() {
                me.isSaving = false;
                me.createNotificationError({
                    title: me.$tc('ppobase-manual-order.detail.errorTitle'),
                    message: 'Failed to save order.'
                });
            });
        },

        onPrintInvoice: function() {
            var me = this;
            me.isPrintingInvoice = true;
            var today = new Date().toISOString().split('T')[0];
            me.ppobaseApiService.createShopwareDocument(me.orderId, 'invoice', { documentDate: today, documentComment: '' })
                .then(function(doc) {
                    return me.ppobaseApiService.downloadShopwareDocument(doc.documentId, doc.documentDeepLink);
                }).then(function(blob) {
                    me.isPrintingInvoice = false;
                    var link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.target = '_blank';
                    link.dispatchEvent(new MouseEvent('click'));
                    link.remove();
                }).catch(function() {
                    me.isPrintingInvoice = false;
                    me.createNotificationError({
                        title: me.$tc('ppobase-manual-order.detail.errorTitle'),
                        message: 'Failed to generate invoice.'
                    });
                });
        },

        onPrintDeliveryNote: function() {
            var me = this;
            me.isPrintingDelivery = true;
            me.ppobaseApiService.getManualOrderDeliveryNoteHtml(me.orderId).then(function(blob) {
                me.isPrintingDelivery = false;
                var url = URL.createObjectURL(blob);
                window.open(url, '_blank');
            }).catch(function() {
                me.isPrintingDelivery = false;
                me.createNotificationError({
                    title: me.$tc('ppobase-manual-order.detail.errorTitle'),
                    message: 'Failed to generate delivery note.'
                });
            });
        },

        onGoToOrder: function() {
            this.$router.push({ name: 'sw.order.detail', params: { id: this.orderId } });
        },

        onGoToCustomer: function() {
            if (this.order && this.order.customerId) {
                this.$router.push({ name: 'sw.customer.detail', params: { id: this.order.customerId } });
            }
        },

        onExportExcel: function() {
            var me = this;
            var orderNumber = me.order ? me.order.orderNumber : me.orderId;
            me.ppobaseApiService.exportManualOrderExcel(me.orderId).then(function(blob) {
                var url = URL.createObjectURL(blob);
                var link = document.createElement('a');
                link.href = url;
                link.download = 'Order_' + orderNumber + '.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }).catch(function() {
                me.createNotificationError({
                    title: me.$tc('ppobase-manual-order.detail.errorTitle'),
                    message: 'Failed to export order.'
                });
            });
        },

        formatDate: function(dateStr) {
            if (!dateStr) return '—';
            var d = new Date(dateStr);
            return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },

        formatNum: function(value) {
            if (value === null || value === undefined) return '0.00';
            return Number(value).toFixed(2);
        }
    }
});
