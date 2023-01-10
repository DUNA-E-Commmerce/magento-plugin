
function isDev() {
    var hostname = document.location.hostname;

    return hostname.includes('dev.');
}

function isStaging() {
    var hostname = document.location.hostname;

    if(hostname.includes('stg.')) {
        return true;
    } else if(hostname.includes('mcstaging.')) {
        return true;
    } else {
        return false;
    }
}

if(isDev()) {
    console.log('Environment: Develop');
} else if(isStaging()) {
    console.log('Environment: Staging');
} else {
    console.log('Environment: Prod');
}

let deuna_widget_version = 'v1.0.0';

let components = [
    'jquery',
    'uiComponent',
    'ko',
    'mage/url'
];

let deunaEnv;

if(isDev()) {
    deunaEnv = 'develop';
    components.push('https://cdn.dev.deuna.io/cdl/index.js');
    components.push(`https://cdn.dev.deuna.io/checkout-widget/${deuna_widget_version}/index.js`);
} else if(isStaging()) {
    deunaEnv = 'staging';
    components.push('https://cdn.stg.deuna.io/cdl/index.js');
    components.push(`https://cdn.stg.deuna.io/checkout-widget/${deuna_widget_version}/index.js`);
} else {
    deunaEnv = 'production';
    components.push('https://cdn.getduna.com/cdl/index.js');
    components.push(`https://cdn.getduna.com/checkout-widget/${deuna_widget_version}/index.js`);
}

define(components, function ($, Component, ko, Url, DeunaCDL, DunaCheckout) {
    'use strict';

    window.DeunaCDL = DeunaCDL;

    return Component.extend({
        defaults: {
            template: 'DUna_Payments/widget',
            dunaCheckout: DunaCheckout(),
            hasEnable: ko.observable(true)
        },
        initialize: function () {
            this._super();
        },
        configure: async function (data) {
            const obj = JSON.parse(data);

            let config = {
                apiKey: this.apiKey,
                env: deunaEnv,
                orderToken: obj.orderToken
            }

            await this.dunaCheckout.configure(config);
        },
        show: function () {
            const self = this,
                  tokenUrl = Url.build('rest/V1/DUna/token');
            this.preventClick();
            $.ajax({
                method: 'GET',
                url: tokenUrl
            })
            .done(async function (data) {
                await self.configure(data);

                await self.dunaCheckout.show();
            });
        },
        preventClick: function () {
            const self = this;
            this.hasEnable(false);
            setTimeout(function () {
                self.hasEnable(true);
            }, 4000)
        }
    });
});
