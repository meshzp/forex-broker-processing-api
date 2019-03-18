<?php

namespace app\controllers;

use app\models\Accounts;
use app\models\AccountsMerchantsLog;
use app\models\AccountsTransactions;
use app\models\AccountsTypes;
use app\models\Ecommpay;
use app\models\TransactionsRequisites;
use app\models\User;
use app\models\UsersRequisites;
use yii;
use yii\web\Response;

class MerchantController extends Controller
{

    const PLATRON_STATUS_OK     = 'ok';
    const PLATRON_STATUS_ERROR  = 'error';
    const PLATRON_STATUS_REJECT = 'rejected';
    const STATUS_PENDING        = 'pending';

    /**
     * @var AccountsMerchantsLog $_log
     */
    private $_log;

    /**
     * Данная функция меняет статус транзации при запросе от PerfectMoney
     * @return array
     */
    public function actionPerfectMoney()
    {
        $this->logRequest("perfectmoney");

        $data = Yii::$app->request->post();

        $answerArray = [
            'pm_status'            => self::PLATRON_STATUS_ERROR,
            'pm_error_description' => 'Unknown error',
        ];

        if ($this->getPerfectMoneyHash($data)) { // proccessing payment if only hash is valid

            /**
             * @var $accountTransaction AccountsTransactions
             */
            $accountTransaction = AccountsTransactions::findOne($data['PAYMENT_ID']);

            if (Yii::$app->request->post('PAYMENT_AMOUNT') == $accountTransaction->amount
                && Yii::$app->request->post('PAYEE_ACCOUNT') == Yii::$app->params['perfect_money_wallet_number']
                && Yii::$app->request->post('PAYMENT_UNITS') == 'USD'
            ) {
// Это еще одна проверка - для параноиков
//                $apcua = $this->additionlPerfectMoneyPaymentCheckingUsingAPI($data);
//                if ($apcua == 'OK') {
                $comment                     = 'PerfectMoney ' . Yii::$app->request->post('PAYER_ACCOUNT');
                $user_id                     = $accountTransaction->account->user_id;
                $accountTransaction->status  = AccountsTransactions::TRANSACTION_STATUS_SUCCESS;
                $accountTransaction->comment = $comment;
                $s                           = $accountTransaction->save();
                if ($s) {
                    if (!UsersRequisites::checkIfRequisitesExist($comment, $user_id)) {
                        $usersRequisites             = new UsersRequisites();
                        $usersRequisites->user_id    = $user_id;
                        $usersRequisites->requisites = $comment;
                        $usersRequisites->owner      = UsersRequisites::checkIfRequisitesExist($comment) ? 0 : 1;
                        $usersRequisites->save();
                    }
                }
                $answerArray                = [
                    'pm_status'      => self::PLATRON_STATUS_OK,
                    'pm_description' => 'Payment received',
                ];
                $this->_log->transaction_id = $accountTransaction->id;

//                } else {
//                    $answerArray['pm_error_description'] = $apcua;
//                }

            } else {
                $answerArray['pm_error_description'] = 'pm_wrong_data';
            }
        } else {
            $answerArray['pm_error_description'] = 'pm_hash_not_valid';
        }

        $this->_log->answer = json_encode($answerArray);
        $this->_log->save();

        return $answerArray;
    }

    private function logRequest($merchant)
    {
        if ($this->_log == null) {
            $this->_log            = new AccountsMerchantsLog();
            $this->_log->merchant  = $merchant;
            $this->_log->direction = AccountsMerchantsLog::DIRECTION_IN;
            $this->_log->data      = json_encode([
                'GET'  => Yii::$app->request->get(),
                'POST' => Yii::$app->request->post(),
            ]);

            return $this->_log->save();
        }

        return true;
    }

    /**
     * Проверяем hash - чтоб данные точно были верными
     *
     * @param $data $_POST
     *
     * @return bool
     */
    private function getPerfectMoneyHash($data)
    {
        if (is_array($data)) {
            $string = mb_strtoupper(
                $data['PAYMENT_ID'] . ':' . $data['PAYEE_ACCOUNT'] . ':' .
                $data['PAYMENT_AMOUNT'] . ':' . $data['PAYMENT_UNITS'] . ':' .
                $data['PAYMENT_BATCH_NUM'] . ':' .
                $data['PAYER_ACCOUNT'] . ':' . md5(Yii::$app->params['perfect_money_alternate_secret']) . ':' .
                $data['TIMESTAMPGMT']);

            $hash = strtoupper(md5($string));
            if ($hash == $data['V2_HASH']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $data
     *
     * @return string
     * А т.к. мы параноики, то мы еще раз проверяем данные от PerfectMoney - отдельным запросом
     */
    function additionlPerfectMoneyPaymentCheckingUsingAPI($data)
    {

        $f = fopen('https://perfectmoney.is/acct/historycsv.asp?AccountID=' . Yii::$app->params['perfect_money_account_id'] .
            '&PassPhrase=' . Yii::$app->params['perfect_money_account_password'] .
            '&startmonth=' . date("m", $data['TIMESTAMPGMT']) .
            '&startday=' . date("d", $data['TIMESTAMPGMT']) .
            '&startyear=' . date("Y", $data['TIMESTAMPGMT']) .
            '&endmonth=' . date("m", $data['TIMESTAMPGMT']) .
            '&endday=' . date("d", $data['TIMESTAMPGMT']) .
            '&endyear=' . date("Y", $data['TIMESTAMPGMT']) .
            '&paymentsreceived=1&batchfilter=' . $data['PAYMENT_BATCH_NUM'], 'rb');
        if ($f === false) {
            return 'error openning url';
        }

        $lines = [];
        while (!feof($f)) {
            array_push($lines, trim(fgets($f)));
        }

        fclose($f);

        if ($lines[0] != 'Time,Type,Batch,Currency,Amount,Fee,Payer Account,Payee Account,Payment ID,Memo') {
            return $lines[0];
        } else {
            $n = count($lines);
            if ($n != 2) {
                return 'payment not found';
            }

            $item = explode(",", $lines[1], 10);
            if (count($item) != 10) {
                return 'invalid API output';
            }
            $item_named['Time']          = $item[0];
            $item_named['Type']          = $item[1];
            $item_named['Batch']         = $item[2];
            $item_named['Currency']      = $item[3];
            $item_named['Amount']        = $item[4];
            $item_named['Fee']           = $item[5];
            $item_named['Payer Account'] = $item[6];
            $item_named['Payee Account'] = $item[7];
            $item_named['Payment ID']    = $item[8];
            $item_named['Memo']          = $item[9];

            if ($item_named['Batch'] == $_POST['PAYMENT_BATCH_NUM'] && $_POST['PAYMENT_ID'] == $item_named['Payment ID'] && $item_named['Type'] == 'Income' && $_POST['PAYEE_ACCOUNT'] == $item_named['Payee Account'] && $_POST['PAYMENT_AMOUNT'] == $item_named['Amount'] && $_POST['PAYMENT_UNITS'] == $item_named['Currency'] && $_POST['PAYER_ACCOUNT'] == $item_named['Payer Account']) {
                return 'OK';
            } else {
                return "Some payment data not match:
batch:  {$data['PAYMENT_BATCH_NUM']} vs. {$item_named['Batch']} = " . (($item_named['Batch'] == $data['PAYMENT_BATCH_NUM']) ? 'OK' : '!!!NOT MATCH!!!') . "
payment_id:  {$data['PAYMENT_ID']} vs. {$item_named['Payment ID']} = " . (($item_named['Payment ID'] == $data['PAYMENT_ID']) ? 'OK' : '!!!NOT MATCH!!!') . "
type:  Income vs. {$item_named['Type']} = " . (('Income' == $item_named['Type']) ? 'OK' : '!!!NOT MATCH!!!') . "
payee_account:  {$data['PAYEE_ACCOUNT']} vs. {$item_named['Payee Account']} = " . (($item_named['Payee Account'] == $data['PAYEE_ACCOUNT']) ? 'OK' : '!!!NOT MATCH!!!') . "
amount:  {$data['PAYMENT_AMOUNT']} vs. {$item_named['Amount']} = " . (($item_named['Amount'] == $data['PAYMENT_AMOUNT']) ? 'OK' : '!!!NOT MATCH!!!') . "
currency:  {$data['PAYMENT_UNITS']} vs. {$item_named['Currency']} = " . (($item_named['Currency'] == $data['PAYMENT_UNITS']) ? 'OK' : '!!!NOT MATCH!!!') . "
payer account:  {$data['PAYER_ACCOUNT']} vs. {$item_named['Payer Account']} = " . (($item_named['Payer Account'] == $data['PAYER_ACCOUNT']) ? 'OK' : '!!!NOT MATCH!!!');
            }
        }
    }

    /**
     * Данная функция меняет статус транзации при запросе от PerfectMoney
     * @return array
     */
    public function actionMoneypro()
    {
        $this->logRequest("moneypro");

        $data = Yii::$app->request->post();

        $answerArray = [
            'mp_status'            => self::PLATRON_STATUS_ERROR,
            'mp_error_description' => 'Unknown error',
        ];

        if ($this->getMoneyproSign($data)) { // proccessing payment if only hash is valid

            /**
             * @var $accountTransaction AccountsTransactions
             */
            $accountTransaction = AccountsTransactions::findOne($data['external_id']);

            if (Yii::$app->request->post('amount') == $accountTransaction->amount) {

                $comment                     = 'MoneyPro ' . Yii::$app->request->post('pay_system') . '_' . Yii::$app->request->post('pay_system');
                $user_id                     = $accountTransaction->account->user_id;
                $accountTransaction->status  = AccountsTransactions::TRANSACTION_STATUS_SUCCESS;
                $accountTransaction->comment = $comment;
                $s                           = $accountTransaction->save();
                if ($s) {
                    if (!UsersRequisites::checkIfRequisitesExist($comment, $user_id)) {
                        $usersRequisites             = new UsersRequisites();
                        $usersRequisites->user_id    = $user_id;
                        $usersRequisites->requisites = $comment;
                        $usersRequisites->owner      = UsersRequisites::checkIfRequisitesExist($comment) ? 0 : 1;
                        $usersRequisites->save();
                    }
                }
                $answerArray                = [
                    'mp_status'      => self::PLATRON_STATUS_OK,
                    'mp_description' => 'Payment received',
                ];
                $this->_log->transaction_id = $accountTransaction->id;
            } else {
                $answerArray['mp_error_description'] = 'mp_wrong_data';
            }
        } else {
            $answerArray['mp_error_description'] = 'mp_hash_not_valid';
        }

        $this->_log->answer = json_encode($answerArray);
        $this->_log->save();

        return $answerArray;
    }

    private function getMoneyproSign($data)
    {
        $sign = $data['sign'];
        // Подпись не может быть пустой
        if (!trim($data['sign'])) {
            return false;
        }
        unset($data['sign']);

        ksort($data, SORT_STRING); // сортируем по ключам в алфавитном порядке элементы массива
        array_push($data, Yii::$app->params['money_pro_secret']); // добавляем в конец массива "секретный ключ"
        $signString = implode(':', $data); // конкатенируем значения через символ ":"
        // берем sha1 хэш в бинарном виде по сформированной строке и кодируем в BASE64
        if (base64_encode(sha1($signString, true)) == $sign) {
            return true;
        }

        return false;
    }

    /**
     * Уведомление оплаты Bitcoin
     * @return array
     */
    public function actionBitcoinBitapsNotify()
    {
        $this->logRequest("bictoin_bitaps");

        $data = Yii::$app->request->post();

        $answerArray = [
            'mp_status'            => self::PLATRON_STATUS_ERROR,
            'mp_error_description' => 'Unknown error',
        ];

        /* структура ответа
         tx_hash={transaction hash}
         address={address}
         invoice={invoice}
         code={payment code}
         amount={amount} # Satoshi
         confirmations={confirmations}
         payout_tx_hash={transaction hash} # payout transaction hash
         payout_miner_fee={amount}
         payout_service_fee={amount} */
        if (isset($data['address'], $data['invoice'], $data['code'], $data['amount'])) {
            $transaction                 = AccountsTransactions::getBitcoinTransaction($data);
            $amount_usd                  = AccountsTransactions::satoshiToUsd($data['amount']);
            if (isset($transaction['id'])) {
                $accountTransaction          = AccountsTransactions::findOne(['id' => $transaction['id']]);
                $this->_log->transaction_id  = $accountTransaction->id;
                $user_id                     = $accountTransaction->account->user_id;
                $comment                     = "Deposit Bitcoin Bitaps {$data['amount']} satoshi";
                $accountTransaction->comment = $comment;
                // Все что меньше доллара - пыль и не факт, что прийдет на кошелек, не зачисляем маленькие суммы
                if ($amount_usd >= 9) {
                    $accountTransaction->status  = AccountsTransactions::TRANSACTION_STATUS_SUCCESS;
                    $accountTransaction->amount  = $amount_usd;
                } else {
                    $answerArray['mp_error_description'] = 'transaction_amount_less_then_allowed';
                }
                $s = $accountTransaction->save();
                // Сохраняем реквизит (просто факт успешного пополнения ввиду специфики Bitcoin), если пополение больше 10 баксов
                if ($s and $accountTransaction->amount >= 10) {
                    $requisite = 'Bitcoin Bitaps';
                    if (!UsersRequisites::checkIfRequisitesExist($requisite, $user_id)) {
                        $usersRequisites             = new UsersRequisites();
                        $usersRequisites->user_id    = $user_id;
                        $usersRequisites->requisites = $requisite;
                        $usersRequisites->owner      = 1;
                        $usersRequisites->save();
                    }
                }
                $answerArray                = [
                    'mp_status'      => self::PLATRON_STATUS_OK,
                    'mp_description' => 'Payment received',
                ];
            } else {
                $answerArray['mp_error_description'] = 'transaction_not_found';
            }
        } else {
            $answerArray['mp_error_description'] = 'mp_wrong_callback_data';
        }

        $this->_log->answer = json_encode($answerArray);
        $this->_log->save();

        return isset($data['invoice']) ? $data['invoice'] : '';
    }

    /**
     * Данная функция меняет статус транзации при запросе от RPNpay
     * @return array
     */
    public function actionRpnPayNotify()
    {
        $this->logRequest("rpnpay");
        $answerArray = [
            'mp_status'            => self::PLATRON_STATUS_ERROR,
            'mp_error_description' => 'Unknown error',
        ];
        $data        = [
            'order_id'     => isset($_REQUEST['order_id']) ? $_REQUEST['order_id'] : '',
            'order_time'   => isset($_REQUEST['order_time']) ? $_REQUEST['order_time'] : '',
            'order_amount' => isset($_REQUEST['order_amount']) ? $_REQUEST['order_amount'] : '',
            'deal_id'      => isset($_REQUEST['deal_id']) ? $_REQUEST['deal_id'] : '',
            'deal_time'    => isset($_REQUEST['deal_time']) ? $_REQUEST['deal_time'] : '',
            'pay_amount'   => isset($_REQUEST['pay_amount']) ? $_REQUEST['pay_amount'] : '',
            'pay_result'   => isset($_REQUEST['pay_result']) ? $_REQUEST['pay_result'] : '',
            'signature'    => isset($_REQUEST['signature']) ? $_REQUEST['signature'] : '',
        ];
        // check response from our narrow eyes friends
        if ($this->checkRPNpaySignature($data)) { // proccessing payment if only hash is valid

            /**
             * @var $accountTransaction AccountsTransactions
             */
            $accountTransaction = AccountsTransactions::findOne($data['order_id']);

//            if ($data['pay_amount'] == $accountTransaction->amount*100) {

            if ($data['pay_result'] == 3) { //RNP success status
                $accountTransaction->status = AccountsTransactions::TRANSACTION_STATUS_SUCCESS;
                $answerArray                = [
                    'Success',
                    'mp_status'      => self::PLATRON_STATUS_OK,
                    'mp_description' => 'Payment received',
                ];
            } elseif ($data['pay_result'] == 1) { // RNP pending status
                $accountTransaction->status = AccountsTransactions::TRANSACTION_STATUS_PENDING;
                $answerArray                = [
                    'mp_status'      => self::STATUS_PENDING,
                    'mp_description' => 'Payment is on processing yet',
                ];
            }
            $s = $accountTransaction->save();
            /**
             * @todo Выяснить можно ли получать и различать реквизит конкретного пользователя
             */
//                if ($s) {
//                    if (!UsersRequisites::checkIfRequisitesExist($comment, $user_id)) {
//                        $usersRequisites = new UsersRequisites();
//                        $usersRequisites->user_id = $user_id;
//                        $usersRequisites->requisites = $comment;
//                        $usersRequisites->owner = UsersRequisites::checkIfRequisitesExist($comment) ? 0 : 1;
//                        $usersRequisites->save();
//                    }
//                }

            $this->_log->transaction_id = $accountTransaction->id;
//            } else {
//                $answerArray['mp_error_description'] = 'mp_wrong_data';
//            }
        } else {
            $answerArray['mp_error_description'] = 'mp_hash_not_valid';
        }

        $this->_log->answer = json_encode($answerArray);
        $this->_log->save();

        return $answerArray;
    }

    private function checkRPNpaySignature($data)
    {

        $params = [];
        foreach ($data as $field => $value) {
            //if( $value === '' ) continue;
            $params[] = "$field=$value";
        }
        $params[]   = Yii::$app->params['rpn_pay_secret'];
        $check_sign = md5(implode('|', $params));
        $signature  = isset($data['signature']) ? $data['signature'] : '';
        if ($check_sign = $signature) {
            return true;
        }

        return false;
    }

    /**
     * @todo не тестированый код
     * Данная функция меняет статус транзации при запросе выплат от RPNpay
     * @return array
     */
    public function actionRpnPayWithdrawNotify()
    {
        $this->logRequest("rpnpay");
        $answerArray = [
            'mp_status'            => self::PLATRON_STATUS_ERROR,
            'mp_error_description' => 'Unknown error',
        ];
        $data        = isset($_REQUEST['data']) ? $_REQUEST['data'] : null;
        if ($data) {
            $oData   = json_decode($data);
            $aPayout = isset($oData->Detial) ? $oData->Detial : null;
            if ($aPayout) {
                foreach ($aPayout as $Payout) {
                    $transaction_id = isset($Payout->OrderSno) ? $Payout->OrderSno : null;
                    if ($transaction_id) {
                        $Transacion = AccountsTransactions::findOne([
                            'id'               => $transaction_id,
                            'transaction_type' => AccountsTransactions::TRANSACTION_TYPE_RPN_PAY,
                            'status'           => AccountsTransactions::TRANSACTION_STATUS_PENDING,
                        ]);
                        if ($Transacion) {
                            $Transacion->status = AccountsTransactions::TRANSACTION_STATUS_SUCCESS;
                            $Transacion->save();
                        }
                    }
                }
            }
        }
    }

    public function actionPlatron()
    {
        /**
         * @var $accountTransaction \app\models\AccountsTransactions
         */

        Yii::$app->response->format = Response::FORMAT_XML;

        $this->logRequest("platron");

        $answerArray = [
            'pg_salt'              => Yii::$app->params['platron_salt'],
            'pg_status'            => self::PLATRON_STATUS_ERROR,
            'pg_error_description' => 'Unknown error',
        ];

        $data = Yii::$app->request->get();

        if (empty($data) || !is_array($data)) {
            $answerArray['pg_error_description'] = 'Wrong data format';
        }

        if (isset($data['pg_sig']) && $data['pg_sig'] == $this->getPlatronSignature($data, 'platron')) {
            $accountTransaction = AccountsTransactions::findOne($data['pg_order_id']);

            if (!empty($accountTransaction) && $accountTransaction->status != AccountsTransactions::TRANSACTION_STATUS_SUCCESS) {
                if (isset($data['pg_result'])
                    && $data['pg_result'] == 1
                ) {
                    if (isset($data['pg_currency'])
                        && isset($data['pg_amount'])
                    ) {
                        if ($data['pg_currency'] == 'USD'
                            && $data['pg_amount'] == $accountTransaction->amount
                        ) {
                            $accountTransaction->status = AccountsTransactions::TRANSACTION_STATUS_ACCEPTED;
                            $accountTransaction->save();

                            $answerArray = [
                                'pg_salt'        => Yii::$app->params['platron_salt'],
                                'pg_status'      => self::PLATRON_STATUS_OK,
                                'pg_description' => 'Payment received',
                            ];

                            $this->_log->transaction_id = $accountTransaction->id;
                        }
                    } else {
                        $answerArray['pg_error_description'] = 'Not enough information received';
                    }
                } else {
                    $answerArray['pg_error_description'] = 'Wrong data ';
                }
            } else {
                $answerArray['pg_error_description'] = 'Transaction not found';
            }
        } else {
            $answerArray['pg_error_description'] = 'Wrong signature';
        }

        $answerArray['pg_sig'] = $this->getPlatronSignature($answerArray, 'platron');
        $this->_log->answer    = json_encode($answerArray);
        $this->_log->save();

        return $answerArray;
    }

    private function getPlatronSignature($data = null, $script = "?")
    {
        $result = false;
        if (is_array($data)) {
            if (isset($data['pg_sig'])) {
                unset($data['pg_sig']);
            }
            ksort($data);
            $result = md5($script . ";" . implode(";", $data) . ";" . Yii::$app->params['platron_secretkey']);
        }

        return $result;
    }

    public function actionEcommpay()
    {
        $answerArray = [];
        $answerArray += $this->ecommpayCheckDeposit();
        $answerArray += $this->ecommpayRequestPayout();
        $answerArray += $this->ecommpayNotification();

        if (empty($answerArray)) {
            $answerArray = [
                'code'              => 500,
                'error_description' => 'Unknown error',
            ];
        }

        $this->_log->answer = json_encode($answerArray);
        $this->_log->save();

        return $answerArray;
    }

    private function ecommpayCheckDeposit()
    {
        /**
         * @var Accounts $account
         */
        $this->logRequest("ecommpay");

        $get = Yii::$app->request->get();
        if (empty($get)) {
            $get = Yii::$app->request->post();
        }

        if (isset($get['action']) && isset($get['site_id']) && $get['action'] == 'check_deposit' && $get['site_id'] == Yii::$app->params['ecommpay_site_id']) {
            // Checking signature
            $signature = Ecommpay::generateSignature($get);
            if ($signature != $get['signature']) {
                $answerArray        = [
                    'code' => 400,
                ];
                $this->_log->answer = json_encode($answerArray);
                $this->_log->save();

                return $answerArray;
            }

            // Checking min sum, if amount < 10.00 then...
            if ($get['site_login'] !== "paytest" && bccomp($get['amount'], 1000, 0) == -1) {
                $answerArray        = [
                    'code'              => 500,
                    'error_description' => 'The amount is less than 10.00 USD',
                ];
                $this->_log->answer = json_encode($answerArray);
                $this->_log->save();

                return $answerArray;
            }

            // Checking user exist
            $user = User::findByUsername($get['site_login']);
            if ($user == null) {
                $answerArray        = [
                    'code' => 404,
                ];
                $this->_log->answer = json_encode($answerArray);
                $this->_log->save();

                return $answerArray;
            }

            // Find personal account of this user
            $account = Accounts::findOne([
                'user_id'      => $user->id,
                'account_type' => AccountsTypes::ACCOUNT_TYPE_PERSONAL,
                'currency'     => $get['currency'],
            ]);
            if ($account == null) {
                $answerArray        = [
                    'code'              => 500,
                    'error_description' => 'Account not found',
                ];
                $this->_log->answer = json_encode($answerArray);
                $this->_log->save();

                return $answerArray;
            }

            //$comment = "ECommPay, ";
            $comment = Ecommpay::getPaymentSystemNameByGroupId($get['payment_group_id']);

            $accountTransaction                   = new AccountsTransactions();
            $accountTransaction->account_id       = $account->id;
            $accountTransaction->transaction_type = AccountsTransactions::TRANSACTION_TYPE_ECOMMPAY;
            $accountTransaction->amount           = bcdiv($get['amount'], 100, 2);
            $accountTransaction->comment          = $comment;
            $accountTransaction->status           = AccountsTransactions::TRANSACTION_STATUS_PENDING;
            if ($accountTransaction->save()) {
                $answerArray                = [
                    'code'        => 0,
                    'external_id' => strval($accountTransaction->id),
                    'description' => "Payment for invoice {$accountTransaction->id} to privatefx.com",
                ];
                $this->_log->transaction_id = $accountTransaction->id;
            } else {
                $answerArray = [
                    'code'              => 500,
                    'error_description' => 'Transaction error',
                ];
            }

            $this->_log->answer = json_encode($answerArray);
            $this->_log->save();

            return $answerArray;
        }

        return [];
    }

    private function ecommpayRequestPayout()
    {
        /**
         * @var Accounts $account
         * @var AccountsTransactions $accountTransaction
         * @var AccountsMerchantsLog $logMessage
         */
        $this->logRequest("ecommpay");

        $get = Yii::$app->request->get();
        if (empty($get)) {
            $get = Yii::$app->request->post();
        }

        if (isset($get['action']) && isset($get['site_id']) && $get['action'] == 'request_payout' && $get['site_id'] == Yii::$app->params['ecommpay_site_id']) {
            // Checking signature
            $signature = Ecommpay::generateSignature($get);
            if ($signature != $get['signature']) {
                $answerArray        = [
                    'code' => 400,
                ];
                $this->_log->answer = json_encode($answerArray);
                $this->_log->save();

                return $answerArray;
            }

            // Checking min sum, if amount < 10.00 then...
            //if (bccomp($get['amount'], 1000, 0) == -1) {
            if ($get['site_login'] !== "ssv" && $get['site_login'] !== "paytest" && bccomp($get['amount'], 1000, 0) == -1) {
                $answerArray        = [
                    'code'              => 500,
                    'error_description' => 'The amount is less than 10.00 USD',
                ];
                $this->_log->answer = json_encode($answerArray);
                $this->_log->save();

                return $answerArray;
            }

            // Checking user exist
            $user = User::findByUsername($get['site_login']);
            if ($user == null) {
                $answerArray        = [
                    'code' => 404,
                ];
                $this->_log->answer = json_encode($answerArray);
                $this->_log->save();

                return $answerArray;
            }

            // Find personal account of this user
            $account = Accounts::findOne([
                'user_id'      => $user->id,
                'account_type' => AccountsTypes::ACCOUNT_TYPE_PERSONAL,
                'currency'     => $get['currency'],
            ]);
            if ($account == null) {
                $answerArray        = [
                    'code'              => 500,
                    'error_description' => 'Account not found',
                ];
                $this->_log->answer = json_encode($answerArray);
                $this->_log->save();

                return $answerArray;
            }

            // Check if balance is enough
            if (bccomp(bcsub($account->balance, $account->blocked_sum, 2), bcdiv($get['amount'], 100, 2), 2) == -1) {
                $answerArray        = [
                    'code' => 402,
                ];
                $this->_log->answer = json_encode($answerArray);
                $this->_log->save();

                return $answerArray;
            }

            $comment = Ecommpay::getPaymentSystemNameByGroupId($get['payment_group_id']);
            if ($get['payment_group_id'] == 1) {
                $logMessage = AccountsMerchantsLog::find()->where(['like', 'merchant', 'ecommpay'])->andWhere(['like', 'data', "\"transaction_id\":\"{$get['customer_purse']}\""])->one();
                if ($logMessage) {
                    $data    = json_decode($logMessage->data, true);
                    $data    = !empty($data['GET']) ? $data['GET'] : $data['POST'];
                    $comment .= ", {$data['customer_purse']}";
                } else {
                    $comment .= ", {$get['customer_purse']}";
                }
            } else {
                $comment .= ", {$get['customer_purse']}";
            }

            if (Ecommpay::mustBeUnique(Ecommpay::getPaymentSystemNameByGroupId($get['payment_group_id']))) {
                $requisites = UsersRequisites::findOne(['requisites' => $comment, 'user_id' => $account->user_id, 'owner' => 1]);
                if (empty($requisites)) {
                    $answerArray        = [
                        'code'              => 500,
                        'error_description' => 'Requisites doesn`t belong to this user',
                    ];
                    $this->_log->answer = json_encode($answerArray);
                    $this->_log->save();

                    return $answerArray;
                }
            }

            $accountTransaction                   = new AccountsTransactions();
            $accountTransaction->account_id       = $account->id;
            $accountTransaction->transaction_type = AccountsTransactions::TRANSACTION_TYPE_ECOMMPAY;
            $accountTransaction->amount           = bcmul(bcdiv($get['amount'], 100, 2), -1, 2);
            $accountTransaction->comment          = $comment;
            $accountTransaction->status           = AccountsTransactions::TRANSACTION_STATUS_PENDING;
            if ($accountTransaction->save()) {
                $answerArray = [
                    'code'        => 50,
                    'external_id' => strval($accountTransaction->id),
                ];

                $this->_log->transaction_id = $accountTransaction->id;

                $transactionRequisites                 = new TransactionsRequisites();
                $transactionRequisites->transaction_id = $accountTransaction->id;
                $transactionRequisites->requisites     = json_encode($get);
                $transactionRequisites->save();
            } else {
                $answerArray = [
                    'code'              => 500,
                    'error_description' => 'Transaction error',
                ];
            }

            $this->_log->answer = json_encode($answerArray);
            $this->_log->save();

            return $answerArray;
        }

        return [];
    }

    private function ecommpayNotification()
    {
        /**
         * @var Accounts $account
         * @var AccountsTransactions $accountTransaction
         */
        $this->logRequest("ecommpay");

        $post = Yii::$app->request->post();

        if (isset($post['status_id']) && $post['status_id'] == 4) {
            // Checking signature
            $signature = Ecommpay::generateSignature($post);
            if ($signature != $post['signature']) {
                $answerArray        = [
                    'code' => 400,
                ];
                $this->_log->answer = json_encode($answerArray);
                $this->_log->save();

                return $answerArray;
            }

            $accountTransaction = AccountsTransactions::findOne($post['external_id']);
            $user_id            = $accountTransaction->account->user_id;
            if ($accountTransaction != null && $accountTransaction->status != AccountsTransactions::TRANSACTION_STATUS_SUCCESS) {
                //$comment = "ECommPay, ";
                $comment = Ecommpay::getPaymentSystemNameByTypeId($post['payment_type_id']);
                if (!empty($post['customer_purse'])) {
                    $comment .= ", {$post['customer_purse']}";
                }

                $accountTransaction->status  = AccountsTransactions::TRANSACTION_STATUS_SUCCESS;
                $accountTransaction->comment = $comment;
                $answerArray                 = [
                    'code' => 200,
                ];
                if (!$accountTransaction->save()) {
                    $answerArray = [
                        'code'              => 500,
                        'error_description' => 'Transaction error',
                    ];
                } else {
                    $this->_log->transaction_id = $accountTransaction->id;
                    if (!UsersRequisites::checkIfRequisitesExist($comment, $user_id)) {
                        $usersRequisites             = new UsersRequisites();
                        $usersRequisites->user_id    = $user_id;
                        $usersRequisites->requisites = $comment;
                        $usersRequisites->owner      = UsersRequisites::checkIfRequisitesExist($comment) ? 0 : 1;
                        $usersRequisites->save();
                    }
                }
            } else {
                $answerArray = [
                    'code'              => 404,
                    'error_description' => 'Transaction not found',
                ];
            }

            $this->_log->answer = json_encode($answerArray);
            $this->_log->save();

            return $answerArray;
        }

        return [];
    }
}
