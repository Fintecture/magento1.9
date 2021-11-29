<?php

class Fintecture_Payment_Model_BankType
{
    public function toOptionArray()
    {
        return [
            [
                'value' => 'all',
                'label' => Mage::helper('fintecture_payment/data')->__('All')
            ],
            [
                'value' => 'retail',
                'label' => Mage::helper('fintecture_payment/data')->__('Retail')
            ],
            [
                'value' => 'corporate',
                'label' => Mage::helper('fintecture_payment/data')->__('Corporate')
            ]
        ];
    }
}
