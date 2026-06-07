import template from './ppobase-goods-receipt-list.html.twig';
import './ppobase-goods-receipt-list.scss';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin = Shopware.Mixin;
var ShopwareCriteria = Shopware.Data.Criteria;

ShopwareComponent.register('ppobase-goods-receipt-list', {
    template: template,

    inject: ['repositoryFactory'],

    mixins: [
        ShopwareMixin.getByName('listing'),
        ShopwareMixin.getByName('notification')
    ],

    data: function() {
        return {
            goodsReceipts: null,
            isLoading: false,
            searchTerm: '',
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            page: 1,
            limit: 25,
            total: 0,
            statusFilter: null
        };
    },

    computed: {
        goodsReceiptRepository: function() {
            return this.repositoryFactory.create('ppobase_goods_receipt');
        },

        columns: function() {
            return [
                {
                    property: 'receiptNumber',
                    label: this.$tc('ppobase-goods-receipt.list.columnReceiptNumber'),
                    allowResize: true,
                    primary: true
                },
                {
                    property: 'purchaseOrder.poNumber',
                    label: this.$tc('ppobase-goods-receipt.list.columnPoNumber'),
                    allowResize: true
                },
                {
                    property: 'supplier.companyTradeName',
                    label: this.$tc('ppobase-goods-receipt.list.columnSupplier'),
                    allowResize: true
                },
                {
                    property: 'status',
                    label: this.$tc('ppobase-goods-receipt.list.columnStatus'),
                    width: '120px'
                },
                {
                    property: 'receiptDate',
                    label: this.$tc('ppobase-goods-receipt.list.columnReceiptDate'),
                    width: '150px'
                },
                {
                    property: 'invoiceReference',
                    label: this.$tc('ppobase-goods-receipt.list.columnInvoiceReference'),
                    width: '150px'
                },
                {
                    property: 'createdAt',
                    label: this.$tc('ppobase-goods-receipt.list.columnCreatedAt'),
                    width: '150px'
                }
            ];
        },

        listCriteria: function() {
            var criteria = new ShopwareCriteria(this.page, this.limit);
            criteria.addAssociation('purchaseOrder');
            criteria.addAssociation('supplier');
            criteria.addSorting(ShopwareCriteria.sort(this.sortBy, this.sortDirection));
            criteria.setTotalCountMode(1);

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            if (this.statusFilter) {
                criteria.addFilter(ShopwareCriteria.equals('status', this.statusFilter));
            }

            return criteria;
        },

        statusOptions: function() {
            return [
                { value: null, label: this.$tc('ppobase-goods-receipt.list.filterAll') },
                { value: 'draft', label: this.$tc('ppobase-goods-receipt.status.draft') },
                { value: 'booked', label: this.$tc('ppobase-goods-receipt.status.booked') },
                { value: 'cancelled', label: this.$tc('ppobase-goods-receipt.status.cancelled') }
            ];
        }
    },

    created: function() {
        this.getList();
    },

    methods: {
        getList: async function() {
            this.isLoading = true;
            try {
                var result = await this.goodsReceiptRepository.search(this.listCriteria, Shopware.Context.api);
                this.goodsReceipts = result;
                this.total = result.total;
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-goods-receipt.list.errorTitle'),
                    message: error.message
                });
            } finally {
                this.isLoading = false;
            }
        },

        onSearch: function(searchTerm) {
            this.searchTerm = searchTerm;
            this.page = 1;
            this.getList();
        },

        onStatusFilterChange: function(value) {
            this.statusFilter = value;
            this.page = 1;
            this.getList();
        },

        onClickRow: function(item) {
            this.$router.push({ name: 'ppobase.goods.receipt.detail', params: { id: item.id } });
        },

        onPageChange: function(data) {
            this.page = data.page;
            this.limit = data.limit;
            this.getList();
        },

        getStatusVariant: function(status) {
            var map = {
                draft: 'info',
                booked: 'success',
                cancelled: 'danger'
            };
            return map[status] || 'neutral';
        },

        formatDate: function(dateString) {
            if (!dateString) return '-';
            return new Date(dateString).toLocaleDateString();
        }
    }
});
