<?php

namespace app\models;

use yii\behaviors\AttributeBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * This is the model class for table "accounts_merchants_log".
 *
 * @property integer $id
 * @property integer $transaction_id
 * @property string $merchant
 * @property integer $direction
 * @property string $data
 * @property string $answer
 */
class AccountsMerchantsLog extends ActiveRecord
{
    const DIRECTION_IN  = 1;
    const DIRECTION_OUT = 2;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'accounts_merchants_log';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class' => AttributeBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['date_created', 'date_updated'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['date_updated'],
                ],
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['merchant', 'data'], 'required'],
            [['transaction_id', 'direction'], 'integer'],
            [['data', 'answer'], 'string'],
            [['merchant'], 'string', 'max' => 16],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'merchant' => 'Merchant',
            'data' => 'Data',
            'answer' => 'Answer',
        ];
    }
}
