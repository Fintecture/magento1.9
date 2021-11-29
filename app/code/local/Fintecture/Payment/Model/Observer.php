<?php

class Fintecture_Payment_Model_Observer
{
    public function adminSystemConfigChangedSectionPayment()
    {
        $stats = Mage::helper('fintecture_payment/stats');
        $stats->logAction('save');
    }
}
