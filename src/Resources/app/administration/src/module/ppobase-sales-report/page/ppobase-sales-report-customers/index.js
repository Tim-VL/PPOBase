import template from './ppobase-sales-report-customers.html.twig';
import './ppobase-sales-report-customers.scss';

import '../../ppobase-sales-report.scss';
import { CHART_COLORS, CHART_PALETTE, createCenterTextPlugin, formatYmd, getDefaultDateRange, sortRows } from '../../ppobase-sales-report.helpers';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin = Shopware.Mixin;

ShopwareComponent.register('ppobase-sales-report-customers', {
    template: template,

    inject: ['ppobaseApiService'],

    mixins: [ShopwareMixin.getByName('notification')],

    data: function() {
        var range = getDefaultDateRange();
        return {
            isLoading: false,
            rows: [],
            summary: null,
            chartData: null,
            dateFrom: range.dateFrom,
            dateTo: range.dateTo,
            sortBy: 'order_count',
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
                { property: 'name', label: this.$tc('ppobase-sales-report.customers.columnName'), primary: true, sortable: true },
                { property: 'email', label: this.$tc('ppobase-sales-report.customers.columnEmail'), sortable: true },
                { property: 'order_count', label: this.$tc('ppobase-sales-report.customers.columnOrderCount'), width: '90px', align: 'right', sortable: true },
                { property: 'total_revenue', label: this.$tc('ppobase-sales-report.customers.columnTotalRevenue'), width: '140px', align: 'right', sortable: true },
                { property: 'last_order_date', label: this.$tc('ppobase-sales-report.customers.columnLastOrder'), width: '180px', sortable: true }
            ];
        },

        gridRows: function() {
            var mapped = (this.rows || []).map(function(r) {
                var name = (r && (r.first_name || r.last_name))
                    ? ((r.first_name || '') + ' ' + (r.last_name || '')).trim()
                    : (r && r.email ? r.email : '-');
                return Object.assign({}, r, { name: name });
            });
            return sortRows(mapped, this.sortBy, this.sortDirection);
        },

        total: function() {
            return (this.gridRows || []).length;
        },

        pagedGridRows: function() {
            var rows = this.gridRows || [];
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
                var res = await this.ppobaseApiService.getCustomersByOrders(this.getParams());
                var data = res && res.data ? res.data : null;
                this.rows = data && data.rows ? data.rows : [];
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
                var canvas = document.getElementById('ppobase-sales-report-customers-chart');
                if (canvas) {
                    var ctx = canvas.getContext('2d');
                    if (ctx) {
                        var labels = self.chartData.labels || [];
                        var orderCounts = self.chartData.orderCounts || [];
                        var revenues = self.chartData.revenues || [];

                        var chart = new ChartJS(ctx, {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        label: self.$tc('ppobase-sales-report.customers.columnOrderCount'),
                                        data: orderCounts,
                                        backgroundColor: CHART_COLORS.primary
                                    },
                                    {
                                        label: self.$tc('ppobase-sales-report.customers.columnTotalRevenue'),
                                        data: revenues,
                                        backgroundColor: CHART_COLORS.secondary
                                    }
                                ]
                            },
                            options: {
                                indexAxis: 'y',
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { position: 'bottom' } },
                                scales: { x: { ticks: { precision: 0 } } }
                            }
                        });

                        self.chartInstances.push(chart);
                    }
                }

                if (self.summary && self.rows && self.rows.length) {
                    var top5 = self.rows.slice(0, 5);
                    var topSum = 0;
                    top5.forEach(function(r) { topSum += (parseInt(r.order_count, 10) || 0); });
                    var totalOrders = parseInt(self.summary.totalOrders, 10) || 0;
                    var rest = Math.max(0, totalOrders - topSum);

                    var doughnutCanvas = document.getElementById('ppobase-sales-report-customers-doughnut');
                    if (doughnutCanvas) {
                        var dctx = doughnutCanvas.getContext('2d');
                        if (!dctx) return;

                        var doughnut = new ChartJS(dctx, {
                            type: 'doughnut',
                            data: {
                                labels: ['Top 5', 'Rest'],
                                datasets: [{
                                    data: [topSum, rest],
                                    backgroundColor: [CHART_PALETTE[0], CHART_PALETTE[4]]
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

                        self.chartInstances.push(doughnut);
                    }
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
            if (!value) {
                return '-';
            }
            var d = new Date(value);
            if (isNaN(d.getTime())) {
                return '-';
            }
            return d.toLocaleString();
        },

        onCustomerClick: function(item) {
            if (!item || !item.customer_id) {
                return;
            }
            this.$router.push({ name: 'ppobase.sales.report.by-customer', params: { customerId: item.customer_id } });
        }
    }
});
