/**
 * Supplier Products Mapping Page - Sorted by SKU, cleaned up
 */

import template from './ppobase-supplier-products.html.twig';
import './ppobase-supplier-products.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('ppobase-supplier-products', {
    template,

    inject: ['repositoryFactory'],

    mixins: [Mixin.getByName('notification')],

    props: {
        supplierId: { type: String, required: true }
    },

    data() {
        return {
            supplier: null,
            mappedProducts: [],
            availableProducts: null,
            isLoading: false,
            isLoadingProducts: false,
            isSaving: false,
            showProductModal: false,
            selectedProducts: [],
            productSearchTerm: '',
            productPage: 1,
            productLimit: 25,
            productTotal: 0
        };
    },

    computed: {
        supplierRepository() { return this.repositoryFactory.create('ppobase_supplier'); },
        productRepository() { return this.repositoryFactory.create('product'); },
        supplierProductRepository() { return this.repositoryFactory.create('ppobase_supplier_product'); },

        productColumns() {
            return [
                { property: 'productNumber', label: this.$tc('ppobase-supplier.products.columnProductNumber'), width: '200px', sortable: true },
                { property: 'displayName', label: this.$tc('ppobase-supplier.products.columnProductName'), primary: true, allowResize: true },
                { property: 'stock', label: this.$tc('ppobase-supplier.products.columnStock'), width: '100px', align: 'right' }
            ];
        },

        mappedProductIds() {
            if (!this.mappedProducts || this.mappedProducts.length === 0) return [];
            return this.mappedProducts.map(item => item.productId);
        }
    },

    created() {
        this.loadSupplier();
        this.loadMappedProducts();
    },

    methods: {
        async loadSupplier() {
            this.isLoading = true;
            try {
                this.supplier = await this.supplierRepository.get(this.supplierId, Shopware.Context.api);
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-supplier.products.errorTitle'), message: error.message });
            } finally {
                this.isLoading = false;
            }
        },

        async loadMappedProducts() {
            this.isLoading = true;
            try {
                const criteria = new Criteria();
                criteria.addFilter(Criteria.equals('supplierId', this.supplierId));
                criteria.addAssociation('product');
                criteria.addAssociation('product.cover');
                criteria.addAssociation('product.options.group');

                const result = await this.supplierProductRepository.search(criteria, Shopware.Context.api);
                const products = result.map(sp => sp.product).filter(product => product);
                const parents = await this.loadParentProducts(products);

                // Convert to sortable array with product info, sorted by SKU
                const mapped = [];
                for (const sp of result) {
                    mapped.push({
                        id: sp.id,
                        productId: sp.productId,
                        supplierId: sp.supplierId,
                        displayName: sp.product ? this.getProductDisplayName(sp.product, parents) : 'Unknown',
                        productNumber: sp.product ? sp.product.productNumber : '',
                        stock: sp.product ? sp.product.stock : 0
                    });
                }
                mapped.sort((a, b) => (a.productNumber || '').localeCompare(b.productNumber || ''));
                this.mappedProducts = mapped;
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-supplier.products.errorTitle'), message: error.message });
            } finally {
                this.isLoading = false;
            }
        },

        async loadAvailableProducts() {
            this.isLoadingProducts = true;
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
                this.isLoadingProducts = false;
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

        onProductSelectionChange(selection) { this.selectedProducts = Object.values(selection); },

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
            this.isSaving = true;
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
                this.isSaving = false;
            }
        },

        async removeProduct(item) {
            this.isLoading = true;
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
                this.isLoading = false;
            }
        },

        onCancel() { this.$router.push({ name: 'ppobase.supplier.list' }); }
    }
});
