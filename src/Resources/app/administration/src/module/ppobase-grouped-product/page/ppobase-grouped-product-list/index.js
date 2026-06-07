import template from './ppobase-grouped-product-list.html.twig';
import './ppobase-grouped-product-list.scss';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin     = Shopware.Mixin;

ShopwareComponent.register('ppobase-grouped-product-list', {
    template: template,

    inject: ['ppobaseApiService', 'repositoryFactory'],

    mixins: [ShopwareMixin.getByName('notification')],

    data: function () {
        return {
            isLoading: false,
            groupedParentIds: [],
            groupedActiveMap: {},
            products: [],
            total: 0,
            page: 1,
            limit: 25,
            searchTerm: '',

            // Selected parent product for inline child management
            selectedParentId: null,
            selectedParentName: '',
            childItems: [],
            childIsLoading: false,
            childIsSaving: false,

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
        productRepository: function () {
            return this.repositoryFactory.create('product');
        },

        productCriteria: function () {
            var Criteria = Shopware.Data.Criteria;
            var criteria = new Criteria(this.page, this.limit);

            if (this.groupedParentIds.length) {
                criteria.addFilter(Criteria.equalsAny('id', this.groupedParentIds));
            } else {
                criteria.addFilter(Criteria.equals('id', null));
            }

            if (this.searchTerm) {
                criteria.addFilter(Criteria.multi('OR', [
                    Criteria.contains('name', this.searchTerm),
                    Criteria.contains('productNumber', this.searchTerm),
                ]));
            }

            criteria.addSorting(Criteria.sort('name', 'ASC'));
            criteria.addAssociation('options.group');
            return criteria;
        },

        columns: function () {
            return [
                { property: 'name',          label: this.$tc('ppobase-grouped-product.list.columnName') },
                { property: 'productNumber', label: this.$tc('ppobase-grouped-product.list.columnNumber') },
                { property: 'active',        label: this.$tc('ppobase-grouped-product.list.columnActive'), width: '80px' },
            ];
        },

        childColumns: function () {
            return [
                { property: 'coverUrl',      label: this.$tc('ppobase-product.grouped.columns.image'),         width: '60px' },
                { property: 'productName',   label: this.$tc('ppobase-product.grouped.columns.name') },
                { property: 'productNumber', label: this.$tc('ppobase-product.grouped.columns.productNumber') },
                { property: 'quantity',      label: this.$tc('ppobase-product.grouped.columns.quantity'),       width: '120px' },
                { property: 'price',         label: this.$tc('ppobase-product.grouped.columns.price'),          width: '110px', align: 'right' },
            ];
        },

        childGrandTotal: function () {
            return this.childItems.reduce(function (sum, item) {
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
            var Criteria = Shopware.Data.Criteria;
            var criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('productNumber', 'ASC'));
            criteria.addAssociation('options.group');
            this.addAssignableProductFilters(criteria);

            var excludeIds = this.getExcludedChildIds();
            if (excludeIds.length > 0) {
                criteria.addFilter(Criteria.not('AND', [Criteria.equalsAny('id', excludeIds)]));
            }

            return criteria;
        },

        hasChildItemChanges: function () {
            return this.childItems.some(function (item) {
                return item.isDirty;
            });
        },
    },

    created: function () {
        this.loadParentIds();
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

        loadParentIds: async function () {
            this.isLoading = true;
            try {
                var result = await this.ppobaseApiService.getGroupedProductParents();
                if (result && result.success) {
                    var parentData = result.data || [];
                    var activeMap = {};
                    this.groupedParentIds = parentData.map(function (item) {
                        if (item && typeof item === 'object') {
                            activeMap[item.id] = item.isGroupedActive !== false;
                            return item.id;
                        }
                        return item;
                    });
                    this.groupedActiveMap = activeMap;
                }
                await this.loadProducts();
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-grouped-product.list.errorTitle'),
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
            }
        },

        loadProducts: async function () {
            var result = await this.productRepository.search(this.productCriteria, Shopware.Context.api);
            var products = [];
            for (var p of result) {
                products.push(p);
            }
            var parents = await this.loadParentProducts(products);
            var activeMap = this.groupedActiveMap;
            for (var p of products) {
                p.displayName   = this.getProductDisplayName(p, parents);
                p.displayActive = activeMap.hasOwnProperty(p.id) ? activeMap[p.id] : true;
            }
            this.products = products;
            this.total    = result.total || 0;
        },

        onSearch: function (value) {
            this.searchTerm = value;
            this.page       = 1;
            this.loadProducts();
        },

        onPageChange: function (event) {
            this.page  = event.page;
            this.limit = event.limit;
            this.loadProducts();
        },

        onEditProduct: function (id) {
            this.$router.push({
                name: 'sw.product.detail.ppobaseGrouped',
                params: { id: id },
            });
        },

        // ── Child management for a selected parent ───────────────────────────

        onManageChildren: async function (product) {
            this.selectedParentId   = product.id;
            var translated = product.translated || {};
            this.selectedParentName = translated.name || product.name || product.productNumber;
            await this.loadChildItems();
        },

        loadChildItems: async function () {
            this.childIsLoading = true;
            try {
                var result = await this.ppobaseApiService.getGroupedProducts(this.selectedParentId);
                if (result && result.success) {
                    this.childItems = result.data || [];
                }
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-grouped-product.list.errorTitle'),
                    message: error.message,
                });
            } finally {
                this.childIsLoading = false;
            }
        },

        onCloseChildren: function () {
            this.selectedParentId   = null;
            this.selectedParentName = '';
            this.childItems         = [];
        },

        getExcludedChildIds: function () {
            var existingChildIds = this.childItems.map(function (i) { return i.childProductId; });
            return existingChildIds.concat([this.selectedParentId]);
        },

        addAssignableProductFilters: function (criteria) {
            var Criteria = Shopware.Data.Criteria;
            criteria.addFilter(Criteria.multi('OR', [
                Criteria.not('AND', [Criteria.equals('parentId', null)]),
                Criteria.equals('childCount', 0),
                Criteria.equals('childCount', null),
            ]));
        },

        loadParentProducts: async function (products) {
            var Criteria = Shopware.Data.Criteria;
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

        getProductActive: function (product, parents) {
            if (product.active !== null && product.active !== undefined) {
                return product.active;
            }

            var parent = product.parentId && parents ? parents[product.parentId] || {} : {};
            return !!parent.active;
        },

        saveChildItem: async function (item) {
            try {
                await this.ppobaseApiService.updateGroupedProduct(item.id, { quantity: item.quantity, position: item.position });
                item.isDirty = false;
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-grouped-product.list.errorTitle'),
                    message: error.message,
                });
            }
        },

        onChildQuantityChange: function (item, value) {
            item.quantity = Math.max(1, parseInt(value, 10) || 1);
            item.isDirty = true;
        },

        saveChangedChildItems: async function () {
            var dirtyItems = this.childItems.filter(function (item) {
                return item.isDirty;
            });

            if (!dirtyItems.length) {
                return;
            }

            this.childIsSaving = true;
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
                await this.loadChildItems();
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-grouped-product.list.errorTitle'),
                    message: error.message,
                });
            } finally {
                this.childIsSaving = false;
            }
        },

        removeChildItem: async function (id) {
            try {
                await this.ppobaseApiService.removeGroupedProduct(id);
                await this.loadChildItems();
                // Refresh parent list in case this was the last child
                await this.loadParentIds();
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-grouped-product.list.errorTitle'),
                    message: error.message,
                });
            }
        },

        // ── Bulk modal ────────────────────────────────────────────────────────

        onBulkAssign: function () {
            this.bulkSelectedProducts = [];
            this.bulkSearchTerm       = '';
            this.bulkPage             = 1;
            this.showBulkModal        = true;
            this.loadBulkProducts();
        },

        loadBulkProducts: async function () {
            this.bulkIsLoading = true;
            try {
                var Criteria = Shopware.Data.Criteria;
                var excludeIds = this.getExcludedChildIds();

                var criteria = new Criteria(this.bulkPage, this.bulkLimit);
                criteria.addSorting(Criteria.sort('productNumber', 'ASC'));
                criteria.addAssociation('options.group');
                this.addAssignableProductFilters(criteria);

                if (excludeIds.length > 0) {
                    criteria.addFilter(Criteria.not('AND', [Criteria.equalsAny('id', excludeIds)]));
                }

                // Use setTerm for Shopware full-text search
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
                    p.displayActive = this.getProductActive(p, parents);
                }
                this.bulkProducts = products;
                this.bulkTotal    = result.total || 0;
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-grouped-product.list.errorTitle'),
                    message: error.message,
                });
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

            var existingLength = this.childItems.length;
            var items = this.bulkSelectedProducts.map(function (product, index) {
                return { childProductId: product.id, quantity: 1, position: existingLength + index };
            });

            try {
                var result = await this.ppobaseApiService.assignGroupedProducts(this.selectedParentId, items);
                if (result && result.success) {
                    this.childItems = result.data || [];
                    await this.loadParentIds();
                }
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-grouped-product.list.errorTitle'),
                    message: error.message,
                });
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

            var items = [{ childProductId: this.singleSelectedProductId, quantity: 1, position: this.childItems.length }];

            try {
                var result = await this.ppobaseApiService.assignGroupedProducts(this.selectedParentId, items);
                if (result && result.success) {
                    this.childItems = result.data || [];
                    await this.loadParentIds();
                }
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-grouped-product.list.errorTitle'),
                    message: error.message,
                });
            } finally {
                this.showAddSingleModal      = false;
                this.singleSelectedProductId = null;
            }
        },
    },
});
