<?php

use \Stripe\Stripe as Stripe;
use \Stripe\Subscription as StripeAPISubscription;

/**
 * An object in Silverstripe that links to a stripe subscription.
 *
 * @package stripe-forms
 * @subpackage model
 * @author Mo <morven@ilateral.co.uk>
 */
class StripeSubscription extends DataObject
{

    /**
     * The status in Stripe to show this is a currently active subscription.
     *
     * @var String
     * @config
     */
    private static $active_status = "active";

    /**
     * Number of times a payment attempt can fail before the subscription
     * is cancelled.
     *
     * @var Int
     * @config
     */
    private static $failier_attempts = 3;

    private static $db = array(
        "StripeID" => "Varchar(255)",
        "PlanID" => "Varchar(255)",
        "Status" => "Varchar",
        "PaymentAttempts" => "Int"
    );

    private static $has_one = array(
        "Member" => "Member"
    );

    private static $summary_fields = array(
        "Member.Email" => "Member",
        "Status" => "Status",
        "StripeID" => "Stripe ID",
        "PlanID" => "Plan"
    );

    /**
     * Simple function to retrieve a subscription by ID
     * from Stripe.
     * 
     * If subscription is invalid, then return null
     *
     * @return \Stripe\Subscription | null
     */
    public function getSubscription()
    {
        Stripe::setApiKey(StripeForms::secret_key());

        try {
            $subscription = StripeAPISubscription::retrieve($this->StripeID);
            return $subscription;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    /**
     * Set the status of this subscription to the status logged in
     * stripe and return the current object.
     *
     * @return StripeSubscription
     */
    public function updateStatus()
    {
        $subscription = $this->getSubscription();

        if($subscription) {
            $this->Status = $subscription->status;
        }

        $this->extend("onAfterUpdate");

        return $this;
    }

    /**
     * Cancel the current subscription in stripe and return
     * the current object.
     *
     * @return StripeSubscription
     */
    public function cancel()
    {
        $subscription = $this->getSubscription();

        if($subscription) {
            $subscription->cancel();
            $this->Status = $subscription->status;
        }

        $this->extend("onAfterCancel");

        return $this;
    }

    public function onBeforeDelete()
    {
        parent::onBeforeDelete();

        $this->cancel();
    }

}