<?php

use \Stripe\Stripe as Stripe;
use \Stripe\Customer as StripeCustomer;
use \Stripe\Subscription as StripeAPISubscription;

/**
 * Custom version of the payment details form that also sets up
 * a subscription that is then assigned to the user.
 *  
 * The end user enters their credit card details and when the
 * form is submitted, they are pushed to Stripe via the API.
 * The form is then pre-populated with summary details of the
 * saved payment details.
 *
 * This form expects a Stripe Plan ID (that the user will be added to).
 * If this is not set, the form will be disabled and an error shown.
 *
 * By default this form uses the default Stripe JS, if you wish
 * to overwrite this functionality (to perform custom operations)
 * then make sure you disable the use_custom_js config variable.
 *
 * @package stripe-forms
 * @subpackage forms
 * @author Mo <morven@ilateral.co.uk>
 */
class StripePaymentSubscriptionForm extends StripePaymentDetailsForm
{
    
    /**
     * The Stripe plan ID (this is used to assign the customer to a plan)
     *
     * @var string
     */
    protected $plan_id;

    public function setPlanID($id)
    {
        $this->plan_id = $id;
        return $this;
    }

    public function getPlanID()
    {
        return $this->plan_id;
    }

    /**
     * Create this form object
     *
     * @param $controller The current controller
     * @param $name the name of this form (defaults to "StripePaymentDetailsForm")
     * @param $plan_id The stripe plan we want to add this user to.
     */
    public function __construct($controller, $name = "StripePaymentDetailsForm", $plan_id = null)
    {
        $this->plan_id = $plan_id;
        
        parent::__construct($controller, $name);

        // if no plan is set, log a message and disable this form
        if (!$plan_id) {
            $this->sessionMessage(
                _t("StripeForms.SelectStripePlan", "You need to select a plan before paying"),
                "bad"
            );

            $this->makeReadOnly();
        }
    }

    /**
     * Save stripe payment details against a customer using the stripe API
     *
     * @return SS_HTTPResponse
     */
    public function doSavePaymentDetails($data)
    {
        $token = $data["StripeToken"];
        $plan_id = $this->plan_id;

        Stripe::setApiKey(StripeForms::secret_key());

        if ($token) {
            $member = Member::currentUser();

            // Try to setup stripe customer and add them to plan
            try {
                $member->saveStripeCustomer($token);
                $already_subscribed = true;

                // See if we have any existing plans that match our ID
                $existing_plans = $member->StripeSubscriptions()->filter("PlanID", $plan_id);

                if (!$existing_plans->exists()) {
                    // First cancel any existing subscriptions (if needed)
                    if (StripeForms::config()->cancel_subscriptions_on_setup && $member->StripeSubscriptions()->exists()) {
                        foreach($member->StripeSubscriptions() as $subscription) {
                            $subscription->cancel();
                            $subscription->write();
                        }
                    }

                    // First clear any existing subscriptions (if needed)
                    if (StripeForms::config()->clear_subscriptions_on_setup && $member->StripeSubscriptions()->exists()) {
                        foreach($member->StripeSubscriptions() as $subscription) {
                            $subscription->delete();
                        }
                    }

                    // Associate subscription in stripe
                    $stripe_subscription = StripeAPISubscription::create(array(
                        "customer" => $member->StripeID,
                        "plan" => $plan_id
                    ));

                    $subscription = StripeSubscription::create();
                    $subscription->Status = $stripe_subscription->status;
                    $subscription->StripeID = $stripe_subscription->id;
                    $subscription->PlanID = $plan_id;
                    $subscription->MemberID = $member->ID;
                    $subscription->write();

                    $member->PaymentAttempts = 0;
                    $member->write();
                }

                $this->extend("onSuccessfulSavePaymentDetails", $data);

                $this->sessionMessage(
                    _t("StripeForms.SubscriptionSetup", "Payment details saved and subscription setup"),
                    "good"
                );
            } catch (Exception $e) {
                $this->sessionMessage($e->getmessage(),"bad");
            }
        }

        return $this->controller->redirectBack();
    }
}