jQuery(document).ready(function() {

    jQuery('form.woocommerce-checkout').on('click', ':submit', function(event) {
        if (jQuery('#payment_method_paytotask_payment_gateway').is(':checked')) {

            event.preventDefault();

            jQuery('button[type=submit].button').text('Loading..').prop("disabled",true);

            jQuery('.blockUI').show();

            /* Act on the event */
            jQuery.ajax({
                dataType: "json",
                method: "POST",
                // hit "ajax_process_checkout()"
                url: wcAjaxObj.process_checkout,
                data: jQuery('form.woocommerce-checkout').serializeArray(),
                success: function(response) {
                    // WC will send the error contents in a normal request
                    if (response.result == "success") {
                        if (response.success == false) {
                            // Remove old errors
                            jQuery('.woocommerce-error, .woocommerce-message').remove();
                            // Add new errors
                            if (response.messages) {
                                jQuery('.payment_method_paytotask_payment_gateway p').append(`
                                    <p class="woocommerce-error" role="alert"> ${response.messages}</p>`);
                            }
                            jQuery('button[type=submit].button').text('Place order').prop("disabled",false);
                        } else {
                            window.location = response.checkout_url;
                        }
                    } else {
                        jQuery('button[type=submit].button').text('Place order').prop("disabled",false);
                        if (response.reload === 'true') {
                            window.location.reload();
                            return;
                        }
                        // Remove old errors
                        jQuery('.woocommerce-error, .woocommerce-message').remove();
                        // Add new errors
                        if (response.messages) {
                            jQuery('form.woocommerce-checkout').prepend(response.messages);
                        }

                        // Cancel processing
                        jQuery('form.woocommerce-checkout').removeClass('processing').unblock();

                        // Lose focus for all fields
                        jQuery('form.woocommerce-checkout').find('.input-text, select').blur();
                    }
                },
                error: function(jqxhr, status) {
                    // We got a 500 or something if we hit here. Shouldn't normally happen
                    alert("We were unable to process your order, please try again in a few minutes.");
                }
            });
        }
    });
});