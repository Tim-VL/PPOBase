import template from './ppobase-sales-report-orders.html.twig';
import './ppobase-sales-report-orders.scss';

import '../../ppobase-sales-report.scss';
import { CHART_COLORS, createCenterTextPlugin, formatYmd, getDefaultDateRange, sortRows } from '../../ppobase-sales-report.helpers';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin = Shopware.Mixin;

ShopwareComponent.register('ppobase-sales-report-orders', {
    template: template,

    inject: ['ppobaseApiService'],

    mixins: [ShopwareMixin.getByName('notification')],

    data: function() {
        var range = getDefaultDateRange();
        return {
            isLoading: false,
            orders: [],
            summary: null,
            chartData: null,
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
        columns: function() {
            return [
                { property: 'order_number', label: this.$tc('ppobase-sales-report.orders.columnOrderNumber'), primary: true, sortable: true },
                { property: 'order_date_time', label: this.$tc('ppobase-sales-report.orders.columnOrderDate'), width: '180px', sortable: true },
                { property: 'customer_name', label: this.$tc('ppobase-sales-report.orders.columnCustomer'), sortable: true },
                { property: 'email', label: this.$tc('ppobase-sales-report.orders.columnEmail'), sortable: true },
                { property: 'order_status', label: this.$tc('ppobase-sales-report.orders.columnStatus'), width: '130px', sortable: true },
                { property: 'currency', label: this.$tc('ppobase-sales-report.orders.columnCurrency'), width: '90px', sortable: true },
                { property: 'amount_total', label: this.$tc('ppobase-sales-report.orders.columnTotal'), width: '130px', align: 'right', sortable: true }
            ];
        },

        gridOrders: function() {
            var mapped = (this.orders || []).map(function(o) {
                var name = ((o.first_name || '') + ' ' + (o.last_name || '')).trim() || o.email || '-';
                return Object.assign({}, o, { customer_name: name });
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
        }
    },

    created: function() {
        this.loadReport();
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
            this.loadReport();
        },

        onResetFilter: function() {
            var range = getDefaultDateRange();
            this.dateFrom = range.dateFrom;
            this.dateTo = range.dateTo;
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
            this.isLoading = true;
            var self = this;
            try {
                var res = await this.ppobaseApiService.getOrdersOverview(this.getParams());
                var data = res && res.data ? res.data : null;
                this.orders = data && data.orders ? data.orders : [];
                this.summary = data ? data.summary : null;
                this.chartData = data ? data.chartData : null;
                this.page = 1;
                self.renderCharts();
            } catch (error) {
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

            if (!self.chartData) {
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
                var lineCanvas = document.getElementById('ppobase-sales-report-orders-line');
                if (lineCanvas && self.chartData.line) {
                    var lineCtx = lineCanvas.getContext('2d');
                    if (!lineCtx) return;

                    var line = self.chartData.line;
                    var gradient = lineCtx.createLinearGradient(0, 0, 0, 350);
                    gradient.addColorStop(0, 'rgba(30, 136, 229, 0.3)');
                    gradient.addColorStop(1, 'rgba(30, 136, 229, 0.02)');

                    var area = new ChartJS(lineCtx, {
                        type: 'line',
                        data: {
                            labels: line.labels || [],
                            datasets: [{
                                label: self.$tc('ppobase-sales-report.orders.chartLineTitle'),
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
                    self.chartInstances.push(area);
                }

                var statusCounts = { Open: 0, Completed: 0, Cancelled: 0 };
                (self.orders || []).forEach(function(o) {
                    var s = (o.order_status || '').toLowerCase();
                    if (s === 'cancelled') {
                        statusCounts.Cancelled += 1;
                    } else if (s === 'completed' || s === 'paid') {
                        statusCounts.Completed += 1;
                    } else {
                        statusCounts.Open += 1;
                    }
                });

                var doughnutCanvas = document.getElementById('ppobase-sales-report-orders-status-doughnut');
                if (doughnutCanvas) {
                    var dctx = doughnutCanvas.getContext('2d');
                    if (!dctx) return;

                    var totalOrders = parseInt(self.summary ? self.summary.totalOrders : 0, 10) || (self.orders || []).length;
                    var statusChart = new ChartJS(dctx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Open', 'Completed', 'Cancelled'],
                            datasets: [{
                                data: [statusCounts.Open, statusCounts.Completed, statusCounts.Cancelled],
                                backgroundColor: [CHART_COLORS.primary, CHART_COLORS.success, CHART_COLORS.danger]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } },
                            centerText: String(totalOrders)
                        },
                        plugins: [centerTextPlugin]
                    });
                    self.chartInstances.push(statusChart);
                }
            }, 150);
        },

        formatMoney: function(value, currency) {
            var n = parseFloat(value || 0);
            var cur = currency || 'EUR';
            try {
                return new Intl.NumberFormat('en-US', { style: 'currency', currency: cur }).format(n);
            } catch (e) {
                return n.toFixed(2) + ' ' + cur;
            }
        },

        formatDate: function(value) {
            if (!value) return '-';
            var d = new Date(value);
            if (isNaN(d.getTime())) return '-';
            return d.toLocaleString();
        },

        getStatusVariant: function(status) {
            if (!status) return 'info';
            if (status === 'cancelled') return 'danger';
            if (status === 'completed' || status === 'paid') return 'success';
            return 'info';
        },

        onOrderClick: function(item) {
            if (!item || !item.order_id) return;
            this.$router.push({ name: 'sw.order.detail', params: { id: item.order_id } });
        }
    }
});
