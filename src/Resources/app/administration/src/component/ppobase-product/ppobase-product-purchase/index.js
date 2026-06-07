import template from './ppobase-product-purchase.html.twig';
import './ppobase-product-purchase.scss';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin = Shopware.Mixin;
var ShopwareCriteria = Shopware.Data.Criteria;

ShopwareComponent.register('ppobase-product-purchase', {
    template: template,

    inject: ['repositoryFactory', 'ppobaseApiService'],

    mixins: [ShopwareMixin.getByName('notification')],

    data: function() {
        return {
            isLoading: false,
            isSaving: false,
            suppliers: [],
            purchaseOrders: [],
            supplierOptions: [],
            draftPurchaseOrders: [],
            showAddSupplierModal: false,
            showAddToPoModal: false,
            currencies: [],
            defaultCurrencyIsoCode: 'EUR',
            addSupplierForm: {
                supplierId: null,
                supplierSku: '',
                supplierPrice: 0,
                supplierCurrency: 'EUR',
            },
            addToPoForm: {
                supplierId: null,
                quantity: 1,
                unit: 'pcs',
                purchaseOrderId: null,
            },
        };
    },

    computed: {
        productId: function() {
            return this.$route.params.id;
        },

        supplierRepository: function() {
            return this.repositoryFactory.create('ppobase_supplier');
        },

        currencyRepository: function() {
            return this.repositoryFactory.create('currency');
        },

        currencyOptions: function() {
            return this.currencies.map(function(c) {
                return {
                    value: c.isoCode,
                    label: c.isoCode + ' - ' + c.name,
                };
            });
        },

        supplierColumns: function() {
            return [
                { property: 'idRange',       label: this.$tc('ppobase-supplier.list.columnItemsIdRange'), width: '120px' },
                { property: 'supplierName',  label: this.$tc('ppobase-supplier.list.columnTradeName'), primary: true },
                { property: 'contactEmail',  label: this.$tc('ppobase-supplier.list.columnContactEmail') },
                { property: 'phone',         label: this.$tc('ppobase-supplier.list.columnPhone'), width: '150px' },
                { property: 'currency',      label: this.$tc('ppobase-supplier.list.columnCurrency'), width: '100px', align: 'center' },
            ];
        },

        purchaseOrderColumns: function() {
            return [
                { property: 'poNumber', label: this.$tc('ppobase-product.purchase.columnPoNumber'), primary: true, width: '140px' },
                { property: 'supplierName', label: this.$tc('ppobase-product.purchase.columnPoSupplier'), width: '160px' },
                { property: 'poStatus', label: this.$tc('ppobase-product.purchase.columnPoStatus'), width: '100px' },
                { property: 'quantityOrdered', label: this.$tc('ppobase-product.purchase.columnQtyOrdered'), width: '90px', align: 'center' },
                { property: 'quantityReceived', label: this.$tc('ppobase-product.purchase.columnQtyReceived'), width: '90px', align: 'center' },
                { property: 'unitPrice', label: this.$tc('ppobase-product.purchase.columnUnitPrice'), width: '110px', align: 'right' },
                { property: 'orderDate', label: this.$tc('ppobase-product.purchase.columnOrderDate'), width: '120px' },
                { property: 'expectedDeliveryDate', label: this.$tc('ppobase-product.purchase.columnExpectedDelivery'), width: '130px' },
            ];
        },
    },

    created: function() {
        this.loadData();
        this.loadSupplierOptions();
        this.loadCurrencies();
    },

    methods: {
        loadData: async function() {
            this.isLoading = true;
            try {
                await Promise.all([
                    this.loadSuppliers(),
                    this.loadPurchaseOrders(),
                ]);
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-product.purchase.errorTitle'),
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
            }
        },

        loadSuppliers: async function() {
            var result = await this.ppobaseApiService.getProductSuppliers(this.productId);
            this.suppliers = (result && result.data) ? result.data : [];
        },

        loadPurchaseOrders: async function() {
            var result = await this.ppobaseApiService.getProductPurchaseOrders(this.productId);
            this.purchaseOrders = (result && result.data) ? result.data : [];
        },

        loadSupplierOptions: async function() {
            var criteria = new ShopwareCriteria();
            criteria.addFilter(ShopwareCriteria.equals('active', true));
            criteria.addSorting(ShopwareCriteria.sort('companyTradeName', 'ASC'));
            var entities = await this.supplierRepository.search(criteria, Shopware.Context.api);
            this.supplierOptions = entities;
        },

        loadCurrencies: async function() {
            var criteria = new ShopwareCriteria();
            criteria.addSorting(ShopwareCriteria.sort('name', 'ASC'));

            var result = await this.currencyRepository.search(criteria, Shopware.Context.api);
            this.currencies = Array.from(result);

            var defaultId = Shopware.Context.app && Shopware.Context.app.systemCurrencyId ? Shopware.Context.app.systemCurrencyId : null;
            var defaultCurrency = defaultId ? this.currencies.find(function(c) { return c.id === defaultId; }) : null;
            this.defaultCurrencyIsoCode = defaultCurrency ? defaultCurrency.isoCode : (this.currencies[0] ? this.currencies[0].isoCode : 'EUR');

            if (!this.addSupplierForm.supplierCurrency) {
                this.addSupplierForm.supplierCurrency = this.defaultCurrencyIsoCode;
            }
        },

        loadDraftPosForSupplier: async function() {
            this.draftPurchaseOrders = [];

            if (!this.addToPoForm.supplierId) {
                return;
            }

            var poRepository = this.repositoryFactory.create('ppobase_purchase_order');
            var criteria = new ShopwareCriteria();
            criteria.addFilter(ShopwareCriteria.equals('supplierId', this.addToPoForm.supplierId));
            criteria.addFilter(ShopwareCriteria.equals('status', 'draft'));
            criteria.addSorting(ShopwareCriteria.sort('createdAt', 'DESC'));
            var pos = await poRepository.search(criteria, Shopware.Context.api);
            this.draftPurchaseOrders = pos;
        },

        formatDate: function(value) {
            if (!value) return '-';
            var d = new Date(value);
            return isNaN(d.getTime()) ? '-' : d.toLocaleDateString();
        },

        onOpenAddSupplierModal: function() {
            this.showAddSupplierModal = true;
            this.addSupplierForm = {
                supplierId: null,
                supplierSku: '',
                supplierPrice: 0,
                supplierCurrency: this.defaultCurrencyIsoCode || 'EUR',
            };
        },

        onAddSupplier: async function() {
            this.isSaving = true;
            try {
                var payload = {
                    supplierId: this.addSupplierForm.supplierId,
                    supplierSku: this.addSupplierForm.supplierSku,
                    supplierPrice: this.addSupplierForm.supplierPrice,
                    supplierCurrency: this.addSupplierForm.supplierCurrency,
                };

                var result = await this.ppobaseApiService.addProductSupplier(this.productId, payload);
                if (!result || !result.success) {
                    throw new Error((result && result.error) || 'Failed to add supplier');
                }

                this.showAddSupplierModal = false;
                this.createNotificationSuccess({
                    title: this.$tc('ppobase-product.purchase.successTitle'),
                    message: this.$tc('ppobase-product.purchase.supplierAddedMessage'),
                });
                await this.loadSuppliers();
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-product.purchase.errorTitle'),
                    message: error.message,
                });
            } finally {
                this.isSaving = false;
            }
        },

        onRemoveSupplier: async function(item) {
            if (!item || !item.id) {
                return;
            }

            try {
                var result = await this.ppobaseApiService.removeProductSupplier(this.productId, item.id);
                if (!result || !result.success) {
                    throw new Error((result && result.error) || 'Failed to remove supplier');
                }

                this.createNotificationSuccess({
                    title: this.$tc('ppobase-product.purchase.successTitle'),
                    message: this.$tc('ppobase-product.purchase.supplierRemovedMessage'),
                });
                await this.loadSuppliers();
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-product.purchase.errorTitle'),
                    message: error.message,
                });
            }
        },

        onOpenAddToPoModal: function() {
            this.showAddToPoModal = true;
            this.addToPoForm.supplierId = this.suppliers.length ? this.suppliers[0].supplierId : null;
            this.addToPoForm.purchaseOrderId = null;
            this.loadDraftPosForSupplier();
        },

        onAddToPo: async function() {
            this.isSaving = true;
            try {
                var payload = {
                    supplierId: this.addToPoForm.supplierId,
                    quantity: this.addToPoForm.quantity,
                    unit: this.addToPoForm.unit,
                    purchaseOrderId: this.addToPoForm.purchaseOrderId || null,
                };

                var result = await this.ppobaseApiService.addProductToPo(this.productId, payload);
                if (!result || !result.success) {
                    throw new Error((result && result.error) || 'Failed to add to PO');
                }

                this.showAddToPoModal = false;
                this.createNotificationSuccess({
                    title: this.$tc('ppobase-product.purchase.successTitle'),
                    message: this.$tc('ppobase-product.purchase.addedToPoMessage') + ' ' + (result.data && result.data.poNumber ? result.data.poNumber : ''),
                });
                await this.loadPurchaseOrders();
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-product.purchase.errorTitle'),
                    message: error.message,
                });
            } finally {
                this.isSaving = false;
            }
        },

        onOpenPurchaseOrder: function(item) {
            if (!item || !item.purchaseOrderId) {
                return;
            }

            var resolved = this.$router.resolve({
                name: 'ppobase.purchase.order.detail',
                params: { id: item.purchaseOrderId },
            });

            window.open(resolved.href, '_blank');
        },

        getPurchaseOrderHref: function(item) {
            if (!item || !item.purchaseOrderId) return '#';
            try {
                var resolved = this.$router.resolve({
                    name: 'ppobase.purchase.order.detail',
                    params: { id: item.purchaseOrderId },
                });
                return resolved.href;
            } catch (e) {
                return '#';
            }
        },
    },
});
