
function isDev() {
    var hostname = document.location.hostname;

    return hostname.includes('dev.') || hostname.includes('local.');
}

function isStaging() {
    var hostname = document.location.hostname;

    return hostname.includes('stg.') || hostname.includes('mcstaging.');
}

function updateCheckoutButton(radioElements,checkoutButton) {
    console.log('enable or disabled');
    for (var i = 0; i < radioElements.length; i++) {
        if (radioElements[i].checked) {
            checkoutButton.disabled = false;
            return;
        }
    }
    checkoutButton.disabled = true;
}

if(isDev()) {
    env = 'Staging';
} else if(isStaging()) {
    env = 'Staging';
} else {
    env = 'Prod';
}

let deuna_widget_version = 'v1.0.0';

console.log(`Env: ${env} ${deuna_widget_version}`);

let components = [
    'jquery',
    'uiComponent',
    'ko',
    'mage/url'
];

let deunaEnv;

if(isDev()) {
    deunaEnv = 'staging';
    components.push('https://cdn.stg.deuna.io/cdl/index.js');
    components.push(`https://cdn.stg.deuna.io/checkout-widget/${deuna_widget_version}/index.js`);
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

    window.addEventListener('load', (event) => {
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function () {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                if (xhr.status === 200) {
                    var response = xhr.responseText;
                    var div = document.createElement('div');
                    div.innerHTML = response;

                    var tdElements = div.querySelectorAll('td');

                    if (tdElements.length > 0) {
                    } else {
                        var csMethodBopisBopis = document.getElementById('cs_method_bopis_bopis');
                        var parentElement = csMethodBopisBopis.parentNode;
                        parentElement.setAttribute('hidden', 'true');
                    }
                    console.log('La petición GET ha finalizado con exito!');

                } else {
                    console.log('La petición GET ha finalizado con un error.');
                }
            }
        };

        var currentURL = window.location.href;
        var urlObject = new URL(currentURL);
        var domain = urlObject.origin;

        xhr.open('GET', domain + '/storepickup/stores/index/?_=' + Date.now());
        xhr.send();
    });

    document.addEventListener("DOMContentLoaded", function() {
        console.log('DOM Cargados');
        var shippingMethodForm = document.getElementById('block-shipping-top');

        if (shippingMethodForm != null) {
            var radioElements = shippingMethodForm.querySelectorAll('input[type="radio"]');
            var checkoutButton = document.getElementById('duna-checkout').querySelector('button');

            updateCheckoutButton( radioElements, checkoutButton);

            for (var i = 0; i < radioElements.length; i++) {
                radioElements[i].addEventListener('change', updateCheckoutButton(radioElements, checkoutButton));
            }
        }
    });

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
            if(env!='Prod')
                console.log(data)

                const obj = JSON.parse(data);

            let config = {
                apiKey: this.apiKey,
                env: deunaEnv,
                orderToken: obj.orderToken
            }

            await this.dunaCheckout.configure(config);
        },
        show: function () {
            if(env!='Prod')
                console.debug('Tokenize DEUNA Checkout');

            const self = this,
                  tokenUrl = Url.build('rest/V1/DUna/token');

            this.preventClick();

            $.ajax({
                method: 'GET',
                url: tokenUrl
            })
            .done(async function (data) {
                // Configure Modal based on data returned from token endpoint
                await self.configure(data);
                // Trigger DEUNA Checkout Modal
                await self.dunaCheckout.show();
            });
        },
        preventClick: function () {
            const self = this;

            this.hasEnable(false);

            setTimeout(function () {
                self.hasEnable(true);
            }, 5000)
        }
    });
});
