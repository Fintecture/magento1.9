<?php

class Fintecture_Payment_Block_Form_PaymentBnpl extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();

        $parse = parse_url(Mage::getBaseUrl());
        $host = $parse['scheme'] . '://' . $parse['host'];

        $this->setData('host', $host)
            ->setTemplate('fintecture_payment/form/payment_bnpl.phtml')
            ->setMethodTitle($this->__('Buy Now, Pay Later. Without fee.'))
            ->setCustomText($this->__('Benefit from a 30 day payment term for your professional purchases.'));

        $fintecture_logo = Mage::getConfig()->getBlockClassName('core/template');
        $fintecture_logo = new $fintecture_logo();
        $fintecture_logo->setTemplate('fintecture_payment/form/logo.phtml');
        $this->setMethodLabelAfterHtml($fintecture_logo->toHtml());
    }
    
    public function getCustomText()
    {
        return $this->customText;
    }

    public function setCustomText($text)
    {
        $this->customText = $text;
        return $this;
    }
}
