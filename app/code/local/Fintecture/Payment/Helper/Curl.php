<?php

class Fintecture_Payment_Helper_Curl extends Mage_Payment_Helper_Data
{
    const FINTECTURE_SANDBOX_API_URL = 'https://api-sandbox.fintecture.com/';
    const FINTECTURE_PRODUCTION_API_URL = 'https://api.fintecture.com/';
    const FINTECTURE_MAX_RETRIES = 3;

    /**
     * This function is used to make post/get queries (post as default)
     *
     * @param string $url
     * @param string|array $body
     * @param array $headers
     * @param string $method
     * @param integer|void $timeout
     * @return array|false
     */
    public function makeQuery($url, $body, $headers, $method = 'POST', $environment = null, $timeout = 5)
    {
        $ch = curl_init($this->getAPIUrl($url, $environment));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $data = curl_exec($ch);

        if ($errno = curl_errno($ch)) {
            $error_message = curl_strerror($errno);
            Mage::log($error_message, 3, 'fintecture.log');
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return ($http_code >= 200 && $http_code < 300) ? json_decode($data, true) : false;
    }

    public function getAccessToken($app = null)
    {
        if (!$app) {
            $config = Mage::helper('fintecture_payment/config');
            $app = $config->getAppInformation();
            if (!$app) {
                return false;
            }
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($app['app_id'] .':'. $app['app_secret']),
        ];

        $payload = 'grant_type=client_credentials&app_id=' . $app['app_id'] . '&scope=PIS';

        $token = $this->makeQuery('oauth/accesstoken', $payload, $headers);
        if (is_array($token) && !array_key_exists('access_token', $token)) {
            return false;
        }
        return $token['access_token'];
    }

    public function getAccessTokenTest($environment, $app_id = '', $app_secret = '', $private_key = '')
    {
        $config = Mage::helper('fintecture_payment/config');
        $app = $config->getAppInformation();

        if (!empty($app_id)) {
            $app['app_id'] = $app_id;
        }
        if (!empty($app_secret)) {
            $app['app_secret'] = $app_secret;
        }
        if (!empty($private_key)) {
            $app['private_key'] = $private_key;
        }

        $crypto = Mage::helper('fintecture_payment/crypto');
        $url = '/oauth/secure/accesstoken';

        $body = [
            'grant_type' => 'client_credentials',
            'app_id' => $app['app_id'],
            'scope' => 'PIS'
        ];

        $headers = [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($app['app_id'] . ':' . $app['app_secret']),
            'app_id: '. $app['app_id'],
            'Date: ' . gmdate('D, d M Y H:i:s \G\M\T', time()),
            'X-Request-ID: ' . $crypto->uuid4(),
            'Digest: SHA-256=' .  $crypto->hashBase64($body)
        ];

        $signature = $crypto->createSignatureHeader(array_merge(['(request-target): post ' . $url], $headers), $app);
        if (!$signature) {
            return false;
        }

        array_push($headers, 'Signature: ' . $signature);

        $body = http_build_query($body, '', '&'); // string with body data separated by &
        $token = $this->makeQuery($url, $body, $headers, 'POST', $environment);
        if (!$token || (is_array($token) && !array_key_exists('access_token', $token))) {
            return false;
        }
        return true;
    }

    /**
     * This function is used to create a link of payment
     */
    public function connect($payload, $params, $method)
    {
        $config = Mage::helper('fintecture_payment/config');
        $app = $config->getAppInformation();
        if (!$app) {
            return false;
        }

        $token = $this->getAccessToken($app);
        if (!$token) {
            return false;
        }

        if ($method && $method === 'bnpl') {
            $payload['meta']['type'] = 'BuyNowPayLater';
        }

        $url = '/pis/v2/connect?' . $params;

        $crypto = Mage::helper('fintecture_payment/crypto');

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
            'Date: ' . gmdate('D, d M Y H:i:s \G\M\T', time()),
            'X-Request-ID: ' . $crypto->uuid4(),
            'Digest: SHA-256=' . $crypto->hashBase64($payload),
        ];

        $signature = $crypto->createSignatureHeader(array_merge(['(request-target): post ' . $url], $headers), $app);
        if (!$signature) {
            return false;
        }

        array_push($headers, 'Signature: ' . $signature);

        $payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $connect = $this->makeQuery($url, $payload, $headers);
        if (!isset($connect['meta']['url'])) {
            return false;
        }
        return $connect['meta']['url'];
    }

    public function getPaymentStatus($session_id)
    {
        $config = Mage::helper('fintecture_payment/config');
        $app = $config->getAppInformation();
        if (!$app) {
            return 'no_config';
        }

        $crypto = Mage::helper('fintecture_payment/crypto');
        $url = '/pis/v2/payments/' . $session_id;

        $attempts = 0;
        do {
            try {
                $token = $this->getAccessToken($app);
                if (!$token) {
                    return 'no_token';
                }

                $headers = [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $token,
                    'Date: ' . gmdate('D, d M Y H:i:s \G\M\T', time()),
                    'X-Request-ID: ' . $crypto->uuid4()
                ];

                $signature = $crypto->createSignatureHeader(array_merge(['(request-target): get ' . $url], $headers), $app);
                if (!$signature) {
                    return 'no_signature';
                }
                array_push($headers, 'Signature: ' . $signature);

                $response = $this->makeQuery($url, null, $headers, 'GET');

                if (!isset($response['meta']['status'])) {
                    // Will go in catch
                    throw new \Exception('No status property');
                }
                return $response;
            } catch (\Exception $e) {
                $attempts++;
                if ($attempts == self::FINTECTURE_MAX_RETRIES) {
                    return 'no_response';
                }
                sleep(1);
                continue;
            }
            break;
        } while ($attempts < self::FINTECTURE_MAX_RETRIES);
    }

    public function getAPIUrl($url, $environment)
    {
        if (substr($url, 0, 4) === 'http') {
            return $url;
        }

        if (!$environment) {
            $config = Mage::helper('fintecture_payment/config');
            $environment = $config->getEnvironment();
        }

        if ($environment === 'production') {
            return self::FINTECTURE_PRODUCTION_API_URL . $url;
        } else {
            return self::FINTECTURE_SANDBOX_API_URL . $url;
        }
    }
}
