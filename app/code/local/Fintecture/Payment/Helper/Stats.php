<?php

class Fintecture_Payment_Helper_Stats extends Mage_Payment_Helper_Data
{
    const FINTECTURE_PLUGIN_VERSION = '1.0.2';
    const FINTECTURE_STATS_URL = 'https://api.fintecture.com/ext/v1/activity';

    public function logAction($action)
    {
        $body = array_merge(
            $this->getSystemInfos(),
            ['action' => $action]
        );
        $body = json_encode($body);

        $headers = [
            'Content-Type: application/json',
        ];

        $curl = Mage::helper('fintecture_payment/curl');
        return $curl->makeQuery(self::FINTECTURE_STATS_URL, $body, $headers);
    }

    private function getSystemInfos()
    {
        $enabled = Mage::helper('core')->isModuleEnabled('Fintecture_Payment');
        $active = Mage::getStoreConfig('payment/fintecture/active');
        $isProduction = Mage::getStoreConfig('payment/fintecture/environment') === 'production';
        $allActivePaymentMethods = Mage::getModel('payment/config')->getActiveMethods();

        return [
            'type' => 'php-mg-1',
            'php_version' => PHP_VERSION,
            'shop_name' => Mage::app()->getStore()->getName(),
            'shop_domain' => parse_url(Mage::getBaseUrl(), PHP_URL_HOST), // just take the domain
            'shop_cms' => 'magento',
            'shop_cms_version' => Mage::getVersion(),
            'module_version' => self::FINTECTURE_PLUGIN_VERSION,
            'shop_payment_methods' => count($allActivePaymentMethods),
            'module_enabled' => $enabled && $active,
            'module_production' => $isProduction,
            'module_sandbox_app_id' => Mage::getStoreConfig('payment/fintecture/sandbox_app_id'),
            'module_production_app_id' => Mage::getStoreConfig('payment/fintecture/production_app_id'),
            'module_branding' => Mage::getStoreConfig('payment/fintecture/show_logo')
        ];
    }
}
