<?php
namespace app\models;

use Yii;
use yii\base\Model;
use yii\db\Expression;

/**
 * Signup form
 */
class TransferFundsForm extends Model
{
    public $sender_user_id;
    public $recipient_user_id;
    public $sender_account_id;
    public $recipient_account_id;
    public $amount;
    public $currency;
    public $sender_comment;
    public $recipient_comment;
    public $protection_code;
    public $protection_expire_days;
    public $force;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['sender_user_id', 'recipient_user_id', 'sender_account_id', 'recipient_account_id', 'amount', 'sender_comment', 'recipient_comment'], 'required'],
            [['sender_user_id', 'recipient_user_id', 'sender_account_id', 'recipient_account_id', 'amount', 'sender_comment', 'recipient_comment', 'protection_code', 'protection_expire_days', 'force'], 'filter', 'filter' => 'trim'],
            [['sender_user_id', 'recipient_user_id', 'sender_account_id', 'recipient_account_id', 'amount', 'sender_comment', 'recipient_comment', 'protection_code', 'protection_expire_days', 'force'], 'safe'],

            [['sender_user_id', 'recipient_user_id', 'sender_account_id', 'recipient_account_id', 'protection_expire_days'], 'integer', 'min' => 0],
            ['protection_code', 'integer', 'min' => 1000],
            ['amount', 'number', 'min' => 0],
            ['currency', 'in', 'range' => Accounts::getCurrencies()],
            [['sender_comment', 'recipient_comment'], 'string', 'max' => 255],
            ['force', 'integer', 'min' => 0, 'max' => 1],

            ['sender_account_id', 'compare', 'compareAttribute' => 'recipient_account_id', 'operator' => '!='],
            ['sender_account_id', 'checkSenderAccountId'],
            ['recipient_account_id', 'checkRecipientAccountId'],
            ['amount', 'checkAmount'],
        ];
    }

    /**
     * Creates account for user
     *
     * @return bool
     */
    public function transfer()
    {
        $lock = Yii::$app->db->createCommand("SELECT IS_FREE_LOCK('scammer')")->queryScalar(); // check if scammer lock is free
        if ($lock == 0 && $lock == null) {  //if scammer lock is not free and set already or check error
            return false;
        } else {
            $set_lock = Yii::$app->db->createCommand("SELECT GET_LOCK('scammer', 60)")->queryScalar();    // set scammer lock wait server answer for 60 sec
        }
        if ($set_lock == 1 && $this->validate()) {
            /**
             * @var Accounts $senderAccount
             * @var Accounts $recipientAccount
             * @var AccountsTransactions $senderAccountTransaction
             * @var AccountsTransactions $recipientAccountTransaction
             */
            $senderAccount      = Accounts::findOne($this->sender_account_id);
            $recipientAccount   = Accounts::findOne($this->recipient_account_id);
            
            if ($senderAccount->user_id == $recipientAccount->user_id) {
                $transaction_type = AccountsTransactions::TRANSACTION_TYPE_TRANSFER;
            } else {
                $transaction_type = AccountsTransactions::TRANSACTION_TYPE_BETWEEN_CABINET;
            }

            $senderAccountTransaction = new AccountsTransactions([
                'account_id'        => $this->sender_account_id,
                'transaction_type'  => $transaction_type,
                'amount'            => bcmul($this->amount, -1, 2),
                'comment'           => html_entity_decode($this->sender_comment, ENT_COMPAT, 'UTF-8'),
                'status'            => AccountsTransactions::TRANSACTION_STATUS_SUCCESS,
            ]);

            $recipientAccountTransaction = new AccountsTransactions([
                'account_id'        => $this->recipient_account_id,
                'transaction_type'  => $transaction_type,
                'amount'            => $this->amount,
                'comment'           => html_entity_decode($this->recipient_comment, ENT_COMPAT, 'UTF-8'),
                'status'            => AccountsTransactions::TRANSACTION_STATUS_SUCCESS,
            ]);
            if (!empty($this->protection_expire_days)) {
                $recipientAccountTransaction->protection_code       = md5($this->protection_code);
                $recipientAccountTransaction->protection_expire_date= new Expression("DATE_ADD(NOW(), INTERVAL :days DAY)", ['days' => $this->protection_expire_days]);
                $recipientAccountTransaction->status                = AccountsTransactions::TRANSACTION_STATUS_PROTECTED;
            }

            if (!$senderAccountTransaction->validate() || !$recipientAccountTransaction->validate()) {
                $this->addErrors($senderAccountTransaction->errors);
                $this->addErrors($recipientAccountTransaction->errors);
                return false;
            }

            $params = [
                'login'     => $senderAccount->foreign_account,
                'amount'    => $senderAccountTransaction->amount,
                'comment'   => $senderAccountTransaction->comment,
            ];
            $senderResult = Yii::$app->{$senderAccount->accountType->driver}->makePayment($params);
            if ($senderResult) {
                if ($senderAccountTransaction->save()) {
                    $recipientAccountTransaction->sender_transaction_id = $senderAccountTransaction->id;
                    $recipientResult = true;
                    if ($recipientAccountTransaction->status == AccountsTransactions::TRANSACTION_STATUS_SUCCESS) {
                        $params = [
                            'login'     => $recipientAccount->foreign_account,
                            'amount'    => $recipientAccountTransaction->amount,
                            'comment'   => $recipientAccountTransaction->comment,
                        ];
                        $recipientResult = Yii::$app->{$recipientAccount->accountType->driver}->makePayment($params);
                    }
                    if ($recipientResult) {
                        if ($recipientAccountTransaction->save()) {
                            $senderAccount->recalcBalance();
                            $senderAccount->save();
                            $recipientAccount->recalcBalance();
                            $recipientAccount->save();
                            Yii::$app->db->createCommand("SELECT RELEASE_LOCK('scammer')")->queryScalar(); // del scammer lock if transfer successful
                            $this->addErrors($senderAccountTransaction->errors);
                            $this->addErrors($recipientAccountTransaction->errors);
                            return [
                                'sender_transaction_id'     => $senderAccountTransaction->id,
                                'recipient_transaction_id'  => $recipientAccountTransaction->id,
                            ];
                        }
                        $this->addErrors($recipientAccountTransaction->errors);
                    }
                }
                $this->addErrors($senderAccountTransaction->errors);
            }
        }
        Yii::$app->db->createCommand("SELECT RELEASE_LOCK('scammer')")->queryScalar(); // del scammer lock if values not validate
        return false;
    }

    public function checkSenderAccountId($attribute, $params)
    {
        /**
         * @var Accounts $account
         */
        if ($params) {}
        $account = Accounts::findOne($this->$attribute);
        if ($account == null || $account->user_id != $this->sender_user_id) {
            $this->addError($attribute, 'Sender is not an owner of this account id.');
        }
        if ($account != null && $account->currency != $this->currency) {
            $this->addError($attribute, 'Sender account has another currency.');
        }
    }

    public function checkRecipientAccountId($attribute, $params)
    {
        /**
         * @var Accounts $account
         */
        if ($params) {}
        $account = Accounts::findOne($this->$attribute);
        if ($account == null || $account->user_id != $this->recipient_user_id) {
            $this->addError($attribute, 'Recipient is not an owner of this account id.');
        }
        if ($account != null && $account->currency != $this->currency) {
            $this->addError($attribute, 'Recipient account has another currency.');
        }
    }

    public function checkAmount($attribute, $params)
    {
        /**
         * @var Accounts $senderAccount
         */
        if ($params) {}
        $senderAccount = Accounts::findOne($this->sender_account_id);
        $senderMarginInfo = Yii::$app->{$senderAccount->accountType->driver}->getTradesMarginInfo(['account_id' => $this->sender_account_id]);
        $finalSenderSum = bcsub($senderMarginInfo['free'], $this->$attribute, 2);
        if ($this->sender_user_id != $this->recipient_user_id) {
            $finalSenderSum = bcsub($finalSenderSum, $senderAccount->blocked_sum, 2);
        }
        if ($senderAccount == null || !is_array($senderMarginInfo) || (bccomp($finalSenderSum, 0, 2) == -1 && empty($this->force))) {
            $this->addError($attribute, 'The amount is bigger than is available on the account of the sender.');
        }
    }

}
