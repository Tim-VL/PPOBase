import template from './ppobase-sales-report-items.html.twig';
import './ppobase-sales-report-items.scss';

import '../../ppobase-sales-report.scss';
import { CHART_COLORS, CHART_PALETTE, formatYmd, getDefaultDateRange, sortRows } from '../../ppobase-sales-report.helpers';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin = Shopware.Mixin;

ShopwareComponent.register('ppobase-sales-report-items', {
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
            sortBy: 'total_quantity',
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
                { property: 'product_name', label: this.$tc('ppobase-sales-report.items.columnProduct'), primary: true, sortable: true },
                { property: 'product_number', label: this.$tc('ppobase-sales-report.items.columnSku'), width: '140px', sortable: true },
                { property: 'total_quantity', label: this.$tc('ppobase-sales-report.items.columnTotalQty'), width: '110px', align: 'right', sortable: true },
                { property: 'order_count', label: this.$tc('ppobase-sales-report.items.columnOrderCount'), width: '90px', align: 'right', sortable: true },
                { property: 'total_revenue', label: this.$tc('ppobase-sales-report.items.columnTotalRevenue'), width: '140px', align: 'right', sortable: true },
                { property: 'avg_unit_price', label: this.$tc('ppobase-sales-report.items.columnAvgPrice'), width: '120px', align: 'right', sortable: true }
            ];
        },

        gridRows: function() {
            return sortRows(this.rows || [], this.sortBy, this.sortDirection);
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
                var res = await this.ppobaseApiService.getItemsBySales(this.getParams());
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

            self.chartRenderTimeout = setTimeout(function() {
                var canvas = document.getElementById('ppobase-sales-report-items-chart');
                if (canvas) {
                    var ctx = canvas.getContext('2d');
                    if (ctx) {
                        var labels = self.chartData.labels || [];
                        var quantities = self.chartData.quantities || [];
                        var revenues = self.chartData.revenues || [];

                        var chart = new ChartJS(ctx, {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        label: self.$tc('ppobase-sales-report.items.columnTotalQty'),
                                        data: quantities,
                                        backgroundColor: CHART_COLORS.primary
                                    },
                                    {
                                        label: self.$tc('ppobase-sales-report.items.columnTotalRevenue'),
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

                if (self.rows && self.rows.length) {
                    var top5 = self.rows
                        .slice()
                        .sort(function(a, b) { return (parseFloat(b.total_revenue || 0) || 0) - (parseFloat(a.total_revenue || 0) || 0); })
                        .slice(0, 5);

                    var polarLabels = top5.map(function(r) { return r.product_name || '-'; });
                    var polarValues = top5.map(function(r) { return parseFloat(r.total_revenue || 0) || 0; });

                    var polarCanvas = document.getElementById('ppobase-sales-report-items-polar');
                    if (polarCanvas) {
                        var pctx = polarCanvas.getContext('2d');
                        if (!pctx) return;

                        var polar = new ChartJS(pctx, {
                            type: 'polarArea',
                            data: {
                                labels: polarLabels,
                                datasets: [{
                                    data: polarValues,
                                    backgroundColor: CHART_PALETTE.slice(0, polarLabels.length)
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { position: 'bottom' } }
                            }
                        });

                        self.chartInstances.push(polar);
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

        onProductClick: function(item) {
            if (!item || !item.product_id) {
                return;
            }
            this.$router.push({ name: 'ppobase.sales.report.by-item', params: { productId: item.product_id } });
        }
    }
});
