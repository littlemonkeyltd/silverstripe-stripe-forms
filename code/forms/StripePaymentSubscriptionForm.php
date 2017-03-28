<?php

use \Stripe\Stripe as Stripe;
use \Stripe\Customer as StripeCustomer;
use \Stripe\Subscription as StripeAPISubscription;

/**
 * Custom form of payment details form that also sets up a subscription to the
 * provided ID
 *
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

    public function __construct($controller, $name = "StripePaymentDetailsForm", $plan_id)
    {
        $this->plan_id = $plan_id;
        
        parent::__construct($controller, $name);
    }

    /**
     * Save stripe payment details against a customer
     *
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
                    // Associate subscription in stripe
                    $stripe_subscription = StripeAPISubscription::create(array(
                        "customer" => $member->StripeID,
                        "plan" => $plan_id
                    ));

                    $subscription = StripeSubscription::create();
                    $subscription->StripeID = $stripe_subscription->id;
                    $subscription->PlanID = $plan_id;
                    $subscription->MemberID = $member->ID;
                    $subscription->write();

                    $member->PaymentAttempts = 0;
                    $member->write();
                }

                $this->extend("onSuccessfulSavePaymentDetails", $data);

                $this->sessionMessage(
                    _t("StripeForms.UpdatedDetails", "Updated payment details"),
                    "good"
                );
            } catch (Exception $e) {
                return $this->controller->httpError(500, $e->getmessage());
            }
        }

        return $this->controller->redirectBack();
    }
}