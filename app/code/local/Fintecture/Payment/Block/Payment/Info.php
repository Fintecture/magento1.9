<?php

class Fintecture_Payment_Block_Payment_Info extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('fintecture_payment/info.phtml');
    }

    public function getPaymentServiceTitle()
    {
        return $this->getMethod()->getTitle();
    }

    public function getPaymentSessionId()
    {
        $order = $this->getInfo()->getOrder();
        $payment = $order ? $order->getPayment() : null;
        $sessionId = $payment ? $payment->getAdditionalInformation('fintecture_session_id') : null;

        return $sessionId;
    }
}
