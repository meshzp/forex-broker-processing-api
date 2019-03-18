<?php
namespace app\models;

use Yii;

class Ecommpay
{
    public static function getPaymentSystemNameByGroupId($id)
    {
        switch ($id) {
            case 1:
                return "Card";
            case 2:
                return "WebMoney";
            case 3:
                return "Yandex.Money";
            case 4:
                return "Foster Card";
            case 5:
                return "Moneta.ru";
            case 6:
                return "QIWI";
            case 7:
                return "Mobile";
            case 8:
                return "Alfa-click";
            case 9:
                return "Money@mail.ru";
            case 10:
                return "Interkassa";
            case 11:
                return "LiqPay";
            case 12:
                return "Privat24";
            case 13:
                return "W1";
            case 14:
                return "MoneyMail";
            case 15:
                return "Terminal";
            case 16:
                return "Bank Account";
            case 17:
                return "Moneybookers";
            case 18:
                return "SMS";
            case 19:
                return "Neteller";
            case 20:
                return "CreditPilot";
            case 22:
                return "Ukash";
            case 24:
                return "Svyaznoy";
            case 25:
                return "Euroset";
            case 26:
                return "OtherTerminal";
            case 27:
                return "CUP";
            case 28:
                return "Contact24";
            case 29:
                return "Comepay";
            case 30:
                return "PayPal";
            case 31:
                return "Boleto";
            case 32:
                return "SBOL";
            case 33:
                return "PSBOL";
            case 65:
                return "PaySec";
            case 67:
                return "Wechat";
            default:
                return "Unknown";
        }
    }

    public static function getPaymentSystemNameByTypeId($id)
    {
        switch ($id) {
            case 1:
            case 2:
            case 3:
            case 4:
            case 5:
            case 6:
            case 7:
            case 8:
            case 9:
                return "Card";
            case 10:
            case 11:
            case 12:
            case 13:
            case 14:
                return "WebMoney";
            case 16:
                return "Yandex.Money";
            case 15:
                return "Foster Card";
            case 17:
                return "Moneta.ru";
            case 18:
                return "QIWI";
            case 19:
            case 20:
            case 21:
            case 22:
            case 23:
            case 24:
                return "Mobile";
            case 25:
                return "Alfa-click";
            case 26:
                return "Money@mail.ru";
            case 27:
                return "Interkassa";
            case 28:
            case 29:
            case 30:
            case 31:
                return "LiqPay";
            case 32:
            case 33:
            case 34:
            case 35:
                return "Privat24";
            case 36:
            case 37:
            case 38:
            case 39:
                return "W1";
            case 40:
            case 41:
            case 42:
                return "MoneyMail";
            case 43:
            case 44:
                return "Terminal";
            case 45:
                return "Bank Account";
            case 46:
                return "Moneybookers";
            case 47:
            case 48:
                return "SMS";
            case 49:
                return "Neteller";
            case 50:
                return "CreditPilot";
            case 52:
                return "Ukash";
            case 56:
                return "Svyaznoy";
            case 57:
                return "Euroset";
            case 58:
                return "OtherTerminal";
            case 59:
                return "CUP";
            case 60:
                return "Contact24";
            case 61:
                return "Comepay";
            case 62:
                return "PayPal";
            case 63:
            case 73:
            case 74:
            case 75:
                return "Boleto";
            case 64:
                return "SBOL";
            case 65:
                return "PSBOL";
            case 99:
                return "PaySec";
            case 101:
                return "Wechat";
            default:
                return "Unknown";
        }
    }

    public static function mustBeUnique($ps)
    {
        $check = in_array($ps, [
            'SBOL',
            'Euroset',
            'Moneta.ru',
            'Svyaznoy',
        ]);

        if ($check) {
            return false;
        }

        return true;
    }

    private static function getPaymentLinkParamsByGroupId($id)
    {
        switch ($id) {
            case 1:     // Card
                return [
                    'link'  => Yii::$app->params['ecommpay_link_card'],
                    'action'=> 'payout',
                ];
            case 2:     // WebMoney
                return [
                    'link'  => Yii::$app->params['ecommpay_link_webmoney'],
                    'action'=> 'wmpayout',
                ];
            case 3:     // Yandex.Money
                return [
                    'link'  => Yii::$app->params['ecommpay_link_yandex'],
                    'action'=> 'ym_payout',
                ];
            case 6:     // QIWI
                return [
                    'link'  => Yii::$app->params['ecommpay_link_qiwi'],
                    'action'=> 'qiwi_payout',
                ];
            case 19:    // Neteller
                return [
                    'link'  => Yii::$app->params['ecommpay_link_neteller'],
                    'action'=> 'neteller_payout',
                ];
            case 27:    // China UnionPay
                return [
                    'link'  => Yii::$app->params['ecommpay_link_qwipi'],
                    'action'=> 'payout',
                ];
            case 65:    // China PaySec
                return [
                    'link'  => Yii::$app->params['ecommpay_link_paysec'],
                    'action'=> 'payout',
                ];
            case 67:    // China wechat
                return [
                    'link'  => Yii::$app->params['ecommpay_link_wechat'],
                    'action'=> 'payout',
                    'bank_id' => 40, //constant value for wechat
                ];
            default:
                return false;
        }
    }

    public static function getAuthKey()
    {
        $params = [
            'site_id'       => Yii::$app->params['ecommpay_site_id'],
            'site_login'    => Yii::$app->user->identity->nickname,
            'customer_ip'   => Yii::$app->request->userIP,
            'currency'      => "USD",
        ];
        $params['signature'] = self::generateSignature($params);

        $output = self::sendRequest(Yii::$app->params['ecommpay_link_auth'], $params);

        return json_decode($output, true);
    }

    public static function makePayout($data)
    {
        $bPayout = false;
        $linkParams = self::getPaymentLinkParamsByGroupId($data['payment_group_id']);
        if ($linkParams) {
            $sExternalId = $data['transaction_id'];
            $params = [
                'action'    => 'createorder',
                'site_id'   => Yii::$app->params['ecommpay_site_id'],
                'external_id'=> $sExternalId,
                'amount'    => $data['amount'],
                'currency'  => 'USD',
                'type_id'   => 2,
            ];
            $params['signature'] = self::generateSignature($params);

            $output = self::sendRequest(Yii::$app->params['ecommpay_link_op'], $params, true);
            $log = new AccountsMerchantsLog([
                'transaction_id'=> $sExternalId,
                'merchant'      => 'ecommpay',
                'direction'     => AccountsMerchantsLog::DIRECTION_OUT,
                'data'          => json_encode($params),
                'answer'        => $output,
            ]);
            $log->save();

            $result = json_decode($output, true);
            if (is_array($result) && isset($result['code']) && $result['code'] == 0) {
                $bPayout = true;
            }
            if (is_array($result) && isset($result['code']) && $result['code'] == 200) {
                $params = [
                    'action'      => 'order_info',
                    'site_id'     => Yii::$app->params['ecommpay_site_id'],
                    'external_id' => $sExternalId,
                    'type_id'     => 2,
                ];
                $params['signature'] = self::generateSignature($params);

                $output = self::sendRequest(Yii::$app->params['ecommpay_link_op'], $params, true);
                $log = new AccountsMerchantsLog([
                    'transaction_id'=> $sExternalId,
                    'merchant'      => 'ecommpay',
                    'direction'     => AccountsMerchantsLog::DIRECTION_OUT,
                    'data'          => json_encode($params),
                    'answer'        => $output,
                ]);
                $log->save();

                $result = json_decode($output, true);
                if (is_array($result) && isset($result['code']) && $result['code'] == 0 && isset($result['status_id']) && $result['status_id'] == 6) {
                    $sRandom = substr(md5(time().rand()), 0, 4);
                    $sExternalId = "{$data['transaction_id']}_{$sRandom}";
                    $params = [
                        'action'    => 'createorder',
                        'site_id'   => Yii::$app->params['ecommpay_site_id'],
                        'external_id'=> $sExternalId,
                        'amount'    => $data['amount'],
                        'currency'  => 'USD',
                    ];
                    $params['signature'] = self::generateSignature($params);

                    $output = self::sendRequest(Yii::$app->params['ecommpay_link_op'], $params, true);
                    $log = new AccountsMerchantsLog([
                        'transaction_id'=> $sExternalId,
                        'merchant'      => 'ecommpay',
                        'direction'     => AccountsMerchantsLog::DIRECTION_OUT,
                        'data'          => json_encode($params),
                        'answer'        => $output,
                    ]);
                    $log->save();

                    $result = json_decode($output, true);
                    if (is_array($result) && isset($result['code']) && $result['code'] == 0) {
                        $bPayout = true;
                    }
                } elseif (is_array($result) && isset($result['code']) && $result['code'] == 0 && isset($result['status_id']) && $result['status_id'] == 1) {
                    $bPayout = true;
                }
            }

            if ($bPayout) {
                $params = [
                    'action'         => $linkParams['action'],
                    'site_id'        => Yii::$app->params['ecommpay_site_id'],
                    'amount'         => $data['amount'],
                    'currency'       => 'USD',
                    'external_id'    => $sExternalId,
                    'comment'        => "withdrawal of funds from the account #{$data['account_id']}",
                ];
                /**
                 * @var $transaction AccountsTransactions
                 */
                $transaction = AccountsTransactions::findOne($sExternalId);
                $userId = $transaction->account->user->id;
                $lastUserIP = Yii::$app->db->createCommand("SELECT ip FROM web.users_login_log WHERE user_id = {$userId} ORDER BY date_attempted DESC LIMIT 1")->queryScalar();
                if ($data['payment_group_id'] == 6) {   // Для QIWI другой параметр для кошелька
                    $params['account_number'] = $data['customer_purse'];
                } elseif ($data['payment_group_id'] == 1) { // Card
                    $params['customer_ip'] = $lastUserIP;
                    $params['transaction_id'] = $data['customer_purse'];
                    $params['holder'] = substr($transaction->account->user->name_latin." ".$transaction->account->user->surname_latin, 0, 255);
                    /*$params['sender_first_name'] = substr($transaction->account->user->name, 0, 255);
                    $params['sender_last_name'] = substr($transaction->account->user->surname, 0, 255);
                    $params['sender_middle_name'] = substr($transaction->account->user->secname, 0, 255);
                    $params['sender_passport_number'] = substr("{$transaction->account->user->document_serial} {$transaction->account->user->document_number}", 0, 255);
                    $params['sender_passport_issue_date'] = substr($transaction->account->user->document_receivedate, 0, 255);
                    $params['sender_passport_issued_by'] = substr($transaction->account->user->document_issuedby, 0, 255);
                    $params['sender_phone'] = substr($transaction->account->user->mobile, -11);
                    $params['sender_birthdate'] = $transaction->account->user->birthdate;
                    $params['sender_address'] = substr($transaction->account->user->address, 0, 255);*/
                } elseif ($data['payment_group_id'] == 27) { // China UnionPay
                    $params['customer_ip'] = $lastUserIP;
                    $params['customer_name'] = "{$transaction->account->user->surname} {$transaction->account->user->name}";
                    $params['bank_code'] = $data['requisites']['bank_code'];
                    $params['bank_branch'] = $data['requisites']['bank_branch'];
                    $params['bank_account_number'] = $data['requisites']['bank_account_number'];
                    $params['bank_city'] = $data['requisites']['bank_city'];
                    $params['bank_province'] = $data['requisites']['bank_province'];
                } else {
                    $params['customer_purse'] = $data['customer_purse'];
                }
                $params['signature'] = self::generateSignature($params);

                $output = self::sendRequest($linkParams['link'], $params, true);
                $log = new AccountsMerchantsLog([
                    'transaction_id'=> $sExternalId,
                    'merchant'      => 'ecommpay',
                    'direction'     => AccountsMerchantsLog::DIRECTION_OUT,
                    'data'          => json_encode($params),
                    'answer'        => $output,
                ]);
                $log->save();

                $result = json_decode($output, true);

                return $result;
            }
        }

        return false;
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
        $signature .= ";" . Yii::$app->params['ecommpay_salt'];

        return sha1($signature);
    }

    private static function sendRequest($url, $params, $post = false)
    {
        $ch = curl_init();
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        } else {
            $url .= "?" . http_build_query($params);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }
}