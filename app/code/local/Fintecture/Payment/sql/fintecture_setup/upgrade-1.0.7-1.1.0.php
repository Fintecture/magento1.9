<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$configPath = 'payment/fintecture/show_logo';

// Delete the configuration value
$installer->deleteConfigData($configPath);

// Create new status
$status = 'order_created';
$installer->run("
    INSERT INTO {$this->getTable('sales_order_status')} (status, label) VALUES ('$status', 'Order Created');
");

$installer->endSetup();