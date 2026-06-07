/**
 * PO History Detail - Shows a single PO event with change diff
 */

import template from './ppobase-po-history-detail.html.twig';
import './ppobase-po-history-detail.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('ppobase-po-history-detail', {
    template,

    inject: ['repositoryFactory'],

    mixins: [Mixin.getByName('notification')],

    props: {
        logId: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            log: null,
            relatedLogs: [],
            isLoading: false,
            isLoadingRelated: false
        };
    },

    computed: {
        activityLogRepository() {
            return this.repositoryFactory.create('ppobase_activity_log');
        },

        pageTitle() {
            if (!this.log) return this.$tc('ppobase-po-history.detail.title');
            const ref = this.log.referenceNumber || '';
            const action = this.getActionLabel(this.log.action);
            return ref ? `${ref} — ${action}` : action;
        },

        parsedOldValues() {
            return this.parseJson(this.log?.oldValues);
        },

        parsedNewValues() {
            return this.parseJson(this.log?.newValues);
        },

        changedFields() {
            const oldVals = this.parsedOldValues || {};
            const newVals = this.parsedNewValues || {};

            if (Object.keys(oldVals).length === 0 && Object.keys(newVals).length === 0) {
                return [];
            }

            const allKeys = new Set([
                ...Object.keys(oldVals),
                ...Object.keys(newVals)
            ]);

            return Array.from(allKeys).map(key => ({
                id: key,
                field: key,
                oldValue: this.formatValue(oldVals[key]),
                newValue: this.formatValue(newVals[key]),
                changed: JSON.stringify(oldVals[key]) !== JSON.stringify(newVals[key])
            }));
        },

        diffColumns() {
            return [
                { property: 'field', label: this.$tc('ppobase-po-history.detail.columnField'), width: '220px' },
                { property: 'oldValue', label: this.$tc('ppobase-po-history.detail.columnBefore') },
                { property: 'newValue', label: this.$tc('ppobase-po-history.detail.columnAfter') }
            ];
        },

        relatedLogColumns() {
            return [
                { property: 'loggedAt', label: this.$tc('ppobase-po-history.detail.columnDate'), width: '180px' },
                { property: 'action', label: this.$tc('ppobase-po-history.detail.columnEvent'), width: '150px' },
                { property: 'description', label: this.$tc('ppobase-po-history.detail.columnDescription') },
                { property: 'userName', label: this.$tc('ppobase-po-history.detail.columnUser'), width: '120px' }
            ];
        }
    },

    created() {
        this.loadLog();
    },

    methods: {
        async loadLog() {
            this.isLoading = true;
            try {
                this.log = await this.activityLogRepository.get(
                    this.logId,
                    Shopware.Context.api,
                    new Criteria()
                );

                if (!this.log) {
                    this.createNotificationError({
                        title: this.$tc('ppobase-po-history.detail.errorTitle'),
                        message: this.$tc('ppobase-po-history.detail.notFoundMessage')
                    });
                    this.$router.push({ name: 'ppobase.po.history.list' });
                    return;
                }

                // Load related logs for the same PO
                if (this.log.referenceNumber) {
                    this.loadRelatedLogs();
                }
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-po-history.detail.errorTitle'),
                    message: error.message
                });
            } finally {
                this.isLoading = false;
            }
        },

        async loadRelatedLogs() {
            this.isLoadingRelated = true;
            try {
                const criteria = new Criteria(1, 50);
                criteria.addFilter(Criteria.equals('referenceNumber', this.log.referenceNumber));
                criteria.addFilter(Criteria.not('AND', [Criteria.equals('id', this.logId)]));
                criteria.addSorting(Criteria.sort('loggedAt', 'DESC'));

                const result = await this.activityLogRepository.search(criteria, Shopware.Context.api);
                this.relatedLogs = result;
            } catch (e) {
                // silently fail
            } finally {
                this.isLoadingRelated = false;
            }
        },

        onBack() {
            this.$router.push({ name: 'ppobase.po.history.list' });
        },

        onRelatedClick(item) {
            this.$router.push({
                name: 'ppobase.po.history.detail',
                params: { id: item.id }
            });
        },

        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        },

        parseJson(value) {
            if (!value) return null;
            try {
                return JSON.parse(value);
            } catch {
                return null;
            }
        },

        formatValue(val) {
            if (val === null || val === undefined) return '—';
            if (val === '') return '(empty)';
            if (typeof val === 'boolean') return val ? 'Yes' : 'No';
            if (typeof val === 'object') return JSON.stringify(val, null, 2);
            return String(val);
        },

        getActionLabel(action) {
            const labels = {
                created: 'Created',
                updated: 'Updated',
                status_changed: 'Status Changed',
                exported: 'Exported',
                emailed: 'Emailed',
                items_added: 'Items Added',
                items_removed: 'Items Removed',
                deleted: 'Deleted'
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
                deleted: 'danger'
            };
            return map[action] || 'neutral';
        }
    }
});
