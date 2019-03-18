<?php
/**
 * Created by PhpStorm.
 * User: artem_000
 * Date: 3/16/2017
 * Time: 1:31 PM
 */

namespace app\models;

class RpnPayForm extends \yii\db\ActiveRecord
{
    const SIFNATURE_METHOD = 1;
    const CURRENCY         = 156;
    public  $OrderSno;
    public  $BankName;
    public  $SubBranch;
    public  $BankAccountName;
    public  $BankCardNo;
    public  $Province; // in CNY
    public  $Area; // array of parameters for single payout
    public  $Amount; //MD5, 2 - SHA1
    private $_Detail = []; // CNY
    private $_signString;

    public function sendPayotRequest()
    {

        $post = [
            'Data' => $this->generateJson(),
        ];

        $ch = curl_init();
        curl_setopt($cURL, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_URL, $url);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    private function generateJson()
    {

        $array = [
            'Withdrawal' => $this->getHead(),
            'Detail'     => $this->_Detail,
        ];

        return json_encode($array);
    }

    /**
     * @return string
     */
    private function getHead()
    {
        $head_arr = [
            'MerchantID'                       => Yii::$app->params['rpn_pay_merchant_id'],
            /*скорее всего это сквозной идентификатор группы выплаты, т.к. у нас пока по одной транзакции, то пока номер выплаты и будет номером транзакции*/
            'Merchant_withdrawal_batch_number' => $this->OrderSno,
            'Total_count'                      => count($this->_Detail),
            'Total_amount'                     => self::getSumByKey($this->_Detail, 'Amount'),
            'Withdrawal_Date'                  => date('YYYY-MM-DD HH:MM:SS'),
            'Signature method'                 => self::SIFNATURE_METHOD,
            'Currency'                         => self::CURRENCY,
            'Singvalue'                        => $this->generateSignature(),
            'fronturl'                         => Yii::$app->params['rpn_pay_fronturl'],
            'backendurl'                       => Yii::$app->params['rpn_pay_backendurl'],
        ];

        return implode(',', $head_arr);
    }

    public function generateSignature()
    {
        $data = [
            'mid'                              => Yii::$app->params['rpn_pay_merchant_id'],
            'merchant_withdrawal_batch_number' => $this->OrderSno,
            'total_count'                      => count($this->_Detail),
            'total_amount'                     => self::getSumByKey($this->_Detail, 'Amount'),
            'withdrawal_date'                  => date('YYYY-MM-DD HH:MM:SS'),
            'APIKEY'                           => Yii::$app->params['rpn_pay_secret'],
        ];

        $this->_signString = implode('|', array_values($data));
        // SignValue method: MerchantID |Merchant withdrawal batch|number| Total count| Total amount|Withdrawal Date|APIKEY
        $this->_signString = md5($this->_signString);

        return $this->_signString;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['BankName', 'SubBranch', 'BankAccountName', 'BankCardNo', 'Amount', 'OrderSno'], 'required'],
            [['Amount'], 'number', 'min' => 10],
            [['OrderSno', 'SubBranch', 'BankCardNo',], 'integer'],
            [['BankName', 'BankAccountName', 'Province', 'Area'], 'filter', 'filter' => 'trim'],
            [['BankName', 'BankAccountName', 'Province', 'Area'], 'string', 'min' => 1, 'max' => 16],
            [['BankName', 'BankAccountName', 'Province', 'Area'], 'match', 'pattern' => '/^[\.\sa-zA-Z\x{0400}-\x{04FF}-\p{Han}]+$/u'],
        ];
    }

    /**
     *
     * @return array
     */
    public function addDetailPayout()
    {
        $TransactionRequisites = TransactionsRequisites::findOne(['transaction_id' => $this->OrderSno]);
        if ($TransactionRequisites) {
            $requisites            = json_decode($TransactionRequisites->requisites);
            $this->BankName        = isset($requisites['BankName']) ? $requisites['BankName'] : null;
            $this->SubBranch       = isset($requisites['SubBranch']) ? $requisites['SubBranch'] : null;
            $this->BankAccountName = isset($requisites['BankAccountName']) ? $requisites['BankAccountName'] : null;
            $this->BankCardNo      = isset($requisites['BankCardNo']) ? $requisites['BankCardNo'] : null;
            $this->Province        = isset($requisites['Province']) ? $requisites['Province'] : null;
            $this->Area            = isset($requisites['Area']) ? $requisites['Area'] : null;
            $this->Amount          = isset($requisites['Amount']) ? $requisites['Amount'] : null;
            if ($this->validate()) {
                array_push($this->_Detail, [
                    'OrderSno'        => $this->OrderSno,
                    'BankName'        => $this->BankName,
                    'SubBranch'       => $this->SubBranch,
                    'BankAccountName' => $this->BankAccountName,
                    'BankCardNo'      => $this->BankCardNo,
                    'Province'        => $this->Province,
                    'Area'            => $this->Area,
                    'Amount'          => $this->Amount,
                ]);
            }
        }

        return $this->_Detail;
    }

    public static function getSumByKey($array, $key_name)
    {
        $sum = 0;
        foreach ($array as $key => $value) {
            if (array_key_exists($key_name, $value)) {
                $sum += $value[$key_name];
            }
        }
        return $sum;
    }

}