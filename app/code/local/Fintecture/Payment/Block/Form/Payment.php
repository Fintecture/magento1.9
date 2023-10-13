<?php

class Fintecture_Payment_Block_Form_Payment extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();

        $parse = parse_url(Mage::getBaseUrl());
        $host = $parse['scheme'] . '://' . $parse['host'];

        $this->setData('host', $host)
            ->setTemplate('fintecture_payment/form/payment.phtml')
            ->setMethodTitle($this->__('Instant bank payment'));

        $fintecture_logo = Mage::getConfig()->getBlockClassName('core/template');
        $fintecture_logo = new $fintecture_logo();
        $fintecture_logo->setTemplate('fintecture_payment/form/logo.phtml');
        $this->setMethodLabelAfterHtml($fintecture_logo->toHtml());
    }
}
