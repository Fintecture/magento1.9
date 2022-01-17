<?php

class Fintecture_Payment_Block_Payment_Info extends Mage_Payment_Block_Info
{
    const METHOD_TITLE = 'Fintecture';

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('fintecture_payment/info.phtml');
    }

    public function getPaymentServiceTitle()
    {
        return self::METHOD_TITLE;
    }
}
