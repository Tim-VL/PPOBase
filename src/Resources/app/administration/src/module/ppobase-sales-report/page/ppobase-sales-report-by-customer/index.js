import template from './ppobase-sales-report-by-customer.html.twig';
import './ppobase-sales-report-by-customer.scss';

import '../../ppobase-sales-report.scss';
import { CHART_COLORS, CHART_PALETTE, createCenterTextPlugin, formatYmd, getDefaultDateRange, sortRows } from '../../ppobase-sales-report.helpers';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin = Shopware.Mixin;

ShopwareComponent.register('ppobase-sales-report-by-customer', {
    template: template,

    inject: ['ppobaseApiService'],

    mixins: [ShopwareMixin.getByName('notification')],

    props: {
        customerId: { type: String, required: false, default: null }
    },

    data: function() {
        var range = getDefaultDateRange();
        return {
            isLoading: false,
            isSearching: false,
            searchTerm: '',
            searchResults: [],
            selectedCustomerId: this.customerId,
            report: null,
            dateFrom: range.dateFrom,
            dateTo: range.dateTo,
            sortBy: 'total_spent',
            sortDirection: 'DESC',
            page: 1,
            limit: 25,
            chartInstances: [],
            chartRenderTimeout: null
        };
    },

    computed: {
        customerColumns: function() {
            return [
                { property: 'name', label: this.$tc('ppobase-sales-report.customers.columnName'), primary: true },
                { property: 'email', label: this.$tc('ppobase-sales-report.customers.columnEmail') }
            ];
        },

        itemColumns: function() {
            return [
                { property: 'product_name', label: this.$tc('ppobase-sales-report.byCustomer.columnProduct'), primary: true, sortable: true },
                { property: 'product_number', label: this.$tc('ppobase-sales-report.byCustomer.columnSku'), width: '140px', sortable: true },
                { property: 'total_quantity', label: this.$tc('ppobase-sales-report.byCustomer.columnTotalQty'), width: '110px', align: 'right', sortable: true },
                { property: 'total_spent', label: this.$tc('ppobase-sales-report.byCustomer.columnTotalSpent'), width: '140px', align: 'right', sortable: true },
                { property: 'avg_price', label: this.$tc('ppobase-sales-report.byCustomer.columnAvgPrice'), width: '120px', align: 'right', sortable: true },
                { property: 'order_count', label: this.$tc('ppobase-sales-report.byCustomer.columnOrderCount'), width: '90px', align: 'right', sortable: true }
            ];
        },

        gridItems: function() {
            var items = this.report && this.report.items ? this.report.items : [];
            return sortRows(items, this.sortBy, this.sortDirection);
        },

        total: function() {
            return (this.gridItems || []).length;
        },

        pagedGridItems: function() {
            var rows = this.gridItems || [];
            var start = (this.page - 1) * this.limit;
            return rows.slice(start, start + this.limit);
        },

        customerName: function() {
            if (!this.report || !this.report.customer) return '-';
            var c = this.report.customer;
            return ((c.first_name || '') + ' ' + (c.last_name || '')).trim() || c.email || '-';
        }
    },

    created: function() {
        if (this.selectedCustomerId) {
            this.loadReport();
        }
    },

    watch: {
        customerId: function(newId) {
            this.selectedCustomerId = newId;
            if (this.selectedCustomerId) {
                this.loadReport();
            } else {
                this.report = null;
            }
        }
    },

    beforeUnmount: function() {
        if (this.chartRenderTimeout) {
            clearTimeout(this.chartRenderTimeout);
            this.chartRenderTimeout = null;
        }
        this.chartInstances.forEach(function(c) { c.destroy(); });
        this.chartInstances = [];
    },

    methods: {
        getParams: function() {
            return {
                dateFrom: formatYmd(this.dateFrom),
                dateTo: formatYmd(this.dateTo)
            };
        },

        onApplyFilter: function() {
            if (this.selectedCustomerId) {
                this.loadReport();
            }
        },

        onResetFilter: function() {
            var range = getDefaultDateRange();
            this.dateFrom = range.dateFrom;
            this.dateTo = range.dateTo;
            if (this.selectedCustomerId) {
                this.loadReport();
            }
        },

        onSearchTermChange: async function(term) {
            this.searchTerm = term;
            if (!term || term.length < 2) {
                this.searchResults = [];
                return;
            }

            this.isSearching = true;
            try {
                var res = await this.ppobaseApiService.searchCustomersForReport(term);
                var data = res && res.data ? res.data : [];
                this.searchResults = (data || []).map(function(r) {
                    var name = ((r.first_name || '') + ' ' + (r.last_name || '')).trim() || r.email || '-';
                    return Object.assign({}, r, { name: name });
                });
            } catch (e) {
                this.searchResults = [];
            } finally {
                this.isSearching = false;
            }
        },

        onSelectCustomer: function(item) {
            if (!item || !item.customer_id) {
                return;
            }
            this.selectedCustomerId = item.customer_id;
            this.searchTerm = '';
            this.searchResults = [];
            this.$router.push({ name: 'ppobase.sales.report.by-customer', params: { customerId: item.customer_id } });
            this.loadReport();
        },

        onColumnSort: function(column) {
            if (!column || !column.dataIndex) {
                return;
            }
            if (this.sortBy === column.dataIndex) {
                this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.sortBy = column.dataIndex;
                this.sortDirection = 'DESC';
            }
        },

        loadReport: async function() {
            if (!this.selectedCustomerId) {
                return;
            }

            this.isLoading = true;
            var self = this;
            try {
                var res = await this.ppobaseApiService.getSalesByCustomer(this.selectedCustomerId, this.getParams());
                this.report = res && res.data ? res.data : null;
                this.page = 1;
                self.renderCharts();
            } catch (error) {
                this.report = null;
                this.createNotificationError({
                    title: this.$tc('ppobase-sales-report.common.errorTitle'),
                    message: this.$tc('ppobase-sales-report.common.errorMessage')
                });
            } finally {
                this.isLoading = false;
            }
        },

        onPageChange: function(data) {
            this.page = data.page;
            this.limit = data.limit;
        },

        loadChartJs: function() {
            return new Promise(function(resolve, reject) {
                if (window.Chart) {
                    resolve(window.Chart);
                    return;
                }
                var script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js';
                script.onload = function() { resolve(window.Chart); };
                script.onerror = function() { reject(new Error('Failed to load Chart.js')); };
                document.head.appendChild(script);
            });
        },

        renderCharts: async function() {
            var self = this;
            self.chartInstances.forEach(function(c) { c.destroy(); });
            self.chartInstances = [];
            if (self.chartRenderTimeout) {
                clearTimeout(self.chartRenderTimeout);
                self.chartRenderTimeout = null;
            }

            if (!self.report || !self.report.chartData) {
                return;
            }

            var ChartJS;
            try {
                ChartJS = await self.loadChartJs();
            } catch (e) {
                return;
            }

            var centerTextPlugin = createCenterTextPlugin();

            self.chartRenderTimeout = setTimeout(function() {
                var pieCanvas = document.getElementById('ppobase-sales-report-by-customer-pie');
                if (pieCanvas && self.report.chartData.pie) {
                    var pieCtx = pieCanvas.getContext('2d');
                    if (!pieCtx) return;

                    var pie = self.report.chartData.pie;
                    var totalSpent = self.report.summary ? (parseFloat(self.report.summary.totalSpent || 0) || 0) : 0;

                    var rawLabels = pie.labels || [];
                    var rawValues = pie.values || [];
                    var labels = [];
                    var values = [];
                    var topCount = Math.min(rawLabels.length, 10);
                    var topSum = 0;
                    for (var i = 0; i < topCount; i++) {
                        labels.push(rawLabels[i]);
                        var v = parseFloat(rawValues[i] || 0) || 0;
                        values.push(v);
                        topSum += v;
                    }
                    var totalSum = 0;
                    for (var j = 0; j < rawValues.length; j++) {
                        totalSum += (parseFloat(rawValues[j] || 0) || 0);
                    }
                    var otherSum = totalSum - topSum;
                    if (otherSum > 0) {
                        labels.push('Others');
                        values.push(parseFloat(otherSum.toFixed(2)));
                    }

                    var doughnutChart = new ChartJS(pieCtx, {
                        type: 'doughnut',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: values,
                                backgroundColor: CHART_PALETTE.slice(0, labels.length)
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } },
                            centerText: self.formatMoney(totalSpent)
                        },
                        plugins: [centerTextPlugin]
                    });
                    self.chartInstances.push(doughnutChart);
                }

                var lineCanvas = document.getElementById('ppobase-sales-report-by-customer-line');
                if (lineCanvas && self.report.chartData.line) {
                    var lineCtx = lineCanvas.getContext('2d');
                    if (!lineCtx) return;

                    var line = self.report.chartData.line;
                    var gradient = lineCtx.createLinearGradient(0, 0, 0, 350);
                    gradient.addColorStop(0, 'rgba(30, 136, 229, 0.3)');
                    gradient.addColorStop(1, 'rgba(30, 136, 229, 0.02)');

                    var areaChart = new ChartJS(lineCtx, {
                        type: 'line',
                        data: {
                            labels: line.labels || [],
                            datasets: [{
                                label: self.$tc('ppobase-sales-report.byCustomer.chartLineTitle'),
                                data: line.revenues || [],
                                fill: true,
                                backgroundColor: gradient,
                                borderColor: CHART_COLORS.primary,
                                tension: 0.4,
                                pointRadius: 5,
                                pointHoverRadius: 7,
                                pointBackgroundColor: CHART_COLORS.primary,
                                borderWidth: 2.5
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                    self.chartInstances.push(areaChart);
                }
            }, 150);
        },

        formatMoney: function(value) {
            var n = parseFloat(value || 0);
            try {
                return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'EUR' }).format(n);
            } catch (e) {
                return n.toFixed(2);
            }
        },

        onProductClick: function(item) {
            if (!item || !item.product_id) {
                return;
            }
            this.$router.push({ name: 'ppobase.sales.report.by-item', params: { productId: item.product_id } });
        }
    }
});
