<?php

class Fintecture_Payment_Helper_Util extends Mage_Payment_Helper_Data
{
    const FINTECTURE_PAYMENT_PREFIX = 'FINTECTURE-';

    public function fillCartFromOrder($order)
    {
        $cart = Mage::getSingleton('checkout/cart');
        $items = $order->getItemsCollection();
        foreach ($items as $item) {
            try {
                $cart->addOrderItem($item);
            } catch (Mage_Core_Exception $e) {
                Mage::log($e->getMessage(), 3, 'fintecture.log');
            } catch (Exception $e) {
                Mage::helper('checkout')->__('Cannot add the item to shopping cart.');
                Mage::log($e->getMessage(), 3, 'fintecture.log');
            }
        }

        $cart->save();
    }

    public function getOrderByState($state)
    {
        $decoded_state = json_decode(utf8_encode(base64_decode($state)));
        $order_id = $decoded_state->order_id;
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($order_id);
        return [$order, $order_id];
    }

    public function getOrderState($payment_status, $get_status, $transaction_id)
    {
        // Check payment status
        if ($payment_status['meta']['status'] === $get_status) {
            if ($payment_status['data']['attributes']['communication'] === $transaction_id) {
                if ($payment_status['meta']['status'] === 'payment_created') {
                    return $this->mappedState('payment_created');
                } elseif ($payment_status['meta']['status'] === 'payment_pending') {
                    return $this->mappedState('payment_pending');
                } elseif ($payment_status['meta']['status'] === 'order_created') {
                    return $this->mappedState('order_created');
                }
            }
        }
        return $this->mappedState();
    }

    public function mappedState($status = '')
    {
        switch ($status) {
            case 'payment_created':
                return Mage_Sales_Model_Order::STATE_PROCESSING;
            case 'order_created':
                return 'order_created';
            case 'payment_pending':
                return Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
            case 'payment_unsuccessful':
                return Mage_Sales_Model_Order::STATE_CANCELED;
            default:
                return Mage_Sales_Model_Order::STATE_CANCELED;
        }
    }

    public function createPayload($order)
    {
        $data = $order->getData();
        $address = $order->getBillingAddress()->getData();
        $fullname = $data['customer_firstname'] . ' ' . $data['customer_lastname'];
        $amount = (string) round($data['grand_total'], 2); // keep only 2 decimals
        $communication = self::FINTECTURE_PAYMENT_PREFIX . $data['increment_id'];

        $payload = [
            'data' => [
                'type' => 'PAYMENT',
                'attributes' => [
                    'amount' => $amount,
                    'currency' => $data['base_currency_code'],
                    'communication' => $communication
                ]
            ],
            'meta' => [
                'psu_name' => $fullname,
                'psu_email' => $data['customer_email'],
                'psu_ip' => $data['remote_ip'],
                'psu_phone' => $address['telephone'],
                'psu_address' => [
                    'street' => $address['street'],
                    'complement' => $address['street'],
                    'zip' => (string) $address['postcode'],
                    'city' => $address['city'],
                    'country' => $address['country_id']
                ]
            ]
        ];

        if (!empty($address['company'])) {
            $payload['meta']['psu_company'] = $address['company'];
        }

        return $payload;
    }

    public function validSignature($private_key)
    {
        $body = file_get_contents('php://input');
        $private_key = openssl_pkey_get_private($private_key);

        $digestBody = 'SHA-256=' . base64_encode(hash('sha256', $body, true));
        $digestHeader = stripslashes($_SERVER['HTTP_DIGEST']);

        $signature = stripslashes($_SERVER['HTTP_SIGNATURE']);
        $signature = str_replace('"', '', $signature);
        $signature = explode(',', $signature)[3]; // 0: keyId, 1: algorithm, 2: headers, 3: signature
        $signature = explode('signature=', $signature)[1]; // just keep the part after "signature="
        openssl_private_decrypt(base64_decode($signature), $decrypted, $private_key, OPENSSL_PKCS1_OAEP_PADDING);

        $signingString = preg_split('/\n|\r\n?/', $decrypted);
        $digestSignature = str_replace('"', '', substr($signingString[1], 8)); // 0: date, 1: digest, 2: x-request-id

        // match the digest calculated from the received payload, the digest found in the headers and the digest decoded from the signature
        $match = $digestBody == $digestSignature && $digestBody == $digestHeader;
        return $match;
    }
}
