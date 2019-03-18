<?php
namespace app\models;

use Yii;
use yii\base\Model;

/**
 * Signup form
 */
class ChangeAccountParamsForm extends Model
{
    public $user_id;
    public $account_id;
    public $leverage;
    public $send_reports;


    const DONT_SEND_TRADE_REPORTS = 0;
    const SEND_TRADE_REPORTS = 1;
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'account_id', 'leverage', 'send_reports'], 'filter', 'filter' => 'trim'],
            [['user_id', 'account_id', 'leverage', 'send_reports'], 'required'],

            ['user_id', 'integer', 'min' => 0],
            ['account_id', 'integer', 'min' => 0],
            ['leverage', 'checkLeverage'],
            [['send_reports'], 'in', 'range' => [self::SEND_TRADE_REPORTS, self::DONT_SEND_TRADE_REPORTS]],
            ['account_id', 'checkAccountId'],
        ];
    }

    /**
     * Creates account for user
     *
     * @return bool
     */
    public function changeParams()
    {
        if ($this->validate()) {
            /**
             * @var Accounts $account
             */
            $account      = Accounts::findOne($this->account_id);

            $params = [
                'login'     => $account->foreign_account,
                'password'  => $this->leverage,
                'investor'  => $this->send_reports,
            ];
            $result = Yii::$app->{$account->accountType->driver}->updateUser($account->foreign_account, "LEVERAGE={$this->leverage}|SEND_REPORTS={$this->send_reports}");
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

    public function checkLeverage($attribute)
    {
        /**
         * @var Accounts $account
         */
        $account  = Accounts::findOne($this->account_id);
        $leverage = Yii::$app->db->createCommand('SELECT LEVERAGE FROM api.MT4_USERS WHERE LOGIN = :login')->bindValue(':login', intval($account->foreign_account))->queryScalar();
        if ($this->$attribute != $leverage) {
            $open_trades = Yii::$app->{$account->accountType->driver}->getOpenedTradesCount($account->foreign_account);
            if (!isset($open_trades) || !empty($open_trades['total']) || !in_array($this->$attribute, AccountsLeverages::getLeverages($this->account_id))) {
                $this->addError($attribute, 'Not correct leverage or isset open trades.');
            }
        }
    }

}
