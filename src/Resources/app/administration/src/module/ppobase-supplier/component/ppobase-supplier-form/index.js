/**
 * Supplier Form Component
 * 
 * A reusable form component that displays all supplier fields organized
 * in logical card sections. Used by both the detail and create pages.
 * 
 * Sections:
 * 1. Basic Info - Active status, supplier number, trade name
 * 2. Company Details - Registration numbers, VAT, etc.
 * 3. Primary Contact - Main contact person details
 * 4. General Contact - Company-wide contact info
 * 5. Billing Address - Invoice address details
 * 6. Shipping Address - Delivery/return address
 * 7. Logistics - Lead time, delivery days
 * 8. Commercial - Currency, payment terms, incoterms
 */

import template from './ppobase-supplier-form.html.twig';

const { Component } = Shopware;

Component.register('ppobase-supplier-form', {
    template,

    props: {
        supplier: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            ccError: null,
            bccError: null,

            incotermsOptions: [
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
            ],

            // Common currency codes
            currencyOptions: [
                { value: 'EUR', label: 'EUR - Euro' },
                { value: 'USD', label: 'USD - US Dollar' },
                { value: 'GBP', label: 'GBP - British Pound' },
                { value: 'CHF', label: 'CHF - Swiss Franc' },
                { value: 'CNY', label: 'CNY - Chinese Yuan' },
                { value: 'JPY', label: 'JPY - Japanese Yen' },
                { value: 'INR', label: 'INR - Indian Rupee' },
                { value: 'AUD', label: 'AUD - Australian Dollar' },
                { value: 'CAD', label: 'CAD - Canadian Dollar' }
            ],

            // Delivery days checkboxes
            deliveryDaysOptions: [
                { value: 'Mon', label: 'Monday' },
                { value: 'Tue', label: 'Tuesday' },
                { value: 'Wed', label: 'Wednesday' },
                { value: 'Thu', label: 'Thursday' },
                { value: 'Fri', label: 'Friday' },
                { value: 'Sat', label: 'Saturday' },
                { value: 'Sun', label: 'Sunday' }
            ]
        };
    },

    computed: {
        /**
         * Convert delivery days string to array for multi-select
         */
        selectedDeliveryDays: {
            get() {
                if (!this.supplier.deliveryDays) {
                    return [];
                }
                return this.supplier.deliveryDays.split(',');
            },
            set(value) {
                this.supplier.deliveryDays = value.join(',');
                this.emitChange();
            }
        }
    },

    methods: {
        onTextareaChange(field, value) {
            this.supplier[field] = value;
            this.emitChange();
        },

        emitChange() {
            this.$emit('change');
        },

        validateEmailList(value) {
            if (!value || !value.trim()) return null;
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            var invalid = value.split(',')
                .map(function(e) { return e.trim(); })
                .filter(function(e) { return e && !emailRegex.test(e); });
            return invalid.length > 0 ? 'Invalid email(s): ' + invalid.join(', ') : null;
        },

        onCcChange(value) {
            this.ccError = this.validateEmailList(value);
            this.emitChange();
        },

        onBccChange(value) {
            this.bccError = this.validateEmailList(value);
            this.emitChange();
        }
    }
});
