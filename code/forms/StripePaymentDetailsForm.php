<?php

use \Stripe\Stripe as Stripe;
use \Stripe\Customer as StripeCustomer;

class StripePaymentDetailsForm extends Form
{

    /**
     * Config variable to specify if we want to use custom JS.
     * Enabling this will disable all requirements calls
     *
     * @var Boolean
     * @config
     */
    private static $use_custom_js = false;
    
    public function __construct($controller, $name = "StripePaymentDetailsForm")
    {
        $publish_key = StripeForms::publish_key();

        parent::__construct(
            $controller,
            $name,

            // Fields
            FieldList::create(
                HiddenField::create("StripeToken"),
                ReadonlyField::create("CardType"),
                TextField::create("CardNumber")
                    ->setAttribute("name", "")
                    ->setAttribute("data-stripe", "number"),
                TextField::create("ExpirationMonth")
                    ->setAttribute("name", "")
                    ->setAttribute("data-stripe", "exp_month"),
                TextField::create("ExpirationYear")
                    ->setAttribute("name", "")
                    ->setAttribute("data-stripe", "exp_year"),
                TextField::create("CVC")
                    ->setAttribute("name", "")
                    ->setAttribute("data-stripe", "cvc"),
                TextField::create("BillingZip", "Billing Zip/Post Code")
                    ->setAttribute("name", "")
                    ->setAttribute("data-stripe", "address_zip")
            ),

            // Actions
            FieldList::create(
                FormAction::create("doSavePaymentDetails", "Save")
                    ->addExtraClass("submit")
            )
        );

        if (!StripeForms::config()->use_custom_js) {
            Requirements::javascript("https://js.stripe.com/v2/");
            Requirements::javascriptTemplate(
                "stripe-forms/javascript/StripePaymentDetailsForm.js",
                array(
                    "PublishKey" => $publish_key,
                    "FormName" => $this->FormName()
                )
            );
        }

        $card_details = $this->get_card_details();

        if ($card_details) {
            $this->loadDataFrom($card_details);
        }
    }

    /**
     * Get the card details from stripe for the current user
     * 
     * @return ArrayData | null
     */
    protected function get_card_details()
    {
        $member = Member::currentUser();
        $customer = $member->getStripeCustomer();

        if ($customer && !$customer->deleted) {
            $stripe_card = $customer->sources->data[0];

            return new ArrayData(array(
                "CardType" => $stripe_card->brand,
                "CardNumber" => str_pad($stripe_card->last4, 16, "*", STR_PAD_LEFT),
                "ExpirationMonth" => $stripe_card->exp_month,
                "ExpirationYear" => $stripe_card->exp_year,
                "BillingZip" => $stripe_card->address_zip
            ));
        }
    }
 
    /**
     * Save stripe payment details against a customer
     *
     */
    public function doSavePaymentDetails($data)
    {
        $token = $data["StripeToken"];

        if ($token) {
            $member = Member::currentUser();

            try {
                $member->saveStripeCustomer();

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