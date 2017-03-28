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

    private static $db = array(
        "StripeID" => "Varchar(255)",
        "PlanID" => "Varchar(255)"
    );

    private static $has_one = array(
        "Member" => "Member"
    );

    private static $summary_fields = array(
        "Member.Email" => "Member",
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
            return null;
        }
    }

}