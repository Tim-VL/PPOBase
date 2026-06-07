/**
 * Supplier Create Page Component - Extends Detail with default currency
 */

import template from './ppobase-supplier-create.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.extend('ppobase-supplier-create', 'ppobase-supplier-detail', {
    template,

    data() {
        return {
            newSupplierId: null,
            nextSupplierNumber: '1000'
        };
    },

    computed: {
        pageTitle() {
            return this.$tc('ppobase-supplier.create.title');
        }
    },

    methods: {
        async loadSupplier() {
            this.isLoading = true;
            try {
                await this.fetchNextSupplierNumber();
                await this.loadCurrencies();
                await this.loadCountries();

                this.supplier = this.supplierRepository.create(Shopware.Context.api);
                this.supplier.active = true;
                this.supplier.supplierNumber = this.nextSupplierNumber;
                this.supplier.vatPercent = 0;

                // Set default currency from Shopware system
                const defaultCurrency = this.currencies.find(c => c.isSystemDefault);
                if (defaultCurrency) {
                    this.supplier.currency = defaultCurrency.isoCode;
                }
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-supplier.create.errorTitle'),
                    message: error.message
                });
            } finally {
                this.isLoading = false;
            }
        },

        async fetchNextSupplierNumber() {
            try {
                const criteria = new Criteria();
                criteria.addSorting(Criteria.sort('supplierNumber', 'DESC'));
                criteria.setLimit(1);

                const result = await this.supplierRepository.search(criteria, Shopware.Context.api);

                if (result.total === 0) {
                    this.nextSupplierNumber = '1000';
                } else {
                    const lastSupplier = result.first();
                    const lastNumber = parseInt(lastSupplier.supplierNumber, 10) || 999;
                    this.nextSupplierNumber = String(Math.max(lastNumber + 1, 1000));
                }
            } catch (error) {
                this.nextSupplierNumber = '1000';
            }
        },

        async onSave() {
            this.isSaving = true;

            if (!this.supplier.companyTradeName) {
                this.createNotificationError({
                    title: this.$tc('ppobase-supplier.create.validationErrorTitle'),
                    message: this.$tc('ppobase-supplier.create.tradeNameRequired')
                });
                this.isSaving = false;
                return;
            }

            if (!this.supplier.companyEmail && !this.supplier.generalEmail) {
                this.createNotificationError({
                    title: this.$tc('ppobase-supplier.create.validationErrorTitle'),
                    message: this.$tc('ppobase-supplier.create.emailRequired')
                });
                this.isSaving = false;
                return;
            }

            if (!this.supplier.currency) {
                this.createNotificationError({
                    title: this.$tc('ppobase-supplier.create.validationErrorTitle'),
                    message: this.$tc('ppobase-supplier.create.currencyRequired')
                });
                this.isSaving = false;
                return;
            }

            if (this.supplier.vatPercent !== null && this.supplier.vatPercent !== undefined) {
                if (this.supplier.vatPercent < 0 || this.supplier.vatPercent > 100) {
                    this.createNotificationError({
                        title: this.$tc('ppobase-supplier.create.validationErrorTitle'),
                        message: this.$tc('ppobase-supplier.create.vatPercentInvalid')
                    });
                    this.isSaving = false;
                    return;
                }
            }

            try {
                this.supplier.createdBy = 'admin';
                await this.supplierRepository.save(this.supplier, Shopware.Context.api);
                this.hasChanges = false;
                this.createNotificationSuccess({
                    title: this.$tc('ppobase-supplier.create.saveSuccessTitle'),
                    message: this.$tc('ppobase-supplier.create.saveSuccessMessage')
                });
                this.$router.push({
                    name: 'ppobase.supplier.detail',
                    params: { id: this.supplier.id }
                });
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-supplier.create.saveErrorTitle'),
                    message: error.message
                });
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
        }
    }
});
