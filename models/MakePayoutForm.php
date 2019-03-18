<?php
namespace app\models;

use Yii;
use yii\base\Model;

/**
 * Signup form
 */
class MakePayoutForm extends Model
{
    public $transaction_id;
    public $account_id;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['transaction_id', 'account_id'], 'filter', 'filter' => 'trim'],
            [['transaction_id', 'account_id'], 'required'],
            [['transaction_id', 'account_id'], 'safe'],

            ['transaction_id', 'integer', 'min' => 0],
            ['account_id', 'integer', 'min' => 0],

            ['account_id', 'checkAccountId'],
        ];
    }

    /**
     * Creates account for user
     *
     * @return bool
     */
    public function makePayout()
    {
        if ($this->validate()) {
            /**
             * @var AccountsTransactions $accountTransaction
             */
            $accountTransaction = AccountsTransactions::findOne($this->transaction_id);

            $result = false;
            if ($accountTransaction != null) {
                if ($accountTransaction->transaction_type == AccountsTransactions::TRANSACTION_TYPE_ECOMMPAY) {
                    try {
                        $transactionRequisites = json_decode($accountTransaction->transactionsRequisites->requisites, true);
                        $params = [
                            'payment_group_id'  => $transactionRequisites['payment_group_id'],
                            'customer_purse'    => $transactionRequisites['customer_purse'],
                            'transaction_id'    => $accountTransaction->id,
                            'account_id'        => $accountTransaction->account_id,
                            'amount'            => bcmul(abs($accountTransaction->amount), 100, 0),
                            'requisites'        => $transactionRequisites,
                        ];
                        $payoutResult = Ecommpay::makePayout($params);
                        if (is_array($payoutResult) && isset($payoutResult['code']) && $payoutResult['code'] == 0) {
                            $accountTransaction->status = AccountsTransactions::TRANSACTION_STATUS_SUCCESS;
                            $result = $accountTransaction->save();
                            $this->addErrors($accountTransaction->errors);
                        } elseif (is_array($payoutResult) && isset($payoutResult['code']) && $payoutResult['code'] != 0) {
                            $this->addError('transaction_id', $payoutResult['message']);
                        } else {
                            $this->addError('transaction_id', 'EcommPay Payout Problem');
                        }
                    } catch (\Exception $e) {
                        $this->addError('transaction_id', 'EcommPay Payout Problem');
                    }
                }
                if ($accountTransaction->transaction_type == AccountsTransactions::TRANSACTION_TYPE_PERFECT_MONEY) {
                    if ($accountTransaction->status == AccountsTransactions::TRANSACTION_STATUS_PENDING) {
                        $requisites = TransactionsRequisites::find()->where(['transaction_id' => $accountTransaction->id])->one();
                        /**
                         * @var $requisites TransactionsRequisites
                         */
                        if ($requisites) {
                            // trying to open URL to process PerfectMoney Spend request
                            $request_data = 'https://perfectmoney.is/acct/confirm.asp?' .
                                'AccountID=' . Yii::$app->params['perfect_money_account_id'] .
                                '&PassPhrase=' . Yii::$app->params['perfect_money_account_password'] .
                                '&Payer_Account=' . Yii::$app->params['perfect_money_wallet_number'] .
                                '&Payee_Account=' . $requisites->requisites .
                                '&Amount=' . abs($accountTransaction->amount) .
                                '&PAY_IN=1' .
                                '&PAYMENT_ID=' . $accountTransaction->id;

                            $f = fopen($request_data, 'rb');

                            if ($f === false) {
                                $this->addError('transaction_id', 'PerfectMoney Payout Problem - connection problem');
                                return false;
                            }

                            $out = '';
                            while (!feof($f)) $out .= fgets($f);
                            fclose($f);

                            // Логгируем, че спросили и че он ответил. Пока сюда
                            // TODO: Возможно надо сделать лог в отдельную табличку
                            $log = new AccountsMerchantsLog();
                            $log->transaction_id = $accountTransaction->id;
                            $log->merchant = 'perfectMoney';
                            $log->direction = AccountsMerchantsLog::DIRECTION_OUT;
                            $log->data = $request_data;
                            $log->answer = $out;
                            $log->save();

                            if(!preg_match_all("/<input name='(.*)' type='hidden' value='(.*)'>/is", $out, $r, PREG_SET_ORDER) || strstr($out, 'ERROR')){
                                $this->addError('transaction_id', 'PerfectMoney Payout Problem');
                                return false;
                            }

                            $accountTransaction->status = AccountsTransactions::TRANSACTION_STATUS_SUCCESS;
                            $result = $accountTransaction->save();
                            $this->addErrors($accountTransaction->errors);
                        } else {
                            $this->addError('transaction_id', 'PerfectMoney Payout Problem - requisites not found');
                        }
                    } else {
                        $this->addError('transaction_id', 'PerfectMoney Payout Problem - you can pay only pending tarnsaction');
                    }
                }
                if ($accountTransaction->transaction_type == AccountsTransactions::TRANSACTION_TYPE_RPN_PAY) {
                    //making structure for json file
                    $RpnPayForm = new RpnPayForm();
                    $RpnPayForm->OrderSno = $accountTransaction->id;
                    $RpnPayForm->addDetailPayout();
                }

                if ($accountTransaction->transaction_type == AccountsTransactions::TRANSACTION_TYPE_BITCOIN_BITAPS) {
                    if (in_array($accountTransaction->status, [AccountsTransactions::TRANSACTION_STATUS_PENDING, AccountsTransactions::TRANSACTION_STATUS_ACCEPTED])) {
                        $requisites = TransactionsRequisites::find()->where(['transaction_id' => $accountTransaction->id])->one();
                        $redeemcode = BtcRedeemCodes::getLatesRedeemCode();
                        if ($requisites && $redeemcode) {
                            $Bitaps = new Bitaps();
                            $payment_params = [
                                'redeemcode' => $redeemcode->redeem_code,
                                'address'    => $requisites->requisites,
                                'amount'     => -1 * AccountsTransactions::usdToSatoshi($accountTransaction->amount),
                            ];
                            $log            = new AccountsMerchantsLog();
                            $log->merchant  = Bitaps::MERCHANT_NAME;
                            $log->direction = AccountsMerchantsLog::DIRECTION_OUT;
                            $log->data      = json_encode($payment_params);

                            $response = $Bitaps->payFromRedeemCode($payment_params);

                            $log->answer = json_encode($response);
                            $log->save();
                            if (!isset($response['error']) && !empty($response['tx_hash'])) {
                                $accountTransaction->status = AccountsTransactions::TRANSACTION_STATUS_SUCCESS;
                                $accountTransaction->comment .= ' Tx hash: '.$response['tx_hash'];
                                $accountTransaction->save();
                                $result = true;
                            } else {
                                $this->addError('transaction_id', $response['error']);
                                $result = false;
                            }
                        }
                    }
                }

            }

            if ($result) {
                return true;
            }
        }

        return false;
    }

    public function checkAccountId($attribute, $params)
    {
        /**
         * @var AccountsTransactions $accountTransaction
         */
        if ($params) {}
        $accountTransaction = AccountsTransactions::findOne($this->transaction_id);
        if ($accountTransaction == null || $accountTransaction->account_id != $this->$attribute) {
            $this->addError($attribute, 'Account is not an owner of this transaction id.');
        }
    }

}
