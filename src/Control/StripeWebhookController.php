<?php

namespace ilateral\SilverStripe\StripeForms\Control;

use \Stripe\Stripe as Stripe;
use \Stripe\Event as StripeEvent;
use \SilverStripe\Control\Controller;
use \SilverStripe\Control\Director;
use \SilverStripe\Security\Member;
use \SilverStripe\Control\Email\Email;
use \SilverStripe\View\ArrayData;
use ilateral\SilverStripe\StripeForms\StripeForms;
use ilateral\SilverStripe\StripeForms\Model\StripeSubscription;

/**
 * Controller to specifically handle stripe webhooks
 * (currently only related to subscriptions).
 *
 */
class StripeWebhookController extends Controller
{
    /**
     * The URL this controller is available via
     * 
     * @var String
     * @config
     */
    private static $url_segment = "stripeforms/webhooks";

    /**
     * Actions (methods that handle URLs) that can be called on this
     * controller
     * 
     * @var array
     * @config
     */
    private static $allowed_actions = array(
        "success",
        "failed"
    );

    /**
     * The json object from the current event
     *
     * @var Object
     */
    protected $event_json;

    /**
     * The stripe event object
     *
     * @var \Stripe\Event
     */
    protected $event;

    public function Link($action = null)
    {
        return Controller::join_links(
            $this->config()->url_segment,
            $action
        );
    }

    public function AbsoluteLink($action = null)
    {
        return Controller::join_links(
            Director::absoluteBaseURL(),
            $this->Link($action)
        );
    }

    /**
     * Get the json data from the current post
     * and convert to an object
     * 
     * @return Object
     */
    protected function get_json_data()
    {
        $input = @file_get_contents("php://input");
        return json_decode($input);
    }

    /**
     * Determine (based on the passed event type)
     * if we want to proceed with the callback
     *
     * @param string $event_type Type of event
     * @return Boolean 
     */
    protected function should_proceed($event_type)
    {
        // Check against Stripe to confirm that the ID is valid (only on live)
        if ($this->event_json->type == $event_type && $this->event) {
            return true;
        }

        return false;
    }

    /**
     * Get the member associated with the callback,
     * or null.
     *
     * @return Member | null
     */
    protected function get_member()
    {
        $member = Member::get()
            ->filter("StripeID", $this->event->data->object->customer)
            ->first();

        return $member;
    }

    public function init()
    {
        parent::init();

        // Setup stripe keys
        Stripe::setApiKey(StripeForms::secret_key());
        
        $this->event_json = $this->get_json_data();
        $this->event = StripeEvent::retrieve($this->event_json->id);
    }

    /**
     * use this action to generate an invoice on a successfull payment
     *
     */
    public function success()
    {
        // Retrieve the request's body and parse it as JSON
        $event_json = $this->event_json;
        $proceed = $this->should_proceed("invoice.payment_succeeded");

        // If the event is a payment failier (and the customer is an existing customer)
        if ($proceed && $member = $this->get_member()) {
            // If we are dealing with a subscription, get the plan
            $subscription_id = $this->event->data->object->subscription;
            $subscription = $member
                ->StripeSubscriptions()
                ->filter("StripeID", $subscription_id)
                ->first();
            
            // If subscription is not the default active status, set it now
            $default_active_status = StripeSubscription::config()->active_status;

            if ($subscription->Status != $default_active_status) {
                $subscription->Status = $default_active_status;
                $subscription->write();
            }

            // Send a notification email
            $email = new Email();
            $email
                ->setTo($member->Email)
                ->setSubject(_t("StripeForms.PaidSubject", "New payment recieved"))
                ->setTemplate('StripeFormsPaidEmail')
                ->populateTemplate(new ArrayData(array(
                    'Subscription' => $subscription,
                    'Member' => $member
                )));
                
            if (StripeForms::config()->send_emails_as) {
                $email->setFrom(StripeForms::config()->send_emails_as);
            }

            $this->extend("updatePaidEmail", $email);

            $email->send();

            return $this->renderWith(array(
                "Webhook_success",
                "Webhook_callback",
                "Webhook",
                "Page"
            ));
        }

        return $this->httpError(404);
    }
    
    /**
     * Action to handle failed payment callbacks. This action logs the number of
     * failed payments and downgrades the user's account after 3.
     * 
     */
    public function failed()
    {
        // Retrieve the request's body and parse it as JSON
        $event_json = $this->event_json;
        $proceed = $this->should_proceed("invoice.payment_failed");

        // If the event is a payment failier (and the customer is an existing customer)
        if ($proceed && $member = $this->get_member()) {
            // If we are dealing with a subscription, get the plan
            $subscription_id = $this->event->data->object->subscription;
            $subscription = $member
                ->StripeSubscriptions()
                ->filter("StripeID", $subscription_id)
                ->first();

            if ($subscription->exists()) {
                // Increment failed attempts
                $subscription->PaymentAttempts = $subscription->PaymentAttempts + 1;

                // If a member has repeated failed payments, downgrade subscription
                if ($subscription->PaymentAttempts >= StripeSubscription::config()->failier_attempts) {
                    // Cancel this subscription
                    $subscription->cancel();
                    $subscription->write();

                    // Send an email informing of subscription cancelation
                    $email = new Email();
                    $email
                        ->setTo($member->Email)
                        ->setSubject(_t("StripeForms.SubscriptionCancelled", "Your subscription has been cancelled"))
                        ->setTemplate('StripeSubscriptionCancelledEmail')
                        ->populateTemplate(new ArrayData(array(
                            "FailedAttempts" => $subscription->PaymentAttempts,
                            "Subscription" => $subscription,
                            'Member' => $member
                        )));
                    
                    if (StripeForms::config()->send_emails_as) {
                        $email->setFrom(StripeForms::config()->send_emails_as);
                    }

                    $this->extend("updateCancelledEmail", $email);

                    $email->send();
                } else {
                    // Send an email informing of the failed payment
                    $email = new Email();
                    $email
                        ->setTo($member->Email)
                        ->setSubject(_t("StripeForms.FailedPayment", "Your payment has failed"))
                        ->setTemplate('StripeFormsFailedEmail')
                        ->populateTemplate(new ArrayData(array(
                            "RemainingAttempts" => StripeSubscription::config()->failier_attempts - $subscription->PaymentAttempts,
                            'Subscription' => $subscription,
                            'Member' => $member
                        )));
                    
                    if (StripeForms::config()->send_emails_as) {
                        $email->setFrom(StripeForms::config()->send_emails_as);
                    }

                    $this->extend("updateFailedEmail", $email);

                    $email->send();
                }

                return $this->renderWith(array(
                    "Webhook_failed",
                    "Webhook_callback",
                    "Webhook",
                    "Page"
                ));
            }
        }

        return $this->httpError(404);
    }
}
