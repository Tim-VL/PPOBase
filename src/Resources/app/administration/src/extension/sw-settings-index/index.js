/**
 * Override sw-settings-index to sort the plugins group by position
 * instead of alphabetically, so PPOBase modules appear in menu order.
 */
Shopware.Component.override('sw-settings-index', {
    computed: {
        settingsGroups() {
            const groups = this.$super('settingsGroups');

            if (groups && groups.plugins && Array.isArray(groups.plugins)) {
                groups.plugins.sort(function(a, b) {
                    var posA = (typeof a.position === 'number') ? a.position : 999;
                    var posB = (typeof b.position === 'number') ? b.position : 999;
                    if (posA !== posB) return posA - posB;
                    var labelA = typeof a.label === 'string' ? a.label : (a.label && a.label.label ? a.label.label : '');
                    var labelB = typeof b.label === 'string' ? b.label : (b.label && b.label.label ? b.label.label : '');
                    return labelA.localeCompare(labelB);
                });
            }

            return groups;
        }
    }
});
