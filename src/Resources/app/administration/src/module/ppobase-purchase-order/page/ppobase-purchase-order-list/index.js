/**
 * Purchase Order List Page
 */

import template from './ppobase-purchase-order-list.html.twig';
import './ppobase-purchase-order-list.scss'

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('ppobase-purchase-order-list', {
    template,

    inject: [
        'repositoryFactory'
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            purchaseOrders: null,
            isLoading: false,
            searchTerm: '',
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            limit: 25,
            statusFilter: null
        };
    },

    computed: {
        purchaseOrderRepository() {
            return this.repositoryFactory.create('ppobase_purchase_order');
        },

        columns() {
            return [
                {
                    property: 'poNumber',
                    label: this.$tc('ppobase-purchase-order.list.columnPoNumber'),
                    allowResize: true,
                    primary: true
                },
                {
                    property: 'supplier.companyTradeName',
                    label: this.$tc('ppobase-purchase-order.list.columnSupplier'),
                    allowResize: true
                },
                {
                    property: 'status',
                    label: this.$tc('ppobase-purchase-order.list.columnStatus'),
                    width: '120px'
                },
                {
                    property: 'orderDate',
                    label: this.$tc('ppobase-purchase-order.list.columnOrderDate'),
                    width: '150px'
                },
                {
                    property: 'total',
                    label: this.$tc('ppobase-purchase-order.list.columnTotal'),
                    width: '120px',
                    align: 'right'
                },
                {
                    property: 'itemCount',
                    label: this.$tc('ppobase-purchase-order.list.columnItems'),
                    width: '80px',
                    align: 'center'
                }
            ];
        },

        purchaseOrderCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            
            criteria.addAssociation('supplier');
            
            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            if (this.statusFilter) {
                criteria.addFilter(Criteria.equals('status', this.statusFilter));
            }

            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            return criteria;
        },

        statusOptions() {
            return [
                { value: null, label: this.$tc('ppobase-purchase-order.list.filterAll') },
                { value: 'draft', label: this.$tc('ppobase-purchase-order.status.draft') },
                { value: 'sent', label: this.$tc('ppobase-purchase-order.status.sent') },
                { value: 'partial', label: this.$tc('ppobase-purchase-order.status.partial') },
                { value: 'received', label: this.$tc('ppobase-purchase-order.status.received') },
                { value: 'cancelled', label: this.$tc('ppobase-purchase-order.status.cancelled') }
            ];
        }
    },

    created() {
        this.getList();
    },

    methods: {
        async getList() {
            this.isLoading = true;

            try {
                const result = await this.purchaseOrderRepository.search(
                    this.purchaseOrderCriteria,
                    Shopware.Context.api
                );

                this.purchaseOrders = result;
                this.total = result.total;
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-purchase-order.list.errorTitle'),
                    message: error.message
                });
            } finally {
                this.isLoading = false;
            }
        },

        onSearch(searchTerm) {
            this.searchTerm = searchTerm;
            this.getList();
        },

        onStatusFilterChange(status) {
            this.statusFilter = status;
            this.getList();
        },

        onSortColumn(column) {
            if (this.sortBy === column.property) {
                this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.sortBy = column.property;
                this.sortDirection = 'ASC';
            }
            this.getList();
        },

        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;
            this.getList();
        },

        getStatusVariant(status) {
            const variants = {
                draft: 'info',
                sent: 'warning',
                partial: 'warning',
                received: 'success',
                cancelled: 'danger'
            };
            return variants[status] || 'neutral';
        },

        formatCurrency(value, currency) {
            if (value === null || value === undefined) return '-';
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency || 'EUR'
            }).format(value);
        },

        formatDate(dateString) {
            if (!dateString) return '-';
            return new Date(dateString).toLocaleDateString();
        }
    }
});