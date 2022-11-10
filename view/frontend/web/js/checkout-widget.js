
function isDev() {
    var hostname = document.location.hostname;

    return hostname.includes('dev.');
}

function isStaging() {
    var hostname = document.location.hostname;

    return hostname.includes('stg.');
}

if(isDev()) {
    console.log('Environment: Develop');
} else if(isStaging()) {
    console.log('Environment: Staging');
}

define([
    'jquery',
    'uiComponent',
    'ko',
    'mage/url',
    'https://cdn.getduna.com/cdl/index.js',
    'https://cdn.getduna.com/checkout-widget/v1.0.0/index.js',
    'https://cdn.stg.deuna.io/cdl/index.js',
    'https://cdn.stg.deuna.io/checkout-widget/v1.0.0/index.js',
    'https://cdn.dev.deuna.io/cdl/index.js',
    'https://cdn.dev.deuna.io/checkout-widget/v1.0.0/index.js',
], function ($, Component, ko, Url, DeunaCDL, DunaCheckout, DeunaCDLStg, DunaCheckoutStg, DeunaCDLDev, DunaCheckoutDev) {
    'use strict';

    if(isDev())
        window.DeunaCDL = DeunaCDLDev;
    else if(isStaging())
        window.DeunaCDL = DeunaCDLStg;
    else
        window.DeunaCDL = DeunaCDL;

    return Component.extend({
        defaults: {
            template: 'DUna_Payments/widget',
            dunaCheckout: {
                'dev': DunaCheckoutDev(),
                'stg': DunaCheckoutStg(),
                'prod': DunaCheckout()
            },
            hasEnable: ko.observable(true)
        },
        initialize: function () {
            this._super();
        },
        configure: async function (data) {
            const obj = JSON.parse(data);
            if(isDev()) {
                await this.dunaCheckout['dev'].configure({
                    apiKey: this.apiKey,
                    env: 'develop',
                    orderToken: obj.orderToken
                });
            } else if(isStaging()) {
                await this.dunaCheckout['stg'].configure({
                    apiKey: this.apiKey,
                    env: 'staging',
                    orderToken: obj.orderToken
                });
            } else {
                await this.dunaCheckout['prod'].configure({
                    apiKey: this.apiKey,
                    env: 'production',
                    orderToken: obj.orderToken
                });
            }
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

                if(isDev())
                    await self.dunaCheckout['dev'].show();
                else if(isStaging())
                    await self.dunaCheckout['stg'].show();
                else
                    await self.dunaCheckout['prod'].show();
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
