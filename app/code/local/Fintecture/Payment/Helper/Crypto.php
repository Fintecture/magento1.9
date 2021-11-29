<?php

class Fintecture_Payment_Helper_Crypto extends Mage_Payment_Helper_Data
{
    public function hashBase64($body)
    {
        // Set to true to get a binary format outout (default hex)
        return base64_encode(hash('sha256', json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true));
    }

    public function uuid4()
    {
        $data = openssl_random_pseudo_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function createSignatureHeader($headers, $app)
    {
        $dictionary_header = [];

        foreach ($headers as &$value) {
            $key_value = explode(': ', $value);
            $dictionary_header[$key_value[0]] = $key_value[1];
        }

        $signing = [];
        $header = [];
        $signed_header_parameter_list = ['(request-target)', 'Date', 'Digest', 'X-Request-ID'];

        foreach ($signed_header_parameter_list as &$param) {
            if (array_key_exists($param, $dictionary_header)) {
                $param_low = strtolower($param);
                array_push($signing, $param_low . ': ' . $dictionary_header[$param]);
                array_push($header, $param_low);
            }
        }

        $string_data = implode("\n", $signing);

        if (!openssl_sign($string_data, $signature, $app['private_key'], OPENSSL_ALGO_SHA256)) {
            return false;
        }

        $signature_base64 = base64_encode($signature);

        return 'keyId="' . $app['app_id'] . '",algorithm="rsa-sha256",headers="' . implode(' ', $header) . '",signature="' . $signature_base64 . '"';
    }
}
