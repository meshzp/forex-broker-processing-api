<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "transactions_requisites".
 *
 * @property integer $transaction_id
 * @property string $requisites
 *
 * @property AccountsTransactions $transaction
 */
class TransactionsRequisites extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'transactions_requisites';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['transaction_id', 'requisites'], 'required'],
            [['transaction_id'], 'integer'],
            [['requisites'], 'string'],
            [['transaction_id'], 'exist', 'skipOnError' => true, 'targetClass' => AccountsTransactions::className(), 'targetAttribute' => ['transaction_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'transaction_id' => 'Transaction ID',
            'requisites' => 'Requisites',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTransaction()
    {
        return $this->hasOne(AccountsTransactions::className(), ['id' => 'transaction_id']);
    }
}
