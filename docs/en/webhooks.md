# Stripe Webhook Integration

This module comes with a controller that can process stripe invoice paid and
invoice failed statuses.

Currently this intended to allow subscriptions to be terminated by Stripe
automatically if a user's payment failes a pre-defined number of times (defaults
to 3).

**NOTE** This service will only cancel a "subscription" by default. If you want to
change anything else (such as the user's plan) you will need to listen for changes
to the subscription and make your own changes at that point (ideally using
StripeSubscription.onAfterCancel extension call).

## Setting up webhooks

To use webhooks, you will need to enable them in your Stripe account. You can do this
by visiting the webhooks screen and adding two new endpoints:

### Success Endpoint

URL: https://yourappurl.com/stripeforms/webhooks/success

This endpoint will listen for the "invoice.payment_succeeded" event (or you can send
all events).

### Failier Endpoint

URL: https://yourappurl.com/stripeforms/webhooks/failed

This endpoint will listen for the "invoice.payment_failed" event (or you can send
all events).

## Changing the number of failed payments

By default the webhook controller expects 3 payment attempts, after the third it
cancels the subscription related to the payment.

If you want to change this default, you will need to use the SilverStripe config
setting:

````
StripeSubscription.failier_attempts
````

**NOTE** If you do this, you will also need to change the number of payment
attempts in Stripe.

## Automated Emails

The webhooks controller also sends 3 possible emails on the back of a successfull
call:

### Payment Success

This email is sent on an `invoice.payment_success` status.

### Payment Failed

This email is sent on an `invoice.payment_failed` status

### Subscription Cancelled

This email is sent when the number of failed payment attempts reaches the `StripeSubscription.failier_attempts`
config setting.

All these emails can be customised either via extensions or custom templates.