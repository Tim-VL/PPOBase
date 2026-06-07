import template from './ppobase-sales-report-dashboard.html.twig';
import './ppobase-sales-report-dashboard.scss';

import '../../ppobase-sales-report.scss';
import { CHART_COLORS, CHART_PALETTE, createCenterTextPlugin, formatYmd, getDefaultDateRange } from '../../ppobase-sales-report.helpers';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin = Shopware.Mixin;

ShopwareComponent.register('ppobase-sales-report-dashboard', {
    template: template,

    inject: ['ppobaseApiService'],

    mixins: [ShopwareMixin.getByName('notification')],

    data: function() {
        var range = getDefaultDateRange();
        return {
            isLoading: false,
            summary: {
                totalOrders: 0,
                totalRevenue: 0,
                totalCustomers: 0,
                avgOrderValue: 0
            },
            chartData: null,
            customerRevenueDoughnut: null,
            dateFrom: range.dateFrom,
            dateTo: range.dateTo,
            chartInstances: [],
            chartRenderTimeout: null
        };
    },

    created: function() {
        this.loadDashboard();
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
            this.loadDashboard();
        },

        onResetFilter: function() {
            var range = getDefaultDateRange();
            this.dateFrom = range.dateFrom;
            this.dateTo = range.dateTo;
            this.loadDashboard();
        },

        loadDashboard: async function() {
            this.isLoading = true;
            var self = this;
            try {
                var params = this.getParams();
                var results = await Promise.all([
                    this.ppobaseApiService.getOrdersOverview(params),
                    this.ppobaseApiService.getCustomersByOrders(params)
                ]);

                var ordersRes = results[0] && results[0].data ? results[0].data : null;
                var customersRes = results[1] && results[1].data ? results[1].data : null;

                this.summary.totalOrders = ordersRes && ordersRes.summary ? ordersRes.summary.totalOrders : 0;
                this.summary.totalRevenue = ordersRes && ordersRes.summary ? ordersRes.summary.totalRevenue : 0;
                this.summary.avgOrderValue = ordersRes && ordersRes.summary ? ordersRes.summary.avgOrderValue : 0;
                this.summary.totalCustomers = customersRes && customersRes.summary ? customersRes.summary.totalCustomers : 0;

                this.chartData = ordersRes ? ordersRes.chartData : null;

                var customerRows = customersRes && customersRes.rows ? customersRes.rows : [];
                var top5 = customerRows.slice(0, 5);
                var labels = [];
                var values = [];
                var topSum = 0;
                top5.forEach(function(r) {
                    var name = ((r.first_name || '') + ' ' + (r.last_name || '')).trim() || r.email || '-';
                    var v = parseFloat(r.total_revenue || 0) || 0;
                    labels.push(name);
                    values.push(v);
                    topSum += v;
                });
                var rest = Math.max(0, (parseFloat(self.summary.totalRevenue || 0) || 0) - topSum);
                if (rest > 0) {
                    labels.push('Others');
                    values.push(parseFloat(rest.toFixed(2)));
                }
                this.customerRevenueDoughnut = { labels: labels, values: values };

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

            if (!self.chartData || !self.chartData.line || !self.chartData.bar) {
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
                var mixedCanvas = document.getElementById('ppobase-sales-report-dashboard-chart');
                if (mixedCanvas) {
                    var mixedCtx = mixedCanvas.getContext('2d');
                    if (mixedCtx) {
                        var labels = self.chartData.line.labels || [];
                        var revenues = self.chartData.line.revenues || [];
                        var orderCounts = self.chartData.bar.orderCounts || [];

                        var gradient = mixedCtx.createLinearGradient(0, 0, 0, 350);
                        gradient.addColorStop(0, 'rgba(30, 136, 229, 0.3)');
                        gradient.addColorStop(1, 'rgba(30, 136, 229, 0.02)');

                        var mixedChart = new ChartJS(mixedCtx, {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        type: 'line',
                                        label: self.$tc('ppobase-sales-report.common.labelTotalRevenue'),
                                        data: revenues,
                                        borderColor: CHART_COLORS.primary,
                                        backgroundColor: gradient,
                                        fill: true,
                                        tension: 0.4,
                                        pointRadius: 5,
                                        pointHoverRadius: 7,
                                        pointBackgroundColor: CHART_COLORS.primary,
                                        borderWidth: 2.5,
                                        yAxisID: 'yRevenue'
                                    },
                                    {
                                        type: 'bar',
                                        label: self.$tc('ppobase-sales-report.common.labelTotalOrders'),
                                        data: orderCounts,
                                        backgroundColor: CHART_COLORS.secondary,
                                        yAxisID: 'yOrders'
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { position: 'bottom' } },
                                scales: {
                                    yRevenue: { position: 'left', ticks: { precision: 0 } },
                                    yOrders: { position: 'right', grid: { drawOnChartArea: false }, ticks: { precision: 0 } }
                                }
                            }
                        });

                        self.chartInstances.push(mixedChart);
                    }
                }

                if (self.customerRevenueDoughnut && self.customerRevenueDoughnut.labels && self.customerRevenueDoughnut.labels.length) {
                    var doughnutCanvas = document.getElementById('ppobase-sales-report-dashboard-doughnut');
                    if (doughnutCanvas) {
                        var doughnutCtx = doughnutCanvas.getContext('2d');
                        if (!doughnutCtx) return;

                        var total = (parseFloat(self.summary.totalRevenue || 0) || 0);
                        var doughnut = new ChartJS(doughnutCtx, {
                            type: 'doughnut',
                            data: {
                                labels: self.customerRevenueDoughnut.labels,
                                datasets: [{
                                    data: self.customerRevenueDoughnut.values,
                                    backgroundColor: CHART_PALETTE.slice(0, self.customerRevenueDoughnut.labels.length)
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { position: 'bottom' } },
                                centerText: self.formatMoney(total)
                            },
                            plugins: [centerTextPlugin]
                        });

                        self.chartInstances.push(doughnut);
                    }
                }
            }, 150);
        },

        goTo: function(routeName) {
            this.$router.push({ name: routeName });
        },

        formatNumber: function(value) {
            var n = parseFloat(value || 0);
            return n.toLocaleString();
        },

        formatMoney: function(value) {
            var n = parseFloat(value || 0);
            try {
                return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'EUR' }).format(n);
            } catch (e) {
                return n.toFixed(2);
            }
        }
    }
});
