/**
 * Admin UI fixes (Magento core workarounds).
 */
var config = {
    config: {
        mixins: {
            'Magento_Ui/js/grid/columns/date': {
                'GrupoAwamotos_CatalogFix/js/grid/columns/date-moment-locale-mixin': true
            }
        }
    }
};
