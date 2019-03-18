<?php
namespace app\models;

use Yii;
use yii\base\Model;
use yii\db\Expression;

/**
 * Signup form
 */
class ChangeTransactionDataForm extends Model
{
    public $transaction_id;
    public $account_id;
    public $amount;
    public $comment;
    public $remark_comment;
    public $status;
    public $protection_expire_days;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['transaction_id', 'account_id', 'amount', 'comment', 'remark_comment', 'status'], 'filter', 'filter' => 'trim'],
            [['transaction_id', 'account_id', 'amount', 'comment', 'status'], 'required'],
            [['transaction_id', 'account_id', 'amount', 'comment', 'remark_comment', 'status'], 'safe'],

            ['transaction_id', 'integer', 'min' => 0],
            ['account_id', 'integer', 'min' => 0],
            ['protection_expire_days', 'integer'],
            ['amount', 'number'],
            [['comment', 'remark_comment'], 'string', 'max' => 512],
            ['status', 'integer', 'min' => 0, 'max' => 4],

            ['account_id', 'checkAccountId'],
        ];
    }

    /**
     * Creates account for user
     *
     * @return bool
     */
    public function changeTransactionData()
    {
        if ($this->validate()) {
            /**
             * @var AccountsTransactions $accountTransaction
             */
            $accountTransaction = AccountsTransactions::findOne($this->transaction_id);

            $result = false;
            if ($accountTransaction != null) {
                $accountTransaction->amount = $this->amount;
                $accountTransaction->comment = html_entity_decode($this->comment, ENT_COMPAT, 'UTF-8');
                $accountTransaction->remark_comment = html_entity_decode($this->remark_comment, ENT_COMPAT, 'UTF-8');
                $accountTransaction->status = $this->status;
                if (is_numeric($this->protection_expire_days)) {
                    $accountTransaction->protection_expire_date = new Expression("DATE_ADD(NOW(), INTERVAL :days DAY)", ['days' => $this->protection_expire_days]);
                }
                $result = $accountTransaction->save();
                $this->addErrors($accountTransaction->errors);
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
