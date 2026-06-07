Shopware.Component.override('sw-product-list', {
    methods: {
        getProductColumns: function() {
            var superResult = this.$super('getProductColumns');
            var columns = (typeof superResult === 'function') ? superResult.call(this) : superResult;

            if (!Array.isArray(columns)) {
                return columns;
            }

            return columns.map(function(column) {
                if (!column || column.property !== 'stock') {
                    return column;
                }

                // Disable inline editing for stock so it is locked everywhere (detail + list).
                var patched = Object.assign({}, column);
                delete patched.inlineEdit;
                patched.allowEdit = false;
                patched.editable = false;
                return patched;
            });
        },
    },
});
