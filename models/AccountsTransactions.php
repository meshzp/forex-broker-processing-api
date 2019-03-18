<?php

namespace app\models;

use Yii;
use yii\behaviors\AttributeBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\db\Query;

/**
 * This is the model class for table "accounts_transactions".
 *
 * @property integer $id
 * @property integer $account_id
 * @property integer $transaction_type
 * @property string $amount
 * @property string $comment
 * @property integer $sender_transaction_id
 * @property string $protection_code
 * @property string $protection_expire_date
 * @property integer $protection_retry_count
 * @property integer $status
 * @property string $remark_comment
 * @property string $date_created
 * @property string $date_updated
 *
 * @property Accounts $account
 * @property TransactionsRequisites $transactionsRequisites
 */
class AccountsTransactions extends ActiveRecord
{
    const TRANSACTION_TYPE_BALANCE         = 1;
    const TRANSACTION_TYPE_TRANSFER        = 2;
    const TRANSACTION_TYPE_ECOMMPAY        = 3;
    const TRANSACTION_TYPE_UAH             = 4;
    const TRANSACTION_TYPE_PLATRON         = 5;
    const TRANSACTION_TYPE_PAMM            = 6;
    const TRANSACTION_TYPE_PARTNER         = 7;
    const TRANSACTION_TYPE_PERFECT_MONEY   = 8;
    const TRANSACTION_TYPE_BANK            = 9;
    const TRANSACTION_TYPE_MONEY_PRO       = 10;
    const TRANSACTION_TYPE_RPN_PAY         = 11;
    const TRANSACTION_TYPE_BITCOIN_BITAPS  = 12;
    const TRANSACTION_TYPE_BETWEEN_CABINET = 22;

    const TRANSACTION_STATUS_FAIL      = 0;
    const TRANSACTION_STATUS_SUCCESS   = 1;
    const TRANSACTION_STATUS_PROTECTED = 2;
    const TRANSACTION_STATUS_PENDING   = 3;
    const TRANSACTION_STATUS_ACCEPTED  = 4;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'accounts_transactions';
    }

    /**
     * @param $data array
     *
     * @return $Account_transaction
     */
    public static function getBitcoinTransaction($data)
    {
        $Account_transaction = (new Query())
            ->select(['pat.id'])
            ->from('processing.accounts_transactions pat')
            ->innerJoin('processing.transactions_requisites ptr', 'ptr.transaction_id = pat.id AND pat.transaction_type  = :bitcoin_bitaps', [
                ':bitcoin_bitaps' => AccountsTransactions::TRANSACTION_TYPE_BITCOIN_BITAPS,
            ])
            ->where('ptr.requisites like :address', [':address' => '%' . $data['address'] . '%'])
            ->andWhere('ptr.requisites like :payment_code', [':payment_code' => '%' . $data['code'] . '%'])
            ->andWhere('ptr.requisites like :invoice', [':invoice' => '%' . $data['invoice'] . '%'])
            ->one();

        return $Account_transaction;
    }

    public static function satoshiToUsd($satoshi_amount)
    {
        $rateBtc    = Yii::$app->db->createCommand('SELECT ASK FROM api.MT4_PRICES WHERE SYMBOL = "BTCUSD" ORDER BY MODIFY_TIME DESC LIMIT 1;')->queryScalar();
        $usd_amount = ($rateBtc * $satoshi_amount) * pow(10, -8);

        return $usd_amount;
    }

    public static function usdToSatoshi($usd_amount)
    {
        $rateBtc    = Yii::$app->db->createCommand('SELECT BID FROM api.MT4_PRICES WHERE SYMBOL = "BTCUSD" ORDER BY MODIFY_TIME DESC LIMIT 1;')->queryScalar();
        $satishi_amount = ceil(($usd_amount/$rateBtc) * pow(10, 8));

        return $satishi_amount;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['account_id', 'transaction_type', 'amount', 'comment'], 'required'],
            [['account_id', 'transaction_type', 'sender_transaction_id', 'protection_retry_count', 'status'], 'integer'],
            [['amount'], 'number'],
            [['protection_code', 'protection_expire_date', 'date_created', 'date_updated'], 'safe'],
            [['comment', 'remark_comment'], 'string', 'max' => 512],
            [['account_id'], 'exist', 'skipOnError' => true, 'targetClass' => Accounts::className(), 'targetAttribute' => ['account_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'                     => 'ID',
            'account_id'             => 'Account ID',
            'transaction_type'       => 'Transaction Type',
            'amount'                 => 'Amount',
            'comment'                => 'Comment',
            'sender_transaction_id'  => 'Sender Transaction ID',
            'protection_code'        => 'Protection Code',
            'protection_expire_date' => 'Protection Expire Date',
            'protection_retry_count' => 'Protection Retry Count',
            'status'                 => 'Status',
            'remark_comment'         => 'Remark Comment',
            'date_created'           => 'Date Created',
            'date_updated'           => 'Date Updated',
        ];
    }

    public function behaviors()
    {
        return [
            [
                'class'      => AttributeBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'date_created',
                ],
                'value'      => new Expression('NOW()'),
            ],
            [
                'class'      => AttributeBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'date_updated',
                ],
                'value'      => new Expression('NOW()'),
            ],
            [
                'class'      => AttributeBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_UPDATE => 'date_updated',
                ],
                'value'      => new Expression('NOW()'),
            ],
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAccount()
    {
        return $this->hasOne(Accounts::className(), ['id' => 'account_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTransactionsRequisites()
    {
        return $this->hasOne(TransactionsRequisites::className(), ['transaction_id' => 'id']);
    }

    public function afterSave($insert, $changedAttributes)
    {
        /**
         * Когда добавляется новая транзакция со status = 1 - нужно обновить поле balance
         * Когда обновляется транзакция и из SUCCESS получается FAILED - надо также обновить balance, но уже перерасчетом
         *
         * @var Accounts $account
         */
        $account = Accounts::findOne($this->account_id);
        if ($account !== null) {
            $account->recalcBalance();
            if (!$account->save()) {
                $this->addErrors($account->errors);
            }
        }
        parent::afterSave($insert, $changedAttributes);
    }
}
