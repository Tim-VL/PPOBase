/**
 * PO History List - Shows purchase order activity timeline
 * Filters activity logs to PO-related entries only
 */

import template from './ppobase-po-history-list.html.twig';
import './ppobase-po-history-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('ppobase-po-history-list', {
    template,

    inject: ['repositoryFactory'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            logs: null,
            isLoading: false,
            total: 0,
            page: 1,
            limit: 25,
            searchTerm: '',
            filterAction: '',
            sortBy: 'loggedAt',
            sortDirection: 'DESC'
        };
    },

    computed: {
        activityLogRepository() {
            return this.repositoryFactory.create('ppobase_activity_log');
        },

        columns() {
            return [
                {
                    property: 'loggedAt',
                    label: this.$tc('ppobase-po-history.list.columnDate'),
                    sortable: true,
                    width: '180px'
                },
                {
                    property: 'referenceNumber',
                    label: this.$tc('ppobase-po-history.list.columnPONumber'),
                    sortable: true,
                    width: '160px'
                },
                {
                    property: 'action',
                    label: this.$tc('ppobase-po-history.list.columnEvent'),
                    sortable: true,
                    width: '160px'
                },
                {
                    property: 'description',
                    label: this.$tc('ppobase-po-history.list.columnDescription'),
                    primary: true
                },
                {
                    property: 'userName',
                    label: this.$tc('ppobase-po-history.list.columnUser'),
                    sortable: true,
                    width: '140px'
                }
            ];
        },

        actionOptions() {
            return [
                { value: '', label: this.$tc('ppobase-po-history.list.filterAll') },
                { value: 'created', label: this.$tc('ppobase-po-history.list.actionCreated') },
                { value: 'updated', label: this.$tc('ppobase-po-history.list.actionUpdated') },
                { value: 'status_changed', label: this.$tc('ppobase-po-history.list.actionStatusChanged') },
                { value: 'exported', label: this.$tc('ppobase-po-history.list.actionExported') },
                { value: 'emailed', label: this.$tc('ppobase-po-history.list.actionEmailed') },
                { value: 'items_added', label: this.$tc('ppobase-po-history.list.actionItemsAdded') },
                { value: 'items_removed', label: this.$tc('ppobase-po-history.list.actionItemsRemoved') }
            ];
        }
    },

    created() {
        this.loadLogs();
    },

    methods: {
        async loadLogs() {
            this.isLoading = true;
            try {
                const criteria = new Criteria(this.page, this.limit);
                criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
                criteria.setTotalCountMode(1);

                // Only show PO-related entries
                criteria.addFilter(
                    Criteria.multi('OR', [
                        Criteria.equals('entityType', 'purchase_order'),
                        Criteria.equals('entityType', 'purchase_order_item')
                    ])
                );

                if (this.searchTerm) {
                    criteria.addFilter(
                        Criteria.multi('OR', [
                            Criteria.contains('referenceNumber', this.searchTerm),
                            Criteria.contains('description', this.searchTerm)
                        ])
                    );
                }

                if (this.filterAction) {
                    criteria.addFilter(Criteria.equals('action', this.filterAction));
                }

                const result = await this.activityLogRepository.search(criteria, Shopware.Context.api);
                this.logs = result;
                this.total = result.total;
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-po-history.list.errorTitle'),
                    message: error.message
                });
            } finally {
                this.isLoading = false;
            }
        },

        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;
            this.loadLogs();
        },

        onSearch(term) {
            this.searchTerm = term;
            this.page = 1;
            this.loadLogs();
        },

        onSort({ sortBy, sortDirection }) {
            this.sortBy = sortBy;
            this.sortDirection = sortDirection;
            this.loadLogs();
        },

        onFilterChange() {
            this.page = 1;
            this.loadLogs();
        },

        onRefresh() {
            this.loadLogs();
        },

        onRowClick(item) {
            // Only navigate to detail if there are old/new values to show
            if (item.oldValues || item.newValues) {
                this.$router.push({
                    name: 'ppobase.po.history.detail',
                    params: { id: item.id }
                });
            }
        },

        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        getActionLabel(action) {
            const labels = {
                created: this.$tc('ppobase-po-history.list.actionCreated'),
                updated: this.$tc('ppobase-po-history.list.actionUpdated'),
                status_changed: this.$tc('ppobase-po-history.list.actionStatusChanged'),
                exported: this.$tc('ppobase-po-history.list.actionExported'),
                emailed: this.$tc('ppobase-po-history.list.actionEmailed'),
                items_added: this.$tc('ppobase-po-history.list.actionItemsAdded'),
                items_removed: this.$tc('ppobase-po-history.list.actionItemsRemoved'),
                deleted: this.$tc('ppobase-po-history.list.actionDeleted')
            };
            return labels[action] || action;
        },

        getActionVariant(action) {
            const map = {
                created: 'success',
                updated: 'info',
                status_changed: 'warning',
                exported: 'neutral',
                emailed: 'info',
                items_added: 'success',
                items_removed: 'danger',
                deleted: 'danger',
                cancelled: 'danger'
            };
            return map[action] || 'neutral';
        },

        getActionIcon(action) {
            const map = {
                created: 'regular-plus-circle',
                updated: 'regular-pencil',
                status_changed: 'regular-exchange',
                exported: 'regular-file-download',
                emailed: 'regular-envelope',
                items_added: 'regular-plus-s',
                items_removed: 'regular-minus-s',
                deleted: 'regular-trash'
            };
            return map[action] || 'regular-circle';
        },

        hasDetails(item) {
            return !!(item.oldValues || item.newValues);
        }
    }
});
