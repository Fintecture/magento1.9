<?php

class Fintecture_Payment_Model_Environment
{
    public function toOptionArray()
    {
        return [
            [
                'value' => 'sandbox',
                'label' => Mage::helper('fintecture_payment/data')->__('Test')
            ],
            [
                'value' => 'production',
                'label' => Mage::helper('fintecture_payment/data')->__('Production')
            ]
        ];
    }
}
