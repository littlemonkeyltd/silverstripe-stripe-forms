<?php

namespace ilateral\SilverStripe\StripeForms\Extensions;

use \Stripe\Stripe as Stripe;
use \Stripe\Customer as StripeCustomer;
use \SilverStripe\ORM\DataExtension;
use ilateral\SilverStripe\StripeForms\Model\StripeSubscription;
use ilateral\SilverStripe\StripeForms\StripeForms;

/**
 * Extension to be used to add extra functionality to a standard Silverstripe
 * member.
 *
 * @package stripe-forms
 * @subpackage extensions
 * @author Mo <morven@ilateral.co.uk>
 */
class StripeFormsMemberExtension extends DataExtension
{
    private static $db = [
        "StripeID" => "Varchar(255)"
    ];

    private static $has_many = [
        "StripeSubscriptions" => StripeSubscription::class
    ];

    /**
     * Get a stripe customer from this member (if one exists)
     *
     * @return Stripe\Customer
     */
    public function getStripeCustomer()
    {
        $secret_key = StripeForms::secret_key();
        $customer = null;

        Stripe::setApiKey($secret_key);

        // First try and get a customer
        if ($this->owner->StripeID) {
            $customer = StripeCustomer::retrieve($this->owner->StripeID);
        }

        return $customer;
    }

    /**
     * Update the current member's stripe ID (or create a new one) using
     * the provided Stripe token.
     *
     * @param $token
     */
    public function saveStripeCustomer($token)
    {
        $customer = $this->owner->getStripeCustomer();

        // If no customer (or deleted), then create a new one, otherwise update
        if (!$customer || ($customer && $customer->deleted) && $token) {
            $customer = StripeCustomer::create([
                "description" => "Customer {$this->owner->FirstName} {$this->owner->Surname}",
                "source" => $token,
                "email" => $this->owner->Email
            ]);

            $this->owner->StripeID = $customer->id;
            $this->owner->write();
        } else {
            $customer->source = $token;
            $customer->save();
        }
    }
}
