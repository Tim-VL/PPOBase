import template from './ppobase-goods-receipt-detail.html.twig';
import './ppobase-goods-receipt-detail.scss';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin = Shopware.Mixin;
var ShopwareCriteria = Shopware.Data.Criteria;

ShopwareComponent.register('ppobase-goods-receipt-detail', {
    template: template,

    inject: ['repositoryFactory', 'ppobaseApiService'],

    mixins: [ShopwareMixin.getByName('notification')],

    props: {
        goodsReceiptId: { type: String, required: true }
    },

    data: function() {
        return {
            goodsReceipt: null,
            isLoading: false,
            isSaving: false,
            isBooking: false,
            isCancelling: false,
            activityLogs: [],
            isLoadingLogs: false
        };
    },

    computed: {
        goodsReceiptRepository: function() {
            return this.repositoryFactory.create('ppobase_goods_receipt');
        },

        goodsReceiptItemRepository: function() {
            return this.repositoryFactory.create('ppobase_goods_receipt_item');
        },

        pageTitle: function() {
            return this.goodsReceipt
                ? (this.goodsReceipt.receiptNumber || this.$tc('ppobase-goods-receipt.detail.title'))
                : this.$tc('ppobase-goods-receipt.detail.title');
        },

        isDraft: function() {
            return this.goodsReceipt && this.goodsReceipt.status === 'draft';
        },

        isBooked: function() {
            return this.goodsReceipt && this.goodsReceipt.status === 'booked';
        },

        itemColumns: function() {
            return [
                { property: 'productName', label: this.$tc('ppobase-goods-receipt.detail.columnProductName'), primary: true },
                { property: 'productNumber', label: this.$tc('ppobase-goods-receipt.detail.columnProductNumber'), width: '120px' },
                { property: 'ean', label: this.$tc('ppobase-goods-receipt.detail.columnEan'), width: '100px' },
                { property: 'mpn', label: this.$tc('ppobase-goods-receipt.detail.columnMpn'), width: '100px' },
                { property: 'quantityExpected', label: this.$tc('ppobase-goods-receipt.detail.columnQtyExpected'), width: '100px', align: 'center' },
                { property: 'quantityReceived', label: this.$tc('ppobase-goods-receipt.detail.columnQtyReceived'), width: '110px', align: 'center' },
                { property: 'quantityRejected', label: this.$tc('ppobase-goods-receipt.detail.columnQtyRejected'), width: '110px', align: 'center' },
                { property: 'unitPrice', label: this.$tc('ppobase-goods-receipt.detail.columnUnitPrice'), width: '110px', align: 'right' },
                { property: 'bookedPrice', label: this.$tc('ppobase-goods-receipt.detail.columnBookedPrice'), width: '110px', align: 'right' },
                { property: 'lineTotal', label: this.$tc('ppobase-goods-receipt.detail.columnLineTotal'), width: '110px', align: 'right' }
            ];
        },

        statusVariant: function() {
            if (!this.goodsReceipt) return 'neutral';
            var map = { draft: 'info', booked: 'success', cancelled: 'danger' };
            return map[this.goodsReceipt.status] || 'neutral';
        }
    },

    created: function() {
        this.loadGoodsReceipt();
    },

    methods: {
        onTextareaChange: function(field, value) {
            if (this.goodsReceipt) {
                this.goodsReceipt[field] = value;
            }
        },

        loadGoodsReceipt: async function() {
            this.isLoading = true;
            try {
                var criteria = new ShopwareCriteria();
                criteria.addAssociation('items');
                criteria.addAssociation('purchaseOrder');
                criteria.addAssociation('supplier');
                criteria.getAssociation('items').addSorting(ShopwareCriteria.sort('createdAt', 'ASC'));
                this.goodsReceipt = await this.goodsReceiptRepository.get(this.goodsReceiptId, Shopware.Context.api, criteria);
                this.loadActivityLogs();
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-goods-receipt.detail.errorTitle'),
                    message: error.message
                });
            } finally {
                this.isLoading = false;
            }
        },

        loadActivityLogs: async function() {
            this.isLoadingLogs = true;
            try {
                var result = await this.ppobaseApiService.getActivityLogs('goods_receipt', this.goodsReceiptId);
                this.activityLogs = (result && result.data) ? result.data : [];
            } catch (error) {
                this.activityLogs = [];
            } finally {
                this.isLoadingLogs = false;
            }
        },

        onSave: async function() {
            this.isSaving = true;
            try {
                // Recalculate line totals before saving
                if (this.goodsReceipt.items) {
                    this.goodsReceipt.items.forEach(function(item) {
                        var price = item.bookedPrice || item.unitPrice || 0;
                        item.lineTotal = (item.quantityReceived || 0) * price;
                    });
                }

                await this.goodsReceiptRepository.save(this.goodsReceipt, Shopware.Context.api);
                this.createNotificationSuccess({
                    title: this.$tc('ppobase-goods-receipt.detail.saveSuccessTitle'),
                    message: this.$tc('ppobase-goods-receipt.detail.saveSuccessMessage')
                });
                await this.loadGoodsReceipt();
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-goods-receipt.detail.saveErrorTitle'),
                    message: error.message
                });
            } finally {
                this.isSaving = false;
            }
        },

        onBookReceipt: async function() {
            this.isBooking = true;
            try {
                // Save first to persist any edits
                if (this.goodsReceipt.items) {
                    this.goodsReceipt.items.forEach(function(item) {
                        var price = item.bookedPrice || item.unitPrice || 0;
                        item.lineTotal = (item.quantityReceived || 0) * price;
                    });
                }
                await this.goodsReceiptRepository.save(this.goodsReceipt, Shopware.Context.api);

                var result = await this.ppobaseApiService.bookGoodsReceipt(this.goodsReceiptId);
                if (result && result.success) {
                    this.createNotificationSuccess({
                        title: this.$tc('ppobase-goods-receipt.detail.bookSuccessTitle'),
                        message: this.$tc('ppobase-goods-receipt.detail.bookSuccessMessage')
                    });
                    await this.loadGoodsReceipt();
                } else {
                    throw new Error(result.error || 'Booking failed');
                }
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-goods-receipt.detail.bookErrorTitle'),
                    message: error.message || this.$tc('ppobase-goods-receipt.detail.bookErrorMessage')
                });
            } finally {
                this.isBooking = false;
            }
        },

        onCancelReceipt: async function() {
            this.isCancelling = true;
            try {
                var result = await this.ppobaseApiService.cancelGoodsReceipt(this.goodsReceiptId);
                if (result && result.success) {
                    this.createNotificationSuccess({
                        title: this.$tc('ppobase-goods-receipt.detail.cancelSuccessTitle'),
                        message: this.$tc('ppobase-goods-receipt.detail.cancelSuccessMessage')
                    });
                    await this.loadGoodsReceipt();
                } else {
                    throw new Error(result.error || 'Cancel failed');
                }
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-goods-receipt.detail.cancelErrorTitle'),
                    message: error.message
                });
            } finally {
                this.isCancelling = false;
            }
        },

        onItemQuantityChange: function(item) {
            var price = item.bookedPrice || item.unitPrice || 0;
            item.lineTotal = (item.quantityReceived || 0) * price;
        },

        onItemPriceChange: function(item) {
            item.lineTotal = (item.quantityReceived || 0) * (item.bookedPrice || 0);
        },

        formatCurrency: function(value) {
            if (!value && value !== 0) return '-';
            var currency = 'EUR';
            if (this.goodsReceipt && this.goodsReceipt.purchaseOrder && this.goodsReceipt.purchaseOrder.currency) {
                currency = this.goodsReceipt.purchaseOrder.currency;
            }
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency }).format(value);
        },

        formatDate: function(dateString) {
            return dateString ? new Date(dateString).toLocaleString() : '-';
        }
    }
});
