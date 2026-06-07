import template from './ppobase-sales-report-by-item.html.twig';
import './ppobase-sales-report-by-item.scss';

import '../../ppobase-sales-report.scss';
import { CHART_COLORS, formatYmd, getDefaultDateRange, sortRows } from '../../ppobase-sales-report.helpers';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin = Shopware.Mixin;

ShopwareComponent.register('ppobase-sales-report-by-item', {
    template: template,

    inject: ['ppobaseApiService'],

    mixins: [ShopwareMixin.getByName('notification')],

    props: {
        productId: { type: String, required: false, default: null }
    },

    data: function() {
        var range = getDefaultDateRange();
        return {
            isLoading: false,
            isSearching: false,
            searchTerm: '',
            searchResults: [],
            selectedProductId: this.productId,
            report: null,
            dateFrom: range.dateFrom,
            dateTo: range.dateTo,
            sortBy: 'order_date_time',
            sortDirection: 'DESC',
            page: 1,
            limit: 25,
            chartInstances: [],
            chartRenderTimeout: null
        };
    },

    computed: {
        productColumns: function() {
            return [
                { property: 'product_name', label: this.$tc('ppobase-sales-report.items.columnProduct'), primary: true },
                { property: 'product_number', label: this.$tc('ppobase-sales-report.items.columnSku') }
            ];
        },

        orderColumns: function() {
            return [
                { property: 'order_number', label: this.$tc('ppobase-sales-report.byItem.columnOrderNumber'), primary: true, sortable: true },
                { property: 'order_date_time', label: this.$tc('ppobase-sales-report.byItem.columnOrderDate'), width: '180px', sortable: true },
                { property: 'customer_name', label: this.$tc('ppobase-sales-report.byItem.columnCustomer'), sortable: true },
                { property: 'email', label: this.$tc('ppobase-sales-report.byItem.columnEmail'), sortable: true },
                { property: 'quantity', label: this.$tc('ppobase-sales-report.byItem.columnQuantity'), width: '90px', align: 'right', sortable: true },
                { property: 'unit_price', label: this.$tc('ppobase-sales-report.byItem.columnUnitPrice'), width: '120px', align: 'right', sortable: true },
                { property: 'total_price', label: this.$tc('ppobase-sales-report.byItem.columnTotalPrice'), width: '120px', align: 'right', sortable: true }
            ];
        },

        gridOrders: function() {
            var rows = this.report && this.report.orders ? this.report.orders : [];
            var mapped = (rows || []).map(function(r) {
                var customerName = ((r.first_name || '') + ' ' + (r.last_name || '')).trim() || r.email || '-';
                return Object.assign({}, r, { customer_name: customerName });
            });
            return sortRows(mapped, this.sortBy, this.sortDirection);
        },

        total: function() {
            return (this.gridOrders || []).length;
        },

        pagedGridOrders: function() {
            var rows = this.gridOrders || [];
            var start = (this.page - 1) * this.limit;
            return rows.slice(start, start + this.limit);
        },

        productName: function() {
            if (!this.report || !this.report.product) return '-';
            return this.report.product.product_name || this.report.product.name || '-';
        }
    },

    created: function() {
        if (this.selectedProductId) {
            this.loadReport();
        }
    },

    watch: {
        productId: function(newId) {
            this.selectedProductId = newId;
            if (this.selectedProductId) {
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
            if (this.selectedProductId) {
                this.loadReport();
            }
        },

        onResetFilter: function() {
            var range = getDefaultDateRange();
            this.dateFrom = range.dateFrom;
            this.dateTo = range.dateTo;
            if (this.selectedProductId) {
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
                var res = await this.ppobaseApiService.searchProductsForReport(term);
                this.searchResults = (res && res.data) ? res.data : [];
            } catch (e) {
                this.searchResults = [];
            } finally {
                this.isSearching = false;
            }
        },

        onSelectProduct: function(item) {
            if (!item || !item.product_id) {
                return;
            }
            this.selectedProductId = item.product_id;
            this.searchTerm = '';
            this.searchResults = [];
            this.$router.push({ name: 'ppobase.sales.report.by-item', params: { productId: item.product_id } });
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
            if (!this.selectedProductId) {
                return;
            }

            this.isLoading = true;
            var self = this;
            try {
                var res = await this.ppobaseApiService.getSalesByItem(this.selectedProductId, this.getParams());
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

            self.chartRenderTimeout = setTimeout(function() {
                var lineCanvas = document.getElementById('ppobase-sales-report-by-item-line');
                if (lineCanvas && self.report.chartData.line) {
                    var ctx = lineCanvas.getContext('2d');
                    if (!ctx) return;

                    var line = self.report.chartData.line;
                    var gradient = ctx.createLinearGradient(0, 0, 0, 350);
                    gradient.addColorStop(0, 'rgba(30, 136, 229, 0.3)');
                    gradient.addColorStop(1, 'rgba(30, 136, 229, 0.02)');

                    var lineChart = new ChartJS(ctx, {
                        type: 'line',
                        data: {
                            labels: line.labels || [],
                            datasets: [
                                {
                                    label: self.$tc('ppobase-sales-report.byItem.labelTotalRevenue'),
                                    data: line.revenues || [],
                                    fill: true,
                                    backgroundColor: gradient,
                                    borderColor: CHART_COLORS.primary,
                                    tension: 0.4,
                                    pointRadius: 5,
                                    pointHoverRadius: 7,
                                    pointBackgroundColor: CHART_COLORS.primary,
                                    borderWidth: 2.5
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom' },
                                tooltip: {
                                    callbacks: {
                                        afterLabel: function(context) {
                                            var idx = context.dataIndex;
                                            var qty = (line.quantities || [])[idx];
                                            return qty ? ('Qty: ' + qty) : '';
                                        }
                                    }
                                }
                            }
                        }
                    });
                    self.chartInstances.push(lineChart);
                }

                var barCanvas = document.getElementById('ppobase-sales-report-by-item-bar');
                if (barCanvas && self.report.chartData.bar) {
                    var barCtx = barCanvas.getContext('2d');
                    if (!barCtx) return;

                    var bar = self.report.chartData.bar;
                    var barChart = new ChartJS(barCtx, {
                        type: 'bar',
                        data: {
                            labels: bar.labels || [],
                            datasets: [{
                                label: self.$tc('ppobase-sales-report.byItem.chartBarTitle'),
                                data: bar.quantities || [],
                                backgroundColor: CHART_COLORS.primary
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } },
                            scales: { x: { ticks: { precision: 0 } } }
                        }
                    });
                    self.chartInstances.push(barChart);
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

        formatDate: function(value) {
            if (!value) return '-';
            var d = new Date(value);
            if (isNaN(d.getTime())) return '-';
            return d.toLocaleString();
        },

        onOrderClick: function(item) {
            if (!item || !item.order_id) return;
            this.$router.push({ name: 'sw.order.detail', params: { id: item.order_id } });
        }
    }
});
