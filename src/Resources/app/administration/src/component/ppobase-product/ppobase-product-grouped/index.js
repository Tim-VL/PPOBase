import template from './ppobase-product-grouped.html.twig';
import './ppobase-product-grouped.scss';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin     = Shopware.Mixin;
var Criteria          = Shopware.Data.Criteria;

ShopwareComponent.register('ppobase-product-grouped', {
    template: template,

    inject: ['ppobaseApiService', 'repositoryFactory'],

    mixins: [ShopwareMixin.getByName('notification')],

    data: function () {
        return {
            isGroupedProduct: false,
            groupedItems: [],
            isLoading: false,
            isSavingQuantities: false,

            // Bulk modal state
            showBulkModal: false,
            bulkProducts: [],
            bulkTotal: 0,
            bulkPage: 1,
            bulkLimit: 25,
            bulkSearchTerm: '',
            bulkIsLoading: false,
            bulkSelectedProducts: [],

            // Single-add modal state
            showAddSingleModal: false,
            singleSelectedProductId: null,
        };
    },

    computed: {
        productId: function () {
            return this.$route.params.id;
        },

        productRepository: function () {
            return this.repositoryFactory.create('product');
        },

        columns: function () {
            return [
                { property: 'coverUrl',      label: this.$tc('ppobase-product.grouped.columns.image'),         width: '60px' },
                { property: 'productName',   label: this.$tc('ppobase-product.grouped.columns.name') },
                { property: 'productNumber', label: this.$tc('ppobase-product.grouped.columns.productNumber') },
                { property: 'quantity',      label: this.$tc('ppobase-product.grouped.columns.quantity'),       width: '120px' },
                { property: 'price',         label: this.$tc('ppobase-product.grouped.columns.price'),          width: '110px', align: 'right' },
            ];
        },

        grandTotal: function () {
            return this.groupedItems.reduce(function (sum, item) {
                var price = parseFloat(item.price);
                var qty   = parseInt(item.quantity, 10) || 1;
                return sum + (isNaN(price) ? 0 : price * qty);
            }, 0);
        },

        bulkColumns: function () {
            return [
                { property: 'productNumber', label: this.$tc('ppobase-product.grouped.columns.productNumber'), width: '180px' },
                { property: 'displayName',   label: this.$tc('ppobase-product.grouped.columns.name') },
            ];
        },

        singleProductCriteria: function () {
            var criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('productNumber', 'ASC'));
            criteria.addAssociation('options.group');
            this.addAssignableProductFilters(criteria);

            var excludeIds = this.getExcludedProductIds();
            if (excludeIds.length > 0) {
                criteria.addFilter(Criteria.not('AND', [Criteria.equalsAny('id', excludeIds)]));
            }

            return criteria;
        },

        hasGroupedItemChanges: function () {
            return this.groupedItems.some(function (item) {
                return item.isDirty;
            });
        },
    },

    watch: {
        productId: function () {
            this.loadGroupedItems();
        },
    },

    created: function () {
        this.loadGroupedItems();
    },

    methods: {
        formatPrice: function (value) {
            var num = parseFloat(value);
            if (isNaN(num)) {
                return '-';
            }

            var isoCode = (Shopware.Context && Shopware.Context.app && Shopware.Context.app.systemCurrencyIsoCode)
                ? Shopware.Context.app.systemCurrencyIsoCode
                : 'EUR';

            try {
                return new Intl.NumberFormat(undefined, {
                    style: 'currency',
                    currency: isoCode,
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }).format(num);
            } catch (e) {
                return num.toFixed(2);
            }
        },

        /** Load existing children from the API. */
        loadGroupedItems: async function () {
            this.isLoading = true;
            try {
                var result = await this.ppobaseApiService.getGroupedProducts(this.productId);
                if (result && result.success) {
                    this.groupedItems     = result.data || [];
                    this.isGroupedProduct = result.active !== undefined
                        ? result.active
                        : this.groupedItems.length > 0;
                }
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-product.grouped.cardTitle'), message: error.message });
            } finally {
                this.isLoading = false;
            }
        },

        saveItem: async function (item) {
            try {
                await this.ppobaseApiService.updateGroupedProduct(item.id, { quantity: item.quantity, position: item.position });
                item.isDirty = false;
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-product.grouped.cardTitle'), message: error.message });
            }
        },

        onQuantityChange: function (item, value) {
            item.quantity = Math.max(1, parseInt(value, 10) || 1);
            item.isDirty = true;
        },

        saveChangedItems: async function () {
            var dirtyItems = this.groupedItems.filter(function (item) {
                return item.isDirty;
            });

            if (!dirtyItems.length) {
                return;
            }

            this.isSavingQuantities = true;
            try {
                for (var item of dirtyItems) {
                    await this.ppobaseApiService.updateGroupedProduct(item.id, {
                        quantity: Math.max(1, parseInt(item.quantity, 10) || 1),
                        position: item.position,
                    });
                    item.isDirty = false;
                }

                this.createNotificationSuccess({
                    title: this.$tc('ppobase-product.grouped.saveSuccessTitle'),
                    message: this.$tc('ppobase-product.grouped.saveSuccessMessage'),
                });
                await this.loadGroupedItems();
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-product.grouped.cardTitle'), message: error.message });
            } finally {
                this.isSavingQuantities = false;
            }
        },

        removeItem: async function (id) {
            try {
                await this.ppobaseApiService.removeGroupedProduct(id);
                await this.loadGroupedItems();
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-product.grouped.cardTitle'), message: error.message });
            }
        },

        onToggleGrouped: async function (enabled) {
            try {
                await this.ppobaseApiService.toggleGroupedProductActive(this.productId, enabled);
            } catch (error) {
                this.isGroupedProduct = !enabled;
                this.createNotificationError({ title: this.$tc('ppobase-product.grouped.cardTitle'), message: error.message });
            }
        },

        getExcludedProductIds: function () {
            var existingChildIds = this.groupedItems.map(function (i) { return i.childProductId; });
            return existingChildIds.concat([this.productId]);
        },

        addAssignableProductFilters: function (criteria) {
            criteria.addFilter(Criteria.multi('OR', [
                Criteria.not('AND', [Criteria.equals('parentId', null)]),
                Criteria.equals('childCount', 0),
                Criteria.equals('childCount', null),
            ]));
        },

        loadParentProducts: async function (products) {
            var parentIds = [];
            products.forEach(function (product) {
                if (product.parentId && parentIds.indexOf(product.parentId) === -1) {
                    parentIds.push(product.parentId);
                }
            });

            if (!parentIds.length) {
                return {};
            }

            var criteria = new Criteria(1, parentIds.length);
            criteria.addFilter(Criteria.equalsAny('id', parentIds));
            var result = await this.productRepository.search(criteria, Shopware.Context.api);
            var parents = {};
            for (var parent of result) {
                parents[parent.id] = parent;
            }

            return parents;
        },

        getProductDisplayName: function (product, parents) {
            var translated = product.translated || {};
            var parent = product.parentId && parents ? parents[product.parentId] || {} : {};
            var parentTranslated = parent.translated || {};
            var name = translated.name
                || product.name
                || parentTranslated.name
                || parent.name
                || product.productNumber;

            if (product.parentId && name === product.productNumber) {
                name = parentTranslated.name || parent.name || name;
            }

            if (product.parentId && product.options && product.options.length) {
                var optionNames = [];
                product.options.forEach(function (option) {
                    var optionTranslated = option.translated || {};
                    var optionName = optionTranslated.name || option.name;
                    if (optionName) {
                        optionNames.push(optionName);
                    }
                });

                if (optionNames.length) {
                    name += ' (' + optionNames.join(', ') + ')';
                }
            }

            return name;
        },

        // ── Bulk modal ────────────────────────────────────────────────────────

        onBulkAssign: function () {
            this.bulkSelectedProducts = [];
            this.bulkSearchTerm       = '';
            this.bulkPage             = 1;
            this.showBulkModal        = true;
            this.loadBulkProducts();
        },

        /** Load products for the bulk modal, skipping ones already assigned. */
        loadBulkProducts: async function () {
            this.bulkIsLoading = true;
            try {
                var criteria = new Criteria(this.bulkPage, this.bulkLimit);
                criteria.addSorting(Criteria.sort('productNumber', 'ASC'));
                criteria.addAssociation('options.group');
                this.addAssignableProductFilters(criteria);

                // Exclude current product and already-assigned children
                var excludeIds = this.getExcludedProductIds();
                if (excludeIds.length > 0) {
                    criteria.addFilter(Criteria.not('AND', [Criteria.equalsAny('id', excludeIds)]));
                }

                // Use setTerm for Shopware full-text search (handles name + productNumber)
                if (this.bulkSearchTerm) {
                    criteria.setTerm(this.bulkSearchTerm);
                }

                var result = await this.productRepository.search(criteria, Shopware.Context.api);
                var products = [];
                for (var p of result) {
                    products.push(p);
                }
                var parents = await this.loadParentProducts(products);
                for (var p of products) {
                    p.displayName = this.getProductDisplayName(p, parents);
                }
                this.bulkProducts = products;
                this.bulkTotal    = result.total || 0;
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-product.grouped.cardTitle'), message: error.message });
            } finally {
                this.bulkIsLoading = false;
            }
        },

        onBulkSearch: function (term) {
            this.bulkSearchTerm = term;
            this.bulkPage       = 1;
            this.loadBulkProducts();
        },

        onBulkPageChange: function (event) {
            this.bulkPage  = event.page;
            this.bulkLimit = event.limit;
            this.loadBulkProducts();
        },

        onBulkSelectionChange: function (selection) {
            this.bulkSelectedProducts = Object.values(selection || {});
        },

        onBulkConfirm: async function () {
            if (!this.bulkSelectedProducts.length) return;

            var existingLength = this.groupedItems.length;
            var items = this.bulkSelectedProducts.map(function (product, index) {
                return { childProductId: product.id, quantity: 1, position: existingLength + index };
            });

            try {
                var result = await this.ppobaseApiService.assignGroupedProducts(this.productId, items);
                if (result && result.success) {
                    this.groupedItems     = result.data || [];
                    this.isGroupedProduct = this.groupedItems.length > 0;
                }
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-product.grouped.cardTitle'), message: error.message });
            } finally {
                this.showBulkModal        = false;
                this.bulkSelectedProducts = [];
            }
        },

        // ── Single-add modal ─────────────────────────────────────────────────

        onAddSingle: function () {
            this.singleSelectedProductId = null;
            this.showAddSingleModal      = true;
        },

        onAddSingleConfirm: async function () {
            if (!this.singleSelectedProductId) return;

            var items = [{ childProductId: this.singleSelectedProductId, quantity: 1, position: this.groupedItems.length }];

            try {
                var result = await this.ppobaseApiService.assignGroupedProducts(this.productId, items);
                if (result && result.success) {
                    this.groupedItems     = result.data || [];
                    this.isGroupedProduct = this.groupedItems.length > 0;
                }
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-product.grouped.cardTitle'), message: error.message });
            } finally {
                this.showAddSingleModal      = false;
                this.singleSelectedProductId = null;
            }
        },
    },
});
