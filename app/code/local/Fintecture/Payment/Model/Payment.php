<?php

class Fintecture_Payment_Model_Payment extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'fintecture';

    protected $_formBlockType = 'fintecture_payment/form_payment'; // This is the block that's displayed on the checkout

    protected $_isInitializeNeeded = true;

    protected $_canUseInternal = true;

    protected $_isGateway = true; //  Is this payment method a gateway (online auth/charge) ?

    protected $_canAuthorize = true; // Can authorize online?

    protected $_canUseCheckout = true; // Can show this payment method as an option on checkout payment page?

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('fintecture/standard/redirect');
    }

    // Use this to set whether the payment method should be available in only certain circumstances
    public function isAvailable($quote = null)
    {
        // Check that Fintecture is enabled
        if (!Mage::getStoreConfigFlag('payment/fintecture/active')) {
            return false;
        }

        // Check that we have a quote
        if (!$quote) {
            return false;
        }

        return true;
    }

    // Errors are handled as a javascript alert on the client side
    // This method gets run twice - once on the quote payment object, once on the order payment object
    // To make sure the values come across from quote payment to order payment, use the config node sales_convert_quote_payment
    public function validate()
    {
        parent::validate();

        // This returns Mage_Sales_Model_Quote_Payment, or the Mage_Sales_Model_Order_Payment
        $info = $this->getInfoInstance();

        return $this;
    }
}
