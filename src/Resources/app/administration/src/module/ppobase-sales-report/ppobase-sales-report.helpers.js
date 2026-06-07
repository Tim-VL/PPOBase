export const CHART_COLORS = {
    primary: '#1E88E5',
    secondary: '#FF9800',
    success: '#43A047',
    danger: '#E53935',
    purple: '#8E24AA',
    teal: '#00897B',
    pink: '#D81B60',
    indigo: '#3949AB',
    amber: '#FFB300',
    cyan: '#00ACC1'
};

export const CHART_PALETTE = [
    '#1E88E5', '#FF9800', '#43A047', '#E53935', '#8E24AA',
    '#00897B', '#D81B60', '#3949AB', '#FFB300', '#00ACC1',
    '#7CB342', '#F4511E', '#6D4C41', '#546E7A', '#EC407A'
];

export function formatYmd(value) {
    if (!value) {
        return null;
    }

    var d = value instanceof Date ? value : new Date(value);
    if (isNaN(d.getTime())) {
        return null;
    }

    var year = String(d.getFullYear());
    var month = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
}

export function getDefaultDateRange() {
    var today = new Date();
    var from = new Date(today.getTime());
    from.setMonth(from.getMonth() - 12);
    return { dateFrom: from, dateTo: today };
}

export function sortRows(rows, sortBy, sortDirection) {
    if (!Array.isArray(rows) || !sortBy) {
        return rows || [];
    }

    var dir = (sortDirection || 'DESC').toUpperCase() === 'ASC' ? 1 : -1;
    var copy = rows.slice();

    copy.sort(function(a, b) {
        var av = a ? a[sortBy] : null;
        var bv = b ? b[sortBy] : null;

        var an = parseFloat(av);
        var bn = parseFloat(bv);
        var bothNumbers = !isNaN(an) && !isNaN(bn);
        if (bothNumbers) {
            return (an - bn) * dir;
        }

        var ad = av ? new Date(av) : null;
        var bd = bv ? new Date(bv) : null;
        var bothDates = ad && bd && !isNaN(ad.getTime()) && !isNaN(bd.getTime());
        if (bothDates) {
            return (ad.getTime() - bd.getTime()) * dir;
        }

        var as = (av === null || av === undefined) ? '' : String(av).toLowerCase();
        var bs = (bv === null || bv === undefined) ? '' : String(bv).toLowerCase();
        if (as < bs) return -1 * dir;
        if (as > bs) return 1 * dir;
        return 0;
    });

    return copy;
}

export function createCenterTextPlugin() {
    return {
        id: 'centerText',
        beforeDraw: function(chart) {
            if (chart.config.type !== 'doughnut') {
                return;
            }

            var text = chart.config.options.centerText || '';
            if (!text) {
                return;
            }

            var meta = chart.getDatasetMeta(0);
            var arc = meta && meta.data && meta.data[0] ? meta.data[0] : null;
            var props = arc && typeof arc.getProps === 'function'
                ? arc.getProps(['x', 'y', 'innerRadius'], true)
                : null;
            var centerX = props && props.x ? props.x : chart.width / 2;
            var centerY = props && props.y ? props.y : chart.height / 2;
            var innerRadius = props && props.innerRadius ? props.innerRadius : Math.min(chart.width, chart.height) / 5;
            var maxTextWidth = Math.max(48, innerRadius * 1.6);
            var maxFontSize = Math.min(48, Math.max(14, innerRadius * 0.48));
            var fontSize = maxFontSize;
            var ctx = chart.ctx;

            ctx.save();
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'center';
            ctx.fillStyle = '#374151';

            do {
                ctx.font = fontSize + 'px sans-serif';
                if (ctx.measureText(text).width <= maxTextWidth || fontSize <= 12) {
                    break;
                }
                fontSize -= 1;
            } while (fontSize > 12);

            ctx.fillText(text, centerX, centerY, maxTextWidth);
            ctx.restore();
        }
    };
}
