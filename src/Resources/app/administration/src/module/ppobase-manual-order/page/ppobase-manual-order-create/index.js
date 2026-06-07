import template from './ppobase-manual-order-create.html.twig';
import './ppobase-manual-order-create.scss';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin = Shopware.Mixin;

var ShopwareCriteria = Shopware.Data.Criteria;

ShopwareComponent.register('ppobase-manual-order-create', {
    template: template,

    inject: ['ppobaseApiService', 'repositoryFactory'],

    mixins: [ShopwareMixin.getByName('notification')],

    data: function() {
        return {
            isLoading: false,
            isCreating: false,

            // Sales Channel
            salesChannelId: null,
            salesChannels: [],

            // Customer
            selectedCustomer: null,
            customerSearchTerm: '',
            customerSearchResults: [],
            customerSearchLoading: false,
            showCustomerResults: false,
            showNewCustomerForm: false,
            newCustomerValidationActive: false,
            newCustomer: {
                salutationId: null,
                firstName: '',
                lastName: '',
                email: '',
                phone: '',
                company: '',
                street: '',
                zipcode: '',
                city: '',
                countryId: null
            },
            savingCustomer: false,

            // Shipping
            shippingMethodId: null,
            shippingMethods: [],
            shippingDate: null,
            shippingCosts: 0,
            shippingAddressId: null,
            customerAddresses: [],
            showNewAddressForm: false,
            newAddressValidationActive: false,
            newAddress: {
                salutationId: null,
                firstName: '',
                lastName: '',
                street: '',
                additionalAddressLine1: '',
                zipcode: '',
                city: '',
                countryId: null,
                phone: '',
                company: ''
            },
            savingAddress: false,

            // Payment
            paymentMethodId: null,
            paymentMethods: [],

            // Items
            productSearchTerm: '',
            productSearchResults: [],
            productSearchLoading: false,
            showProductResults: false,
            orderItems: [],
            manualOrderItemCounter: 0,

            // Options
            orderReference: '',
            notes: '',
            sendConfirmation: false,

            // Debounce timers
            customerSearchTimeout: null,
            productSearchTimeout: null
        };
    },

    computed: {
        salesChannelRepository: function() {
            return this.repositoryFactory.create('sales_channel');
        },

        configuredSalesChannelId: function() {
            var state = Shopware.State.get('systemConfig') || {};
            return state['PPOBase.config.defaultSalesChannelId'] || null;
        },

        subtotal: function() {
            var total = 0;
            for (var i = 0; i < this.orderItems.length; i++) {
                total += this.orderItems[i].lineTotal;
            }
            return Math.round(total * 100) / 100;
        },

        taxAmount: function() {
            var total = 0;
            // Tax on line items (extracted from gross price)
            for (var i = 0; i < this.orderItems.length; i++) {
                var item = this.orderItems[i];
                var tax = item.lineTotal * (item.taxRate / (100 + item.taxRate));
                total += tax;
            }
            // Tax on shipping costs (uses first item's tax rate, same as backend)
            var shipping = Number(this.shippingCosts) || 0;
            if (shipping > 0 && this.orderItems.length > 0) {
                var shippingTaxRate = this.orderItems[0].taxRate || 0;
                total += shipping - (shipping / (1 + shippingTaxRate / 100));
            }
            return Math.round(total * 100) / 100;
        },

        grandTotal: function() {
            return Math.round((this.subtotal + (Number(this.shippingCosts) || 0)) * 100) / 100;
        },

        shippingAddressOptions: function() {
            var options = [
                { value: null, label: this.$tc('ppobase-manual-order.create.optionDefaultAddress') }
            ];
            for (var i = 0; i < this.customerAddresses.length; i++) {
                var addr = this.customerAddresses[i];
                var label = addr.street + ', ' + addr.zipcode + ' ' + addr.city;
                if (addr.countryName) {
                    label += ', ' + addr.countryName;
                }
                options.push({ value: addr.id, label: label });
            }
            return options;
        },

        canCreate: function() {
            return this.salesChannelId &&
                   this.selectedCustomer &&
                   this.orderItems.length > 0 &&
                   this.shippingMethodId &&
                   this.paymentMethodId &&
                   !this.isCreating;
        }
    },

    created: function() {
        this.loadSalesChannels();
    },

    beforeUnmount: function() {
        if (this.customerSearchTimeout) clearTimeout(this.customerSearchTimeout);
        if (this.productSearchTimeout) clearTimeout(this.productSearchTimeout);
    },

    methods: {
        // === Sales Channel ===
        loadSalesChannels: async function() {
            var me = this;
            try {
                var criteria = new ShopwareCriteria();
                criteria.addFilter(ShopwareCriteria.equals('active', true));
                criteria.addSorting(ShopwareCriteria.sort('name', 'ASC'));
                var result = await me.salesChannelRepository.search(criteria, Shopware.Context.api);
                me.salesChannels = result.map(function(sc) {
                    return { id: sc.id, name: sc.translated ? (sc.translated.name || sc.name) : sc.name };
                });

                // Auto-select if plugin config has a default sales channel
                var configuredId = me.configuredSalesChannelId;
                if (configuredId && me.salesChannels.some(function(sc) { return sc.id === configuredId; })) {
                    me.onSalesChannelChange(configuredId);
                }
            } catch (error) {
                // Fallback to API service if repository fails
                me.ppobaseApiService.getSalesChannels().then(function(response) {
                    if (response && response.success && response.data) {
                        me.salesChannels = response.data;
                    }
                }).catch(function() {});
            }
        },

        onSalesChannelChange: function(value) {
            this.salesChannelId = value;
            this.shippingMethodId = null;
            this.paymentMethodId = null;
            this.shippingMethods = [];
            this.paymentMethods = [];

            if (value) {
                this.loadShippingMethods(value);
                this.loadPaymentMethods(value);
            }
        },

        loadShippingMethods: function(salesChannelId) {
            var me = this;
            me.ppobaseApiService.getShippingMethods(salesChannelId).then(function(response) {
                if (response.success) {
                    me.shippingMethods = response.data;
                }
            });
        },

        loadPaymentMethods: function(salesChannelId) {
            var me = this;
            me.ppobaseApiService.getPaymentMethods(salesChannelId).then(function(response) {
                if (response.success) {
                    me.paymentMethods = response.data;
                }
            });
        },

        onShippingMethodChange: function(value) {
            this.shippingMethodId = value;
        },

        onShippingAddressChange: function(value) {
            this.shippingAddressId = value;
        },

        onPaymentMethodChange: function(value) {
            this.paymentMethodId = value;
        },

        // === Customer Search ===
        onCustomerSearchInput: function(value) {
            this.customerSearchTerm = value;
            var me = this;

            if (me.customerSearchTimeout) clearTimeout(me.customerSearchTimeout);

            if (!value || value.length < 2) {
                me.customerSearchResults = [];
                me.showCustomerResults = false;
                return;
            }

            me.customerSearchTimeout = setTimeout(function() {
                me.customerSearchLoading = true;
                me.ppobaseApiService.searchOrderCustomers(value).then(function(response) {
                    if (response.success) {
                        me.customerSearchResults = response.data;
                        me.showCustomerResults = true;
                    }
                    me.customerSearchLoading = false;
                }).catch(function() {
                    me.customerSearchLoading = false;
                });
            }, 300);
        },

        onSelectCustomer: function(customer) {
            this.selectedCustomer = customer;
            this.showCustomerResults = false;
            this.customerSearchTerm = '';
            this.customerSearchResults = [];
            this.showNewCustomerForm = false;

            this.loadCustomerAddresses(customer.id);
        },

        onChangeCustomer: function() {
            this.selectedCustomer = null;
            this.customerAddresses = [];
            this.shippingAddressId = null;
        },

        onToggleNewCustomerForm: function() {
            this.showNewCustomerForm = !this.showNewCustomerForm;
            this.showCustomerResults = false;
            if (!this.showNewCustomerForm) {
                this.newCustomerValidationActive = false;
            }
        },

        onSaveNewCustomer: function() {
            var me = this;

            if (!me.salesChannelId) {
                me.createNotificationError({
                    title: me.$tc('ppobase-manual-order.create.validationError'),
                    message: me.$tc('ppobase-manual-order.create.salesChannelRequiredMsg')
                });
                return;
            }

            me.newCustomerValidationActive = true;

            if (!me.newCustomer.salutationId ||
                !me.newCustomer.firstName ||
                !me.newCustomer.lastName ||
                !me.newCustomer.email ||
                !me.newCustomer.street ||
                !me.newCustomer.zipcode ||
                !me.newCustomer.city ||
                !me.newCustomer.countryId
            ) {
                me.createNotificationError({
                    title: me.$tc('ppobase-manual-order.create.validationError'),
                    message: 'Please fill all required customer fields.'
                });
                return;
            }

            me.savingCustomer = true;

            var data = Object.assign({}, me.newCustomer, { salesChannelId: me.salesChannelId });

            me.ppobaseApiService.createInlineCustomer(data).then(function(response) {
                me.savingCustomer = false;
                if (response.success) {
                    me.createNotificationSuccess({
                        title: me.$tc('ppobase-manual-order.create.successTitle'),
                        message: me.$tc('ppobase-manual-order.create.customerCreatedMessage')
                    });

                    var customer = {
                        id: response.customerId,
                        firstName: me.newCustomer.firstName,
                        lastName: me.newCustomer.lastName,
                        email: me.newCustomer.email,
                        customerNumber: '',
                        company: me.newCustomer.company
                    };
                    me.onSelectCustomer(customer);

                    // Reset form
                    me.newCustomerValidationActive = false;
                    me.newCustomer = {
                        salutationId: null,
                        firstName: '',
                        lastName: '',
                        email: '',
                        phone: '',
                        company: '',
                        street: '',
                        zipcode: '',
                        city: '',
                        countryId: null
                    };
                }
            }).catch(function(err) {
                me.savingCustomer = false;
                me.createNotificationError({
                    title: me.$tc('ppobase-manual-order.create.errorTitle'),
                    message: err.response && err.response.data && err.response.data.error ? err.response.data.error : me.$tc('ppobase-manual-order.create.errorMessage')
                });
            });
        },

        loadCustomerAddresses: function(customerId) {
            var me = this;
            me.ppobaseApiService.getCustomerAddresses(customerId).then(function(response) {
                if (response.success) {
                    me.customerAddresses = response.data;
                }
            });
        },

        // === Shipping Address ===
        onToggleNewAddressForm: function() {
            this.showNewAddressForm = !this.showNewAddressForm;
            if (!this.showNewAddressForm) {
                this.newAddressValidationActive = false;
            }
        },

        onCancelNewAddress: function() {
            this.showNewAddressForm = false;
            this.newAddressValidationActive = false;
        },

        onSaveNewAddress: function() {
            var me = this;

            if (!me.selectedCustomer) return;

            me.newAddressValidationActive = true;

            if (!me.newAddress.salutationId ||
                !me.newAddress.firstName ||
                !me.newAddress.lastName ||
                !me.newAddress.street ||
                !me.newAddress.zipcode ||
                !me.newAddress.city ||
                !me.newAddress.countryId
            ) {
                me.createNotificationError({
                    title: me.$tc('ppobase-manual-order.create.validationError'),
                    message: 'Please fill all required address fields.'
                });
                return;
            }

            me.savingAddress = true;

            me.ppobaseApiService.createCustomerAddress(me.selectedCustomer.id, me.newAddress).then(function(response) {
                me.savingAddress = false;
                if (response.success) {
                    me.createNotificationSuccess({
                        title: me.$tc('ppobase-manual-order.create.successTitle'),
                        message: me.$tc('ppobase-manual-order.create.addressCreatedMessage')
                    });

                    // Reload addresses and auto-select new one
                    me.loadCustomerAddresses(me.selectedCustomer.id);
                    me.shippingAddressId = response.addressId;
                    me.showNewAddressForm = false;
                    me.newAddressValidationActive = false;

                    // Reset form
                    me.newAddress = {
                        salutationId: null,
                        firstName: '',
                        lastName: '',
                        street: '',
                        additionalAddressLine1: '',
                        zipcode: '',
                        city: '',
                        countryId: null,
                        phone: '',
                        company: ''
                    };
                }
            }).catch(function(err) {
                me.savingAddress = false;
                me.createNotificationError({
                    title: me.$tc('ppobase-manual-order.create.errorTitle'),
                    message: err.response && err.response.data && err.response.data.error ? err.response.data.error : 'Failed to create address.'
                });
            });
        },

        // === Product Search ===
        onProductSearchInput: function(value) {
            this.productSearchTerm = value;
            var me = this;

            if (me.productSearchTimeout) clearTimeout(me.productSearchTimeout);

            if (!value || value.length < 2 || !me.salesChannelId) {
                me.productSearchResults = [];
                me.showProductResults = false;
                return;
            }

            me.productSearchTimeout = setTimeout(function() {
                me.productSearchLoading = true;
                me.ppobaseApiService.searchOrderProducts(value, me.salesChannelId).then(function(response) {
                    if (response.success) {
                        me.productSearchResults = response.data;
                        me.showProductResults = true;
                    }
                    me.productSearchLoading = false;
                }).catch(function() {
                    me.productSearchLoading = false;
                });
            }, 300);
        },

        onSelectProduct: async function(product) {
            // Check if product already in list
            for (var i = 0; i < this.orderItems.length; i++) {
                if (this.orderItems[i].productId === product.id && !this.orderItems[i].ppobaseGroupedChild) {
                    this.orderItems[i].quantity += 1;
                    this.recalcLineTotal(i);
                    this.syncGroupedChildrenForParent(this.orderItems[i]);
                    this.showProductResults = false;
                    this.productSearchTerm = '';
                    return;
                }
            }

            var parentItem = {
                manualItemId: this.createManualItemId(),
                productId: product.id,
                productName: product.name,
                productNumber: product.productNumber,
                unitPrice: product.price,
                quantity: 1,
                taxRate: product.taxRate,
                lineTotal: product.price,
                ppobaseRemovedGroupedChildProductIds: []
            };

            this.orderItems.push(parentItem);
            await this.addGroupedChildrenForParent(parentItem);

            this.showProductResults = false;
            this.productSearchTerm = '';
            this.productSearchResults = [];
        },

        onItemPriceChange: function(index, value) {
            if (this.orderItems[index].ppobaseGroupedChild) {
                return;
            }
            this.orderItems[index].unitPrice = Number(value) || 0;
            this.recalcLineTotal(index);
        },

        onItemQuantityChange: function(index, value) {
            if (this.orderItems[index].ppobaseGroupedChild) {
                return;
            }
            var qty = parseInt(value) || 1;
            if (qty < 1) qty = 1;
            this.orderItems[index].quantity = qty;
            this.recalcLineTotal(index);
            this.syncGroupedChildrenForParent(this.orderItems[index]);
        },

        recalcLineTotal: function(index) {
            var item = this.orderItems[index];
            item.lineTotal = Math.round(item.unitPrice * item.quantity * 100) / 100;
        },

        onRemoveItem: function(index) {
            var item = this.orderItems[index];
            if (item && item.ppobaseGroupedChild) {
                this.rememberRemovedGroupedChild(item);
                this.orderItems.splice(index, 1);
                return;
            }

            if (item) {
                this.orderItems = this.orderItems.filter(function(orderItem) {
                    return orderItem.manualItemId !== item.manualItemId
                        && orderItem.ppobaseGroupedParentItemId !== item.manualItemId;
                });
                return;
            }

            this.orderItems.splice(index, 1);
        },

        createManualItemId: function() {
            this.manualOrderItemCounter += 1;
            return 'manual-' + Date.now() + '-' + this.manualOrderItemCounter;
        },

        addGroupedChildrenForParent: async function(parentItem) {
            try {
                var response = await this.ppobaseApiService.getGroupedProducts(parentItem.productId);
                if (!response || !response.success || response.active === false) {
                    return;
                }

                var children = response.data || [];
                for (var i = 0; i < children.length; i++) {
                    var child = children[i];
                    if (!child.childProductId || this.isGroupedChildRemoved(parentItem, child.childProductId)) {
                        continue;
                    }

                    this.orderItems.push({
                        manualItemId: this.createManualItemId(),
                        productId: child.childProductId,
                        productName: child.productName,
                        productNumber: child.productNumber,
                        unitPrice: 0,
                        quantity: parentItem.quantity * (parseInt(child.quantity, 10) || 1),
                        taxRate: parentItem.taxRate || 0,
                        lineTotal: 0,
                        ppobaseGroupedChild: true,
                        ppobaseGroupedParentItemId: parentItem.manualItemId,
                        ppobaseGroupedParentProductId: parentItem.productId,
                        ppobaseChildBaseQty: parseInt(child.quantity, 10) || 1
                    });
                }
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-manual-order.create.errorTitle'),
                    message: error.message || this.$tc('ppobase-manual-order.create.errorMessage')
                });
            }
        },

        syncGroupedChildrenForParent: function(parentItem) {
            if (!parentItem || !parentItem.manualItemId) {
                return;
            }

            for (var i = 0; i < this.orderItems.length; i++) {
                var child = this.orderItems[i];
                if (child.ppobaseGroupedParentItemId !== parentItem.manualItemId) {
                    continue;
                }

                child.quantity = parentItem.quantity * (child.ppobaseChildBaseQty || 1);
                child.unitPrice = 0;
                child.lineTotal = 0;
            }
        },

        rememberRemovedGroupedChild: function(childItem) {
            var parentItem = null;
            for (var i = 0; i < this.orderItems.length; i++) {
                if (this.orderItems[i].manualItemId === childItem.ppobaseGroupedParentItemId) {
                    parentItem = this.orderItems[i];
                    break;
                }
            }

            if (!parentItem) {
                return;
            }

            if (!Array.isArray(parentItem.ppobaseRemovedGroupedChildProductIds)) {
                parentItem.ppobaseRemovedGroupedChildProductIds = [];
            }

            if (parentItem.ppobaseRemovedGroupedChildProductIds.indexOf(childItem.productId) === -1) {
                parentItem.ppobaseRemovedGroupedChildProductIds.push(childItem.productId);
            }
        },

        isGroupedChildRemoved: function(parentItem, childProductId) {
            return Array.isArray(parentItem.ppobaseRemovedGroupedChildProductIds)
                && parentItem.ppobaseRemovedGroupedChildProductIds.indexOf(childProductId) !== -1;
        },

        // === Create Order ===
        onCreateOrder: function() {
            var me = this;

            // Validate
            if (!me.salesChannelId) {
                me.createNotificationError({ title: me.$tc('ppobase-manual-order.create.validationError'), message: me.$tc('ppobase-manual-order.create.salesChannelRequiredMsg') });
                return;
            }
            if (!me.selectedCustomer) {
                me.createNotificationError({ title: me.$tc('ppobase-manual-order.create.validationError'), message: me.$tc('ppobase-manual-order.create.customerRequiredMsg') });
                return;
            }
            if (me.orderItems.length === 0) {
                me.createNotificationError({ title: me.$tc('ppobase-manual-order.create.validationError'), message: me.$tc('ppobase-manual-order.create.itemsRequiredMsg') });
                return;
            }
            if (!me.shippingMethodId) {
                me.createNotificationError({ title: me.$tc('ppobase-manual-order.create.validationError'), message: me.$tc('ppobase-manual-order.create.shippingRequiredMsg') });
                return;
            }
            if (!me.paymentMethodId) {
                me.createNotificationError({ title: me.$tc('ppobase-manual-order.create.validationError'), message: me.$tc('ppobase-manual-order.create.paymentRequiredMsg') });
                return;
            }

            var items = [];
            for (var i = 0; i < me.orderItems.length; i++) {
                var item = me.orderItems[i];
                items.push({
                    productId: item.productId,
                    quantity: item.quantity,
                    unitPrice: item.ppobaseGroupedChild ? 0 : item.unitPrice,
                    ppobaseGroupedChild: item.ppobaseGroupedChild === true,
                    ppobaseGroupedParentProductId: item.ppobaseGroupedParentProductId || null,
                    ppobaseChildBaseQty: item.ppobaseChildBaseQty || null,
                    ppobaseRemovedGroupedChildProductIds: item.ppobaseRemovedGroupedChildProductIds || []
                });
            }

            if (items.length === 0) {
                me.createNotificationError({ title: me.$tc('ppobase-manual-order.create.validationError'), message: me.$tc('ppobase-manual-order.create.itemsRequiredMsg') });
                return;
            }

            var payload = {
                salesChannelId: me.salesChannelId,
                customerId: me.selectedCustomer.id,
                shippingMethodId: me.shippingMethodId,
                paymentMethodId: me.paymentMethodId,
                shippingAddressId: me.shippingAddressId || null,
                billingAddressId: null,
                shippingCosts: me.shippingCosts || 0,
                orderReference: me.orderReference || null,
                shippingDate: me.shippingDate || null,
                notes: me.notes || null,
                sendConfirmation: me.sendConfirmation,
                items: items
            };

            me.isCreating = true;

            me.ppobaseApiService.createManualOrder(payload).then(function(response) {
                me.isCreating = false;
                if (response.success) {
                    me.createNotificationSuccess({
                        title: me.$tc('ppobase-manual-order.create.successTitle'),
                        message: me.$tc('ppobase-manual-order.create.successMessage') + ' ' + response.orderNumber
                    });
                    me.$router.push({ name: 'ppobase.manual.order.list' });
                } else {
                    me.createNotificationError({
                        title: me.$tc('ppobase-manual-order.create.errorTitle'),
                        message: response.error || me.$tc('ppobase-manual-order.create.errorMessage')
                    });
                }
            }).catch(function(err) {
                me.isCreating = false;
                var msg = me.$tc('ppobase-manual-order.create.errorMessage');
                if (err.response && err.response.data && err.response.data.error) {
                    msg = err.response.data.error;
                }
                me.createNotificationError({
                    title: me.$tc('ppobase-manual-order.create.errorTitle'),
                    message: msg
                });
            });
        },

        resetForm: function() {
            this.selectedCustomer = null;
            this.customerSearchTerm = '';
            this.customerSearchResults = [];
            this.showNewCustomerForm = false;
            this.shippingMethodId = null;
            this.shippingDate = null;
            this.shippingCosts = 0;
            this.shippingAddressId = null;
            this.customerAddresses = [];
            this.showNewAddressForm = false;
            this.paymentMethodId = null;
            this.orderItems = [];
            this.orderReference = '';
            this.notes = '';
            this.sendConfirmation = false;
            this.productSearchTerm = '';
            this.productSearchResults = [];
        },

        onCancel: function() {
            this.$router.push({ name: 'ppobase.manual.order.list' });
        },

        formatPrice: function(value) {
            if (value === null || value === undefined) return '0.00';
            return Number(value).toFixed(2);
        },

        requiredFieldError: function(active, value) {
            if (!active) return null;
            if (value === null || value === undefined) return { code: 'REQUIRED', detail: 'This field is required.' };
            if (typeof value === 'string' && value.trim() === '') return { code: 'REQUIRED', detail: 'This field is required.' };
            return null;
        }
    }
});
