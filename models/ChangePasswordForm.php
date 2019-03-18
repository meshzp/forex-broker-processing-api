<?php
namespace app\models;

use Yii;
use yii\base\Model;

/**
 * Signup form
 */
class ChangePasswordForm extends Model
{
    public $user_id;
    public $account_id;
    public $password;
    public $investor;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'account_id', 'password', 'investor'], 'filter', 'filter' => 'trim'],
            [['user_id', 'account_id', 'password', 'investor'], 'required'],
            [['user_id', 'account_id', 'password', 'investor'], 'safe'],

            ['user_id', 'integer', 'min' => 0],
            ['account_id', 'integer', 'min' => 0],
            ['password', 'string', 'min' => 6, 'max' => 16],
            ['password', 'match', 'pattern' => '/^(?=.*[A-Z])(?=.*[0-9])(?=.*[a-z]).{6,16}$/', 'message' => 'Your password need to contain at least one character in upper case, one character in lower case and one numeric character.'],
            ['investor', 'integer', 'min' => 0, 'max' => 1],

            ['account_id', 'checkAccountId'],
        ];
    }

    /**
     * Creates account for user
     *
     * @return bool
     */
    public function changePassword()
    {
        if ($this->validate()) {
            /**
             * @var Accounts $account
             */
            $account      = Accounts::findOne($this->account_id);

            $params = [
                'login'     => $account->foreign_account,
                'password'  => $this->password,
                'investor'  => $this->investor,
            ];
            $result = Yii::$app->{$account->accountType->driver}->сhangePass($params);
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
