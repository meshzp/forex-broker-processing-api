<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "accounts".
 *
 * @property integer $id
 * @property integer $user_id
 * @property integer $account_type
 * @property string $currency
 * @property float $balance
 * @property float $blocked_sum
 * @property string $foreign_account
 * @property string $foreign_settings
 *
 * @property User $user
 * @property AccountsTypes $accountType
 * @property AccountsTransactions[] $accountsTransactions
 */
class Accounts extends ActiveRecord
{
    const CURRENCY_USD = 'USD';
    const CURRENCY_UAH = 'UAH';
    const CURRENCY_RUB = 'RUB';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'accounts';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'account_type'], 'required'],
            [['user_id', 'account_type'], 'integer'],
            [['balance'], 'number'],
            [['foreign_account'], 'string', 'max' => 16],
            [['account_type'], 'exist', 'skipOnError' => true, 'targetClass' => AccountsTypes::className(), 'targetAttribute' => ['account_type' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'              => 'ID',
            'user_id'         => 'User ID',
            'account_type'    => 'Account Type',
            'balance'         => 'Balance',
            'blocked_sum'     => 'Blocked Sum',
            'foreign_account' => 'Foreign Account',
        ];
    }

    public static function getCurrencies()
    {
        return [
            self::CURRENCY_USD,
            self::CURRENCY_UAH,
            self::CURRENCY_RUB,
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAccountType()
    {
        return $this->hasOne(AccountsTypes::className(), ['id' => 'account_type']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAccountsTransactions()
    {
        return $this->hasMany(AccountsTransactions::className(), ['account_id' => 'id']);
    }

    public function recalcBalance()
    {
        switch ($this->accountType->driver) {
            case AccountsTypes::DRIVER_WEBACTIONS:
            case AccountsTypes::DRIVER_UPDOWNACTIONS:
                $aBalance = Yii::$app->{$this->accountType->driver}->getTradesMarginInfo(['account_id' => $this->id]);
                if ($aBalance && isset($aBalance['equity']) && is_numeric($aBalance['equity'])) {
                    $this->balance = $aBalance['equity'];
                } else {
                    $this->balance = 0;
                }
                break;
            case AccountsTypes::DRIVER_BOACTIONS:
                $aBalance = Yii::$app->{$this->accountType->driver}->refreshBalance($this->foreign_account);
                if ($aBalance && isset($aBalance['status']) && $aBalance['status'] == 1) {
                    $this->balance = $aBalance['balance'];
                } else {
                    $this->balance = 0;
                }
                break;
            case AccountsTypes::DRIVER_DBACTIONS:
            default:
                $newBalance = AccountsTransactions::find()->where('account_id = :account_id AND status != :status_fail AND (status = :status_success OR amount < 0)', ['account_id' => $this->id, 'status_fail' => AccountsTransactions::TRANSACTION_STATUS_FAIL, 'status_success' => AccountsTransactions::TRANSACTION_STATUS_SUCCESS])->sum('amount');
                if ($newBalance == null) {
                    $newBalance = 0;
                }
                $this->balance = $newBalance;
                break;
        }
    }

    /**
     * @param bool $insert
     * @return bool
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                /**
                 * Проверка только при вставке записи (создании счета) что счет указанного типа может быть только один.
                 *
                 * @var AccountsTypes $accountType
                 */
                $accountType = AccountsTypes::findOne($this->account_type);
                if ($accountType->type_unique) {
                    $account = Accounts::findOne([
                        'user_id'      => $this->user_id,
                        'account_type' => $this->account_type,
                        'currency'     => $this->currency,
                    ]);
                    if ($account !== null) {
                        $this->addError('account_type', 'Account with that type already exist.');
                        return false;
                    }
                }
            }
            return true;
        } else {
            return false;
        }
    }

}
