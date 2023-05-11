require(['jquery'], function($) {
    'use strict';

    $(document).ready(function() {
        $('#capture_payment').click(function() {
            var orderId = $('input[name="order_id"]').val();
            console.log('orderId: ' + orderId);

            $.ajax({
                url: '/rest/V1/DUna/capture/' + orderId,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        alert('Payment captured successfully.');
                    } else {
                        alert('Could not capture payment.');
                    }
                },
                error: function() {
                    alert('An error occurred while capturing the payment.');
                }
            });
        });
      });

});
