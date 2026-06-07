/**
 * Supplier Detail Page Component - With Tabs
 */

import template from './ppobase-supplier-detail.html.twig';
import './ppobase-supplier-detail.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('ppobase-supplier-detail', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('placeholder')],

    props: {
        supplierId: { type: String, required: true }
    },

    data() {
        return {
            supplier: null,
            isLoading: false,
            isSaving: false,
            hasChanges: false,
            activeTab: 'general',
            showUnsavedChangesModal: false,
            unsavedChangesAction: null,
            countries: [],
            currencies: [],
            defaultCurrencyId: null,
            mappedProducts: [],
            isLoadingProducts: false,
            showProductModal: false,
            availableProducts: null,
            selectedProducts: [],
            isLoadingAvailable: false,
            isSavingProducts: false,
            productSearchTerm: '',
            productPage: 1,
            productLimit: 25,
            productTotal: 0
        };
    },

    computed: {
        supplierRepository() {
            return this.repositoryFactory.create('ppobase_supplier');
        },

        countryRepository() {
            return this.repositoryFactory.create('country');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        supplierProductRepository() {
            return this.repositoryFactory.create('ppobase_supplier_product');
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        pageTitle() {
            if (this.supplier && this.supplier.companyTradeName) {
                return this.supplier.companyTradeName;
            }
            return this.$tc('ppobase-supplier.detail.title');
        },

        countryOptions() {
            return this.countries.map(c => ({
                value: c.name,
                label: c.name
            }));
        },

        currencyOptions() {
            const options = [];
            // Put default currency first
            const defaultCurrency = this.currencies.find(c => c.isSystemDefault);
            if (defaultCurrency) {
                options.push({
                    value: defaultCurrency.isoCode,
                    label: `${defaultCurrency.isoCode} - ${defaultCurrency.name} (Default)`
                });
            }
            this.currencies.forEach(c => {
                if (!c.isSystemDefault) {
                    options.push({
                        value: c.isoCode,
                        label: `${c.isoCode} - ${c.name}`
                    });
                }
            });
            return options;
        },

        mappedProductColumns() {
            return [
                { property: 'productNumber', label: this.$tc('ppobase-supplier.products.columnProductNumber'), width: '200px', sortable: true },
                { property: 'name', label: this.$tc('ppobase-supplier.products.columnProductName'), primary: true, allowResize: true },
                { property: 'stock', label: this.$tc('ppobase-supplier.products.columnStock'), width: '100px', align: 'right' }
            ];
        },

        availableProductColumns() {
            return [
                { property: 'productNumber', label: this.$tc('ppobase-supplier.products.columnProductNumber'), width: '200px' },
                { property: 'displayName', label: this.$tc('ppobase-supplier.products.columnProductName'), primary: true },
                { property: 'stock', label: this.$tc('ppobase-supplier.products.columnStock'), width: '100px', align: 'right' }
            ];
        },

        mappedProductIds() {
            if (!this.mappedProducts || this.mappedProducts.length === 0) return [];
            return this.mappedProducts.map(item => item.productId);
        },

        // Incoterms options
        incotermsOptions() {
            return [
                { value: 'EXW', label: 'EXW - Ex Works' },
                { value: 'FCA', label: 'FCA - Free Carrier' },
                { value: 'CPT', label: 'CPT - Carriage Paid To' },
                { value: 'CIP', label: 'CIP - Carriage and Insurance Paid To' },
                { value: 'DAP', label: 'DAP - Delivered at Place' },
                { value: 'DPU', label: 'DPU - Delivered at Place Unloaded' },
                { value: 'DDP', label: 'DDP - Delivered Duty Paid' },
                { value: 'FAS', label: 'FAS - Free Alongside Ship' },
                { value: 'FOB', label: 'FOB - Free on Board' },
                { value: 'CFR', label: 'CFR - Cost and Freight' },
                { value: 'CIF', label: 'CIF - Cost, Insurance and Freight' }
            ];
        },

        deliveryDaysOptions() {
            return [
                { value: 'Mon', label: 'Monday' },
                { value: 'Tue', label: 'Tuesday' },
                { value: 'Wed', label: 'Wednesday' },
                { value: 'Thu', label: 'Thursday' },
                { value: 'Fri', label: 'Friday' },
                { value: 'Sat', label: 'Saturday' },
                { value: 'Sun', label: 'Sunday' }
            ];
        },

        selectedDeliveryDays: {
            get() {
                if (!this.supplier || !this.supplier.deliveryDays) return [];
                return this.supplier.deliveryDays.split(',');
            },
            set(value) {
                if (this.supplier) {
                    this.supplier.deliveryDays = value.join(',');
                    this.hasChanges = true;
                }
            }
        },

        ...mapPropertyErrors('supplier', ['companyTradeName', 'contactEmail', 'generalEmail'])
    },

    watch: {
        supplierId() { this.loadSupplier(); },
        activeTab(newTab) {
            if (newTab === 'products' && this.mappedProducts.length === 0) {
                this.loadMappedProducts();
            }
        }
    },

    created() {
        this.loadSupplier();
        this.loadCountries();
        this.loadCurrencies();
    },

    beforeRouteLeave(to, from, next) {
        if (this.hasChanges) {
            this.unsavedChangesAction = next;
            this.showUnsavedChangesModal = true;
            return;
        }
        next();
    },

    methods: {
        async loadSupplier() {
            this.isLoading = true;
            try {
                const criteria = new Criteria();
                this.supplier = await this.supplierRepository.get(this.supplierId, Shopware.Context.api, criteria);

                if (!this.supplier) {
                    this.createNotificationError({
                        title: this.$tc('ppobase-supplier.detail.errorTitle'),
                        message: this.$tc('ppobase-supplier.detail.notFoundMessage')
                    });
                    this.$router.push({ name: 'ppobase.supplier.list' });
                }
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-supplier.detail.errorTitle'), message: error.message });
            } finally {
                this.isLoading = false;
            }
        },

        async loadCountries() {
            try {
                const criteria = new Criteria();
                criteria.addSorting(Criteria.sort('name', 'ASC'));
                criteria.setLimit(500);
                const result = await this.countryRepository.search(criteria, Shopware.Context.api);
                this.countries = result;
            } catch (e) {
                // fallback - no countries loaded
            }
        },

        async loadCurrencies() {
            try {
                const criteria = new Criteria();
                criteria.addSorting(Criteria.sort('name', 'ASC'));
                criteria.setLimit(100);
                const result = await this.currencyRepository.search(criteria, Shopware.Context.api);
                this.currencies = result;
            } catch (e) {
                // fallback
            }
        },

        async loadMappedProducts() {
            this.isLoadingProducts = true;
            try {
                const criteria = new Criteria();
                criteria.addFilter(Criteria.equals('supplierId', this.supplierId));
                criteria.addAssociation('product');
                criteria.addAssociation('product.cover');
                criteria.addAssociation('product.options.group');

                const result = await this.supplierProductRepository.search(criteria, Shopware.Context.api);
                const products = result.map(sp => sp.product).filter(product => product);
                const parents = await this.loadParentProducts(products);
                // Map to flat list sorted by SKU
                this.mappedProducts = result.map(sp => ({
                    ...sp,
                    name: sp.product ? this.getProductDisplayName(sp.product, parents) : 'Unknown',
                    productNumber: sp.product ? sp.product.productNumber : '',
                    stock: sp.product ? sp.product.stock : 0
                }));
                // Sort by productNumber (SKU)
                this.mappedProducts.sort((a, b) => (a.productNumber || '').localeCompare(b.productNumber || ''));
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-supplier.products.errorTitle'), message: error.message });
            } finally {
                this.isLoadingProducts = false;
            }
        },

        async onSave() {
            this.isSaving = true;
            try {
                this.supplier.updatedBy = 'admin';
                await this.supplierRepository.save(this.supplier, Shopware.Context.api);
                this.createNotificationSuccess({
                    title: this.$tc('ppobase-supplier.detail.saveSuccessTitle'),
                    message: this.$tc('ppobase-supplier.detail.saveSuccessMessage')
                });
                this.hasChanges = false;
                await this.loadSupplier();
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-supplier.detail.saveErrorTitle'), message: error.message });
            } finally {
                this.isSaving = false;
            }
        },

        onCancel() {
            if (this.hasChanges) {
                this.unsavedChangesAction = () => {
                    this.$router.push({ name: 'ppobase.supplier.list' });
                };
                this.showUnsavedChangesModal = true;
                return;
            }
            this.$router.push({ name: 'ppobase.supplier.list' });
        },

        onFormChange() {
            this.hasChanges = true;
        },

        onTextareaChange(field, value) {
            if (this.supplier) {
                this.supplier[field] = value;
                this.hasChanges = true;
            }
        },

        /**
         * Robust tab change handler.
         * sw-tabs @new-item-active may emit:
         *   - a string (tab name)
         *   - an object with a .name property
         *   - a DOM element or component reference
         * This handler covers all cases.
         */
        onChangeTab(item) {
            if (typeof item === 'string') {
                this.activeTab = item;
            } else if (item && typeof item === 'object' && item.name) {
                this.activeTab = item.name;
            }
        },

        setActiveTab(tabName) {
            this.activeTab = tabName;
        },

        // Unsaved changes modal handlers
        async onSaveAndLeave() {
            this.showUnsavedChangesModal = false;
            await this.onSave();
            if (this.unsavedChangesAction) {
                if (typeof this.unsavedChangesAction === 'function') {
                    this.unsavedChangesAction();
                }
            }
            this.unsavedChangesAction = null;
        },

        onDiscardAndLeave() {
            this.showUnsavedChangesModal = false;
            this.hasChanges = false;
            if (this.unsavedChangesAction) {
                if (typeof this.unsavedChangesAction === 'function') {
                    this.unsavedChangesAction();
                }
            }
            this.unsavedChangesAction = null;
        },

        onStay() {
            this.showUnsavedChangesModal = false;
            this.unsavedChangesAction = null;
        },

        // Products tab methods
        openProductModal() {
            this.selectedProducts = [];
            this.productSearchTerm = '';
            this.productPage = 1;
            this.showProductModal = true;
            this.loadAvailableProducts();
        },

        closeProductModal() {
            this.showProductModal = false;
            this.selectedProducts = [];
            this.availableProducts = null;
        },

        async loadAvailableProducts() {
            this.isLoadingAvailable = true;
            try {
                const criteria = new Criteria(this.productPage, this.productLimit);
                criteria.addAssociation('cover');
                criteria.addAssociation('options.group');
                this.addAssignableProductFilters(criteria);

                if (this.mappedProductIds.length > 0) {
                    criteria.addFilter(Criteria.not('AND', [Criteria.equalsAny('id', this.mappedProductIds)]));
                }
                if (this.productSearchTerm) {
                    criteria.setTerm(this.productSearchTerm);
                }
                criteria.addSorting(Criteria.sort('productNumber', 'ASC'));

                const result = await this.productRepository.search(criteria, Shopware.Context.api);
                const products = [];
                for (const product of result) {
                    products.push(product);
                }
                const parents = await this.loadParentProducts(products);
                products.forEach(product => {
                    product.displayName = this.getProductDisplayName(product, parents);
                });
                this.availableProducts = products;
                this.productTotal = result.total;
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-supplier.products.errorTitle'), message: error.message });
            } finally {
                this.isLoadingAvailable = false;
            }
        },

        addAssignableProductFilters(criteria) {
            criteria.addFilter(Criteria.multi('OR', [
                Criteria.not('AND', [Criteria.equals('parentId', null)]),
                Criteria.equals('childCount', 0),
                Criteria.equals('childCount', null),
            ]));
        },

        async loadParentProducts(products) {
            const parentIds = [];
            products.forEach(product => {
                if (product.parentId && parentIds.indexOf(product.parentId) === -1) {
                    parentIds.push(product.parentId);
                }
            });

            if (!parentIds.length) {
                return {};
            }

            const criteria = new Criteria(1, parentIds.length);
            criteria.addFilter(Criteria.equalsAny('id', parentIds));
            const result = await this.productRepository.search(criteria, Shopware.Context.api);
            const parents = {};
            for (const parent of result) {
                parents[parent.id] = parent;
            }

            return parents;
        },

        getProductDisplayName(product, parents = {}) {
            const translated = product.translated || {};
            const parent = product.parentId && parents ? parents[product.parentId] || {} : {};
            const parentTranslated = parent.translated || {};
            let name = translated.name
                || product.name
                || parentTranslated.name
                || parent.name
                || product.productNumber;

            if (product.parentId && name === product.productNumber) {
                name = parentTranslated.name || parent.name || name;
            }

            if (product.parentId && product.options && product.options.length) {
                const optionNames = [];
                product.options.forEach(option => {
                    const optionTranslated = option.translated || {};
                    const optionName = optionTranslated.name || option.name;
                    if (optionName) {
                        optionNames.push(optionName);
                    }
                });

                if (optionNames.length) {
                    name += ` (${optionNames.join(', ')})`;
                }
            }

            return name;
        },

        onProductSelectionChange(selection) {
            this.selectedProducts = Object.values(selection);
        },

        onProductSearch(searchTerm) {
            this.productSearchTerm = searchTerm;
            this.productPage = 1;
            this.loadAvailableProducts();
        },

        onProductPageChange({ page, limit }) {
            this.productPage = page;
            this.productLimit = limit;
            this.loadAvailableProducts();
        },

        async addSelectedProducts() {
            if (this.selectedProducts.length === 0) return;
            this.isSavingProducts = true;
            try {
                for (const product of this.selectedProducts) {
                    const mapping = this.supplierProductRepository.create(Shopware.Context.api);
                    mapping.supplierId = this.supplierId;
                    mapping.productId = product.id;
                    await this.supplierProductRepository.save(mapping, Shopware.Context.api);
                }
                this.createNotificationSuccess({
                    title: this.$tc('ppobase-supplier.products.successTitle'),
                    message: this.$tc('ppobase-supplier.products.productsAddedMessage')
                });
                this.closeProductModal();
                this.loadMappedProducts();
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-supplier.products.errorTitle'), message: error.message });
            } finally {
                this.isSavingProducts = false;
            }
        },

        async removeProduct(item) {
            this.isLoadingProducts = true;
            try {
                await this.supplierProductRepository.delete(item.id, Shopware.Context.api);
                this.createNotificationSuccess({
                    title: this.$tc('ppobase-supplier.products.successTitle'),
                    message: this.$tc('ppobase-supplier.products.productRemovedMessage')
                });
                this.loadMappedProducts();
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-supplier.products.errorTitle'), message: error.message });
            } finally {
                this.isLoadingProducts = false;
            }
        },

        formatDate(dateString) {
            if (!dateString) return '-';
            return new Date(dateString).toLocaleDateString();
        },

        formatCurrency(value) {
            if (!value) return '-';
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: this.supplier?.currency || 'EUR' }).format(value);
        }
    }
});
