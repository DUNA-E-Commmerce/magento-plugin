require(['jquery'], function($) {
    'use strict';

    $(document).ready(function() {
        $('#capture_payment').click(function() {
            let orderId = document.getElementById('order_id_to_capture').value;

            $.ajax({
                url: '/rest/V1/DUna/capture/' + orderId,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    console.log(data);
                    if (data) {
                        alert('Pago Capturado Exitosamente');
                        location.reload();
                    } else {
                        alert('El pago no pudo ser capturado.');
                    }
                },
                error: function() {
                    alert('An error occurred while capturing the payment.');
                }
            });
        });
      });
});
