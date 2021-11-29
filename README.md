# Fintecture Payment module for Magento 1.9

Fintecture is a Fintech that has a payment solution via bank transfer available at https://www.fintecture.com.

## Requirements

- PHP 5.6
- Magento 1.9

## Installation

- Connect to your server (FTP/SSH...)
- Browse to **/var/www/html** (may be different for you, depending on where you have installed Magento 1)
- Copy/paste **app** folder (but don't delete yours, it should just add new files)

## Activation
- Enable the module in System -> Payment Methods

## Configuration

Go to Stores > Configuration > Sales > Payment methods.

- Select environment (test/production)
- Fill API key, API secret and API private key based on the selected environment (https://console.fintecture.com/)
- Choose to display Fintecture logo or not
- Test your connection (if everything is ok you should have a success message)
- Don't forget to enable the payment method unless it won't be displayed on front-end
- Empty your cache on System > Cache Management > Flush Magento Cache & Flush Cache Storage