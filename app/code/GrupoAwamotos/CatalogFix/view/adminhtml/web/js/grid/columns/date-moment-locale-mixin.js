/**
 * Evita moment.defineLocale em locale já existente (warning no console do admin).
 * @see vendor/magento/module-ui/view/base/web/js/grid/columns/date.js
 */
define([
    'moment',
    'mageUtils',
    'underscore'
], function (moment, utils, _) {
    'use strict';

    var configuredLocales = {};

    return function (DateColumn) {
        return DateColumn.extend({
            /**
             * @inheritdoc
             */
            getLabel: function (value, format) {
                var date,
                    locale,
                    config;

                if (this.storeLocale !== undefined) {
                    locale = this.storeLocale;
                    config = utils.extend({}, this.calendarConfig);

                    if (!configuredLocales[locale]) {
                        if (moment.locales().indexOf(locale) !== -1) {
                            moment.updateLocale(locale, config);
                        } else {
                            moment.defineLocale(locale, config);
                        }
                        configuredLocales[locale] = true;
                    }
                    moment.locale(locale);
                }

                date = moment.utc(this._super());

                if (!_.isUndefined(this.timezone) && moment.tz.zone(this.timezone) !== null) {
                    date = date.tz(this.timezone);
                }

                return date.isValid() && value[this.index] ?
                    date.format(format || this.dateFormat) :
                    '';
            }
        });
    };
});
