<?php

class Fintecture_Payment_Helper_Config extends Mage_Payment_Helper_Data
{
    public function getEnvironment()
    {
        $environment = Mage::getStoreConfig('payment/fintecture/environment');
        return $environment;
    }

    public function getAppInformation()
    {
        $environment = $this->getEnvironment();

        $config = [];

        if ($environment === 'production') {
            $config['app_id'] = Mage::getStoreConfig('payment/fintecture/production_app_id');
            $config['app_secret'] = Mage::getStoreConfig('payment/fintecture/production_app_secret');
            $config['private_key'] = Mage::getStoreConfig('payment/fintecture/production_private_key');
        } elseif ($environment === 'sandbox') {
            $config['app_id'] = Mage::getStoreConfig('payment/fintecture/sandbox_app_id');
            $config['app_secret'] = Mage::getStoreConfig('payment/fintecture/sandbox_app_secret');
            $config['private_key'] = Mage::getStoreConfig('payment/fintecture/sandbox_private_key');
        }

        if (array_key_exists('private_key', $config)) {
            $private_key_path = Mage::getBaseDir('media') . '/fintecture-uploads/' . $config['private_key'];
            $config['private_key'] = trim(file_get_contents($private_key_path));
        }

        if (!array_key_exists('app_id', $config) || !array_key_exists('app_secret', $config)
            || !array_key_exists('private_key', $config) || empty($config['app_id']) || empty($config['app_secret'])
            || empty($config['private_key'])) {
            return false;
        }

        return $config;
    }
}
