/**
 * Purchase Order Create Page - Gets currency from Shopware system
 */

import template from './ppobase-purchase-order-create.html.twig';
import './ppobase-purchase-order-create.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.extend('ppobase-purchase-order-create', 'ppobase-purchase-order-detail', {
    template,

    data() {
        return {
            suppliers: [],
            selectedSupplierId: null,
            selectedSalesChannelId: null,
            defaultCurrencyIso: 'EUR'
        };
    },

    computed: {
        supplierRepository() {
            return this.repositoryFactory.create('ppobase_supplier');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        pageTitle() {
            return this.$tc('ppobase-purchase-order.create.title');
        },

        addItemColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('ppobase-purchase-order.detail.columnProduct'),
                    primary: true,
                    allowResize: true
                },
                {
                    property: 'productNumber',
                    label: this.$tc('ppobase-purchase-order.detail.columnSku'),
                    width: '150px',
                    allowResize: true
                },
                {
                    property: 'supplierPrice',
                    label: this.$tc('ppobase-purchase-order.detail.columnPrice'),
                    width: '120px',
                    allowResize: true
                }
            ];
        }
    },

    created() {
        this.loadSuppliers();
        this.loadDefaultCurrency();
    },

    methods: {
        async loadSuppliers() {
            this.isLoading = true;
            try {
                const criteria = new Criteria();
                criteria.addFilter(Criteria.equals('active', true));
                criteria.addSorting(Criteria.sort('companyTradeName', 'ASC'));
                const result = await this.supplierRepository.search(criteria, Shopware.Context.api);
                this.suppliers = result;
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-purchase-order.create.errorTitle'), message: error.message });
            } finally {
                this.isLoading = false;
            }
        },

        async loadDefaultCurrency() {
            try {
                const criteria = new Criteria();
                criteria.addFilter(Criteria.equals('isSystemDefault', true));
                criteria.setLimit(1);
                const result = await this.currencyRepository.search(criteria, Shopware.Context.api);
                if (result.total > 0) {
                    this.defaultCurrencyIso = result.first().isoCode;
                }
            } catch (e) {
                // fallback to EUR
            }
        },

        loadPurchaseOrder() {
            // Override - don't load, create new
        },

        onSalesChannelSelected(salesChannelId) {
            this.selectedSalesChannelId = salesChannelId || null;
            if (this.purchaseOrder) {
                this.purchaseOrder.salesChannelId = this.selectedSalesChannelId;
                this.hasChanges = true;
            }
        },

        async onSupplierSelected(supplierId) {
            if (!supplierId) return;

            this.selectedSupplierId = supplierId;
            const supplier = this.suppliers.find(s => s.id === supplierId);
            const poNumber = await this.generatePoNumber();

            this.purchaseOrder = this.purchaseOrderRepository.create(Shopware.Context.api);
            this.purchaseOrder.poNumber = poNumber;
            this.purchaseOrder.supplierId = supplierId;
            this.purchaseOrder.supplier = supplier;
            this.purchaseOrder.salesChannelId = this.selectedSalesChannelId;
            this.purchaseOrder.status = 'draft';
            this.purchaseOrder.orderDate = new Date().toISOString();
            // Use supplier currency, fall back to Shopware default
            this.purchaseOrder.currency = supplier?.currency || this.defaultCurrencyIso;
            this.purchaseOrder.subtotal = 0;
            this.purchaseOrder.taxAmount = 0;
            this.purchaseOrder.total = 0;
            this.purchaseOrder.itemCount = 0;
            this.purchaseOrder.emailSent = false;
            this.purchaseOrder.createdBy = 'admin';
            this.purchaseOrder.items = [];
            this.hasChanges = true;
        },

        async generatePoNumber() {
            const year = new Date().getFullYear();
            const criteria = new Criteria();
            criteria.addFilter(Criteria.contains('poNumber', `PO${year}-`));
            criteria.addSorting(Criteria.sort('poNumber', 'DESC'));
            criteria.setLimit(1);
            try {
                const result = await this.purchaseOrderRepository.search(criteria, Shopware.Context.api);
                if (result.total === 0) return `PO${year}-0001`;
                const lastPo = result.first();
                const lastNumber = parseInt(lastPo.poNumber.split('-')[1], 10) || 0;
                return `PO${year}-${String(lastNumber + 1).padStart(4, '0')}`;
            } catch (error) {
                return `PO${year}-0001`;
            }
        },

        async onSave() {
            if (!this.purchaseOrder) {
                this.createNotificationError({
                    title: this.$tc('ppobase-purchase-order.create.errorTitle'),
                    message: this.$tc('ppobase-purchase-order.create.selectSupplierFirst')
                });
                return;
            }

            if (!this.selectedSalesChannelId) {
                this.createNotificationError({
                    title: this.$tc('ppobase-purchase-order.create.errorTitle'),
                    message: this.$tc('ppobase-purchase-order.create.selectSalesChannelFirst')
                });
                return;
            }

            if (this.purchaseOrder && this.purchaseOrder.items) {
                for (var i = this.purchaseOrder.items.length - 1; i >= 0; i--) {
                    if ((this.purchaseOrder.items[i].quantityOrdered || 0) === 0) {
                        this.purchaseOrder.items.splice(i, 1);
                    }
                }
            }
            this.isSaving = true;
            try {
                this.recalculateTotals();
                this.purchaseOrder.salesChannelId = this.selectedSalesChannelId;
                await this.purchaseOrderRepository.save(this.purchaseOrder, Shopware.Context.api);
                this.hasChanges = false;
                this.createNotificationSuccess({
                    title: this.$tc('ppobase-purchase-order.create.saveSuccessTitle'),
                    message: this.$tc('ppobase-purchase-order.create.saveSuccessMessage')
                });
                this.$router.push({
                    name: 'ppobase.purchase.order.detail',
                    params: { id: this.purchaseOrder.id }
                });
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-purchase-order.create.saveErrorTitle'), message: error.message });
            } finally {
                this.isSaving = false;
            }
        },

        onCancel() {
            if (this.hasChanges) {
                this.unsavedChangesAction = () => {
                    this.$router.push({ name: 'ppobase.purchase.order.list' });
                };
                this.showUnsavedChangesModal = true;
                return;
            }
            this.$router.push({ name: 'ppobase.purchase.order.list' });
        }
    }
});
