<?php
namespace app\models;

use Yii;
use yii\base\Model;
use yii\db\Expression;

/**
 * Signup form
 */
class BalanceFundsForm extends Model
{
    public $id;
    public $user_id;
    public $account_id;
    public $status;
    public $transaction_type;
    public $amount;
    public $currency;
    public $comment;
    public $force;
    public $requisites;
    public $sender_transaction_id;
    public $protection_expire_days;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'account_id', 'status', 'transaction_type', 'amount', 'comment', 'force', 'sender_transaction_id', 'protection_expire_days'], 'filter', 'filter' => 'trim'],
            [['user_id', 'account_id', 'amount', 'comment'], 'required'],
            [['user_id', 'account_id', 'status', 'transaction_type', 'amount', 'comment', 'force'], 'safe'],

            [['user_id', 'account_id'], 'integer', 'min' => 0],
            ['transaction_type', 'integer', 'min' => 1, 'max' => 12],
            ['amount', 'number'],
            ['currency', 'in', 'range' => Accounts::getCurrencies()],
            ['comment', 'string', 'max' => 512],
            ['force', 'integer', 'min' => 0, 'max' => 1],

            ['account_id', 'checkAccountId'],
            ['amount', 'checkAmount'],

            ['requisites', 'string', 'max' => 512],
        ];
    }

    /**
     * Creates account for user
     *
     * @return bool
     */
    public function balance()
    {
        if ($this->validate()) {
            /**
             * @var Accounts $account
             * @var AccountsTransactions $accountTransaction
             */
            $account      = Accounts::findOne($this->account_id);

            $accountTransaction = new AccountsTransactions([
                'account_id'        => $this->account_id,
                'transaction_type'  => AccountsTransactions::TRANSACTION_TYPE_BALANCE,
                'amount'            => $this->amount,
                'comment'           => html_entity_decode($this->comment, ENT_COMPAT, 'UTF-8'),
                'status'            => AccountsTransactions::TRANSACTION_STATUS_SUCCESS,
            ]);
            if (!empty($this->status)) {
                $accountTransaction->status                 = $this->status;
            }
            if (!empty($this->transaction_type)) {
                $accountTransaction->transaction_type       = $this->transaction_type;
            }
            if (!empty($this->sender_transaction_id)) {
                $accountTransaction->sender_transaction_id  = $this->sender_transaction_id;
            }
            if (!empty($this->protection_expire_days)) {
                $accountTransaction->protection_expire_date = new Expression("DATE_ADD(NOW(), INTERVAL :days DAY)", ['days' => $this->protection_expire_days]);
            }

            $params = [
                'login'     => $account->foreign_account,
                'amount'    => $accountTransaction->amount,
                'comment'   => $accountTransaction->comment,
            ];
            $result = Yii::$app->{$account->accountType->driver}->makePayment($params);
            if ($result) {
                if ($accountTransaction->save()) {

                    if (!empty($this->requisites)) {
                        $transactionRequisites = new TransactionsRequisites();
                        $transactionRequisites->transaction_id = $accountTransaction->id;
                        $transactionRequisites->requisites = $this->requisites;
                        $transactionRequisites->save();
                    }
                    $account->recalcBalance();
                    $account->save();
                    $this->id = $accountTransaction->id;
                    return true;
                }
                $this->addErrors($accountTransaction->errors);
            }
        }

        return false;
    }

    public function checkAccountId($attribute, $params)
    {
        /**
         * @var Accounts $account
         */
        if ($params) {}
        $account = Accounts::findOne($this->$attribute);
        if ($account == null || $account->user_id != $this->user_id) {
            $this->addError($attribute, 'User is not an owner of this account id.');
        }
        if ($account != null && !empty($this->currency) && $account->currency != $this->currency) {
            $this->addError($attribute, 'Account has another currency.');
        }
    }

    public function checkAmount($attribute, $params)
    {
        /**
         * @var Accounts $account
         */
        if ($params) {}
        $account = Accounts::findOne($this->account_id);
        $accountMarginInfo = Yii::$app->{$account->accountType->driver}->getTradesMarginInfo(['account_id' => $this->account_id]);
        $finalSum = bcadd($accountMarginInfo['free'], $this->$attribute, 2);
        if (bccomp($this->$attribute, 0, 2) == -1 && in_array($this->transaction_type, [AccountsTransactions::TRANSACTION_TYPE_ECOMMPAY, AccountsTransactions::TRANSACTION_TYPE_PERFECT_MONEY, AccountsTransactions::TRANSACTION_TYPE_MONEY_PRO, AccountsTransactions::TRANSACTION_TYPE_UAH])) {
            $finalSum = bcsub($finalSum, $account->blocked_sum, 2);
        }
        if ($account == null || !is_array($accountMarginInfo) || (bccomp($this->$attribute, 0, 2) == -1 && bccomp($finalSum, 0, 2) == -1 && empty($this->force))) {
            $this->addError($attribute, 'The amount is bigger than is available on the account.');
        }
    }

}
