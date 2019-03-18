<?php
namespace app\models;

use Yii;
use yii\base\Model;

/**
 * Signup form
 */
class UnlockTransactionForm extends Model
{
    public $account_id;
    public $transaction_id;
    public $protection_code;

    private $_transaction;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['account_id', 'transaction_id', 'protection_code'], 'required'],
            [['account_id', 'transaction_id', 'protection_code'], 'filter', 'filter' => 'trim'],
            [['account_id', 'transaction_id', 'protection_code'], 'safe'],

            [['account_id', 'transaction_id'], 'integer', 'min' => 0],
            ['protection_code', 'integer', 'min' => 1000],

            ['transaction_id', 'checkTransactionId'],
            //['protection_code', 'checkProtectionCode'],
        ];
    }

    /**
     * Creates account for user
     *
     * @return bool
     */
    public function unlock()
    {
        if ($this->validate()) {
            /**
             * @var AccountsTransactions $accountTransaction
             * @var AccountsTransactions $senderAccountTransaction
             */
            $accountTransaction = AccountsTransactions::findOne($this->transaction_id);

            // Если Время и Стекло - нужно поменять статус этой транзакции и откатить перевод отправителя
            if ($accountTransaction->status == AccountsTransactions::TRANSACTION_STATUS_PROTECTED && time() > strtotime($accountTransaction->protection_expire_date)) {
                $accountTransaction->status = AccountsTransactions::TRANSACTION_STATUS_FAIL;
                $accountTransaction->save();
                $senderAccountTransaction = AccountsTransactions::findOne($accountTransaction->sender_transaction_id);
                if ($senderAccountTransaction != null) {
                    $senderAccountTransaction->status = AccountsTransactions::TRANSACTION_STATUS_FAIL;
                    $senderAccountTransaction->save();
                }
                $this->addError('transaction_id', 'Transaction is expired.');
                return false;
            }

            // Если код протекции не правильный - нужно уменьшить счетчик. Если он станет равен 0 - отменить перевод.
            if ($accountTransaction->protection_code != md5($this->protection_code)) {
                $accountTransaction->protection_retry_count--;
                $accountTransaction->save();
                $this->addError('transaction_id', "Protection code incorrect. Attempts remaining: {$accountTransaction->protection_retry_count}");
                if ($accountTransaction->protection_retry_count == 0) {
                    $accountTransaction->status = AccountsTransactions::TRANSACTION_STATUS_FAIL;
                    $accountTransaction->save();
                    $senderAccountTransaction = AccountsTransactions::findOne($accountTransaction->sender_transaction_id);
                    if ($senderAccountTransaction != null) {
                        $senderAccountTransaction->status = AccountsTransactions::TRANSACTION_STATUS_FAIL;
                        $senderAccountTransaction->save();
                    }
                    $this->addError('transaction_id', "Protection code incorrect. Attempts remaining: {$accountTransaction->protection_retry_count}. Transfer failed.");
                }
                return false;
            } else {
                $accountTransaction->status = AccountsTransactions::TRANSACTION_STATUS_SUCCESS;
                $accountTransaction->save();
                return true;
            }
        }

        return false;
    }

    public function checkTransactionId($attribute, $params)
    {
        /**
         * @var AccountsTransactions $accountTransaction
         */
        if ($params) {}
        $this->_transaction = $accountTransaction = AccountsTransactions::findOne($this->$attribute);
        if ($accountTransaction == null || $accountTransaction->account_id != $this->account_id) {
            $this->addError($attribute, 'Account is not an owner of this transaction id.');
        }
        if ($accountTransaction != null && $accountTransaction->status != AccountsTransactions::TRANSACTION_STATUS_PROTECTED) {
            $this->addError($attribute, 'Transaction is not protected.');
        }
    }

    /*public function checkProtectionCode($attribute, $params)
    {
        /**
         * @var AccountsTransactions $accountTransaction
         */
        /*if ($params) {}
        if ($this->_transaction != null || (bccomp(bcadd($account->balance, $this->$attribute, 2), 0, 2) == -1 && empty($this->force))) {
            $this->addError($attribute, 'The amount is bigger than is available on the account.');
        }
    }*/

}
