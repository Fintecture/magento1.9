<?php

class Fintecture_Payment_StandardController extends Mage_Core_Controller_Front_Action
{
    // The redirect action is triggered when someone places an order
    public function redirectAction()
    {
        $order = new Mage_Sales_Model_Order();
        $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $order->loadByIncrementId($orderId);

        // Create state param with order id
        $state = ['order_id' => $orderId];
        $state = base64_encode(json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $params = http_build_query([
            'state' => $state
        ]);

        // Create payload
        $util = Mage::helper('fintecture_payment/util');
        $payload = $util->createPayload($order);

        // Get redirect URL
        $curl = Mage::helper('fintecture_payment/curl');
        $connect = $curl->connect($payload, $params);
        if (!$connect) {
            Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure');
        } else {
            Mage::app()->getResponse()->setRedirect($connect)->sendResponse();
        }
    }

    // The response action is triggered when your gateway sends back a response after processing the customer's payment
    public function responseAction()
    {
        $request = $this->getRequest();
        $params = [
            'state' => $request->getParam('state') ? $request->getParam('state') : '',
            'status' => $request->getParam('status') ? $request->getParam('status') : '',
            'session_id' => $request->getParam('session_id') ? $request->getParam('session_id') : ''
        ];

        // Get order by state
        $util = Mage::helper('fintecture_payment/util');
        if (!empty($params['state'])) {
            list($order, $order_id) = $util->getOrderByState($params['state']);

            // Set Fintecture Session ID
            if (!empty($params['session_id'])) {
                $payment = $order->getPayment();
                $payment->setAdditionalInformation('fintecture_session_id', $params['session_id']);
                $payment->save();
            }
        }

        $error = empty($params['state']) || empty($params['status']) || empty($params['session_id'] ||
            !in_array($params['status'], ['payment_created', 'payment_pending']));

        if (!$error) {
            $curl = Mage::helper('fintecture_payment/curl');

            Mage::getSingleton('checkout/session')->setLastQuoteId($order->getQuoteId());
            Mage::getSingleton('checkout/session')->setLastOrderId($order->getId())->setLastRealOrderId($order->getIncrementId());

            $payment_status = $curl->getPaymentStatus($params['session_id']);
            $transaction_id = $util::FINTECTURE_PAYMENT_PREFIX . $order_id;

            // Update the order's state with given status
            $state = $util->getOrderState($payment_status, $params['status'], $transaction_id);
            $order->setState($state, true);

            if ($state === Mage_Sales_Model_Order::STATE_PROCESSING) {
                // Payment was successful, so update the order's state, send order email and move to the success page
                $order->setTotalPaid($order->getGrandTotal());
                $order->sendNewOrderEmail();
                $order->setEmailSent(true);
                $order->save();

                // Disable quote
                $quoteId = $order->getQuoteId();
                $quote = Mage::getModel('sales/quote')->load($quoteId);
                $quote->setIsActive(0)->save();

                if ($order->canInvoice()) {
                    $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

                    $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
                    $invoice->register();

                    $transactionSave = Mage::getModel('core/resource_transaction')
                        ->addObject($invoice)
                        ->addObject($invoice->getOrder());
                    $transactionSave->save();

                    $invoice->getOrder()->setIsInProcess(true);
                    $order->addStatusHistoryComment(
                        'Auto Invoice generated.',
                        Mage_Sales_Model_Order::STATE_PROCESSING
                    )->setIsCustomerNotified(true);
                } else {
                    $order->addStatusHistoryComment('Fintecture: Order cannot be invoiced.', false);
                }
                $order->save();

                Mage::getSingleton('checkout/session')->setLastSuccessQuoteId($order->getQuoteId());
                Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success');
            } elseif ($state === Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                $order->save();

                // Disable quote
                $quoteId = $order->getQuoteId();
                $quote = Mage::getModel('sales/quote')->load($quoteId);
                $quote->setIsActive(0)->save();

                Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success');
                Mage::getSingleton('core/session')->addNotice($this->__('Your payment is being validated by your bank.'));
            } else {
                // There is a problem in the response we got
                $this->cancelAction();
                Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure');
            }
        } else {
            // There is a problem in the response we got
            $this->cancelAction();
            Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure');
        }
    }

    // The cancel action is triggered when an order is to be holded
    public function cancelAction()
    {
        if (Mage::getSingleton('checkout/session')->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
            if ($order->getId()) {
                // Flag the order as 'holded' and save it
                $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Gateway has declined the payment.')->save();
            }

            // Restore cart
            $util = Mage::helper('fintecture_payment/util');
            $util->fillCartFromOrder($order);
        }
    }

    // The paymentcreated action is triggered as a webhook by Fintecture to update order information
    public function paymentcreatedAction()
    {
        $util = Mage::helper('fintecture_payment/util');
        $config = Mage::helper('fintecture_payment/config');
        $app = $config->getAppInformation();
        if (!$app || !$util->validSignature($app['private_key'])) {
            http_response_code(401);
            exit('invalid_signature');
        }

        $params = [
            'state' => $this->getRequest()->getParam('state') ? $this->getRequest()->getParam('state') : '',
            'status' => $this->getRequest()->getParam('status') ? $this->getRequest()->getParam('status') : '',
            'session_id' => $this->getRequest()->getParam('session_id') ? $this->getRequest()->getParam('session_id') : ''
        ];

        $order = $util->getOrderByState($params['state'])[0];
        if (!$order) {
            http_response_code(400);
            exit('invalid_cart');
        }

        // Set Fintecture Session ID
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('fintecture_session_id', $params['session_id']);
        $payment->save();

        // Update the order's state with given status
        $newState = $util->mappedState($params['status']);
        $order->setState($newState, true);

        // Send mail from pending -> processing
        if ($order->getState() === Mage_Sales_Model_Order::STATE_PENDING_PAYMENT &&
            $newState === Mage_Sales_Model_Order::STATE_PROCESSING) {
            // Payment was successful, so update the order's state and send order email
            $order->setTotalPaid($order->getGrandTotal());
            $order->sendNewOrderEmail();
            $order->setEmailSent(true);
            $order->save();

            if ($order->canInvoice()) {
                $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
                $invoice->register();

                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
                $transactionSave->save();

                $invoice->getOrder()->setIsInProcess(true);
                $order->addStatusHistoryComment(
                    'Auto Invoice generated.',
                    Mage_Sales_Model_Order::STATE_PROCESSING
                )->setIsCustomerNotified(true);
            } else {
                $order->addStatusHistoryComment('Fintecture: Order cannot be invoiced.', false);
            }
        }

        $order->save();

        exit;
    }

    // The connectiontest action is triggered to test app credentials in admin
    public function connectiontestAction()
    {
        $environment = Mage::app()->getRequest()->getParam('environment');
        $app_id = Mage::app()->getRequest()->getParam('app_id');
        $app_secret = Mage::app()->getRequest()->getParam('app_secret');
        $private_key = trim(Mage::app()->getRequest()->getParam('private_key'));

        $curl = Mage::helper('fintecture_payment/curl');
        $token = $curl->getAccessTokenTest($environment, $app_id, $app_secret, $private_key);
        if (!$token) {
            http_response_code(400);
        }
        exit;
    }

    // The stats action is triggered to send telemetry
    public function checkoutstatsAction()
    {
        $stats = Mage::helper('fintecture_payment/stats');
        $stats->logAction('checkout');
        exit;
    }
}
