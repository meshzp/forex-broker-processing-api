<?php
namespace app\models;

use Yii;

class Accentpay
{
    const ACCENTPAY_SITE_ID = 2989;
    const ACCENTPAY_SALT = '0138454c847e7a3b783856d16702742a471671e5';
    const ACCENTPAY_LINK_AUTH = 'https://treasury-sandbox.ecommpay.com/authlink/obtain';
    const ACCENTPAY_LINK_OPEN = 'https://treasury-sandbox.ecommpay.com/open';

    public static function getAuthKey()
    {
        $params = [
            'site_id'       => self::ACCENTPAY_SITE_ID,
            'site_login'    => Yii::$app->user->identity->nickname,
            'customer_ip'   => Yii::$app->request->userIP,
            'currency'      => "USD",
        ];
        $params['signature'] = self::generateSignature($params);

        $output = self::sendRequest(self::ACCENTPAY_LINK_AUTH, $params);

        return json_decode($output, true);
    }

    public static function generateSignature($params, $with_empty = false)
    {
        ksort($params);
        $signatureArray = [];
        foreach ($params as $key => $value) {
            if ((strlen($value)>0 || $with_empty) && $key != 'signature') {
                $signatureArray[] = "{$key}:{$value}";
            }
        }
        $signature = implode(";", $signatureArray);
        $signature .= ";" . self::ACCENTPAY_SALT;

        return sha1($signature);
    }

    private static function sendRequest($url, $params)
    {
        $ch = curl_init();
        $url .= "?".http_build_query($params);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }
}