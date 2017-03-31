Stripe.setPublishableKey('$PublishKey');
jQuery.noConflict();

var payment_form_id = '#$FormName';

(function($) {
    var payment_form = $(payment_form_id);

    payment_form.bind(
        "submit",
        function(event) {
            // Disable the submit button to prevent repeated clicks:
            payment_form
                .find('.submit')
                .prop('disabled', true);

            // Request a token from Stripe:
            Stripe.card.createToken(payment_form, stripeResponseHandler);

            // Prevent the form from being submitted:
            return false;
        }
    );

    function stripeResponseHandler(status, response) {
        // Grab the form:
        var payment_form = $(payment_form_id);

        if (response.error) { // Problem!
            // Show the errors on the form:
            payment_form
                .find(payment_form_id + '_error')
                .text(response.error.message)
                .show();

            payment_form
                .find('.submit')
                .prop('disabled', false);

        } else {
            // Get the token ID:
            var token = response.id;

            // Insert the token ID into the form so it gets submitted to the server:
            payment_form
                .find(payment_form_id + "_StripeToken")
                .val(token);

            // Submit the form:
            payment_form
                .get(0)
                .submit();
        }
    };
}(jQuery));