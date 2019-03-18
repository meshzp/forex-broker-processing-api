<?php
namespace app\models;

use Yii;
use yii\base\Model;

/**
 * Signup form
 */
class CreateAccountForm extends Model
{
    public $user_id;
    public $type;
    public $currency;
    public $name;
    public $password;
    public $investor;
    public $email;
    public $country;
    public $city;
    public $address;
    public $comment;
    public $phone;
    public $phone_password;
    public $zip_code;
    public $leverage;
    public $ip;
    public $agent;
    public $enabled;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'type', 'currency', 'name', 'password', 'investor', 'email', 'country', 'city', 'address', 'comment', 'phone', 'phone_password', 'zip_code', 'leverage', 'ip', 'agent', 'enabled'], 'filter', 'filter' => 'trim'],
            [['user_id', 'type', 'currency', 'name', 'password', 'investor', 'email', 'country', 'city', 'address', 'phone_password', 'zip_code', 'leverage', 'ip'], 'required'],
            [['user_id', 'type', 'currency', 'name', 'password', 'investor', 'email', 'country', 'city', 'address', 'comment', 'phone', 'phone_password', 'zip_code', 'leverage', 'ip', 'agent', 'enabled'], 'safe'],

            ['user_id', 'integer', 'min' => 0],
            ['type', 'integer', 'min' => 0],
            ['type', 'checkUnique'],
            ['currency', 'in', 'range' => Accounts::getCurrencies()],
            ['name', 'string', 'max' => 128],
            ['password', 'string', 'min' => 6, 'max' => 16],
            ['password', 'match', 'pattern' => '/^(?=.*[A-Z])(?=.*[0-9])(?=.*[a-z]).{6,16}$/', 'message' => 'Your password need to contain at least one character in upper case, one character in lower case and one numeric character.'],
            ['investor', 'string', 'min' => 6, 'max' => 16],
            ['email', 'string', 'max' => 48],
            ['email', 'email'],
            ['country', 'string', 'max' => 32],
            ['city', 'string', 'max' => 32],
            ['address', 'string', 'max' => 128],
            ['comment', 'string', 'max' => 64],
            ['phone', 'string', 'max' => 32],
            ['phone_password', 'string', 'max' => 32],
            ['zip_code', 'string', 'max' => 16],
            ['leverage', 'integer', 'max' => 1000],
            ['ip', 'string', 'max' => 16],
            ['agent', 'integer', 'min' => 0],
            ['enabled', 'integer', 'min' => 0, 'max' => 1],
        ];
    }

    /**
     * Creates account for user
     *
     * @return bool
     */
    public function create()
    {
        if ($this->validate()) {
            $account = new Accounts();
            $account->user_id       = $this->user_id;
            $account->account_type  = $this->type;
            /**
             * @var AccountsTypes $accountType
             */
            $accountType = AccountsTypes::findOne($this->type);
            if ($accountType != null) {
                if ($account->save()) {
                    $params = get_object_vars($this);
                    $params['group'] = $accountType->group_name;
                    $params['account_id'] = $account->id;
                    $foreignAccount = Yii::$app->{$accountType->driver}->createAccount($params);
                    if ($foreignAccount['account']) {
                        if (strlen($foreignAccount['account']) > 1) {
                            $account->foreign_account = strval($foreignAccount['account']);
                            $account->foreign_settings = json_encode($foreignAccount['settings']);
                        }
                        $account->save();
                        $this->addErrors($account->errors);
                        return $account->id;
                    } else {
                        $account->delete();
                        $this->addErrors($account->errors);
                    }
                } else {
                    $this->addErrors("Account save failed");
                }
            } else {
                $this->addErrors("AccountsTypes failed");
            }
        } else {
            $this->addErrors("Validate failed");
        }

        return false;
    }

    public function checkUnique($attribute, $params)
    {
        /**
         * @var Accounts $account
         * @var AccountsTypes $accountType
         */
        if ($params) {}
        $accountType = AccountsTypes::findOne($this->$attribute);
        if ($accountType != null) {
            if (!empty($accountType->type_unique)) {
                $account = Accounts::findOne([
                    'user_id'       => $this->user_id,
                    'account_type'  => $this->$attribute,
                    'currency'      => $this->currency,
                ]);
                if ($account != null) {
                    $this->addError($attribute, 'Account with that type already exist.');
                }
            }
        } else {
            $this->addError($attribute, 'Account type not exist.');
        }
    }
}
