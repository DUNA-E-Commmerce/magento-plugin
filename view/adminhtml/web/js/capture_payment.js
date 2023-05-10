require(['jquery'], function($) {
    'use strict';

    $(document).ready(function() {
        $('#capture_payment').click(function() {
            var orderId = $('input[name="order_id"]').val();
            console.log('orderId: ' + orderId);

            $.ajax({
                url: '/duna_payments/set/capturepayment',
                type: 'POST',
                dataType: 'json',
                data: {
                    order_id: orderId,
                },
                success: function (data) {
                    if (data.success) {
                        alert('Payment captured successfully.');
                    } else {
                        alert('Could not capture payment.');
                    }
                },
                error: function () {
                    alert('An error occurred while capturing the payment.');
                }
            });
        });
      });

});
