<?php

class Fintecture_Payment_Block_Adminhtml_System_Config_Form_Button extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('fintecture_payment/system/config/button.phtml');
    }

    protected function _getElementHtml($element)
    {
        return $this->_toHtml();
    }

    public function getAjaxCheckUrl()
    {
        return Mage::helper('adminhtml')->getUrl('fintecture/standard/connectiontest');
    }

    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
            'id' => 'fintecture_button',
            'label' => $this->helper('adminhtml')->__('Test connection'),
            'onclick' => 'javascript:check(); return false;'
        ]);

        return $button->toHtml();
    }
}
