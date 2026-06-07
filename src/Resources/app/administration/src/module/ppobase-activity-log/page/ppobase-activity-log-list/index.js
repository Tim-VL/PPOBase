/**
 * Activity Log List Page
 */

import template from './ppobase-activity-log-list.html.twig';
import './ppobase-activity-log-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('ppobase-activity-log-list', {
    template,

    inject: ['repositoryFactory'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            activityLogs: null,
            isLoading: false,
            sortBy: 'loggedAt',
            sortDirection: 'DESC',
            page: 1,
            limit: 25,
            total: 0,
            filterEntityType: null,
            filterAction: null
        };
    },

    computed: {
        activityLogRepository() {
            return this.repositoryFactory.create('ppobase_activity_log');
        },

        columns() {
            return [
                { property: 'loggedAt', label: this.$tc('ppobase-activity-log.list.columnDate'), width: '180px', sortable: true },
                { property: 'entityType', label: this.$tc('ppobase-activity-log.list.columnEntityType'), width: '140px', sortable: true },
                { property: 'referenceNumber', label: this.$tc('ppobase-activity-log.list.columnReference'), width: '150px' },
                { property: 'action', label: this.$tc('ppobase-activity-log.list.columnAction'), width: '140px', sortable: true },
                { property: 'description', label: this.$tc('ppobase-activity-log.list.columnDescription'), primary: true },
                { property: 'userName', label: this.$tc('ppobase-activity-log.list.columnUser'), width: '120px' },
                { property: 'ipAddress', label: this.$tc('ppobase-activity-log.list.columnIp'), width: '130px' }
            ];
        },

        entityTypeOptions() {
            return [
                { value: null, label: this.$tc('ppobase-activity-log.list.filterAll') },
                { value: 'supplier', label: this.$tc('ppobase-activity-log.list.entitySupplier') },
                { value: 'purchase_order', label: this.$tc('ppobase-activity-log.list.entityPurchaseOrder') },
                { value: 'supplier_product', label: this.$tc('ppobase-activity-log.list.entitySupplierProduct') },
                { value: 'purchase_order_item', label: this.$tc('ppobase-activity-log.list.entityPurchaseOrderItem') },
                { value: 'grouped_product', label: this.$tc('ppobase-activity-log.list.entityGroupedProduct') }
            ];
        },

        actionOptions() {
            return [
                { value: null, label: this.$tc('ppobase-activity-log.list.filterAll') },
                { value: 'created', label: this.$tc('ppobase-activity-log.list.actionCreated') },
                { value: 'updated', label: this.$tc('ppobase-activity-log.list.actionUpdated') },
                { value: 'deleted', label: this.$tc('ppobase-activity-log.list.actionDeleted') },
                { value: 'status_changed', label: this.$tc('ppobase-activity-log.list.actionStatusChanged') },
                { value: 'exported', label: this.$tc('ppobase-activity-log.list.actionExported') },
                { value: 'emailed', label: this.$tc('ppobase-activity-log.list.actionEmailed') },
                { value: 'items_added', label: this.$tc('ppobase-activity-log.list.actionItemsAdded') },
                { value: 'items_assigned', label: this.$tc('ppobase-activity-log.list.actionItemsAssigned') },
                { value: 'items_removed', label: this.$tc('ppobase-activity-log.list.actionItemsRemoved') },
                { value: 'product_mapped', label: this.$tc('ppobase-activity-log.list.actionProductMapped') },
                { value: 'product_unmapped', label: this.$tc('ppobase-activity-log.list.actionProductUnmapped') }
            ];
        }
    },

    created() {
        this.loadActivityLogs();
    },

    methods: {
        async loadActivityLogs() {
            this.isLoading = true;

            try {
                const criteria = new Criteria(this.page, this.limit);
                criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
                criteria.setTotalCountMode(1);

                if (this.filterEntityType) {
                    criteria.addFilter(Criteria.equals('entityType', this.filterEntityType));
                }
                if (this.filterAction) {
                    criteria.addFilter(Criteria.equals('action', this.filterAction));
                }

                const result = await this.activityLogRepository.search(criteria, Shopware.Context.api);
                this.activityLogs = result;
                this.total = result?.total ?? 0;
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-activity-log.list.errorTitle'),
                    message: error.message
                });
            } finally {
                this.isLoading = false;
            }
        },

        onColumnSort(column) {
            if (this.sortBy === column.dataIndex) {
                this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.sortBy = column.dataIndex;
                this.sortDirection = 'DESC';
            }
            this.loadActivityLogs();
        },

        onPageChange(data) {
            this.page = data.page;
            this.limit = data.limit;
            this.loadActivityLogs();
        },

        onFilterChange() {
            this.page = 1;
            this.loadActivityLogs();
        },

        formatDate(dateString) {
            if (!dateString) return '-';
            return new Date(dateString).toLocaleString();
        },

        getActionVariant(action) {
            const variants = {
                created: 'success',
                updated: 'info',
                deleted: 'danger',
                status_changed: 'warning',
                exported: 'neutral',
                emailed: 'info',
                items_added: 'success',
                items_removed: 'warning',
                product_mapped: 'success',
                product_unmapped: 'warning'
            };
            return variants[action] || 'neutral';
        },

        getEntityTypeLabel(type) {
            const labels = {
                supplier: this.$tc('ppobase-activity-log.list.entitySupplier'),
                purchase_order: this.$tc('ppobase-activity-log.list.entityPurchaseOrder'),
                supplier_product: this.$tc('ppobase-activity-log.list.entitySupplierProduct'),
                purchase_order_item: this.$tc('ppobase-activity-log.list.entityPurchaseOrderItem'),
                grouped_product: this.$tc('ppobase-activity-log.list.entityGroupedProduct')
            };
            return labels[type] || type;
        }
    }
});
