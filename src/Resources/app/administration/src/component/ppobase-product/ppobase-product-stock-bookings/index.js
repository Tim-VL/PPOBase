import template from './ppobase-product-stock-bookings.html.twig';
import './ppobase-product-stock-bookings.scss';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin = Shopware.Mixin;
var ShopwareCriteria = Shopware.Data.Criteria;

ShopwareComponent.register('ppobase-product-stock-bookings', {
    template: template,

    inject: ['repositoryFactory', 'ppobaseApiService'],

    mixins: [ShopwareMixin.getByName('notification')],

    data: function() {
        return {
            isLoading: false,
            isSaving: false,
            product: null,
            stockBookings: [],
            goodsReceipts: [],
            form: {
                stockAdjustment: '',
                reason: '',
                notes: '',
            },
            customReason: '',
        };
    },

    computed: {
        productId: function() {
            return this.$route.params.id;
        },

        productRepository: function() {
            return this.repositoryFactory.create('product');
        },

        currentStock: function() {
            return this.product ? (parseInt(this.product.stock, 10) || 0) : 0;
        },

        parsedStockAdjustment: function() {
            return this.parseStockAdjustment(this.form.stockAdjustment);
        },

        adjustedStock: function() {
            if (this.parsedStockAdjustment === null) {
                return null;
            }

            return this.currentStock + this.parsedStockAdjustment;
        },

        reasonOptions: function() {
            return [
                { value: 'Own use', label: 'Own use' },
                { value: 'Samples', label: 'Samples' },
                { value: 'Shipping Errors', label: 'Shipping Errors' },
                { value: 'Damage and Spoilage', label: 'Damage and Spoilage' },
                { value: '__custom__', label: 'Custom...' },
            ];
        },

        isCustomReason: function() {
            return this.form.reason === '__custom__';
        },

        effectiveReason: function() {
            return this.isCustomReason ? this.customReason.trim() : this.form.reason;
        },

        bookingColumns: function() {
            return [
                { property: 'bookedAt', label: this.$tc('ppobase-product.stock.columnDate'), width: '180px' },
                { property: 'oldStock', label: this.$tc('ppobase-product.stock.columnOldStock'), width: '100px', align: 'center' },
                { property: 'newStock', label: this.$tc('ppobase-product.stock.columnNewStock'), width: '100px', align: 'center' },
                { property: 'stockChange', label: this.$tc('ppobase-product.stock.columnChange'), width: '100px', align: 'center' },
                { property: 'reason', label: this.$tc('ppobase-product.stock.columnReason') },
                { property: 'notes', label: this.$tc('ppobase-product.stock.columnNotes') },
                { property: 'userName', label: this.$tc('ppobase-product.stock.columnUser'), width: '160px' },
                { property: 'referenceType', label: this.$tc('ppobase-product.stock.columnRefType'), width: '120px' },
            ];
        },

        goodsReceiptColumns: function() {
            return [
                { property: 'receiptNumber', label: this.$tc('ppobase-product.stock.columnReceiptNumber'), primary: true },
                { property: 'status', label: this.$tc('ppobase-product.stock.columnStatus'), width: '120px' },
                { property: 'quantityReceived', label: this.$tc('ppobase-product.stock.columnQtyReceived'), width: '120px', align: 'center' },
                { property: 'bookedPrice', label: this.$tc('ppobase-product.stock.columnBookedPrice'), width: '120px', align: 'right' },
                { property: 'receiptDate', label: this.$tc('ppobase-product.stock.columnReceiptDate'), width: '180px' },
            ];
        },
    },

    created: function() {
        this.loadData();
    },

    methods: {
        loadData: async function() {
            this.isLoading = true;
            try {
                var criteria = new ShopwareCriteria();
                this.product = await this.productRepository.get(this.productId, Shopware.Context.api, criteria);
                this.form.stockAdjustment = '';
                this.form.reason = '';
                this.form.notes = '';
                this.customReason = '';

                await Promise.all([
                    this.loadBookings(),
                    this.loadGoodsReceipts(),
                ]);
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-product.stock.errorTitle'),
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
            }
        },

        loadBookings: async function() {
            var result = await this.ppobaseApiService.getStockBookings(this.productId);
            this.stockBookings = (result && result.data) ? result.data : [];
        },

        loadGoodsReceipts: async function() {
            var result = await this.ppobaseApiService.getProductGoodsReceipts(this.productId);
            this.goodsReceipts = (result && result.data) ? result.data : [];
        },

        onNotesChange: function(value) {
            this.form.notes = value;
        },

        onStockAdjustmentChange: function(value) {
            var normalized = String(value || '')
                .replace(/[^\d+-]/g, '')
                .replace(/(?!^)[+-]/g, '');

            if (normalized.length > 1) {
                normalized = normalized.charAt(0) + normalized.slice(1).replace(/[+-]/g, '');
            }

            this.form.stockAdjustment = normalized;
        },

        parseStockAdjustment: function(value) {
            var normalized = String(value || '').trim();
            if (!/^[+-]\d+$/.test(normalized)) {
                return null;
            }

            var adjustment = parseInt(normalized, 10);
            return Number.isNaN(adjustment) ? null : adjustment;
        },

        onApplyStock: async function() {
            var adjustment = this.parsedStockAdjustment;
            if (adjustment === null) {
                this.createNotificationWarning({
                    title: this.$tc('ppobase-product.stock.errorTitle'),
                    message: this.$tc('ppobase-product.stock.invalidAdjustmentMessage'),
                });
                return;
            }

            var finalStock = this.currentStock + adjustment;
            if (finalStock < 0) {
                this.createNotificationWarning({
                    title: this.$tc('ppobase-product.stock.errorTitle'),
                    message: this.$tc('ppobase-product.stock.negativeStockMessage'),
                });
                return;
            }

            if (adjustment === 0) {
                this.createNotificationWarning({
                    title: this.$tc('ppobase-product.stock.errorTitle'),
                    message: this.$tc('ppobase-product.stock.zeroAdjustmentMessage'),
                });
                return;
            }

            this.isSaving = true;
            try {
                var payload = {
                    newStock: finalStock,
                    reason: this.effectiveReason,
                    notes: this.form.notes,
                };
                var result = await this.ppobaseApiService.createStockBooking(this.productId, payload);

                if (!result || !result.success) {
                    throw new Error((result && result.error) || 'Stock booking failed');
                }

                this.createNotificationSuccess({
                    title: this.$tc('ppobase-product.stock.successTitle'),
                    message: this.$tc('ppobase-product.stock.successMessage'),
                });

                await this.loadData();
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-product.stock.errorTitle'),
                    message: error.message,
                });
            } finally {
                this.isSaving = false;
            }
        },

        formatDate: function(value) {
            return value ? new Date(value).toLocaleString() : '-';
        },

        formatChange: function(value) {
            var num = parseInt(value, 10) || 0;
            return num >= 0 ? ('+' + num) : String(num);
        },

        onOpenGoodsReceipt: function(item) {
            if (!item || !item.goodsReceiptId) {
                return;
            }

            this.$router.push({
                name: 'ppobase.goods.receipt.detail',
                params: { id: item.goodsReceiptId },
            });
        },
    },
});
