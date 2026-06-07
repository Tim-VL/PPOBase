/**
 * Supplier List Page - Shows manual ID range, hides auto supplier ID
 */

import template from './ppobase-supplier-list.html.twig';
import './ppobase-supplier-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('ppobase-supplier-list', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('listing'), Mixin.getByName('notification')],

    data() {
        return {
            suppliers: null,
            isLoading: false,
            searchTerm: '',
            supplierToDelete: null,
            showDeleteModal: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            limit: 25
        };
    },

    computed: {
        supplierRepository() {
            return this.repositoryFactory.create('ppobase_supplier');
        },

        columns() {
            return [
                { property: 'active', label: this.$tc('ppobase-supplier.list.columnActive'), width: '80px', align: 'center' },
                { property: 'itemsIdRange', label: this.$tc('ppobase-supplier.list.columnItemsIdRange'), allowResize: true, width: '120px' },
                { property: 'companyTradeName', label: this.$tc('ppobase-supplier.list.columnTradeName'), allowResize: true, primary: true },
                { property: 'contactEmail', label: this.$tc('ppobase-supplier.list.columnContactEmail'), allowResize: true },
                { property: 'generalPhone', label: this.$tc('ppobase-supplier.list.columnPhone'), allowResize: true, width: '150px' },
                { property: 'currency', label: this.$tc('ppobase-supplier.list.columnCurrency'), width: '100px', align: 'center' }
            ];
        },

        supplierCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            if (this.searchTerm) { criteria.setTerm(this.searchTerm); }
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            return criteria;
        }
    },

    created() { this.getList(); },

    methods: {
        async getList() {
            this.isLoading = true;
            try {
                const result = await this.supplierRepository.search(this.supplierCriteria, Shopware.Context.api);
                this.suppliers = result;
                this.total = result.total;
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-supplier.list.errorTitle'), message: error.message });
            } finally {
                this.isLoading = false;
            }
        },

        onSearch(searchTerm) { this.searchTerm = searchTerm; this.getList(); },

        onSortColumn(column) {
            if (this.sortBy === column.property) {
                this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.sortBy = column.property;
                this.sortDirection = 'ASC';
            }
            this.getList();
        },

        onPageChange({ page, limit }) { this.page = page; this.limit = limit; this.getList(); },

        onMapProducts(supplier) {
            this.$router.push({ name: 'ppobase.supplier.products', params: { id: supplier.id } });
        },

        onDeleteSupplier(supplier) { this.supplierToDelete = supplier; this.showDeleteModal = true; },
        onCloseDeleteModal() { this.supplierToDelete = null; this.showDeleteModal = false; },

        async onConfirmDelete() {
            if (!this.supplierToDelete) return;
            this.isLoading = true;
            try {
                await this.supplierRepository.delete(this.supplierToDelete.id, Shopware.Context.api);
                this.createNotificationSuccess({
                    title: this.$tc('ppobase-supplier.list.deleteSuccessTitle'),
                    message: this.$tc('ppobase-supplier.list.deleteSuccessMessage', 0, { name: this.supplierToDelete.companyTradeName })
                });
                this.getList();
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-supplier.list.deleteErrorTitle'), message: error.message });
            } finally {
                this.isLoading = false;
                this.onCloseDeleteModal();
            }
        }
    }
});
