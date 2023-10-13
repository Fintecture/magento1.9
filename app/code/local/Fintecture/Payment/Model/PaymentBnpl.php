<?php

class Fintecture_Payment_Model_PaymentBnpl extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'bnpl_fintecture';

    protected $_formBlockType = 'fintecture_payment/form_paymentBnpl'; // This is the block that's displayed on the checkout
    protected $_infoBlockType = 'fintecture_payment/payment_infoBnpl'; // This is the block that's displayed on the checkout

    protected $_isInitializeNeeded = true;

    protected $_canUseInternal = true;

    protected $_isGateway = true; //  Is this payment method a gateway (online auth/charge) ?

    protected $_canAuthorize = true; // Can authorize online?

    protected $_canUseCheckout = true; // Can show this payment method as an option on checkout payment page?

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('fintecture/standard/redirect', array('_query' => 'method=bnpl', '_use_rewrite' => false));
    }

    // Use this to set whether the payment method should be available in only certain circumstances
    public function isAvailable($quote = null)
    {
        // Check that Fintecture BNPL is enabled
        if (!Mage::getStoreConfigFlag('payment/bnpl_fintecture/active')) {
            return false;
        }

        // Check that we have a quote
        if (!$quote) {
            return false;
        }

        // Check if the order amount is less than the amount required to display BNPL
        $cartAmount = (float) $quote->getGrandTotal();
        if ($cartAmount < (float) Mage::getStoreConfig('payment/bnpl_fintecture/minimun_amount_bnpl')) {
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
