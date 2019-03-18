<?php

namespace app\models;

use Yii;

class Bitaps
{
    const MERCHANT_NAME = 'bictoin_bitaps';
    /**
     * Redeem code is like a Bitcoin wallet. You can receive payments to generated Redeem Code Address and transfer money by query.
     *
     * @param $confirmations integer the desired number of confirmations
     *
     * @return array
     *
     */
    public function createRedeemCheck($confirmations = 3)
    {
        $url     = 'https://bitaps.com/api/create/redeemcode';
        $data    = $this->sendRequest($url, ['confirmations' => $confirmations]);
        $respond = json_decode($data, true);

        /*
         *  respond example
         * {
         *       "address": "1CZgyfDsA5Bob2wVyTbCxSGXwSAEur4LYB",
         *       "redeem_code": "BTCvNqa8knrVkGyYmrfMTRtU77xrnPWiiH8n8accdEm9W1EHsRbCz",
         *       "invoice": "invPvfHny5QnffugyKgwYvUeXjCUgz4ywhxzANDcTHa7amWLKbjnb"
         *  }
         */
        return $respond ? $respond : [];
    }

    /**
     * @param $url string
     * @param $params array[mixed]
     * @param bool $post bool
     *
     * @return mixed
     */
    private function sendRequest($url, $params = [], $post = false)
    {
        $ch = curl_init();
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        } else {
            $url .= '?' . http_build_query($params);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    /**
     * @param $redeemcode string Redeem Code Hash
     *
     * @return array
     */
    public function getRedeemCodeInfo($redeemcode)
    {
        $url     = 'https://bitaps.com/api/get/redeemcode/info';
        $params  = json_encode(['redeemcode' => $redeemcode]);
        $data    = $this->sendRequest($url, $params, true);
        $respond = json_decode($data, true);

        /*
         *  respond example
         * {
         *      "address": "{address}",
         *      "balance": {amount},
         *      "pending_balance": {amount},
         *      "paid_out": {amount}
         *  }
         */

        return $respond ? $respond : [];
    }

    /**
     * @param $payment_params array:
     *      redeemcode  Redeem Code
     *      address     Bitcoin receiver address
     *      amount  	'amount' or 'All available'. Amount in Satoshi. In case if amount = 'All available', the remaining balance will be sent to specified address.
     *      fee_level	'high', 'medium', 'low' miner fee level, optional, default = 'low'
     *      custom_fee	Custom fee Satoshi per byte. If choose it, fee_level will be ignored.
     * @return array
     */
    public function payFromRedeemCode($payment_params)
    {
        $url     = 'https://bitaps.com/api/use/redeemcode';
        $respond = [];
        if (isset($payment_params['redeemcode'], $payment_params['address'], $payment_params['amount'])) {
            $params = [
                'redeemcode' => $payment_params['redeemcode'],
                'address'    => $payment_params['address'],
                'amount'     => $payment_params['amount'],
                'fee_level'  => isset($payment_params['fee_level']) ? $payment_params['fee_level'] : 'low', //miner fee level
//                'custom_fee' => isset($payment_params['custom_fee']) ? $payment_params['custom_fee'] : null, //Custom fee Satoshi per byte. If choose it, fee_level will be ignored.
            ];
            $sParams = json_encode($params);
            $data    = $this->sendRequest($url, $sParams, true);
            $respond = json_decode($data, true);
            /*
             *  {
             *     "tx_hash": "{transaction hash}",
             *  }
             */
        }
        return $respond ? $respond : [];
    }

    /**
     * With Redeem Code you can pay up to 250 receivers. Also you can addmessage to this transaction with maximum length 80 bytes. This function supplied with BIP 70 and BIP74.
     * Limits:
     * Maximum input coins 250
     * Maximum receivers 250
     * Recommended minimal sending amount 30 000 Satoshi
     * Dust limit 546 Satoshi
     * @param $payment_params array
     * {
     *  "redeemcode": {code},
     *  "data": {data}, OP_Return message in transaction, max length 80 bytes, optional field, default empty
     *  "fee_level": {fee_level},
     *  "payment_list":  [
     *  {"address": {address},
     *  "amount": {amount}}, Amount in Satoshi
     *      ... # up to 250 addresses
     *      ]
     *  }
     * @param $rules array
     * @return array
     */
    public function massPaymentFromRedeemCode($payment_params, $rules = [])
    {
        $url = 'https://bitaps.com/api/use/redeemcode/list';
        $respond = [];
        if (isset($payment_params['redeemcode'], $payment_params['payment_list']) && is_array($payment_params['payment_list'])) {
            $params = [
                'redeemcode' => $payment_params['redeemcode'],
                'data'       => isset($payment_params['data']) ? $payment_params['data'] : '',
                'fee_level'  => isset($payment_params['fee_level']) ? $payment_params['fee_level'] : 'low', //miner fee level
                'payment_list' => $payment_params['payment_list'],
            ];
            $data    = $this->sendRequest($url, $params, true);
            $respond = json_decode($data, true);
            /*
             *  {
             *     "tx_hash": "{transaction hash}",
             *  }
             */
        }
        return $respond ? $respond : [];
    }

    /**
     * @param $address propper Bitcion address
     *
     * @return array
     */
    public function getAddressInfo($address = '')
    {
        $url = 'https://bitaps.com/api/address/';

        /*
         * {
         *      'balance':{balance},
         *      'confirmed_balance':{only confirmed balance},
         *      'received':{total amount received},
         *      'sent':{total amount sent},
         *      'pending':{pending amount},
         *      'multisig_received':{count},
         *      'multisig_sent':{count},
         *      'tx_received':{count},
         *      'tx_sent':{count},
         *      'tx_multisig_received':{count},
         *      'tx_multisig_sent':{count},
         *      'tx_unconfirmed':{count},
         *      'tx_invalid':{count}
         * }
         */
        if ($address) {
            $url  .= $address;
            $data = $this->sendRequest($url, [], false);
            $respond = json_decode($data, true);
        }
        return $respond ? $respond : [];
    }

}