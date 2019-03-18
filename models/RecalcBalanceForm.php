<?php
namespace app\models;

use Yii;
use yii\base\Model;

/**
 * Signup form
 */
class RecalcBalanceForm extends Model
{
    public $user_id;
    public $account_id;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'account_id'], 'filter', 'filter' => 'trim'],
            [['user_id', 'account_id'], 'required'],
            [['user_id', 'account_id'], 'safe'],

            ['user_id', 'integer', 'min' => 0],
            ['account_id', 'integer', 'min' => 0],

            ['account_id', 'checkAccountId'],
        ];
    }

    /**
     * Creates account for user
     *
     * @return bool
     */
    public function recalcBalance()
    {
        if ($this->validate()) {
            /**
             * @var Accounts $account
             */
            $account = Accounts::findOne($this->account_id);
            $account->recalcBalance();
            $result = $account->save();
            $this->addErrors($account->errors);

            if ($result) {
                return true;
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
    }

}
