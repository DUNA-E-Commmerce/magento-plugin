
function isDev() {
    var hostname = document.location.hostname;

    return hostname.includes('dev.') || hostname.includes('local.');
}

function isStaging() {
    var hostname = document.location.hostname;

    return hostname.includes('stg.') || hostname.includes('mcstaging.');
}

function updateCheckoutButton(radioElements,checkoutButton) {
    for (var i = 0; i < radioElements.length; i++) {
        if (radioElements[i].checked) {
            checkoutButton.disabled = false;
            return;
        }
    }
    checkoutButton.disabled = true;
}

function addListenersToRadios(radioElements,checkoutButton) {
    for (var i = 0; i < radioElements.length; i++) {
        radioElements[i].addEventListener('change', updateCheckoutButton(radioElements, checkoutButton));
    }
}

function verifySelectedRadioShipping(checkoutButton) {
    const element = document.querySelector('[id*="shipping-method-forms"]');

    if (element) {
        const radioButtons = document.querySelectorAll('#' + element.id + ' input[type="radio"]');
        var radiosOk = false;
        
        radioButtons.forEach(radio => {
            if (radio.value == "bopis_bopis"){
                radio.disabled = true;
            }

            radio.addEventListener('change', function() {
                if (radio.value == "bopis_bopis"){
                    if (selectedStoreElement && selectedStoreElement.textContent.trim() !== ''){
                        checkoutButton.disabled = false;
                    }else{
                        checkoutButton.disabled = true;
                    }                  
                }else{
                    checkoutButton.disabled = false;
                }
                radiosOk = true;
            });
        });
        

        const isRadioSelected = Array.from(radioButtons).some(radio => radio.checked);

        if (isRadioSelected) {
            checkoutButton.disabled = false;
        } else {
            checkoutButton.disabled = true;
        }

        if (radiosOk){
            clearInterval();
        }

    } else {
        console.log('error Buscando formulario')
    }

}

if(isDev()) {
    env = 'Develop';
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

    setTimeout(function() {
        var checkoutButton2 = document.getElementById('duna-checkout').querySelector('button');
        checkoutButton2.disabled = true;
    }, 500);

    window.addEventListener('load', (event) => {
        
        var shippingMethodForm = document.getElementById('block-shipping-top');
    
        if (shippingMethodForm != null) {
            var radioElements = shippingMethodForm.querySelectorAll('input[type="radio"]');
            var checkoutButton = document.getElementById('duna-checkout').querySelector('button');
            checkoutButton.disabled = true;

            var radioElement2 = document.getElementById('cs_method_bopis_bopis');
            if (radioElement2) {
                radioElement2.disabled = true;
            }

            updateCheckoutButton( radioElements, checkoutButton);
            addListenersToRadios( radioElements, checkoutButton);
        }

        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function () {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                if (xhr.status === 200) {
                    var response = xhr.responseText;
                    var div = document.createElement('div');
                    div.innerHTML = response;
                    var radioElement = document.getElementById('cs_method_bopis_bopis');
                    if (radioElement) {
                     radioElement.disabled = true;
                    }
    
                    var tdElements = div.querySelectorAll('td');
    
                    if (tdElements.length > 0) {
                    } else {
                        var csMethodBopisBopis = document.getElementById('cs_method_bopis_bopis');
                        var parentElement = csMethodBopisBopis.parentNode;
                        parentElement.setAttribute('hidden', 'true');
                    }
                    
                    setInterval(verifySelectedRadioShipping(checkoutButton), 300);
                    
                    setTimeout(function() {
                        verifySelectedRadioShipping(checkoutButton);
                    }, 500);

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
            })
            .error(function (error, status, message) {
                alert(`Error (${status}): ${message}`);

                window.location.reload();
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

