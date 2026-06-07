import template from './ppobase-product-packaging.html.twig';
import './ppobase-product-packaging.scss';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin = Shopware.Mixin;

ShopwareComponent.register('ppobase-product-packaging', {
    template: template,

    inject: ['ppobaseApiService'],

    mixins: [ShopwareMixin.getByName('notification')],

    data: function() {
        return {
            isLoading: false,
            isSaving: false,
            extensionData: {
                itemsPerCarton: 0,
                cartonsPerLayer: 0,
                cartonLength: 0,
                cartonHeight: 0,
                cartonWidth: 0,
                cartonWeightNet: 0,
                cartonWeightGross: 0,
                ruleNotes: '',
            },
        };
    },

    computed: {
        productId: function() {
            return this.$route.params.id;
        },

        cartonVolume: function() {
            var length = parseFloat(this.extensionData.cartonLength || 0);
            var height = parseFloat(this.extensionData.cartonHeight || 0);
            var width = parseFloat(this.extensionData.cartonWidth || 0);
            return ((length * height * width) / 1000000).toFixed(6);
        },
    },

    created: function() {
        this.loadExtensionData();
    },

    methods: {
        loadExtensionData: async function() {
            this.isLoading = true;
            try {
                var result = await this.ppobaseApiService.getProductExtension(this.productId);
                this.extensionData = Object.assign({}, this.extensionData, (result && result.data) ? result.data : {});
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-product.packaging.errorTitle'),
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
            }
        },

        onSave: async function() {
            this.isSaving = true;
            try {
                var result = await this.ppobaseApiService.saveProductExtension(this.productId, this.extensionData);
                if (!result || !result.success) {
                    throw new Error((result && result.error) || 'Failed to save packaging data');
                }

                this.createNotificationSuccess({
                    title: this.$tc('ppobase-product.packaging.successTitle'),
                    message: this.$tc('ppobase-product.packaging.successMessage'),
                });

                await this.loadExtensionData();
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-product.packaging.errorTitle'),
                    message: error.message,
                });
            } finally {
                this.isSaving = false;
            }
        },
    },
});
